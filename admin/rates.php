<?php
/**
 * Hourly rate grid by academic rank and by type (F16).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

$errors = [];

if (is_post()) {
    verify_csrf();
    $rates = $_POST['rates'] ?? [];
    $clean = [];
    foreach (GRADES as $gradeIndex => $grade) {
        foreach (array_keys(TYPES) as $type) {
            $value = trim((string) ($rates[$gradeIndex][$type] ?? ''));
            if (!ctype_digit($value) || (int) $value > 1000000) {
                $errors[] = 'The rate “' . $grade . ' / ' . $type . '” must be a positive whole number.';
                continue;
            }
            $clean[] = [$grade, $type, (int) $value];
        }
    }
    if (!$errors) {
        foreach ($clean as [$grade, $type, $value]) {
            db_exec(
                'INSERT INTO hourly_rates (academic_rank, type, rate_per_hour) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE rate_per_hour = VALUES(rate_per_hour)',
                'ssi',
                [$grade, $type, $value]
            );
        }
        log_action('Hourly rates', 'Hourly rate grid updated');
        flash('success', 'The hourly rate grid has been saved.');
        redirect_self();
    }
}

$current = [];
foreach (db_all('SELECT * FROM hourly_rates') as $row) {
    $current[$row['academic_rank']][$row['type']] = (int) $row['rate_per_hour'];
}

$page_title = 'Hourly rates';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('cash-coin', 'greeting-icon') ?>Hourly rates</h1>
        <p class="lead-text">Amount paid for one approved hour, in <?= e(APP_CURRENCY) ?>, by the teacher's academic rank and the session type.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card needs-validation" novalidate style="max-width: 820px">
    <?= csrf_field() ?>
    <div class="table-responsive">
        <table class="table table-card align-middle">
            <thead>
            <tr>
                <th>Academic rank</th>
                <?php foreach (TYPES as $type => $label): ?><th><?= e($type) ?> <span class="fw-normal text-lowercase">(<?= e($label) ?>)</span></th><?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach (GRADES as $gradeIndex => $grade): ?>
                <tr>
                    <td class="fw-semibold"><?= e($grade) ?></td>
                    <?php foreach (array_keys(TYPES) as $type): ?>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" min="0" step="100" required
                                       name="rates[<?= $gradeIndex ?>][<?= e($type) ?>]"
                                       aria-label="<?= e($grade . ' ' . $type) ?>"
                                       value="<?= e($current[$grade][$type] ?? 0) ?>">
                                <span class="input-group-text"><?= e(APP_CURRENCY) ?>/h</span>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center gap-2">
        <span class="form-hint">A rate change applies to sessions that are not paid yet.</span>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save the rates</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
