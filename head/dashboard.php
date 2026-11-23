<?php
/**
 * Head of department dashboard (F21, limited to their department).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['head']);
$year = require_active_year();
$yearId = (int) $year['id'];
$departmentId = (int) $user['department_id'];

$teachers = teachers_overview($yearId, $departmentId);
$courses = courses_coverage($yearId, $departmentId);
$incomplete = array_filter($courses, fn($c) => !$c['complete']);
$overloaded = array_filter($teachers, fn($t) => $t['overloaded']);
$pending = (int) db_value(
    "SELECT COUNT(*) FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id JOIN courses c ON c.id = a.course_id
     WHERE a.year_id = ? AND s.status = 'declared' AND c.department_id = ? AND a.teacher_id <> ?",
    'iii',
    [$yearId, $departmentId, (int) $user['id']]
);

$page_title = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('sun', 'greeting-icon') ?><?= e(greeting()) ?>, <?= e($user['first_name']) ?></h1>
        <p class="lead-text">Department <?= e($user['department_name']) ?> · academic year <?= e($year['label']) ?></p>
    </div>
</div>

<?= how_it_works([
    ['Give each course to a teacher', 'Use “Give a course to a teacher”. The hours left are shown for you.'],
    ['Teachers declare their classes', 'After each class they fill in a short form.'],
    ['You approve them', 'Open “Approve classes”, check, then approve or reject.'],
    ['The accountant pays', 'Only approved classes are paid.'],
], 'What is my job here? (4 steps)') ?>

<h2 class="section-title">What would you like to do?</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xxl-3"><?= action_tile('head/sessions_validation.php', 'check2-square', 'Approve classes', 'Check what teachers declared.', 'main', $pending) ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('admin/assignment_form.php', 'person-plus-fill', 'Give a course to a teacher', 'Create a new assignment.', 'amber') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('head/assignments.php', 'list-check', 'Who teaches what', 'All assignments of the department.', 'blue') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('teacher/sessions.php', 'easel2', 'I taught a class', 'Declare my own class.', 'pink') ?></div>
</div>

<h2 class="section-title">My department at a glance</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('people') ?>Teachers</div>
            <div class="kpi-value"><?= count($teachers) ?></div>
            <div class="kpi-note">in the department</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= e(url('admin/courses.php?incomplete=1')) ?>" class="text-decoration-none">
        <div class="card kpi <?= $incomplete ? 'kpi-alert' : 'kpi-good' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('exclamation-diamond') ?>Courses without teacher</div>
            <div class="kpi-value"><?= count($incomplete) ?> <span class="fs-6 fw-normal text-body-secondary">/ <?= count($courses) ?></span></div>
            <div class="kpi-note">hours still to assign</div>
        </div></div></a>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi <?= $overloaded ? 'kpi-alert' : 'kpi-good' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('speedometer2') ?>Overloaded</div>
            <div class="kpi-value"><?= count($overloaded) ?></div>
            <div class="kpi-note">teachers</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <a href="<?= e(url('head/sessions_validation.php')) ?>" class="text-decoration-none">
        <div class="card kpi <?= $pending ? 'kpi-alert' : '' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('hourglass-split') ?>To approve</div>
            <div class="kpi-value"><?= $pending ?></div>
            <div class="kpi-note">classes waiting</div>
        </div></div></a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><?= icon('people') ?>Workload of the department's teachers</div>
    <?php $show_money = false; require __DIR__ . '/../includes/views/teachers_table.php'; ?>
</div>

<div class="card">
    <div class="card-header"><?= icon('journal-bookmark') ?>Courses: hours given out of hours planned</div>
    <div class="table-responsive">
        <table class="table table-card">
            <thead><tr><th>Course</th><th>Level</th><th>CM · Lecture</th><th>TD · Tutorial</th><th>TP · Lab</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><span class="code-badge"><?= e($c['code']) ?></span> <?= e($c['title']) ?></td>
                    <td><?= e($c['level']) ?> · S<?= (int) $c['semester'] ?></td>
                    <?php foreach (array_keys(TYPES) as $type):
                        $planned = course_type_hours($c, $type);
                        $assigned = (float) $c['assigned_' . strtolower($type)]; ?>
                        <td class="small text-nowrap">
                            <?php if ($planned <= 0): ?><span class="text-body-tertiary">—</span>
                            <?php else: ?><span class="<?= $assigned >= $planned ? 'text-success' : 'text-danger fw-semibold' ?>"><?= hours($assigned) ?></span> / <?= hours($planned) ?><?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <?php if (!$c['complete']): ?>
                            <a href="<?= e(url('admin/assignment_form.php?course_id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><?= icon('person-plus') ?>Give to a teacher</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
