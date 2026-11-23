<?php
/**
 * Teacher space: what I am owed and what I have received (F19, F20).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['teacher', 'head']);
$year = require_active_year();

$summary = teacher_summary((int) $user['id'], (int) $year['id']);
$payments = db_all(
    'SELECT p.*, (SELECT COUNT(*) FROM class_sessions s WHERE s.payment_id = p.id) AS session_count,
            (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.payment_id = p.id) AS hours
     FROM payments p WHERE p.teacher_id = ? AND p.year_id = ? ORDER BY p.payment_date DESC, p.id DESC',
    'ii',
    [(int) $user['id'], (int) $year['id']]
);
$unpaid = unpaid_sessions((int) $user['id'], (int) $year['id']);

$page_title = 'My money';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('wallet2', 'greeting-icon') ?>My money</h1>
        <p class="lead-text">Year <?= e($year['label']) ?> · rates for your academic rank (<?= e($user['academic_rank']) ?>)</p>
    </div>
    <span class="badge fs-6 <?= e($summary['status_class']) ?>"><?= e($summary['status_label']) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card kpi"><div class="card-body">
        <div class="kpi-label">Amount due</div><div class="kpi-value"><?= money($summary['due']) ?></div>
        <div class="kpi-note"><?= hours($summary['approved_hours']) ?> approved</div>
    </div></div></div>
    <div class="col-md-4"><div class="card kpi kpi-good"><div class="card-body">
        <div class="kpi-label">Already paid</div><div class="kpi-value"><?= money($summary['paid']) ?></div>
        <div class="kpi-note"><?= count($payments) ?> payment(s)</div>
    </div></div></div>
    <div class="col-md-4"><div class="card kpi <?= $summary['balance'] > 0 ? 'kpi-alert' : '' ?>"><div class="card-body">
        <div class="kpi-label">Balance due</div><div class="kpi-value"><?= money($summary['balance']) ?></div>
        <div class="kpi-note"><?= count($unpaid) ?> approved session(s) not yet paid</div>
    </div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Payments received</div>
            <div class="table-responsive">
                <table class="table table-card">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="num">Sessions</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= format_date($p['payment_date']) ?></td>
                            <td><?= e(PAYMENT_METHODS[$p['method']] ?? $p['method']) ?></td>
                            <td class="small"><?= e($p['reference'] ?: '—') ?></td>
                            <td class="num"><?= (int) $p['session_count'] ?> · <?= hours($p['hours']) ?></td>
                            <td class="num fw-semibold"><?= money($p['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$payments): ?><tr><td colspan="5" class="empty-state">No payment received yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Approved sessions waiting for payment</div>
            <div class="table-responsive">
                <table class="table table-card">
                    <thead><tr><th>Date</th><th>Course</th><th class="num">Duration</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($unpaid as $s): ?>
                        <tr>
                            <td><?= format_date($s['session_date']) ?></td>
                            <td><span class="code-badge"><?= e($s['code']) ?></span> <?= type_badge($s['type']) ?></td>
                            <td class="num"><?= hours($s['duration']) ?></td>
                            <td class="num small"><?= money($s['rate']) ?>/h</td>
                            <td class="num"><?= money($s['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$unpaid): ?><tr><td colspan="5" class="empty-state">Nothing waiting for payment.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
