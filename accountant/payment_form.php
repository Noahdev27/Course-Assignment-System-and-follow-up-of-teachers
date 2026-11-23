<?php
/**
 * Recording a payment (F17, F18, BR13, BR14, BR15).
 * The accountant ticks the approved sessions to settle: the amount is computed from the hourly rates,
 * so it can never exceed the balance due.
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['accountant', 'admin']);
$year = require_active_year();
$yearId = (int) $year['id'];
$today = date('Y-m-d');

$teacherId = (int) (is_post() ? post('teacher_id') : get('teacher_id'));
$teacher = $teacherId ? teacher_summary($teacherId, $yearId) : null;
$form = ['payment_date' => $today, 'method' => 'bank_transfer', 'reference' => '', 'ids' => []];
$errors = [];

if (is_post()) {
    verify_csrf();
    $form = [
        'payment_date' => post('payment_date'),
        'method' => post('method'),
        'reference' => post('reference'),
        'ids' => array_map('intval', (array) ($_POST['ids'] ?? [])),
    ];
    if (!$teacher) $errors[] = 'Choose a teacher.';
    if (!valid_date($form['payment_date']) || $form['payment_date'] > $today) $errors[] = 'The payment date must be today or in the past.';
    if (!array_key_exists($form['method'], PAYMENT_METHODS)) $errors[] = 'Choose the payment method.';
    if (mb_strlen($form['reference']) > 60) $errors[] = 'The reference is limited to 60 characters.';
    if (!$form['ids']) $errors[] = 'Tick at least one session to pay.';

    $selected = [];
    if ($teacher && !$errors) {
        // Only approved, unpaid sessions of this teacher in the active year are kept
        foreach (unpaid_sessions($teacherId, $yearId) as $session) {
            if (in_array((int) $session['id'], $form['ids'], true)) {
                $selected[] = $session;
            }
        }
        if (count($selected) !== count(array_unique($form['ids']))) {
            $errors[] = 'Some ticked sessions no longer need paying (already paid or not approved). Reload the page.';
        }
    }

    if (!$errors) {
        $amount = (int) round(array_sum(array_column($selected, 'amount')));
        if ($amount <= 0) {
            $errors[] = 'The computed amount is zero: check the hourly rate grid.';
        } elseif ($amount > (float) $teacher['balance']) {
            $errors[] = 'The payment would exceed the balance due (BR15).';
        }
    }

    if (!$errors) {
        $db = db();
        $db->begin_transaction();
        try {
            db_exec(
                'INSERT INTO payments (teacher_id, year_id, amount, payment_date, method, reference, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iiisssi',
                [$teacherId, $yearId, $amount, $form['payment_date'], $form['method'], $form['reference'] ?: null, (int) $user['id']]
            );
            $paymentId = db_insert_id();
            foreach ($selected as $session) {
                $updated = db_exec(
                    "UPDATE class_sessions SET payment_id = ? WHERE id = ? AND status = 'approved' AND payment_id IS NULL",
                    'ii',
                    [$paymentId, (int) $session['id']]
                );
                if ($updated !== 1) {
                    throw new RuntimeException('Session already paid in the meantime.');
                }
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollback();
            flash('danger', 'The payment was not saved: a session changed in the meantime. Please try again.');
            redirect('accountant/payment_form.php?teacher_id=' . $teacherId);
        }
        log_action('Payment', money($amount) . ' for ' . full_name($teacher) . ' (' . count($selected) . ' sessions)');
        flash('success', 'Payment of ' . money($amount) . ' recorded for ' . full_name($teacher) . '.');
        redirect('accountant/payments.php');
    }
}

$toPay = array_values(array_filter(teachers_overview($yearId), fn($t) => (float) $t['balance'] > 0));
$sessions = $teacher ? unpaid_sessions($teacherId, $yearId) : [];
if (!is_post()) {
    $form['ids'] = array_map(fn($s) => (int) $s['id'], $sessions); // everything is ticked by default
}

$page_title = 'New payment';
$nav_active = $user['role'] === 'admin' ? 'accountant/payments.php' : 'accountant/payment_form.php';
$page_scripts = ['payments.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('cash-stack', 'greeting-icon') ?>Pay a teacher</h1>
        <p class="lead-text">Year <?= e($year['label']) ?> · only approved, unpaid sessions can be settled.</p>
    </div>
    <a href="<?= e(url('accountant/payments.php')) ?>" class="btn btn-outline-secondary">Payment list</a>
</div>

<form method="get" class="card mb-4" id="teacherPicker" data-api-due="<?= e(url('api/amount_due.php')) ?>">
    <div class="card-body row g-3 align-items-end">
        <div class="col-md-6">
            <label for="teacher_id" class="form-label">Teacher to pay</label>
            <select class="form-select" id="teacher_id" name="teacher_id" required>
                <option value="">Choose…</option>
                <?php foreach ($toPay as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"<?= selected($teacherId, $t['id']) ?>>
                        <?= e($t['last_name'] . ' ' . $t['first_name']) ?> — balance <?= money($t['balance']) ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($teacher && (float) $teacher['balance'] <= 0): ?>
                    <option value="<?= (int) $teacher['id'] ?>" selected><?= e(full_name($teacher)) ?> — nothing to pay</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="col-md-4">
            <div class="info-box" id="dueInfo" hidden></div>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-outline-primary">Show</button>
        </div>
    </div>
</form>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($teacher): ?>
    <?php if (!$sessions): ?>
        <div class="alert alert-info"><?= e(full_name($teacher)) ?> has no approved session waiting for payment.</div>
    <?php else: ?>
    <form method="post" class="needs-validation" novalidate id="paymentForm">
        <?= csrf_field() ?>
        <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span>Sessions to settle · <?= e(full_name($teacher)) ?> (<?= e($teacher['academic_rank']) ?>)</span>
                    </div>
                    <div class="table-responsive" data-require-one="#noSessionMessage">
                        <table class="table table-card">
                            <thead><tr>
                                <th><input type="checkbox" class="form-check-input" id="checkAllPay" aria-label="Tick all"<?= checked(count($form['ids']) === count($sessions)) ?>></th>
                                <th>Date</th><th>Course</th><th class="num">Duration</th><th class="num">Rate</th><th class="num">Amount</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input pay-check" name="ids[]" value="<?= (int) $s['id'] ?>"
                                               data-amount="<?= e($s['amount']) ?>" aria-label="Pay this session"<?= checked(in_array((int) $s['id'], $form['ids'], true)) ?>></td>
                                    <td class="text-nowrap"><?= format_date($s['session_date']) ?></td>
                                    <td><span class="code-badge"><?= e($s['code']) ?></span> <?= type_badge($s['type']) ?>
                                        <div class="small text-body-secondary"><?= e($s['content']) ?></div></td>
                                    <td class="num"><?= hours($s['duration']) ?></td>
                                    <td class="num small"><?= money($s['rate']) ?>/h</td>
                                    <td class="num"><?= money($s['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body pt-0">
                        <div class="text-danger small" id="noSessionMessage" hidden>Tick at least one session.</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">Payment</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <div class="text-body-secondary small">Computed amount</div>
                            <div class="payment-total" id="paymentTotal">0 FCFA</div>
                            <div class="small text-body-secondary">Balance before this payment: <?= money($teacher['balance']) ?></div>
                        </div>
                        <div class="col-12">
                            <label for="payment_date" class="form-label">Payment date</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= e($form['payment_date']) ?>"
                                   max="<?= e($today) ?>" data-max-today required>
                            <div class="invalid-feedback">Today or earlier.</div>
                        </div>
                        <div class="col-12">
                            <label for="method" class="form-label">Method</label>
                            <select class="form-select" id="method" name="method" required>
                                <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= selected($form['method'], $key) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="reference" class="form-label">Reference <span class="text-body-secondary">(receipt, transaction…)</span></label>
                            <input type="text" class="form-control" id="reference" name="reference" maxlength="60" value="<?= e($form['reference']) ?>">
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save the payment</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
