<?php
/**
 * Create and edit a course (F5, BR1, BR5).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

$id = (int) get('id');
$existing = $id ? db_one('SELECT * FROM courses WHERE id = ?', 'i', [$id]) : null;
if ($id && !$existing) {
    flash('danger', 'Course not found.');
    redirect('admin/courses.php');
}

$data = $existing ?? [
    'code' => '', 'title' => '', 'department_id' => '', 'level' => 'L1', 'semester' => 1,
    'cm_hours' => 0, 'td_hours' => 0, 'tp_hours' => 0, 'credits' => 0,
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'code' => mb_strtoupper(post('code')),
        'title' => post('title'),
        'department_id' => post('department_id'),
        'level' => post('level'),
        'semester' => post('semester'),
        'cm_hours' => post('cm_hours', '0'),
        'td_hours' => post('td_hours', '0'),
        'tp_hours' => post('tp_hours', '0'),
        'credits' => post('credits', '0'),
    ];

    if (!preg_match('/^[A-Z0-9-]{3,20}$/', $data['code'])) {
        $errors[] = 'The code must contain 3 to 20 letters, digits or hyphens (e.g. CSC101).';
    } elseif (db_value('SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?', 'si', [$data['code'], $id]) > 0) {
        $errors[] = 'This course code already exists (BR1).';
    }
    if ($data['title'] === '' || mb_strlen($data['title']) > 150) $errors[] = 'The title is required (150 characters at most).';
    if (!db_value('SELECT COUNT(*) FROM departments WHERE id = ?', 'i', [(int) $data['department_id']])) $errors[] = 'Choose a department.';
    if (!in_array($data['level'], LEVELS, true)) $errors[] = 'Choose a level.';
    if (!in_array((string) $data['semester'], ['1', '2'], true)) $errors[] = 'The semester must be 1 or 2.';
    foreach (['cm_hours' => 'CM', 'td_hours' => 'TD', 'tp_hours' => 'TP', 'credits' => 'credits'] as $field => $label) {
        if (!ctype_digit((string) $data[$field]) || (int) $data[$field] > 500) {
            $errors[] = 'The value “' . $label . '” must be a whole number between 0 and 500.';
        }
    }
    if (!$errors && (int) $data['cm_hours'] + (int) $data['td_hours'] + (int) $data['tp_hours'] === 0) {
        $errors[] = 'The course must plan at least one hour of CM, TD or TP.';
    }

    // Planned hours cannot go below the hours already assigned in the active year (BR5)
    $year = active_year();
    if (!$errors && $existing && $year) {
        foreach (array_keys(TYPES) as $type) {
            $assigned = course_assigned_hours($id, (int) $year['id'], $type);
            if ((int) $data[strtolower($type) . '_hours'] < $assigned) {
                $errors[] = hours($assigned) . ' de ' . $type . ' are already assigned this year: planned hours cannot be lower.';
            }
        }
    }

    if (!$errors) {
        $params = [
            $data['code'], $data['title'], (int) $data['department_id'], $data['level'], (int) $data['semester'],
            (int) $data['cm_hours'], (int) $data['td_hours'], (int) $data['tp_hours'], (int) $data['credits'],
        ];
        if ($existing) {
            $params[] = $id;
            db_exec(
                'UPDATE courses SET code = ?, title = ?, department_id = ?, level = ?, semester = ?,
                 cm_hours = ?, td_hours = ?, tp_hours = ?, credits = ? WHERE id = ?',
                'ssisiiiiii',
                $params
            );
            log_action('Course updated', $data['code'] . ' ' . $data['title']);
        } else {
            db_exec(
                'INSERT INTO courses (code, title, department_id, level, semester, cm_hours, td_hours, tp_hours, credits)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'ssisiiiii',
                $params
            );
            log_action('Course created', $data['code'] . ' ' . $data['title']);
        }
        flash('success', 'Course ' . $data['code'] . ' has been saved.');
        redirect('admin/courses.php');
    }
}

$departments = db_all('SELECT * FROM departments ORDER BY name');
$page_title = $existing ? 'Edit a course' : 'New course';
$nav_active = 'admin/courses.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= icon('journal-bookmark', 'greeting-icon') ?><?= e($page_title) ?></h1>
    <a href="<?= e(url('admin/courses.php')) ?>" class="btn btn-outline-secondary">Back to the list</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card needs-validation" novalidate style="max-width: 900px">
    <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <label for="code" class="form-label">Code</label>
            <input type="text" class="form-control text-uppercase" id="code" name="code" value="<?= e($data['code']) ?>"
                   required pattern="[A-Za-z0-9\-]{3,20}" maxlength="20">
            <div class="invalid-feedback">3 to 20 characters, e.g. CSC101.</div>
        </div>
        <div class="col-md-9">
            <label for="title" class="form-label">Title</label>
            <input type="text" class="form-control" id="title" name="title" value="<?= e($data['title']) ?>" required maxlength="150">
            <div class="invalid-feedback">The title is required.</div>
        </div>
        <div class="col-md-6">
            <label for="department_id" class="form-label">Department</label>
            <select class="form-select" id="department_id" name="department_id" required>
                <option value="">Choose…</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"<?= selected($data['department_id'], $d['id']) ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Choose a department.</div>
        </div>
        <div class="col-6 col-md-3">
            <label for="level" class="form-label">Level</label>
            <select class="form-select" id="level" name="level" required>
                <?php foreach (LEVELS as $n): ?><option value="<?= e($n) ?>"<?= selected($data['level'], $n) ?>><?= e($n) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="semester" class="form-label">Semester</label>
            <select class="form-select" id="semester" name="semester" required>
                <option value="1"<?= selected($data['semester'], 1) ?>>Semester 1</option>
                <option value="2"<?= selected($data['semester'], 2) ?>>Semester 2</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="cm_hours" class="form-label">CM hours</label>
            <input type="number" class="form-control" id="cm_hours" name="cm_hours" min="0" max="500" step="1" value="<?= e($data['cm_hours']) ?>" required>
        </div>
        <div class="col-6 col-md-3">
            <label for="td_hours" class="form-label">TD hours</label>
            <input type="number" class="form-control" id="td_hours" name="td_hours" min="0" max="500" step="1" value="<?= e($data['td_hours']) ?>" required>
        </div>
        <div class="col-6 col-md-3">
            <label for="tp_hours" class="form-label">TP hours</label>
            <input type="number" class="form-control" id="tp_hours" name="tp_hours" min="0" max="500" step="1" value="<?= e($data['tp_hours']) ?>" required>
        </div>
        <div class="col-6 col-md-3">
            <label for="credits" class="form-label">Credits</label>
            <input type="number" class="form-control" id="credits" name="credits" min="0" max="60" step="1" value="<?= e($data['credits']) ?>" required>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= e(url('admin/courses.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
