<?php
/**
 * Login page (F1). Then redirects each user to the space of their role.
 */
require_once __DIR__ . '/includes/functions.php';

if ($user = current_user()) {
    redirect(dashboard_path($user['role']));
}

$email = '';
$error = '';

if (is_post()) {
    verify_csrf();
    $email = mb_strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');

    $account = $email !== ''
        ? db_one('SELECT * FROM users WHERE email = ?', 's', [$email])
        : null;

    if (!$account || !password_verify($password, $account['password_hash'])) {
        $error = 'The email address or the password is not correct. Check them and try again.';
    } elseif ((int) $account['is_active'] !== 1) {
        // BR2: a deactivated account cannot log in; a new request waits for the administrator
        $error = is_pending_signup((int) $account['id'])
            ? 'Your account request has been received. The administrator has not approved it yet: please try again later.'
            : 'This account is deactivated. Please contact the administrator.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        db_exec('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)', 'iss',
            [(int) $account['id'], 'Login', $account['email']]);
        redirect(dashboard_path($account['role']));
    }
}

$demoAccounts = [
    ['admin@cas.local', 'Administrator', 'shield-lock'],
    ['tchoua@cas.local', 'Head of department', 'diagram-3'],
    ['essomba@cas.local', 'Teacher', 'person-video3'],
    ['accountant@cas.local', 'Accountant', 'calculator'],
];

$page_title = 'Log in';
$body_class = 'guest-page';
require __DIR__ . '/includes/header.php';
?>
<div class="guest-wrap">
    <section class="guest-side">
        <span class="brand-mark brand-mark-lg"><?= icon('mortarboard-fill') ?></span>
        <h1>Welcome!</h1>
        <p class="mb-0">This website helps the school share out the courses, check that classes took place and pay the teachers.</p>
        <ul class="journey">
            <li><?= icon('person-check') ?><div><strong>1. A course is given to a teacher</strong><span>The head of department decides who teaches what.</span></div></li>
            <li><?= icon('easel2') ?><div><strong>2. The teacher declares each class</strong><span>Date, time and what was taught.</span></div></li>
            <li><?= icon('patch-check') ?><div><strong>3. The head approves it</strong><span>Only real, approved classes count.</span></div></li>
            <li><?= icon('cash-stack') ?><div><strong>4. The accountant pays</strong><span>The amount is calculated automatically.</span></div></li>
        </ul>
    </section>

    <section class="guest-form">
        <h2 class="mb-1">Log in</h2>
        <p class="text-body-secondary">Enter the email address and the password of your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger app-alert" role="alert"><?= icon('exclamation-circle-fill', 'alert-icon') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="needs-validation" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="email" class="form-label"><?= icon('envelope', 'me-1') ?>Email address</label>
                <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= e($email) ?>"
                       placeholder="name@example.com" required autocomplete="username" autofocus>
                <div class="invalid-feedback">Type your email address, for example name@example.com.</div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label"><?= icon('key', 'me-1') ?>Password</label>
                <div class="password-field">
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required
                           autocomplete="current-password">
                    <button type="button" class="toggle-password" data-toggle-password="#password" aria-pressed="false"><?= icon('eye') ?> Show</button>
                    <div class="invalid-feedback">Type your password.</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100" id="loginButton"><?= icon('box-arrow-in-right') ?>Log in</button>
            <p class="form-hint mt-2 mb-0 text-center">Forgot your password? The administrator can give you a new one.</p>
        </form>

        <div class="or-line">No account yet?</div>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-accent btn-lg w-100"><?= icon('person-plus') ?>Create my teacher account</a>

        <details class="mt-4">
            <summary class="text-body-secondary small">Try the demo accounts (password: Passe1234)</summary>
            <div class="demo-grid mt-2">
                <?php foreach ($demoAccounts as [$demoEmail, $role, $iconName]): ?>
                    <button type="button" class="demo-account" data-fill-login="<?= e($demoEmail) ?>" data-password="Passe1234">
                        <?= icon($iconName) ?><span><strong><?= e($role) ?></strong><small><?= e($demoEmail) ?></small></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </details>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
