<?php
/**
 * Accountant dashboard: amounts due, paid and remaining (F19, F21).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['accountant', 'admin']);
$year = require_active_year();
$yearId = (int) $year['id'];

$teachers = teachers_overview($yearId);
$totalDue = array_sum(array_column($teachers, 'due'));
$totalPaid = array_sum(array_column($teachers, 'paid'));
$totalRemaining = array_sum(array_column($teachers, 'balance'));
$toPay = array_filter($teachers, fn($t) => (float) $t['balance'] > 0);
$unpaidSessions = (int) db_value(
    "SELECT COUNT(*) FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id
     WHERE a.year_id = ? AND s.status = 'approved' AND s.payment_id IS NULL",
    'i',
    [$yearId]
);
usort($teachers, fn($a, $b) => (float) $b['balance'] <=> (float) $a['balance']);

$page_title = 'Dashboard';
$nav_active = $user['role'] === 'admin' ? 'accountant/payments.php' : 'accountant/dashboard.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('sun', 'greeting-icon') ?><?= e(greeting()) ?>, <?= e($user['first_name']) ?></h1>
        <p class="lead-text">Teacher payments · academic year <?= e($year['label']) ?></p>
    </div>
</div>

<?= how_it_works([
    ['Choose a teacher', 'Teachers with money to receive have a “Pay” button.'],
    ['Tick the classes to pay', 'Only classes approved by the head are listed.'],
    ['Check the amount', 'It is calculated for you from the hourly rates.'],
    ['Save and print', 'Give the printed statement to the teacher.'],
], 'How do I pay a teacher? (4 steps)') ?>

<h2 class="section-title">What would you like to do?</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-4"><?= action_tile('accountant/payment_form.php', 'cash-stack', 'Pay a teacher', 'Record a new payment.', 'main', count($toPay)) ?></div>
    <div class="col-md-6 col-xl-4"><?= action_tile('accountant/payments.php', 'receipt', 'Payments made', 'List, search and CSV export.', 'amber') ?></div>
    <div class="col-md-6 col-xl-4"><?= action_tile('accountant/statements.php', 'printer', 'Print a statement', 'A sheet per teacher.', 'blue') ?></div>
</div>

<h2 class="section-title">Money at a glance</h2>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body">
        <div class="kpi-label"><?= icon('calculator') ?>Total earned</div><div class="kpi-value fs-4"><?= money($totalDue) ?></div>
        <div class="kpi-note">approved hours</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi kpi-good"><div class="card-body">
        <div class="kpi-label"><?= icon('check2-circle') ?>Already paid</div><div class="kpi-value fs-4"><?= money($totalPaid) ?></div>
        <div class="kpi-note"><?= $totalDue > 0 ? round($totalPaid / $totalDue * 100) : 0 ?>% of amount due</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi <?= $totalRemaining > 0 ? 'kpi-alert' : '' ?>"><div class="card-body">
        <div class="kpi-label"><?= icon('hourglass-split') ?>Still to pay</div><div class="kpi-value fs-4"><?= money($totalRemaining) ?></div>
        <div class="kpi-note"><?= $unpaidSessions ?> approved sessions not yet paid</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card kpi"><div class="card-body">
        <div class="kpi-label"><?= icon('people') ?>Teachers to pay</div><div class="kpi-value"><?= count($toPay) ?></div>
        <div class="kpi-note">out of <?= count($teachers) ?></div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header"><?= icon('people') ?>Where each teacher stands</div>
    <div class="table-responsive">
        <table class="table table-hover table-card">
            <thead><tr><th>Teacher</th><th>Dept</th><th>Academic rank</th><th class="num">Approved hours</th><th class="num">Due</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td class="fw-semibold"><?= e(full_name($t)) ?></td>
                    <td><?= e($t['department_code']) ?></td>
                    <td class="small"><?= e($t['academic_rank']) ?></td>
                    <td class="num"><?= hours($t['approved_hours']) ?></td>
                    <td class="num"><?= money($t['due']) ?></td>
                    <td class="num"><?= money($t['paid']) ?></td>
                    <td class="num fw-semibold"><?= money($t['balance']) ?></td>
                    <td><span class="badge <?= e($t['status_class']) ?>"><?= e($t['status_label']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ((float) $t['balance'] > 0): ?>
                            <a href="<?= e(url('accountant/payment_form.php?teacher_id=' . $t['id'])) ?>" class="btn btn-sm btn-primary"><?= icon('cash-coin') ?>Pay</a>
                        <?php endif; ?>
                        <a href="<?= e(url('accountant/statements.php?teacher_id=' . $t['id'])) ?>" class="btn btn-sm btn-outline-secondary"><?= icon('printer') ?>Statement</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
