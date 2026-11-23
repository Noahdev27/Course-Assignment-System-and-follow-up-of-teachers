<?php
/**
 * Printable reports: teaching load sheet per teacher, list by course, list by department (F22, F23).
 * A head of department only sees their own department.
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin', 'head']);
$activeYear = require_active_year();
$isAdmin = $user['role'] === 'admin';
$scopeDepartment = $isAdmin ? null : (int) $user['department_id'];

$years = db_all('SELECT * FROM academic_years ORDER BY start_date DESC');
$yearId = (int) (get('year') ?: $activeYear['id']);
$year = db_one('SELECT * FROM academic_years WHERE id = ?', 'i', [$yearId]) ?? $activeYear;
$yearId = (int) $year['id'];
$type = in_array(get('type'), ['teacher', 'course', 'department'], true) ? get('type') : 'teacher';

$teachers = teachers_overview($yearId, $scopeDepartment);
$teacherId = (int) get('teacher_id') ?: (int) ($teachers[0]['id'] ?? 0);
$teacher = null;
foreach ($teachers as $t) {
    if ((int) $t['id'] === $teacherId) {
        $teacher = $t;
    }
}

$assignmentSql = "SELECT a.*, c.code, c.title, c.level, c.semester, c.department_id, d.code AS department_code, d.name AS department_name,
                         u.last_name, u.first_name, u.academic_rank,
                         (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s WHERE s.assignment_id = a.id AND s.status = 'approved') AS approved_hours
                  FROM assignments a
                  JOIN courses c ON c.id = a.course_id
                  JOIN departments d ON d.id = c.department_id
                  JOIN users u ON u.id = a.teacher_id
                  WHERE a.year_id = ?";

if ($type === 'teacher' && $teacher) {
    $rows = db_all($assignmentSql . " AND a.teacher_id = ? ORDER BY c.semester, c.code, FIELD(a.type, 'CM', 'TD', 'TP')", 'ii', [$yearId, $teacherId]);
} elseif ($type === 'course') {
    $courses = courses_coverage($yearId, $scopeDepartment);
    $sql = $assignmentSql . ($scopeDepartment ? ' AND c.department_id = ?' : '') . " ORDER BY c.code, FIELD(a.type, 'CM', 'TD', 'TP')";
    $byCourse = [];
    foreach (db_all($sql, $scopeDepartment ? 'ii' : 'i', $scopeDepartment ? [$yearId, $scopeDepartment] : [$yearId]) as $a) {
        $byCourse[$a['course_id']][] = $a;
    }
} elseif ($type === 'department') {
    $byDepartment = [];
    foreach ($teachers as $t) {
        $byDepartment[$t['department_name'] ?? 'No department'][] = $t;
    }
    ksort($byDepartment);

    if (get('export') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="teacher-workloads-' . $year['label'] . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Department', 'Teacher', 'Academic rank', 'Workload (h)', 'Max workload (h)', 'Approved hours', 'Due (' . APP_CURRENCY . ')', 'Paid', 'Balance'], ';');
        foreach ($teachers as $t) {
            fputcsv($out, [$t['department_code'], full_name($t), $t['academic_rank'], (float) $t['teaching_load'], (float) $t['max_load'],
                (float) $t['approved_hours'], (int) round($t['due']), (int) round($t['paid']), (int) round($t['balance'])], ';');
        }
        fclose($out);
        exit;
    }
}

$tabs = ['teacher' => 'Teaching load sheet', 'course' => 'By course', 'department' => 'By department'];
$query = fn(array $extra) => '?' . http_build_query(array_merge(['type' => $type, 'year' => $yearId], $extra));

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header no-print">
    <div>
        <h1><?= icon('bar-chart-line', 'greeting-icon') ?>Reports</h1>
        <p class="lead-text">Printable version: “Print” button (or Ctrl+P).</p>
    </div>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <?php if ($teacherId): ?><input type="hidden" name="teacher_id" value="<?= $teacherId ?>"><?php endif; ?>
            <select name="year" class="form-select" aria-label="Year" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                    <option value="<?= (int) $y['id'] ?>"<?= selected($yearId, $y['id']) ?>><?= e($y['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php if ($type === 'department'): ?>
            <a href="<?= e(url('admin/reports.php' . $query(['export' => 'csv']))) ?>" class="btn btn-outline-secondary text-nowrap">Export to CSV</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" data-print>Print</button>
    </div>
</div>

<ul class="nav nav-tabs mb-3 no-print">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $type === $key ? ' active' : '' ?>" href="<?= e(url('admin/reports.php?' . http_build_query(['type' => $key, 'year' => $yearId]))) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="print-sheet">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <div class="text-body-secondary small"><?= e(APP_NAME) ?><?= $isAdmin ? '' : ' · department ' . e($user['department_name']) ?></div>
            <h2 class="h4 mb-0"><?= e($tabs[$type]) ?></h2>
            <div>Academic year <?= e($year['label']) ?></div>
        </div>
        <div class="text-end small text-body-secondary">Printed on <?= date('d/m/Y') ?></div>
    </div>

<?php if ($type === 'teacher'): ?>
    <form method="get" class="mb-4 no-print" style="max-width: 420px">
        <input type="hidden" name="type" value="teacher">
        <input type="hidden" name="year" value="<?= $yearId ?>">
        <label for="teacher_id" class="form-label">Teacher</label>
        <select class="form-select" id="teacher_id" name="teacher_id" onchange="this.form.submit()">
            <?php foreach ($teachers as $t): ?>
                <option value="<?= (int) $t['id'] ?>"<?= selected($teacherId, $t['id']) ?>><?= e($t['last_name'] . ' ' . $t['first_name']) ?> (<?= e($t['department_code']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($teacher): ?>
        <div class="mb-3">
            <div class="fw-semibold fs-5"><?= e(full_name($teacher)) ?></div>
            <div><?= e($teacher['academic_rank']) ?> · <?= e($teacher['department_name']) ?> · maximum workload <?= hours($teacher['max_load']) ?></div>
        </div>
        <table class="table table-sm">
            <thead><tr><th>Course</th><th>Level</th><th>Type</th><th class="num">Assigned hours</th><th class="num">Hours taught</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $a): ?>
                <tr><td><?= e($a['code'] . ' — ' . $a['title']) ?></td><td><?= e($a['level']) ?> · S<?= (int) $a['semester'] ?></td>
                    <td><?= e($a['type']) ?></td><td class="num"><?= hours($a['hours']) ?></td><td class="num"><?= hours($a['approved_hours']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty-state">No assignment this year.</td></tr><?php endif; ?>
            </tbody>
            <tfoot>
            <tr class="fw-bold"><td colspan="3">Total</td><td class="num"><?= hours($teacher['teaching_load']) ?></td><td class="num"><?= hours($teacher['approved_hours']) ?></td></tr>
            </tfoot>
        </table>
        <?php if ($teacher['overloaded']): ?>
            <p class="text-danger fw-semibold">Workload above the maximum of <?= hours($teacher['max_load']) ?>.</p>
        <?php endif; ?>
        <div class="row mt-5">
            <div class="col-6"><div class="signature-line">Head of department</div></div>
            <div class="col-6"><div class="signature-line">The teacher</div></div>
        </div>
    <?php else: ?>
        <p class="empty-state">No teacher.</p>
    <?php endif; ?>

<?php elseif ($type === 'course'): ?>
    <table class="table table-sm">
        <thead><tr><th>Course</th><th>Level</th><th>Type</th><th>Teacher</th><th class="num">Hours</th><th class="num">Taught</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c):
            $list = $byCourse[$c['id']] ?? []; ?>
            <tr class="table-light">
                <td colspan="6" class="fw-semibold"><?= e($c['code'] . ' — ' . $c['title']) ?>
                    <span class="small fw-normal text-body-secondary">· <?= e($c['level']) ?> S<?= (int) $c['semester'] ?>
                        · CM <?= hours($c['cm_hours']) ?>, TD <?= hours($c['td_hours']) ?>, TP <?= hours($c['tp_hours']) ?></span>
                    <?php if (!$c['complete']): ?><span class="badge text-bg-danger ms-1">Incomplete</span><?php endif; ?>
                </td>
            </tr>
            <?php foreach ($list as $a): ?>
                <tr><td></td><td></td><td><?= e($a['type']) ?></td><td><?= e(full_name($a)) ?></td>
                    <td class="num"><?= hours($a['hours']) ?></td><td class="num"><?= hours($a['approved_hours']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td></td><td colspan="5" class="text-body-secondary small">No teacher assigned.</td></tr><?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php else: ?>
    <?php foreach ($byDepartment as $departmentName => $list): ?>
        <h3 class="h6 text-uppercase text-body-secondary mt-3"><?= e($departmentName) ?></h3>
        <table class="table table-sm mb-4">
            <thead><tr><th>Teacher</th><th>Academic rank</th><th class="num">Workload</th><th class="num">Max</th><th class="num">Taught</th><th class="num">Due</th><th class="num">Balance</th></tr></thead>
            <tbody>
            <?php foreach ($list as $t): ?>
                <tr class="<?= $t['overloaded'] ? 'table-danger' : '' ?>">
                    <td><?= e(full_name($t)) ?></td><td class="small"><?= e($t['academic_rank']) ?></td>
                    <td class="num"><?= hours($t['teaching_load']) ?></td><td class="num"><?= hours($t['max_load']) ?></td>
                    <td class="num"><?= hours($t['approved_hours']) ?></td><td class="num"><?= money($t['due']) ?></td><td class="num"><?= money($t['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold">
                <td colspan="2">Total</td>
                <td class="num"><?= hours(array_sum(array_column($list, 'teaching_load'))) ?></td><td></td>
                <td class="num"><?= hours(array_sum(array_column($list, 'approved_hours'))) ?></td>
                <td class="num"><?= money(array_sum(array_column($list, 'due'))) ?></td>
                <td class="num"><?= money(array_sum(array_column($list, 'balance'))) ?></td>
            </tr></tfoot>
        </table>
    <?php endforeach; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
