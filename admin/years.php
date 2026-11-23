<?php
/**
 * Academic years: creation, active year, deletion (F7, BR9).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

$form = ['label' => '', 'start_date' => '', 'end_date' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'create') {
        $form = ['label' => post('label'), 'start_date' => post('start_date'), 'end_date' => post('end_date')];
        if (!preg_match('/^(\d{4})-(\d{4})$/', $form['label'], $m) || (int) $m[2] !== (int) $m[1] + 1) {
            $errors[] = 'The label must follow the format 2026-2027.';
        } elseif (db_value('SELECT COUNT(*) FROM academic_years WHERE label = ?', 's', [$form['label']]) > 0) {
            $errors[] = 'This academic year already exists.';
        }
        if (!valid_date($form['start_date']) || !valid_date($form['end_date'])) {
            $errors[] = 'Enter a start date and an end date.';
        } elseif ($form['end_date'] <= $form['start_date']) {
            $errors[] = 'The end date must be after the start date.';
        }
        if (!$errors) {
            db_exec('INSERT INTO academic_years (label, start_date, end_date) VALUES (?, ?, ?)', 'sss',
                [$form['label'], $form['start_date'], $form['end_date']]);
            log_action('Academic year', 'Year ' . $form['label'] . ' created');
            flash('success', 'Year ' . $form['label'] . ' has been created. Make it active when it starts.');
            redirect_self();
        }
    } elseif ($action === 'activate' && $id) {
        $target = db_one('SELECT * FROM academic_years WHERE id = ?', 'i', [$id]);
        if ($target) {
            // Only one active year at a time
            db_exec('UPDATE academic_years SET is_active = 0');
            db_exec('UPDATE academic_years SET is_active = 1 WHERE id = ?', 'i', [$id]);
            log_action('Academic year', 'Year ' . $target['label'] . ' set as the active year');
            flash('success', 'Year ' . $target['label'] . ' is now the active year.');
        }
        redirect_self();
    } elseif ($action === 'delete' && $id) {
        $target = db_one('SELECT * FROM academic_years WHERE id = ?', 'i', [$id]);
        $used = (int) db_value('SELECT COUNT(*) FROM assignments WHERE year_id = ?', 'i', [$id])
            + (int) db_value('SELECT COUNT(*) FROM payments WHERE year_id = ?', 'i', [$id]);
        if (!$target) {
            flash('danger', 'Year not found.');
        } elseif ((int) $target['is_active'] === 1) {
            flash('warning', 'The active year cannot be deleted.');
        } elseif ($used > 0) {
            flash('warning', 'This year has assignments or payments: it is kept.');
        } else {
            db_exec('DELETE FROM preferences WHERE year_id = ?', 'i', [$id]);
            db_exec('DELETE FROM academic_years WHERE id = ?', 'i', [$id]);
            log_action('Academic year', 'Year ' . $target['label'] . ' deleted');
            flash('success', 'Year ' . $target['label'] . ' has been deleted.');
        }
        redirect_self();
    }
}

$years = db_all(
    'SELECT y.*, (SELECT COUNT(*) FROM assignments a WHERE a.year_id = y.id) AS assignment_count
     FROM academic_years y ORDER BY y.start_date DESC'
);

$page_title = 'Academic years';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('calendar3', 'greeting-icon') ?>Academic years</h1>
        <p class="lead-text">Only the active year can receive assignments, sessions and payments; the other years are read-only.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-card">
                    <thead><tr><th>Year</th><th>Start</th><th>End</th><th class="num">Assignments</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($years as $y): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($y['label']) ?></td>
                            <td><?= format_date($y['start_date']) ?></td>
                            <td><?= format_date($y['end_date']) ?></td>
                            <td class="num"><?= (int) $y['assignment_count'] ?></td>
                            <td><?= (int) $y['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-light border">Read-only</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <?php if (!(int) $y['is_active']): ?>
                                    <form method="post" class="d-inline" data-confirm="Make <?= e($y['label']) ?> the active year?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="id" value="<?= (int) $y['id'] ?>">
                                        <button class="btn btn-sm btn-outline-primary">Make active</button>
                                    </form>
                                    <?php if ((int) $y['assignment_count'] === 0): ?>
                                    <form method="post" class="d-inline" data-confirm="Delete year <?= e($y['label']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $y['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">New year</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" class="row g-3 needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="col-12">
                        <label for="label" class="form-label">Label</label>
                        <input type="text" class="form-control" id="label" name="label" placeholder="2027-2028"
                               value="<?= e($form['label']) ?>" required pattern="\d{4}-\d{4}">
                        <div class="invalid-feedback">Expected format: 2027-2028.</div>
                    </div>
                    <div class="col-6">
                        <label for="start_date" class="form-label">Start</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($form['start_date']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="end_date" class="form-label">End</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= e($form['end_date']) ?>"
                               required data-after="#start_date">
                        <div class="invalid-feedback">The end must be after the start.</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary"><?= icon('plus-lg') ?>Create the year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
