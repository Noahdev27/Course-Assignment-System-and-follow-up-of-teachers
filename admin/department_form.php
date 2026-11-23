<?php
/**
 * Create and edit a department, appoint its head (F4).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

$id = (int) get('id');
$existing = $id ? db_one('SELECT * FROM departments WHERE id = ?', 'i', [$id]) : null;
if ($id && !$existing) {
    flash('danger', 'Department not found.');
    redirect('admin/departments.php');
}

$data = $existing ?? ['name' => '', 'code' => '', 'head_id' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'name' => post('name'),
        'code' => mb_strtoupper(post('code')),
        'head_id' => post('head_id'),
    ];
    if ($data['name'] === '' || mb_strlen($data['name']) > 100) $errors[] = 'The name is required (100 characters at most).';
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $data['code'])) {
        $errors[] = 'The code must contain 2 to 10 letters or digits (e.g. CSC).';
    } elseif (db_value('SELECT COUNT(*) FROM departments WHERE code = ? AND id <> ?', 'si', [$data['code'], $id]) > 0) {
        $errors[] = 'This code is already used by another department.';
    }
    $headId = $data['head_id'] !== '' ? (int) $data['head_id'] : null;
    if ($headId && !db_value("SELECT COUNT(*) FROM users WHERE id = ? AND role IN ('teacher', 'head') AND is_active = 1", 'i', [$headId])) {
        $errors[] = 'The head must be an active teacher.';
    }

    if (!$errors) {
        if ($existing) {
            db_exec('UPDATE departments SET name = ?, code = ? WHERE id = ?', 'ssi', [$data['name'], $data['code'], $id]);
            $departmentId = $id;
            log_action('Department updated', $data['name']);
        } else {
            db_exec('INSERT INTO departments (name, code) VALUES (?, ?)', 'ss', [$data['name'], $data['code']]);
            $departmentId = db_insert_id();
            log_action('Department created', $data['name']);
        }
        set_department_head($departmentId, $headId);
        flash('success', 'Department ' . $data['name'] . ' has been saved.');
        redirect('admin/departments.php');
    }
}

$candidates = db_all(
    "SELECT u.id, u.last_name, u.first_name, d.code AS department_code FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.role IN ('teacher', 'head') AND u.is_active = 1
     ORDER BY (u.department_id = ?) DESC, u.last_name, u.first_name",
    'i',
    [$id]
);

$page_title = $existing ? 'Edit a department' : 'New department';
$nav_active = 'admin/departments.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= icon('building', 'greeting-icon') ?><?= e($page_title) ?></h1>
    <a href="<?= e(url('admin/departments.php')) ?>" class="btn btn-outline-secondary">Back to the list</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card needs-validation" novalidate style="max-width: 760px">
    <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-md-8">
            <label for="name" class="form-label">Department name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= e($data['name']) ?>" required maxlength="100">
            <div class="invalid-feedback">The name is required.</div>
        </div>
        <div class="col-md-4">
            <label for="code" class="form-label">Code</label>
            <input type="text" class="form-control text-uppercase" id="code" name="code" value="<?= e($data['code']) ?>"
                   required pattern="[A-Za-z0-9]{2,10}" maxlength="10">
            <div class="invalid-feedback">2 to 10 letters or digits.</div>
        </div>
        <div class="col-12">
            <label for="head_id" class="form-label">Head of department</label>
            <select class="form-select" id="head_id" name="head_id">
                <option value="">None yet</option>
                <?php foreach ($candidates as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($data['head_id'], $c['id']) ?>>
                        <?= e($c['last_name'] . ' ' . $c['first_name']) ?> (<?= e($c['department_code'] ?? 'no department') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint mt-1">The selected person becomes “Head of department” and joins this department; the previous head becomes a teacher again.</div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= e(url('admin/departments.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
