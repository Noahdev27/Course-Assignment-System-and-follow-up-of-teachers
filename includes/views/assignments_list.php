<?php
/**
 * Assignment list, shared by admin/assignments.php and head/assignments.php (F8, F10).
 * Expects: $user (administrator or head). A head only sees their own department (BR7).
 */
$isAdmin = $user['role'] === 'admin';
$activeYear = require_active_year();
$scopeDepartment = $isAdmin ? null : (int) $user['department_id'];

if (is_post()) {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'delete' && $id) {
        $assignment = db_one(
            'SELECT a.*, c.code, c.department_id, u.last_name, u.first_name FROM assignments a
             JOIN courses c ON c.id = a.course_id JOIN users u ON u.id = a.teacher_id WHERE a.id = ?',
            'i',
            [$id]
        );
        $sessions = $assignment ? (int) db_value('SELECT COUNT(*) FROM class_sessions WHERE assignment_id = ?', 'i', [$id]) : 0;
        if (!$assignment || ($scopeDepartment && (int) $assignment['department_id'] !== $scopeDepartment)) {
            flash('danger', 'Assignment not found.');
        } elseif ((int) $assignment['year_id'] !== (int) $activeYear['id']) {
            flash('warning', 'Assignments from past years are read-only (BR9).');
        } elseif ($sessions > 0) {
            flash('warning', 'Sessions are already declared on this assignment: it can no longer be cancelled.');
        } else {
            db_exec('DELETE FROM assignments WHERE id = ?', 'i', [$id]);
            log_action('Assignment cancelled', $assignment['code'] . ' ' . $assignment['type'] . ' — ' . full_name($assignment));
            flash('success', 'The assignment of ' . $assignment['code'] . ' (' . $assignment['type'] . ') has been cancelled.');
        }
    }
    redirect_self();
}

$years = db_all('SELECT * FROM academic_years ORDER BY start_date DESC');
$yearId = (int) (get('year') ?: $activeYear['id']);
$viewedYear = db_one('SELECT * FROM academic_years WHERE id = ?', 'i', [$yearId]) ?? $activeYear;
$isEditable = (int) $viewedYear['id'] === (int) $activeYear['id'];

$sql = "SELECT a.*, c.code, c.title, c.department_id, d.code AS department_code,
               u.last_name, u.first_name, u.academic_rank,
               (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status = 'approved') AS approved_hours,
               (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status = 'declared') AS pending_hours,
               (SELECT COUNT(*) FROM class_sessions s WHERE s.assignment_id = a.id) AS session_count
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN departments d ON d.id = c.department_id
        JOIN users u ON u.id = a.teacher_id
        WHERE a.year_id = ?";
$types = 'i';
$params = [(int) $viewedYear['id']];
if ($scopeDepartment) {
    $sql .= ' AND c.department_id = ?';
    $types .= 'i';
    $params[] = $scopeDepartment;
}
$sql .= " ORDER BY c.code, FIELD(a.type, 'CM', 'TD', 'TP'), u.last_name";
$assignments = db_all($sql, $types, $params);
$departments = db_all('SELECT * FROM departments ORDER BY code');

$page_title = 'Assignments';
require __DIR__ . '/../header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('list-check', 'greeting-icon') ?>Assignments<?= $isAdmin ? '' : ' · department ' . e($user['department_code']) ?></h1>
        <p class="lead-text"><span data-count-for="#assignmentsTable"><?= count($assignments) ?></span> assignments for <?= e($viewedYear['label']) ?>
            <?= $isEditable ? '' : ' · read-only' ?></p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <select name="year" class="form-select" aria-label="Academic year" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int) $y['id'] ?>"<?= selected($viewedYear['id'], $y['id']) ?>><?= e($y['label']) ?><?= (int) $y['is_active'] ? ' (active)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($isEditable): ?>
            <a href="<?= e(url('admin/assignment_form.php')) ?>" class="btn btn-primary text-nowrap">New assignment</a>
        <?php endif; ?>
    </div>
</div>

<div class="filters row g-2">
    <div class="col-md-5">
        <input type="search" class="form-control" placeholder="Course or teacher…" aria-label="Search"
               data-filter-for="#assignmentsTable" data-field="text">
    </div>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#assignmentsTable" data-field="type" aria-label="Type">
            <option value="">All types</option>
            <?php foreach (array_keys(TYPES) as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#assignmentsTable" data-field="dept" aria-label="Department">
            <option value="">Departments</option>
            <?php foreach ($departments as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['code']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-card" id="assignmentsTable">
            <thead>
            <tr>
                <th>Course</th>
                <th>Type</th>
                <th>Teacher</th>
                <th class="num">Hours</th>
                <th class="cell-progress">Hours taught (approved)</th>
                <th>Comment</th>
                <?php if ($isEditable): ?><th class="text-end">Actions</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <tr data-row data-type="<?= e($a['type']) ?>" data-dept="<?= e($a['department_code']) ?>">
                    <td><span class="code-badge"><?= e($a['code']) ?></span> <?= e($a['title']) ?></td>
                    <td><?= type_badge($a['type']) ?></td>
                    <td><?= e(full_name($a)) ?><div class="small text-body-secondary"><?= e($a['academic_rank']) ?></div></td>
                    <td class="num"><?= hours($a['hours']) ?></td>
                    <td>
                        <?= progress_bar((float) $a['approved_hours'], (float) $a['hours'], true) ?>
                        <span class="small text-body-secondary"><?= hours($a['approved_hours']) ?> / <?= hours($a['hours']) ?>
                            <?= (float) $a['pending_hours'] > 0 ? ' · ' . hours($a['pending_hours']) . ' pending' : '' ?></span>
                    </td>
                    <td class="small"><?= e($a['comment']) ?></td>
                    <?php if ($isEditable): ?>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/assignment_form.php?id=' . $a['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <?php if ((int) $a['session_count'] === 0): ?>
                            <form method="post" class="d-inline" data-confirm="Cancel the assignment of <?= e($a['code']) ?> (<?= e($a['type']) ?>) to <?= e(full_name($a)) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <tr data-empty <?= $assignments ? 'hidden' : '' ?>><td colspan="7" class="empty-state">No assignment for this year.</td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../footer.php'; ?>
