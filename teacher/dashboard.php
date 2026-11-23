<?php
/**
 * Teacher space: my courses, my workload, my hours taught and my amounts (F15, F20).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['teacher', 'head']);
$year = require_active_year();

$summary = teacher_summary((int) $user['id'], (int) $year['id']);
$assignments = db_all(
    "SELECT a.*, c.code, c.title, c.level, c.semester,
            (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status = 'approved') AS approved_hours,
            (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status = 'declared') AS pending_hours
     FROM assignments a JOIN courses c ON c.id = a.course_id
     WHERE a.teacher_id = ? AND a.year_id = ?
     ORDER BY c.semester, c.code, FIELD(a.type, 'CM', 'TD', 'TP')",
    'ii',
    [(int) $user['id'], (int) $year['id']]
);

$sessionCount = (int) db_value(
    'SELECT COUNT(*) FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id WHERE a.teacher_id = ? AND a.year_id = ?',
    'ii',
    [(int) $user['id'], (int) $year['id']]
);

$page_title = 'My courses and workload';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('sun', 'greeting-icon') ?><?= e(greeting()) ?>, <?= e($user['first_name']) ?></h1>
        <p class="lead-text"><?= e($user['academic_rank']) ?> · <?= e($user['department_name']) ?> · academic year <?= e($year['label']) ?></p>
    </div>
</div>

<?= how_it_works([
    ['You teach a class', 'Lecture, tutorial or lab, as planned.'],
    ['You declare it here', 'Click “I taught a class”: date, time, what you taught.'],
    ['Your head approves it', 'You see “Approved” next to the class.'],
    ['The accountant pays you', 'The money appears in “My money”.'],
], 'How do I get paid? (4 steps)', $sessionCount === 0) ?>

<h2 class="section-title">What would you like to do?</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xxl-3"><?= action_tile('teacher/sessions.php', 'easel2-fill', 'I taught a class', 'Declare it in one minute.', 'main') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('teacher/sessions.php#mySessions', 'clock-history', 'My classes', 'See if they are approved.', 'amber') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('teacher/payments.php', 'wallet2', 'My money', 'What I was paid, what is left.', 'blue') ?></div>
    <div class="col-md-6 col-xxl-3"><?= action_tile('teacher/profile.php', 'person-circle', 'My profile', 'Phone number and password.', 'pink') ?></div>
</div>

<h2 class="section-title">My year at a glance</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card kpi <?= $summary['overloaded'] ? 'kpi-alert' : '' ?>"><div class="card-body">
            <div class="kpi-label"><?= icon('briefcase') ?>Hours given to me</div>
            <div class="kpi-value"><?= hours($summary['teaching_load']) ?></div>
            <div class="kpi-note mb-2">out of <?= hours($summary['max_load']) ?> maximum</div>
            <?= progress_bar((float) $summary['teaching_load'], (float) $summary['max_load']) ?>
        </div></div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('easel2') ?>Hours taught</div>
            <div class="kpi-value"><?= hours($summary['approved_hours']) ?></div>
            <div class="kpi-note mb-2">approved · <?= hours($summary['pending_hours']) ?> pending</div>
            <?= progress_bar((float) $summary['approved_hours'], (float) $summary['teaching_load']) ?>
        </div></div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('cash-coin') ?>Earned so far</div>
            <div class="kpi-value fs-4"><?= money($summary['due']) ?></div>
            <div class="kpi-note">of which <?= money($summary['paid']) ?> already paid</div>
        </div></div>
    </div>
    <div class="col-md-6 col-lg-3">
        <a href="<?= e(url('teacher/payments.php')) ?>" class="text-decoration-none">
        <div class="card kpi"><div class="card-body">
            <div class="kpi-label"><?= icon('piggy-bank') ?>Still to receive</div>
            <div class="kpi-value fs-4"><?= money($summary['balance']) ?></div>
            <div class="kpi-note"><span class="badge <?= e($summary['status_class']) ?>"><?= e($summary['status_label']) ?></span></div>
        </div></div></a>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= icon('journal-text') ?>My courses this year</div>
    <div class="table-responsive">
        <table class="table table-card">
            <thead>
            <tr>
                <th>Course</th>
                <th>Level</th>
                <th>Type</th>
                <th class="num">Hours given</th>
                <th class="num">Approved</th>
                <th class="num">Waiting</th>
                <th class="num">Remaining</th>
                <th class="cell-progress">Progress</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $a):
                $remaining = max(0, (float) $a['hours'] - (float) $a['approved_hours'] - (float) $a['pending_hours']); ?>
                <tr>
                    <td><span class="code-badge"><?= e($a['code']) ?></span> <?= e($a['title']) ?></td>
                    <td><?= e($a['level']) ?> · S<?= (int) $a['semester'] ?></td>
                    <td><?= type_badge($a['type']) ?></td>
                    <td class="num"><?= hours($a['hours']) ?></td>
                    <td class="num"><?= hours($a['approved_hours']) ?></td>
                    <td class="num"><?= hours($a['pending_hours']) ?></td>
                    <td class="num"><?= hours($remaining) ?></td>
                    <td>
                        <?= progress_bar((float) $a['approved_hours'], (float) $a['hours'], true) ?>
                        <span class="small text-body-secondary"><?= (float) $a['hours'] > 0 ? round((float) $a['approved_hours'] / (float) $a['hours'] * 100) : 0 ?>%</span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assignments): ?>
                <tr><td colspan="8" class="empty-state"><?= icon('journal-x') ?>No course has been given to you yet this year. Your head of department will add them.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
