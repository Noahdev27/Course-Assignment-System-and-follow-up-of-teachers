<?php
/**
 * Administrator dashboard (F21).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);
$year = require_active_year();
$yearId = (int) $year['id'];

$teachers = teachers_overview($yearId);
$activeTeachers = array_filter($teachers, fn($t) => (int) $t['is_active'] === 1);
$courses = courses_coverage($yearId);
$incomplete = array_filter($courses, fn($c) => !$c['complete']);
$overloaded = array_filter($teachers, fn($t) => $t['overloaded']);
$pending = (int) db_value(
    "SELECT COUNT(*) FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id
     WHERE a.year_id = ? AND s.status = 'declared'",
    'i',
    [$yearId]
);
$totalRemaining = array_sum(array_column($teachers, 'balance'));
$requests = pending_signups();
$recentLog = db_all(
    "SELECT j.*, u.last_name, u.first_name FROM activity_log j LEFT JOIN users u ON u.id = j.user_id
     WHERE j.action NOT IN ('Login', 'Logout')
     ORDER BY j.created_at DESC, j.id DESC LIMIT 6"
);

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('sun', 'greeting-icon') ?><?= e(greeting()) ?>, <?= e($user['first_name']) ?></h1>
        <p class="lead-text">Here is where things stand for academic year <?= e($year['label']) ?>.</p>
    </div>
</div>

<?php $return_to = 'dashboard'; require __DIR__ . '/../includes/views/signup_requests.php'; ?>

<h2 class="section-title">What would you like to do?</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xxl-3"><?= action_tile('admin/assignment_form.php', 'person-plus-fill', 'Give a course to a teacher', 'Create a new assignment.', 'main') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('head/sessions_validation.php', 'check2-square', 'Approve classes', 'Classes declared by the heads.', 'amber', $pending) ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('admin/users.php', 'people', 'Manage accounts', 'Teachers, heads, accountant.', 'blue', count($requests)) ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('admin/reports.php', 'bar-chart-line', 'See the reports', 'By department or by teacher.', 'pink') ?></div>
</div>

<h2 class="section-title">At a glance</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('people') ?>Teachers</div>
            <div class="kpi-value"><?= count($activeTeachers) ?></div>
            <div class="kpi-note">active</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('journal-bookmark') ?>Courses</div>
            <div class="kpi-value"><?= count($courses) ?></div>
            <div class="kpi-note">active</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <a href="<?= e(url('admin/courses.php?incomplete=1')) ?>" class="text-decoration-none">
        <div class="card kpi <?= $incomplete ? 'kpi-alert' : 'kpi-good' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('exclamation-diamond') ?>Without teacher</div>
            <div class="kpi-value"><?= count($incomplete) ?></div>
            <div class="kpi-note">courses with hours left</div>
        </div></div></a>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card kpi <?= $overloaded ? 'kpi-alert' : 'kpi-good' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('speedometer2') ?>Overloaded</div>
            <div class="kpi-value"><?= count($overloaded) ?></div>
            <div class="kpi-note">teachers</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-2">
        <a href="<?= e(url('head/sessions_validation.php')) ?>" class="text-decoration-none">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('hourglass-split') ?>To approve</div>
            <div class="kpi-value"><?= $pending ?></div>
            <div class="kpi-note">classes waiting</div>
        </div></div></a>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('cash-coin') ?>Still to pay</div>
            <div class="kpi-value fs-5"><?= money($totalRemaining) ?></div>
            <div class="kpi-note">for approved classes</div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= icon('people') ?>Teachers: workload and payments</span>
                <a href="<?= e(url('admin/reports.php?type=department')) ?>" class="small">Full report</a>
            </div>
            <?php $show_money = true; require __DIR__ . '/../includes/views/teachers_table.php'; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= icon('exclamation-diamond') ?>Courses that still need a teacher</div>
            <ul class="list-group list-group-flush">
                <?php foreach (array_slice($incomplete, 0, 6) as $c): ?>
                    <li class="list-group-item">
                        <span class="code-badge"><?= e($c['code']) ?></span> <?= e($c['title']) ?>
                        <div class="small text-body-secondary mt-1">
                            <?php foreach (TYPES as $type => $label):
                                $planned = course_type_hours($c, $type);
                                if ($planned <= 0) continue;
                                $done = (float) $c['assigned_' . strtolower($type)]; ?>
                                <span class="me-2 <?= $done < $planned ? 'text-danger' : '' ?>"><?= $type ?> <?= hours($done) ?>/<?= hours($planned) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$incomplete): ?>
                    <li class="list-group-item text-body-secondary"><?= icon('emoji-smile') ?> Well done: every course has its teachers.</li>
                <?php elseif (count($incomplete) > 6): ?>
                    <li class="list-group-item"><a href="<?= e(url('admin/courses.php?incomplete=1')) ?>">See the <?= count($incomplete) ?> courses</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= icon('clock-history') ?>Latest actions</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($recentLog as $log): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($log['action']) ?></div>
                        <div><?= e($log['details']) ?></div>
                        <div class="text-body-secondary"><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?> · <?= e(date('d/m/Y H:i', strtotime($log['created_at']))) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
