<?php
/**
 * Sign-up page: a teacher asks for an account.
 * The account is created deactivated; the administrator approves it (or refuses it)
 * from the dashboard. Only the “teacher” role can be requested here: other roles
 * (head of department, accountant, administrator) are given by the administrator.
 */
require_once __DIR__ . '/includes/functions.php';

if ($user = current_user()) {
    redirect(dashboard_path($user['role']));
}

$departments = db_all('SELECT id, code, name FROM departments ORDER BY name');
$data = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'department_id' => '', 'academic_rank' => ''];
$errors = [];
$sentTo = $_SESSION['signup_sent'] ?? null;
unset($_SESSION['signup_sent']);

if (is_post()) {
    verify_csrf();
    foreach (array_keys($data) as $key) {
        $data[$key] = post($key);
    }
    $data['email'] = mb_strtolower($data['email']);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    // Hidden field that people never see: robots that fill it are ignored
    if (post('website') !== '') {
        redirect('index.php');
    }

    if ($data['first_name'] === '' || mb_strlen($data['first_name']) > 80) $errors[] = 'Type your first name.';
    if ($data['last_name'] === '' || mb_strlen($data['last_name']) > 80) $errors[] = 'Type your last name.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150) {
        $errors[] = 'Type a valid email address, for example name@example.com.';
    } elseif (db_value('SELECT COUNT(*) FROM users WHERE email = ?', 's', [$data['email']]) > 0) {
        $errors[] = 'An account already uses this email address (BR1). Log in, or ask the administrator for help.';
    }
    if (mb_strlen($data['phone']) > 30) $errors[] = 'The phone number is too long.';
    $department = db_one('SELECT id FROM departments WHERE id = ?', 'i', [(int) $data['department_id']]);
    if (!$department) $errors[] = 'Choose your department.';
    if (!in_array($data['academic_rank'], GRADES, true)) $errors[] = 'Choose your academic rank.';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'The password needs at least 8 characters, with letters and at least one number.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords are not the same.';
    }

    if (!$errors) {
        $db = db();
        $db->begin_transaction();
        try {
            db_exec(
                "INSERT INTO users (last_name, first_name, email, password_hash, role, department_id, academic_rank, max_load, phone, is_active)
                 VALUES (?, ?, ?, ?, 'teacher', ?, ?, ?, ?, 0)",
                'ssssisis',
                [$data['last_name'], $data['first_name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
                    (int) $data['department_id'], $data['academic_rank'], RANK_MAX_LOAD[$data['academic_rank']] ?? 0,
                    $data['phone'] !== '' ? $data['phone'] : null]
            );
            $newId = db_insert_id();
            db_exec('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', 'iss',
                [$newId, 'Sign-up request', full_name($data) . ' · ' . $data['email']]);
            $db->commit();
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            throw $e;
        }
        $_SESSION['signup_sent'] = $data['first_name'];
        redirect('register.php');
    }
}

$page_title = 'Create an account';
$body_class = 'guest-page';
require __DIR__ . '/includes/header.php';
?>
<?php if ($sentTo !== null): ?>
<div class="guest-wrap narrow">
    <section class="guest-form text-center">
        <div class="success-burst mb-3"><?= icon('check-lg') ?></div>
        <h2>Thank you, <?= e($sentTo) ?>! Your request has been sent.</h2>
        <p class="text-body-secondary">You do not need to do anything else for now.</p>
        <ul class="next-steps">
            <li><?= icon('person-badge') ?><div><strong>The administrator checks your request.</strong><br><span class="text-body-secondary">This is done by a person, usually within a few days.</span></div></li>
            <li><?= icon('unlock') ?><div><strong>Your account is then activated.</strong><br><span class="text-body-secondary">Until then, logging in shows a “waiting for approval” message.</span></div></li>
            <li><?= icon('box-arrow-in-right') ?><div><strong>You log in with your email and your password.</strong><br><span class="text-body-secondary">You will see your courses, declare your classes and follow your payments.</span></div></li>
        </ul>
        <a href="<?= e(url('index.php')) ?>" class="btn btn-primary btn-lg"><?= icon('arrow-left') ?>Back to the login page</a>
    </section>
</div>
<?php else: ?>
<div class="guest-wrap narrow">
    <section class="guest-form">
        <a href="<?= e(url('index.php')) ?>" class="small text-decoration-none"><?= icon('arrow-left') ?> Back to the login page</a>
        <h2 class="mt-3 mb-1">Create my teacher account</h2>
        <p class="text-body-secondary">Fill in the three parts below. It takes about two minutes. The administrator will then approve your account.</p>

        <?php if ($errors): ?>
            <div class="alert alert-danger app-alert" role="alert">
                <?= icon('exclamation-circle-fill', 'alert-icon') ?>
                <div><strong>Please check these points:</strong>
                    <ul class="mb-0 mt-1"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="d-none" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <h3 class="form-section-title"><span class="num-circle">1</span>Who are you?</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e($data['first_name']) ?>"
                           maxlength="80" required autocomplete="given-name">
                    <div class="invalid-feedback">Type your first name.</div>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e($data['last_name']) ?>"
                           maxlength="80" required autocomplete="family-name">
                    <div class="invalid-feedback">Type your last name.</div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($data['email']) ?>"
                           maxlength="150" placeholder="name@example.com" required autocomplete="email">
                    <div class="form-hint">You will use it to log in.</div>
                    <div class="invalid-feedback">Type a valid email address.</div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone <span class="text-body-secondary fw-normal">(optional)</span></label>
                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($data['phone']) ?>"
                           maxlength="30" placeholder="+237 6xx xx xx xx" autocomplete="tel">
                </div>
            </div>

            <h3 class="form-section-title"><span class="num-circle">2</span>Where do you teach?</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="department_id" class="form-label">Department</label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">Choose…</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"<?= selected($data['department_id'], $d['id']) ?>><?= e($d['name']) ?> (<?= e($d['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Choose your department.</div>
                </div>
                <div class="col-md-6">
                    <label for="academic_rank" class="form-label">Academic rank</label>
                    <select class="form-select" id="academic_rank" name="academic_rank" required>
                        <option value="">Choose…</option>
                        <?php foreach (GRADES as $rank): ?>
                            <option value="<?= e($rank) ?>"<?= selected($data['academic_rank'], $rank) ?>><?= e($rank) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">It sets your hourly rate.</div>
                    <div class="invalid-feedback">Choose your academic rank.</div>
                </div>
            </div>

            <h3 class="form-section-title"><span class="num-circle">3</span>Choose a password</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-field">
                        <input type="password" class="form-control" id="password" name="password" minlength="8"
                               pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" required autocomplete="new-password" aria-describedby="passwordHint">
                        <button type="button" class="toggle-password" data-toggle-password="#password" aria-pressed="false"><?= icon('eye') ?> Show</button>
                        <div class="invalid-feedback">At least 8 characters, with letters and a number.</div>
                    </div>
                    <div class="form-hint" id="passwordHint">At least 8 characters, with letters and at least one number.</div>
                </div>
                <div class="col-md-6">
                    <label for="password_confirm" class="form-label">Type it again</label>
                    <div class="password-field">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required
                               data-match="#password" autocomplete="new-password">
                        <button type="button" class="toggle-password" data-toggle-password="#password_confirm" aria-pressed="false"><?= icon('eye') ?> Show</button>
                        <div class="invalid-feedback">The two passwords must be the same.</div>
                    </div>
                </div>
            </div>

            <div class="info-box mb-4"><?= icon('info-circle', 'me-1') ?>
                This page creates <strong>teacher</strong> accounts. Heads of department and accountants receive their account from the administrator.
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100"><?= icon('send') ?>Send my request</button>
        </form>
    </section>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
