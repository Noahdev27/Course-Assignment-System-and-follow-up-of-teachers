<?php
/**
 * Shared header: Bootstrap, role-based navigation bar, help panel, flash messages.
 * Optional variables set by the page before including it:
 *   $page_title  page title
 *   $nav_active  path of the menu link to highlight (e.g. 'admin/users.php')
 *   $body_class  extra class on <body> (the login and sign-up pages use 'guest-page')
 */

$user = current_user();
$year = active_year();
$page_title = $page_title ?? APP_NAME;
$body_class = $body_class ?? '';
$scriptPath = ltrim(substr($_SERVER['SCRIPT_NAME'] ?? '', strlen(rtrim(BASE_URL, '/'))), '/');
$nav_active = $nav_active ?? $scriptPath;

// Number of account requests shown on the admin menu
$pendingCount = ($user && $user['role'] === 'admin') ? count(pending_signups()) : 0;

// Menus by role: [label, path, icon]; a nested array becomes a dropdown menu
$teachingMenu = ['My teaching', [
    ['My courses and workload', 'teacher/dashboard.php', 'journal-text'],
    ['My classes', 'teacher/sessions.php', 'clock-history'],
    ['My money', 'teacher/payments.php', 'wallet2'],
], 'person-workspace'];
$menus = [
    'admin' => [
        ['Home', 'admin/dashboard.php', 'house-door'],
        ['Settings', [
            ['Users', 'admin/users.php', 'people'],
            ['Departments', 'admin/departments.php', 'building'],
            ['Courses', 'admin/courses.php', 'journal-bookmark'],
            ['Academic years', 'admin/years.php', 'calendar3'],
            ['Hourly rates', 'admin/rates.php', 'cash-coin'],
        ], 'gear'],
        ['Assignments', 'admin/assignments.php', 'person-check'],
        ['Classes to approve', 'head/sessions_validation.php', 'check2-square'],
        ['Payments', 'accountant/payments.php', 'wallet2'],
        ['Reports', 'admin/reports.php', 'bar-chart-line'],
    ],
    'head' => [
        ['Home', 'head/dashboard.php', 'house-door'],
        ['Assignments', 'head/assignments.php', 'person-check'],
        ['Courses', 'admin/courses.php', 'journal-bookmark'],
        ['Classes to approve', 'head/sessions_validation.php', 'check2-square'],
        ['Reports', 'admin/reports.php', 'bar-chart-line'],
        $teachingMenu,
    ],
    'teacher' => [
        ['Home', 'teacher/dashboard.php', 'house-door'],
        ['My classes', 'teacher/sessions.php', 'clock-history'],
        ['My money', 'teacher/payments.php', 'wallet2'],
    ],
    'accountant' => [
        ['Home', 'accountant/dashboard.php', 'house-door'],
        ['Payments', 'accountant/payments.php', 'wallet2'],
        ['New payment', 'accountant/payment_form.php', 'plus-circle'],
        ['Payment statements', 'accountant/statements.php', 'printer'],
    ],
];
$menu = $user ? ($menus[$user['role']] ?? []) : [];

// Help panel: what each role can do, in plain words
$help = [
    'admin' => [
        'You prepare the year and make sure every course has a teacher.',
        'Accept the account requests of new teachers (Home page).',
        'Create courses and departments in Settings.',
        'Use “Assignments” to say who teaches which course.',
        'Approve the classes declared by the heads of department.',
    ],
    'head' => [
        'You manage the teachers and courses of your department.',
        'Use “Assignments” to give each course to a teacher.',
        'Open “Sessions to approve” to confirm the classes really took place.',
        'You also teach: “My teaching” holds your own courses and pay.',
    ],
    'teacher' => [
        'After each class, click “I taught a class” and fill in the form.',
        'Your head of department checks it and approves it.',
        'Approved classes are paid: follow your money in “My money”.',
    ],
    'accountant' => [
        'You pay teachers for the classes that were approved.',
        'Click “New payment”, choose the teacher and tick the classes to pay.',
        'The amount is computed for you from the hourly rates.',
        'Print a statement to give to the teacher.',
    ],
];
$flashIcons = ['success' => 'check-circle-fill', 'danger' => 'x-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>" media="print">
</head>
<body class="<?= e($body_class) ?>">
<?php if ($user): ?>
<nav class="navbar navbar-expand-xl navbar-dark app-navbar no-print">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand" href="<?= e(url(dashboard_path($user['role']))) ?>">
            <span class="brand-mark"><?= icon('mortarboard-fill') ?></span>
            <span class="brand-text">Course Assignment</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Show menu">
            <span class="navbar-toggler-icon"></span> <span class="small ms-1">Menu</span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?php foreach ($menu as [$label, $target, $iconName]): ?>
                    <?php if (is_array($target)):
                        $isActive = in_array($nav_active, array_column($target, 1), true); ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?= $isActive ? ' active' : '' ?>" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false"><?= icon($iconName) ?> <?= e($label) ?></a>
                            <ul class="dropdown-menu">
                                <?php foreach ($target as [$subLabel, $subPath, $subIcon]): ?>
                                    <li><a class="dropdown-item<?= $nav_active === $subPath ? ' active' : '' ?>"
                                           href="<?= e(url($subPath)) ?>"><?= icon($subIcon, 'me-2') ?><?= e($subLabel) ?>
                                            <?php if ($subPath === 'admin/users.php' && $pendingCount): ?>
                                                <span class="badge rounded-pill text-bg-warning ms-1"><?= $pendingCount ?></span>
                                            <?php endif; ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $nav_active === $target ? ' active' : '' ?>"
                               <?= $nav_active === $target ? 'aria-current="page"' : '' ?>
                               href="<?= e(url($target)) ?>"><?= icon($iconName) ?> <?= e($label) ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($year): ?>
                    <span class="badge rounded-pill year-pill" title="Active academic year"><?= icon('calendar-check') ?> <?= e($year['label']) ?></span>
                <?php endif; ?>
                <button type="button" class="btn btn-help" data-bs-toggle="offcanvas" data-bs-target="#helpPanel" aria-controls="helpPanel">
                    <?= icon('question-circle-fill') ?> Help
                </button>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white user-chip" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1))) ?></span>
                        <span><?= e($user['first_name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-body-secondary"><?= e(full_name($user)) ?><br><?= e(ROLES[$user['role']] ?? $user['role']) ?></span></li>
                        <li><a class="dropdown-item" href="<?= e(url('teacher/profile.php')) ?>"><?= icon('person-circle', 'me-2') ?>My profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(url('logout.php')) ?>"><?= icon('box-arrow-right', 'me-2') ?>Log out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-end no-print" tabindex="-1" id="helpPanel" aria-labelledby="helpPanelTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="helpPanelTitle"><?= icon('life-preserver', 'me-2') ?>How it works</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-body-secondary">You are logged in as <strong><?= e(ROLES[$user['role']] ?? $user['role']) ?></strong>.</p>
        <ol class="help-list">
            <?php foreach ($help[$user['role']] ?? [] as $line): ?>
                <li><?= e($line) ?></li>
            <?php endforeach; ?>
        </ol>
        <h3 class="h6 mt-4">Words you will see</h3>
        <dl class="glossary">
            <dt>CM · Lecture</dt><dd>A class given to the whole group.</dd>
            <dt>TD · Tutorial</dt><dd>Exercises in smaller groups.</dd>
            <dt>TP · Lab</dt><dd>Practical work, often on computers.</dd>
            <dt>Assignment</dt><dd>A course given to a teacher, with a number of hours.</dd>
            <dt>Session</dt><dd>One class that really took place (date, time, what was taught).</dd>
            <dt>Workload</dt><dd>All the hours given to a teacher for the year, compared with their maximum.</dd>
            <dt>Balance due</dt><dd>Money still owed to a teacher for approved classes.</dd>
        </dl>
        <div class="info-box mt-4"><?= icon('telephone', 'me-1') ?> Stuck? Contact the administrator of the system.</div>
    </div>
</div>
<?php endif; ?>
<main class="<?= $user ? 'container-xl py-4' : 'guest-main' ?>">
<?php foreach (take_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show no-print app-alert" role="alert"
         <?= $flash['type'] === 'success' ? 'data-celebrate' : '' ?>>
        <?= icon($flashIcons[$flash['type']] ?? 'info-circle-fill', 'alert-icon') ?>
        <div><?= e($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
