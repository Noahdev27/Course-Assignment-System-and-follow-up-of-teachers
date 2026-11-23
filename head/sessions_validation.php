<?php
/**
 * Approval or rejection of declared sessions (F14, BR12, BR13).
 * A head approves the sessions of their department, except their own: the administrator handles those.
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['head', 'admin']);
$year = require_active_year();
$isAdmin = $user['role'] === 'admin';

/** Pending session the user is allowed to review, or null. */
function reviewable_session(int $sessionId, array $user, array $year): ?array
{
    $session = db_one(
        "SELECT s.*, a.hours AS assigned_hours, a.teacher_id, a.year_id, a.type, c.code, c.department_id,
                u.last_name, u.first_name
         FROM class_sessions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN courses c ON c.id = a.course_id
         JOIN users u ON u.id = a.teacher_id
         WHERE s.id = ?",
        'i',
        [$sessionId]
    );
    if (!$session || $session['status'] !== 'declared' || (int) $session['year_id'] !== (int) $year['id']) {
        return null;
    }
    if ($user['role'] === 'head'
        && ((int) $session['department_id'] !== (int) $user['department_id'] || (int) $session['teacher_id'] === (int) $user['id'])) {
        return null;
    }
    return $session;
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $ids = $action === 'validate_many' ? array_map('intval', (array) ($_POST['ids'] ?? [])) : [(int) post('id')];
    $validated = 0;

    foreach ($ids as $sessionId) {
        $session = reviewable_session($sessionId, $user, $year);
        if (!$session) {
            flash('warning', 'A session could not be processed: it is no longer pending or is outside your scope.');
            continue;
        }
        $label = $session['code'] . ' ' . $session['type'] . ' of ' . format_date($session['session_date']) . ' — ' . full_name($session);

        if ($action === 'reject') {
            $reason = post('reason');
            if ($reason === '') {
                flash('danger', 'Give the reason for rejection.');
                break;
            }
            db_exec("UPDATE class_sessions SET status = 'rejected', rejection_reason = ?, reviewed_by = ? WHERE id = ?", 'sii',
                [mb_substr($reason, 0, 255), (int) $user['id'], $sessionId]);
            log_action('Session rejected', $label . ': ' . $reason);
            flash('success', 'Session rejected: ' . $label . '.');
            continue;
        }

        // BR12: approved hours do not exceed assigned hours
        $alreadyValidated = assignment_session_hours((int) $session['assignment_id'], true);
        if ($alreadyValidated + (float) $session['duration'] > (float) $session['assigned_hours']) {
            flash('danger', 'Cannot approve ' . $label . ': approved hours would exceed the '
                . hours($session['assigned_hours']) . ' assigned (BR12).');
            continue;
        }
        db_exec("UPDATE class_sessions SET status = 'approved', rejection_reason = NULL, reviewed_by = ? WHERE id = ?", 'ii',
            [(int) $user['id'], $sessionId]);
        log_action('Session approved', $label);
        $validated++;
    }
    if ($validated > 0) {
        flash('success', $validated === 1 ? 'Session approved.' : $validated . ' sessions approved.');
    }
    redirect_self();
}

$baseSql = "SELECT s.*, a.type, a.hours AS assigned_hours, c.code, c.title, d.code AS department_code,
                   u.last_name, u.first_name, v.last_name AS reviewer_last_name, v.first_name AS reviewer_first_name
            FROM class_sessions s
            JOIN assignments a ON a.id = s.assignment_id
            JOIN courses c ON c.id = a.course_id
            JOIN departments d ON d.id = c.department_id
            JOIN users u ON u.id = a.teacher_id
            LEFT JOIN users v ON v.id = s.reviewed_by
            WHERE a.year_id = ?";
$types = 'i';
$params = [(int) $year['id']];
if (!$isAdmin) {
    $baseSql .= ' AND c.department_id = ? AND a.teacher_id <> ?';
    $types .= 'ii';
    $params[] = (int) $user['department_id'];
    $params[] = (int) $user['id'];
}
$pending = db_all($baseSql . " AND s.status = 'declared' ORDER BY s.session_date, s.start_time", $types, $params);
$history = db_all($baseSql . " AND s.status <> 'declared' ORDER BY s.session_date DESC, s.start_time DESC LIMIT 25", $types, $params);

$page_title = 'Sessions to approve';
$page_scripts = ['sessions.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('check2-square', 'greeting-icon') ?>Classes to approve</h1>
        <p class="lead-text">
            <?= count($pending) ? count($pending) . ' class(es) are waiting for your decision' : 'Nothing is waiting for you' ?><?= $isAdmin ? '' : ' · department ' . e($user['department_code']) ?>.
            Approve a class if it really took place; otherwise reject it and say why.
            <?= $isAdmin ? '' : 'Your own classes are approved by the administrator.' ?>
        </p>
    </div>
</div>

<form method="post" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="validate_many">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= icon('hourglass-split') ?>Waiting for your decision</span>
            <?php if ($pending): ?>
                <button type="submit" class="btn btn-sm btn-success" id="bulkValidate" disabled><?= icon('check2-all') ?>Approve the ticked classes</button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-card">
                <thead>
                <tr>
                    <th><input class="form-check-input" type="checkbox" id="checkAll" aria-label="Select all" <?= $pending ? '' : 'disabled' ?>></th>
                    <th>Date</th>
                    <th>Teacher</th>
                    <th>Course</th>
                    <th>Time</th>
                    <th class="num">Duration</th>
                    <th>Content</th>
                    <th class="text-end">Decision</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pending as $s): ?>
                    <tr>
                        <td><input class="form-check-input session-check" type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" aria-label="Select"></td>
                        <td class="text-nowrap"><?= format_date($s['session_date']) ?></td>
                        <td><?= e(full_name($s)) ?></td>
                        <td><span class="code-badge"><?= e($s['code']) ?></span> <?= type_badge($s['type']) ?>
                            <div class="small text-body-secondary"><?= e($s['title']) ?></div></td>
                        <td class="text-nowrap"><?= time_short($s['start_time']) ?>–<?= time_short($s['end_time']) ?></td>
                        <td class="num"><?= hours($s['duration']) ?></td>
                        <td class="small"><?= e($s['content']) ?></td>
                        <td class="text-end text-nowrap">
                            <button type="submit" class="btn btn-sm btn-outline-success" form="single-<?= (int) $s['id'] ?>"><?= icon('check-lg') ?>Approve</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"
                                    data-session-id="<?= (int) $s['id'] ?>"
                                    data-session-label="<?= e($s['code'] . ' ' . $s['type'] . ' of ' . format_date($s['session_date']) . ' — ' . full_name($s)) ?>"><?= icon('x-lg') ?>Reject</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pending): ?>
                    <tr><td colspan="8" class="empty-state"><?= icon('cup-hot') ?>All done! No class is waiting for approval.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php foreach ($pending as $s): ?>
    <form method="post" id="single-<?= (int) $s['id'] ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="validate">
        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
    </form>
<?php endforeach; ?>

<div class="card">
    <div class="card-header"><?= icon('clock-history') ?>Latest decisions</div>
    <div class="table-responsive">
        <table class="table table-card">
            <thead><tr><th>Date</th><th>Teacher</th><th>Course</th><th class="num">Duration</th><th>Status</th><th>Decided by</th></tr></thead>
            <tbody>
            <?php foreach ($history as $s): ?>
                <tr>
                    <td class="text-nowrap"><?= format_date($s['session_date']) ?></td>
                    <td><?= e(full_name($s)) ?></td>
                    <td><span class="code-badge"><?= e($s['code']) ?></span> <?= type_badge($s['type']) ?></td>
                    <td class="num"><?= hours($s['duration']) ?></td>
                    <td><?= session_badge($s['status']) ?><?php if ($s['rejection_reason']): ?><div class="small text-body-secondary"><?= e($s['rejection_reason']) ?></div><?php endif; ?></td>
                    <td class="small"><?= e(trim(($s['reviewer_last_name'] ?? '') . ' ' . ($s['reviewer_first_name'] ?? ''))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$history): ?><tr><td colspan="6" class="empty-state">No decision yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="rejectSessionId">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="rejectModalLabel"><?= icon('x-circle', 'text-danger me-1') ?>Reject this class</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary" id="rejectSessionLabel"></p>
                <label for="reason" class="form-label">Why do you reject it? The teacher will read this.</label>
                <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="255" required placeholder="For example: this class did not take place on that day"></textarea>
                <div class="invalid-feedback">A reason is required: the teacher will see it.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
