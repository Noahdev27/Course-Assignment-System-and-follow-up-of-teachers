<?php
/**
 * Profile of the logged-in user: phone and password change (all roles).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_login();
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'phone') {
        $phone = post('phone');
        if (mb_strlen($phone) > 30) {
            $errors[] = 'The phone number is too long.';
        } else {
            db_exec('UPDATE users SET phone = ? WHERE id = ?', 'si', [$phone !== '' ? $phone : null, (int) $user['id']]);
            flash('success', 'Your phone number has been saved.');
            redirect_self();
        }
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'The current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'The new password must contain at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            log_action('Password changed');
            flash('success', 'Your password has been changed.');
            redirect_self();
        }
    }
}

$page_title = 'My profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <h1><?= icon('person-circle', 'greeting-icon') ?>My profile</h1>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">My details</div>
            <div class="card-body">
                <dl class="row mb-4">
                    <dt class="col-sm-4">Last name</dt><dd class="col-sm-8"><?= e(full_name($user)) ?></dd>
                    <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8"><?= e($user['email']) ?></dd>
                    <dt class="col-sm-4">Role</dt><dd class="col-sm-8"><?= e(ROLES[$user['role']] ?? $user['role']) ?></dd>
                    <?php if (is_teacher_role($user['role'])): ?>
                        <dt class="col-sm-4">Department</dt><dd class="col-sm-8"><?= e($user['department_name'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Academic rank</dt><dd class="col-sm-8"><?= e($user['academic_rank'] ?? '—') ?></dd>
                        <dt class="col-sm-4">Maximum workload</dt><dd class="col-sm-8"><?= hours($user['max_load']) ?> per year</dd>
                    <?php endif; ?>
                </dl>
                <form method="post" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="phone">
                    <div class="col">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($user['phone']) ?>" maxlength="30">
                    </div>
                    <div class="col-auto"><button class="btn btn-outline-primary">Save</button></div>
                </form>
                <p class="form-hint mt-3 mb-0">To change your name, email or academic rank, contact the administrator.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Change password</div>
            <div class="card-body">
                <form method="post" class="row g-3 needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="col-12">
                        <label for="current_password" class="form-label">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="col-md-6">
                        <label for="new_password" class="form-label">New password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required autocomplete="new-password">
                        <div class="invalid-feedback">At least 8 characters.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirm" class="form-label">Confirmation</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required data-match="#new_password" autocomplete="new-password">
                        <div class="invalid-feedback">Both passwords must match.</div>
                    </div>
                    <div class="col-12 text-end"><button type="submit" class="btn btn-primary"><?= icon('key') ?>Change password</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
