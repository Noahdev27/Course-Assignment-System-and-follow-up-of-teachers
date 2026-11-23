<?php
/**
 * Payments of the year, with CSV export (F18, F23).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['accountant', 'admin']);
$year = require_active_year();

$payments = db_all(
    'SELECT p.*, u.last_name, u.first_name, u.academic_rank, d.code AS department_code, r.last_name AS recorder_last_name, r.first_name AS recorder_first_name,
            (SELECT COUNT(*) FROM class_sessions s WHERE s.payment_id = p.id) AS session_count,
            (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.payment_id = p.id) AS hours
     FROM payments p
     JOIN users u ON u.id = p.teacher_id
     LEFT JOIN departments d ON d.id = u.department_id
     LEFT JOIN users r ON r.id = p.recorded_by
     WHERE p.year_id = ?
     ORDER BY p.payment_date DESC, p.id DESC',
    'i',
    [(int) $year['id']]
);

// CSV export generated in plain PHP (opens in Excel)
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . $year['label'] . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so that Excel reads accented characters
    fputcsv($out, ['Date', 'Teacher', 'Department', 'Academic rank', 'Sessions', 'Hours', 'Amount (' . APP_CURRENCY . ')', 'Method', 'Reference'], ';');
    foreach ($payments as $p) {
        fputcsv($out, [
            $p['payment_date'], full_name($p), $p['department_code'], $p['academic_rank'], $p['session_count'],
            (float) $p['hours'], $p['amount'], PAYMENT_METHODS[$p['method']] ?? $p['method'], $p['reference'],
        ], ';');
    }
    fclose($out);
    exit;
}

$page_title = 'Payments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('receipt', 'greeting-icon') ?>Payments made</h1>
        <p class="lead-text"><?= count($payments) ?> payment(s) · <?= money(array_sum(array_column($payments, 'amount'))) ?> paid in <?= e($year['label']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('accountant/payments.php?export=csv')) ?>" class="btn btn-outline-secondary">Export to CSV</a>
        <a href="<?= e(url('accountant/payment_form.php')) ?>" class="btn btn-primary"><?= icon('cash-stack') ?>Pay a teacher</a>
    </div>
</div>

<div class="filters row g-2">
    <div class="col-md-5">
        <input type="search" class="form-control" placeholder="Teacher, reference…" aria-label="Search" data-filter-for="#paymentsTable" data-field="text">
    </div>
    <div class="col-md-3">
        <select class="form-select" data-filter-for="#paymentsTable" data-field="mode" aria-label="Method">
            <option value="">All methods</option>
            <?php foreach (PAYMENT_METHODS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-card" id="paymentsTable">
            <thead><tr><th>Date</th><th>Teacher</th><th class="num">Sessions</th><th class="num">Amount</th><th>Method</th><th>Reference</th><th>Recorded by</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr data-row data-mode="<?= e($p['method']) ?>">
                    <td class="text-nowrap"><?= format_date($p['payment_date']) ?></td>
                    <td><?= e(full_name($p)) ?> <span class="small text-body-secondary"><?= e($p['department_code']) ?></span></td>
                    <td class="num"><?= (int) $p['session_count'] ?> · <?= hours($p['hours']) ?></td>
                    <td class="num fw-semibold"><?= money($p['amount']) ?></td>
                    <td><?= e(PAYMENT_METHODS[$p['method']] ?? $p['method']) ?></td>
                    <td class="small"><?= e($p['reference'] ?: '—') ?></td>
                    <td class="small"><?= e(trim(($p['recorder_first_name'] ?? '') . ' ' . ($p['recorder_last_name'] ?? ''))) ?></td>
                    <td class="text-end"><a href="<?= e(url('accountant/statements.php?teacher_id=' . $p['teacher_id'])) ?>" class="btn btn-sm btn-outline-secondary">Statement</a></td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty <?= $payments ? 'hidden' : '' ?>><td colspan="8" class="empty-state">No payment for this year.</td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
