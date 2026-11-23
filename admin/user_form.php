<?php
/**
 * Create and edit an account (F3, BR1).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

$id = (int) get('id');
$existing = $id ? db_one('SELECT * FROM users WHERE id = ?', 'i', [$id]) : null;
if ($id && !$existing) {
    flash('danger', 'Account not found.');
    redirect('admin/users.php');
}

$data = $existing ?? [
    'last_name' => '', 'first_name' => '', 'email' => '', 'role' => 'teacher', 'department_id' => '',
    'academic_rank' => '', 'max_load' => 200, 'phone' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'last_name' => post('last_name'),
        'first_name' => post('first_name'),
        'email' => mb_strtolower(post('email')),
        'role' => post('role'),
        'department_id' => post('department_id'),
        'academic_rank' => post('academic_rank'),
        'max_load' => post('max_load'),
        'phone' => post('phone'),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $isTeacher = is_teacher_role($data['role']);

    if ($data['last_name'] === '' || mb_strlen($data['last_name']) > 80) $errors[] = 'The last name is required (80 characters at most).';
    if ($data['first_name'] === '' || mb_strlen($data['first_name']) > 80) $errors[] = 'The first name is required (80 characters at most).';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'The email address is not valid.';
    } elseif (db_value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', 'si', [$data['email'], $id]) > 0) {
        $errors[] = 'This email address is already used by another account (BR1).';
    }
    if (!array_key_exists($data['role'], ROLES)) $errors[] = 'Choose a role.';
    if ($existing && $id === (int) $user['id'] && $data['role'] !== 'admin') {
        $errors[] = 'You cannot remove your own administrator role.';
    }
    if ($isTeacher) {
        if (!db_value('SELECT COUNT(*) FROM departments WHERE id = ?', 'i', [(int) $data['department_id']])) {
            $errors[] = 'Choose the teacher\'s department.';
        }
        if (!in_array($data['academic_rank'], GRADES, true)) $errors[] = 'Choose the teacher\'s academic rank.';
        if (!ctype_digit((string) $data['max_load']) || (int) $data['max_load'] < 1 || (int) $data['max_load'] > 1000) {
            $errors[] = 'The maximum workload must be a number of hours between 1 and 1000.';
        }
    } elseif ($existing && is_teacher_role($existing['role'])) {
        $hasAssignments = db_value('SELECT COUNT(*) FROM assignments WHERE teacher_id = ?', 'i', [$id]);
        if ($hasAssignments > 0) {
            $errors[] = 'This account has assignments: it must keep a teacher role.';
        }
    }
    if (!$existing && strlen($password) < 8) {
        $errors[] = 'The password must contain at least 8 characters.';
    } elseif ($existing && $password !== '' && strlen($password) < 8) {
        $errors[] = 'The new password must contain at least 8 characters.';
    }

    if (!$errors) {
        $departmentId = $isTeacher ? (int) $data['department_id'] : null;
        $grade = $isTeacher ? $data['academic_rank'] : null;
        $maxLoad = $isTeacher ? (int) $data['max_load'] : 0;
        $phone = $data['phone'] !== '' ? $data['phone'] : null;

        if ($existing) {
            db_exec(
                'UPDATE users SET last_name = ?, first_name = ?, email = ?, role = ?, department_id = ?, academic_rank = ?,
                 max_load = ?, phone = ? WHERE id = ?',
                'ssssisisi',
                [$data['last_name'], $data['first_name'], $data['email'], $data['role'], $departmentId, $grade, $maxLoad, $phone, $id]
            );
            if ($password !== '') {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            $userId = $id;
            log_action('Account updated', $data['first_name'] . ' ' . $data['last_name']);
        } else {
            db_exec(
                'INSERT INTO users (last_name, first_name, email, password_hash, role, department_id, academic_rank, max_load, phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'sssssisis',
                [$data['last_name'], $data['first_name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
                    $data['role'], $departmentId, $grade, $maxLoad, $phone]
            );
            $userId = db_insert_id();
            log_action('Account created', $data['first_name'] . ' ' . $data['last_name'] . ' (' . (ROLES[$data['role']] ?? '') . ')');
        }

        // The “head” role and the departments.head_id column stay consistent
        if ($data['role'] === 'head') {
            set_department_head($departmentId, $userId);
        } else {
            db_exec('UPDATE departments SET head_id = NULL WHERE head_id = ?', 'i', [$userId]);
        }

        flash('success', 'The account of ' . $data['first_name'] . ' ' . $data['last_name'] . ' has been saved.');
        redirect('admin/users.php');
    }
}

$departments = db_all('SELECT * FROM departments ORDER BY name');
$page_title = $existing ? 'Edit an account' : 'New account';
$nav_active = 'admin/users.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('person-gear', 'greeting-icon') ?><?= e($page_title) ?></h1>
        <?php if ($existing): ?><p class="lead-text"><?= e(full_name($existing)) ?></p><?php endif; ?>
    </div>
    <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline-secondary">Back to the list</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card needs-validation" novalidate>
    <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6">
            <label for="last_name" class="form-label">Last name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e($data['last_name']) ?>" required maxlength="80">
            <div class="invalid-feedback">The last name is required.</div>
        </div>
        <div class="col-md-6">
            <label for="first_name" class="form-label">First name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e($data['first_name']) ?>" required maxlength="80">
            <div class="invalid-feedback">The first name is required.</div>
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= e($data['email']) ?>" required maxlength="150">
            <div class="invalid-feedback">Enter a valid email address.</div>
        </div>
        <div class="col-md-6">
            <label for="phone" class="form-label">Phone <span class="text-body-secondary">(optional)</span></label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($data['phone']) ?>" maxlength="30">
        </div>
        <div class="col-md-6">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role" required>
                <?php foreach (ROLES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= selected($data['role'], $key) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-hint mt-1">A head of department automatically becomes the head of the selected department.</div>
        </div>
        <div class="col-md-6">
            <label for="password" class="form-label">
                <?= $existing ? 'New password <span class="text-body-secondary">(leave empty to keep it)</span>' : 'Password' ?>
            </label>
            <input type="password" class="form-control" id="password" name="password" minlength="8"
                   autocomplete="new-password" <?= $existing ? '' : 'required' ?>>
            <div class="invalid-feedback">At least 8 characters.</div>
        </div>

        <div class="col-12" data-show-if="#role:teacher,head">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id" data-required-when-visible>
                        <option value="">Choose…</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"<?= selected($data['department_id'], $d['id']) ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose a department.</div>
                </div>
                <div class="col-md-4">
                    <label for="academic_rank" class="form-label">Academic rank</label>
                    <select class="form-select" id="academic_rank" name="academic_rank" data-required-when-visible>
                        <option value="">Choose…</option>
                        <?php foreach (GRADES as $grade): ?>
                            <option value="<?= e($grade) ?>"<?= selected($data['academic_rank'], $grade) ?>><?= e($grade) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose an academic rank.</div>
                </div>
                <div class="col-md-4">
                    <label for="max_load" class="form-label">Maximum workload (hours per year)</label>
                    <input type="number" class="form-control" id="max_load" name="max_load" min="1" max="1000" step="1"
                           value="<?= e($data['max_load']) ?>" data-required-when-visible>
                    <div class="invalid-feedback">Between 1 and 1000 hours.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= e(url('admin/users.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
