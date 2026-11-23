<?php
/**
 * Printable payment statements: overall summary or detailed statement for a teacher (F22).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['accountant', 'admin']);
$year = require_active_year();
$yearId = (int) $year['id'];

$teachers = teachers_overview($yearId);
$teacherId = (int) get('teacher_id');
$teacher = $teacherId ? teacher_summary($teacherId, $yearId) : null;

$sessions = [];
$payments = [];
if ($teacher) {
    $sessions = db_all(
        "SELECT s.*, a.type, c.code, c.title, COALESCE(t.rate_per_hour, 0) AS rate,
                s.duration * COALESCE(t.rate_per_hour, 0) AS computed_amount, p.payment_date
         FROM class_sessions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN courses c ON c.id = a.course_id
         JOIN users u ON u.id = a.teacher_id
         LEFT JOIN hourly_rates t ON t.academic_rank = u.academic_rank AND t.type = a.type
         LEFT JOIN payments p ON p.id = s.payment_id
         WHERE a.teacher_id = ? AND a.year_id = ? AND s.status = 'approved'
         ORDER BY c.code, s.session_date",
        'ii',
        [$teacherId, $yearId]
    );
    $payments = db_all(
        'SELECT * FROM payments WHERE teacher_id = ? AND year_id = ? ORDER BY payment_date',
        'ii',
        [$teacherId, $yearId]
    );
}

$page_title = 'Payment statements';
$nav_active = $user['role'] === 'admin' ? 'accountant/payments.php' : 'accountant/statements.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header no-print">
    <div>
        <h1><?= icon('printer', 'greeting-icon') ?>Payment statements</h1>
        <p class="lead-text">Choose a teacher for a detailed statement, or print the summary.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <select name="teacher_id" class="form-select" aria-label="Teacher" onchange="this.form.submit()">
                <option value="">Summary of all teachers</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"<?= selected($teacherId, $t['id']) ?>><?= e($t['last_name'] . ' ' . $t['first_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button type="button" class="btn btn-primary text-nowrap" data-print>Print</button>
    </div>
</div>

<div class="print-sheet">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <div class="text-body-secondary small"><?= e(APP_NAME) ?></div>
            <h2 class="h4 mb-0"><?= $teacher ? 'Payment statement' : 'Payment summary' ?></h2>
            <div>Academic year <?= e($year['label']) ?></div>
        </div>
        <div class="text-end small text-body-secondary">Printed on <?= date('d/m/Y \a\t H:i') ?></div>
    </div>

    <?php if ($teacher): ?>
        <div class="row mb-4">
            <div class="col-sm-6">
                <div class="fw-semibold fs-5"><?= e(full_name($teacher)) ?></div>
                <div><?= e($teacher['academic_rank']) ?> · <?= e($teacher['department_name']) ?></div>
                <div class="small text-body-secondary"><?= e($teacher['email']) ?></div>
            </div>
            <div class="col-sm-6 text-sm-end">
                <span class="badge fs-6 <?= e($teacher['status_class']) ?>"><?= e($teacher['status_label']) ?></span>
            </div>
        </div>

        <h3 class="h6 text-uppercase text-body-secondary">Approved sessions</h3>
        <table class="table table-sm mb-4">
            <thead><tr><th>Course</th><th>Date</th><th class="num">Duration</th><th class="num">Rate</th><th class="num">Amount</th><th>Paid on</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td><?= e($s['code'] . ' ' . $s['type']) ?></td>
                    <td><?= format_date($s['session_date']) ?></td>
                    <td class="num"><?= hours($s['duration']) ?></td>
                    <td class="num"><?= money($s['rate']) ?></td>
                    <td class="num"><?= money($s['computed_amount']) ?></td>
                    <td><?= $s['payment_date'] ? format_date($s['payment_date']) : '<span class="text-danger">To pay</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sessions): ?><tr><td colspan="6" class="empty-state">No approved session.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <h3 class="h6 text-uppercase text-body-secondary">Payments</h3>
        <table class="table table-sm mb-4">
            <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr><td><?= format_date($p['payment_date']) ?></td><td><?= e(PAYMENT_METHODS[$p['method']] ?? $p['method']) ?></td>
                    <td><?= e($p['reference'] ?: '—') ?></td><td class="num"><?= money($p['amount']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="4" class="empty-state">No payment.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <table class="table table-sm w-auto ms-auto">
            <tr><th>Approved hours</th><td class="num"><?= hours($teacher['approved_hours']) ?></td></tr>
            <tr><th>Amount due</th><td class="num"><?= money($teacher['due']) ?></td></tr>
            <tr><th>Total paid</th><td class="num"><?= money($teacher['paid']) ?></td></tr>
            <tr class="fw-bold"><th>Balance due</th><td class="num"><?= money($teacher['balance']) ?></td></tr>
        </table>

        <div class="row mt-5">
            <div class="col-6"><div class="signature-line">The accountant</div></div>
            <div class="col-6"><div class="signature-line">The teacher</div></div>
        </div>
    <?php else: ?>
        <table class="table table-sm">
            <thead><tr><th>Teacher</th><th>Dept</th><th>Academic rank</th><th class="num">Approved hours</th><th class="num">Due</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><?= e(full_name($t)) ?></td><td><?= e($t['department_code']) ?></td><td class="small"><?= e($t['academic_rank']) ?></td>
                    <td class="num"><?= hours($t['approved_hours']) ?></td><td class="num"><?= money($t['due']) ?></td>
                    <td class="num"><?= money($t['paid']) ?></td><td class="num"><?= money($t['balance']) ?></td>
                    <td><span class="badge <?= e($t['status_class']) ?>"><?= e($t['status_label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr class="fw-bold">
                <td colspan="4">Total</td>
                <td class="num"><?= money(array_sum(array_column($teachers, 'due'))) ?></td>
                <td class="num"><?= money(array_sum(array_column($teachers, 'paid'))) ?></td>
                <td class="num"><?= money(array_sum(array_column($teachers, 'balance'))) ?></td>
                <td></td>
            </tr>
            </tfoot>
        </table>
        <div class="row mt-5"><div class="col-6 offset-6"><div class="signature-line">The accountant</div></div></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
