<?php
/**
 * Declaring the sessions taught and tracking their approval (F13, BR11, BR12, BR14).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['teacher', 'head']);
$year = require_active_year();
$userId = (int) $user['id'];
$today = date('Y-m-d');

/** Session of the logged-in teacher, or null. */
function my_session(int $sessionId, int $userId): ?array
{
    return db_one(
        'SELECT s.*, a.teacher_id, a.year_id FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id
         WHERE s.id = ? AND a.teacher_id = ?',
        'ii',
        [$sessionId, $userId]
    );
}

$editId = (int) get('edit');
$editing = $editId ? my_session($editId, $userId) : null;
if ($editId && (!$editing || $editing['status'] !== 'declared')) {
    flash('warning', 'Only sessions still pending can be edited.');
    redirect('teacher/sessions.php');
}

$data = $editing
    ? ['assignment_id' => $editing['assignment_id'], 'session_date' => $editing['session_date'],
        'start_time' => time_short($editing['start_time']), 'end_time' => time_short($editing['end_time']),
        'content' => $editing['content']]
    : ['assignment_id' => (int) get('assignment_id') ?: '', 'session_date' => $today, 'start_time' => '08:00', 'end_time' => '10:00', 'content' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'delete') {
        $session = my_session((int) post('id'), $userId);
        if (!$session || $session['status'] === 'approved' || $session['payment_id']) {
            // BR14: an approved or paid session can no longer be deleted
            flash('warning', 'This session can no longer be deleted.');
        } else {
            db_exec('DELETE FROM class_sessions WHERE id = ?', 'i', [(int) $session['id']]);
            log_action('Session deleted', 'Session of ' . format_date($session['session_date']));
            flash('success', 'The session of ' . format_date($session['session_date']) . ' has been deleted.');
        }
        redirect('teacher/sessions.php');
    }

    $data = [
        'assignment_id' => (int) post('assignment_id'),
        'session_date' => post('session_date'),
        'start_time' => post('start_time'),
        'end_time' => post('end_time'),
        'content' => post('content'),
    ];
    $excludeId = $editing ? (int) $editing['id'] : 0;

    // BR11: only on their own assignments of the active year
    $assignment = db_one(
        'SELECT a.*, c.code FROM assignments a JOIN courses c ON c.id = a.course_id
         WHERE a.id = ? AND a.teacher_id = ? AND a.year_id = ?',
        'iii',
        [$data['assignment_id'], $userId, (int) $year['id']]
    );
    if (!$assignment) $errors[] = 'Choose one of your courses in the active year (BR11).';

    if (!valid_date($data['session_date'])) {
        $errors[] = 'Enter the session date.';
    } elseif ($data['session_date'] > $today) {
        $errors[] = 'A session cannot be declared with a future date (BR11).';
    } elseif ($data['session_date'] < $year['start_date']) {
        $errors[] = 'The date must fall within academic year ' . $year['label'] . '.';
    }

    $duration = 0;
    if (!valid_time($data['start_time']) || !valid_time($data['end_time'])) {
        $errors[] = 'Enter the start time and the end time.';
    } else {
        [$h1, $m1] = array_map('intval', explode(':', $data['start_time']));
        [$h2, $m2] = array_map('intval', explode(':', $data['end_time']));
        $duration = round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 60, 2);
        if ($duration <= 0) {
            $errors[] = 'The end time must be after the start time.';
        } elseif ($duration > 8) {
            $errors[] = 'A session cannot exceed 8 hours.';
        }
    }
    if ($data['content'] === '' || mb_strlen($data['content']) > 255) {
        $errors[] = 'Describe the content covered (255 characters at most).';
    }

    if (!$errors) {
        // BR12: the session must fit within the assigned hours
        $used = assignment_session_hours((int) $assignment['id'], false, $excludeId);
        if ($used + $duration > (float) $assignment['hours']) {
            $errors[] = sprintf(
                'Only %s are left on this assignment (%s assigned, %s already declared or approved) (BR12).',
                hours(max(0, (float) $assignment['hours'] - $used)),
                hours($assignment['hours']),
                hours($used)
            );
        }
        // No two overlapping sessions on the same day
        $overlap = db_value(
            "SELECT COUNT(*) FROM class_sessions s JOIN assignments a ON a.id = s.assignment_id
             WHERE a.teacher_id = ? AND s.session_date = ? AND s.status <> 'rejected' AND s.id <> ?
               AND s.start_time < ? AND s.end_time > ?",
            'isiss',
            [$userId, $data['session_date'], $excludeId, $data['end_time'] . ':00', $data['start_time'] . ':00']
        );
        if ($overlap > 0) {
            $errors[] = 'You already have a session declared in this time slot that day.';
        }
    }

    if (!$errors) {
        $label = $assignment['code'] . ' ' . $assignment['type'] . ' of ' . format_date($data['session_date']) . ' (' . hours($duration) . ')';
        if ($editing) {
            db_exec(
                'UPDATE class_sessions SET assignment_id = ?, session_date = ?, start_time = ?, end_time = ?, duration = ?, content = ? WHERE id = ?',
                'isssdsi',
                [$data['assignment_id'], $data['session_date'], $data['start_time'], $data['end_time'], $duration, $data['content'], $excludeId]
            );
            log_action('Session updated', $label);
            flash('success', 'Session updated: ' . $label . '.');
        } else {
            db_exec(
                'INSERT INTO class_sessions (assignment_id, session_date, start_time, end_time, duration, content) VALUES (?, ?, ?, ?, ?, ?)',
                'isssds',
                [$data['assignment_id'], $data['session_date'], $data['start_time'], $data['end_time'], $duration, $data['content']]
            );
            log_action('Session declared', $label);
            flash('success', 'Well done! Your class ' . $label . ' has been sent. Your head of department will approve it.');
        }
        redirect('teacher/sessions.php');
    }
}

$myAssignments = db_all(
    "SELECT a.*, c.code, c.title,
            (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status <> 'rejected' AND s.id <> ?) AS used_hours
     FROM assignments a JOIN courses c ON c.id = a.course_id
     WHERE a.teacher_id = ? AND a.year_id = ?
     ORDER BY c.code, FIELD(a.type, 'CM', 'TD', 'TP')",
    'iii',
    [$editing ? (int) $editing['id'] : 0, $userId, (int) $year['id']]
);
$sessions = db_all(
    "SELECT s.*, a.type, c.code, c.title FROM class_sessions s
     JOIN assignments a ON a.id = s.assignment_id JOIN courses c ON c.id = a.course_id
     WHERE a.teacher_id = ? AND a.year_id = ?
     ORDER BY s.session_date DESC, s.start_time DESC",
    'ii',
    [$userId, (int) $year['id']]
);

$page_title = 'My classes';
$page_scripts = ['sessions.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('easel2', 'greeting-icon') ?>My classes</h1>
        <p class="lead-text">After each class, fill in the form on the left. Your head of department approves it, then it is paid.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?= icon($editing ? 'pencil-square' : 'plus-circle') ?><?= $editing ? 'Change this class' : 'I taught a class' ?></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (!$myAssignments): ?>
                    <div class="empty-state"><?= icon('journal-x') ?>No course has been given to you yet this year, so there is nothing to declare. Your head of department will add your courses.</div>
                <?php else: ?>
                <form method="post" class="row g-3 needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <div class="col-12">
                        <label for="assignment_id" class="form-label">1. Which course?</label>
                        <select class="form-select" id="assignment_id" name="assignment_id" required>
                            <option value="">Choose the course…</option>
                            <?php foreach ($myAssignments as $a):
                                $remaining = max(0, (float) $a['hours'] - (float) $a['used_hours']); ?>
                                <option value="<?= (int) $a['id'] ?>"<?= selected($data['assignment_id'], $a['id']) ?>
                                        data-hours="<?= e($a['hours']) ?>" data-used="<?= e($a['used_hours']) ?>" data-remaining="<?= e($remaining) ?>">
                                    <?= e($a['code'] . ' ' . $a['type'] . ' — ' . $a['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Choose the course concerned.</div>
                        <div class="info-box mt-2" id="remainingInfo" hidden></div>
                    </div>
                    <div class="col-12">
                        <label for="session_date" class="form-label">2. Which day?</label>
                        <input type="date" class="form-control" id="session_date" name="session_date" value="<?= e($data['session_date']) ?>"
                               min="<?= e($year['start_date']) ?>" max="<?= e($today) ?>" data-max-today required>
                        <div class="invalid-feedback">The date must be today or in the past, within the academic year.</div>
                    </div>
                    <div class="col-6">
                        <label for="start_time" class="form-label">3. Started at</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" value="<?= e($data['start_time']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label for="end_time" class="form-label">Ended at</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" value="<?= e($data['end_time']) ?>" required data-after="#start_time">
                        <div class="invalid-feedback">The end must be after the start.</div>
                    </div>
                    <div class="col-12"><div class="info-box"><?= icon('stopwatch', 'me-1') ?>Length of the class: <strong id="computedDuration">—</strong></div></div>
                    <div class="col-12">
                        <label for="content" class="form-label">4. What did you teach?</label>
                        <textarea class="form-control" id="content" name="content" rows="3" maxlength="255" required placeholder="For example: Chapter 2, loops and conditions"><?= e($data['content']) ?></textarea>
                        <div class="invalid-feedback">Describe the content of the session.</div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <?php if ($editing): ?><a href="<?= e(url('teacher/sessions.php')) ?>" class="btn btn-outline-secondary"><?= icon('x-lg') ?>Cancel</a><?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-lg"><?= icon($editing ? 'check-lg' : 'send') ?><?= $editing ? 'Save the changes' : 'Send to my head of department' ?></button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card" id="mySessions">
            <div class="card-header"><?= icon('clock-history') ?>My classes in <?= e($year['label']) ?></div>
            <ul class="list-group list-group-flush class-list">
                <?php foreach ($sessions as $s): ?>
                    <li class="list-group-item class-item">
                        <div class="class-date">
                            <strong><?= e(date('d', strtotime($s['session_date']))) ?></strong>
                            <span><?= e(date('M Y', strtotime($s['session_date']))) ?></span>
                        </div>
                        <div class="class-body">
                            <div><span class="code-badge"><?= e($s['code']) ?></span> <?= type_badge($s['type']) ?></div>
                            <div class="mt-1"><?= e($s['content']) ?></div>
                            <div class="small text-body-secondary"><?= icon('clock') ?> <?= time_short($s['start_time']) ?>–<?= time_short($s['end_time']) ?> · <?= hours($s['duration']) ?></div>
                            <div class="mt-2">
                                <?= session_badge($s['status']) ?>
                                <?php if ($s['payment_id']): ?><span class="badge text-bg-primary"><?= icon('cash-coin') ?> Paid</span><?php endif; ?>
                            </div>
                            <?php if ($s['status'] === 'rejected' && $s['rejection_reason']): ?>
                                <div class="small text-danger mt-1">Reason: <?= e($s['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="class-actions">
                            <?php if ($s['status'] === 'declared'): ?>
                                <a href="<?= e(url('teacher/sessions.php?edit=' . $s['id'])) ?>" class="btn btn-sm btn-outline-primary"><?= icon('pencil') ?>Change</a>
                            <?php endif; ?>
                            <?php if ($s['status'] !== 'approved' && !$s['payment_id']): ?>
                                <form method="post" data-confirm="Delete the class of <?= e(format_date($s['session_date'])) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger w-100"><?= icon('trash') ?>Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
                <?php if (!$sessions): ?>
                    <li class="list-group-item empty-state"><?= icon('calendar2-plus') ?>You have not declared any class yet. Use the form to declare your first one.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
