<?php
/**
 * Course catalogue with its coverage by assignments (F5, F6, BR8).
 * A head of department views the courses of their department, without editing them.
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin', 'head']);
$year = require_active_year();
$isAdmin = $user['role'] === 'admin';

if (is_post()) {
    verify_csrf();
    if (!$isAdmin) {
        forbidden();
    }
    $id = (int) post('id');
    $course = $id ? db_one('SELECT * FROM courses WHERE id = ?', 'i', [$id]) : null;
    $assignments = $course ? (int) db_value('SELECT COUNT(*) FROM assignments WHERE course_id = ?', 'i', [$id]) : 0;

    if (!$course) {
        flash('danger', 'Course not found.');
    } elseif (post('action') === 'toggle') {
        $newState = (int) $course['is_active'] ? 0 : 1;
        db_exec('UPDATE courses SET is_active = ? WHERE id = ?', 'ii', [$newState, $id]);
        log_action($newState ? 'Course reactivated' : 'Course deactivated', $course['code']);
        flash('success', $course['code'] . ($newState ? ' has been reactivated.' : ' has been deactivated.'));
    } elseif (post('action') === 'delete') {
        if ($assignments > 0) {
            // BR8: a course with assignments is never deleted
            flash('warning', $course['code'] . ' has assignments: it cannot be deleted, deactivate it instead.');
        } else {
            db_exec('DELETE FROM preferences WHERE course_id = ?', 'i', [$id]);
            db_exec('DELETE FROM courses WHERE id = ?', 'i', [$id]);
            log_action('Course deleted', $course['code'] . ' ' . $course['title']);
            flash('success', $course['code'] . ' has been deleted.');
        }
    }
    redirect_self();
}

$departmentFilter = $isAdmin ? null : (int) $user['department_id'];
$courses = courses_coverage((int) $year['id'], $departmentFilter, false);
$departments = db_all('SELECT * FROM departments ORDER BY code');
$onlyIncomplete = get('incomplete') === '1';

$page_title = 'Courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('journal-bookmark', 'greeting-icon') ?>Courses<?= $isAdmin ? '' : ' of the department ' . e($user['department_code']) ?></h1>
        <p class="lead-text"><span data-count-for="#coursesTable"><?= count($courses) ?></span> courses shown · coverage for <?= e($year['label']) ?></p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="<?= e(url('admin/course_form.php')) ?>" class="btn btn-primary"><?= icon('plus-lg') ?>New course</a>
    <?php endif; ?>
</div>

<div class="filters row g-2">
    <div class="col-md-4">
        <input type="search" class="form-control" placeholder="Code or title…" aria-label="Search for a course"
               data-filter-for="#coursesTable" data-field="text">
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#coursesTable" data-field="dept" aria-label="Department">
            <option value="">Departments</option>
            <?php foreach ($departments as $d): ?><option value="<?= e($d['code']) ?>"><?= e($d['code']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#coursesTable" data-field="level" aria-label="Level">
            <option value="">Levels</option>
            <?php foreach (LEVELS as $n): ?><option value="<?= e($n) ?>"><?= e($n) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#coursesTable" data-field="semester" aria-label="Semester">
            <option value="">Semesters</option>
            <option value="1">Semester 1</option>
            <option value="2">Semester 2</option>
        </select>
    </div>
    <div class="col-6 col-md-2 d-flex align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="onlyIncomplete" data-filter-for="#coursesTable"
                   data-field="incomplete"<?= checked($onlyIncomplete) ?>>
            <label class="form-check-label" for="onlyIncomplete">Unassigned</label>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-card" id="coursesTable">
            <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Dept</th>
                <th>Level</th>
                <th>Sem.</th>
                <th>CM</th>
                <th>TD</th>
                <th>TP</th>
                <th class="num">Credits</th>
                <?php if ($isAdmin): ?><th class="text-end">Actions</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr data-row data-dept="<?= e($c['department_code']) ?>" data-level="<?= e($c['level']) ?>"
                    data-semester="<?= (int) $c['semester'] ?>" data-incomplete="<?= !$c['complete'] && (int) $c['is_active'] ? '1' : '0' ?>"
                    class="<?= (int) $c['is_active'] ? '' : 'table-secondary' ?>">
                    <td><span class="code-badge"><?= e($c['code']) ?></span></td>
                    <td>
                        <?= e($c['title']) ?>
                        <?php if (!(int) $c['is_active']): ?><span class="badge text-bg-secondary ms-1">Deactivated</span><?php endif; ?>
                    </td>
                    <td><?= e($c['department_code']) ?></td>
                    <td><?= e($c['level']) ?></td>
                    <td><?= (int) $c['semester'] ?></td>
                    <?php foreach (array_keys(TYPES) as $type):
                        $planned = course_type_hours($c, $type);
                        $assigned = (float) $c['assigned_' . strtolower($type)]; ?>
                        <td class="text-nowrap small">
                            <?php if ($planned <= 0): ?>
                                <span class="text-body-tertiary">—</span>
                            <?php else: ?>
                                <span class="<?= $assigned >= $planned ? 'text-success' : 'text-danger fw-semibold' ?>"><?= hours($assigned) ?></span>
                                <span class="text-body-secondary">/ <?= hours($planned) ?></span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="num"><?= (int) $c['credits'] ?></td>
                    <?php if ($isAdmin): ?>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/course_form.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"><?= (int) $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <?php if ((int) $c['assignment_count'] === 0): ?>
                        <form method="post" class="d-inline" data-confirm="Permanently delete <?= e($c['code']) ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <tr data-empty hidden><td colspan="10" class="empty-state">No course matches the filters.</td></tr>
            </tbody>
        </table>
    </div>
</div>
<p class="form-hint mt-2">Hours shown are those already assigned in the active year, out of the planned hours. In red: hours still to assign.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
