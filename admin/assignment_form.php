<?php
/**
 * Create and edit an assignment (F8, F9, F10).
 * Open to the administrator and the head of department (limited to their department, BR7).
 * Server-side checks: BR3 to BR7, BR9, BR12 (see validate_assignment()).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin', 'head']);
$year = require_active_year();
$isAdmin = $user['role'] === 'admin';
$listPath = $isAdmin ? 'admin/assignments.php' : 'head/assignments.php';
$scopeDepartment = $isAdmin ? null : (int) $user['department_id'];

$id = (int) get('id');
$existing = null;
if ($id) {
    $existing = db_one(
        'SELECT a.*, c.department_id FROM assignments a JOIN courses c ON c.id = a.course_id WHERE a.id = ?',
        'i',
        [$id]
    );
    if (!$existing || ($scopeDepartment && (int) $existing['department_id'] !== $scopeDepartment)) {
        flash('danger', 'Assignment not found.');
        redirect($listPath);
    }
    if ((int) $existing['year_id'] !== (int) $year['id']) {
        flash('warning', 'Assignments from past years are read-only (BR9).');
        redirect($listPath);
    }
}
// An assignment that already has sessions keeps its course, teacher and type
$locked = $existing && (int) db_value('SELECT COUNT(*) FROM class_sessions WHERE assignment_id = ?', 'i', [$id]) > 0;

$data = $existing ?? [
    'course_id' => (int) get('course_id') ?: '', 'teacher_id' => '', 'type' => get('type') ?: 'CM',
    'hours' => '', 'comment' => '',
];
$data['force'] = false;
$data['justification'] = '';
$errors = [];

if (is_post()) {
    verify_csrf();
    $data = [
        'course_id' => (int) post('course_id'),
        'teacher_id' => (int) post('teacher_id'),
        'type' => post('type'),
        'hours' => str_replace(',', '.', post('hours')),
        'comment' => post('comment'),
        'force' => post('force') === '1',
        'justification' => post('justification'),
    ];
    if ($locked) {
        $data['course_id'] = (int) $existing['course_id'];
        $data['teacher_id'] = (int) $existing['teacher_id'];
        $data['type'] = $existing['type'];
    }
    if (mb_strlen($data['comment']) > 200) {
        $errors[] = 'The comment is limited to 200 characters.';
    }
    $errors = array_merge($errors, validate_assignment($data, $existing, $user, $year));

    if (!$errors) {
        $comment = $data['comment'];
        $load = teacher_load($data['teacher_id'], (int) $year['id'], $id) + (float) $data['hours'];
        $teacher = db_one('SELECT * FROM users WHERE id = ?', 'i', [$data['teacher_id']]);
        $isOverride = $isAdmin && $data['force'] && (float) $teacher['max_load'] > 0 && $load > (float) $teacher['max_load'];
        if ($isOverride) {
            $comment = trim('[Overload approved: ' . $data['justification'] . '] ' . $comment);
            $comment = mb_substr($comment, 0, 255);
        }
        $course = db_one('SELECT code FROM courses WHERE id = ?', 'i', [$data['course_id']]);
        $label = $course['code'] . ' ' . $data['type'] . ' (' . hours($data['hours']) . ') — ' . full_name($teacher);

        if ($existing) {
            db_exec(
                'UPDATE assignments SET course_id = ?, teacher_id = ?, type = ?, hours = ?, comment = ? WHERE id = ?',
                'iisdsi',
                [$data['course_id'], $data['teacher_id'], $data['type'], (float) $data['hours'], $comment ?: null, $id]
            );
            log_action('Assignment updated', $label);
        } else {
            db_exec(
                'INSERT INTO assignments (course_id, teacher_id, year_id, type, hours, comment, assigned_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'iiisdsi',
                [$data['course_id'], $data['teacher_id'], (int) $year['id'], $data['type'], (float) $data['hours'], $comment ?: null, (int) $user['id']]
            );
            log_action('Assignment', $label);
        }
        if ($isOverride) {
            log_action('Workload override', $label . ' — ' . $data['justification']);
        }
        flash('success', 'Assignment saved: ' . $label . '.');
        redirect($listPath);
    }
}

// Choice lists, with data used by JavaScript (planned and already assigned hours)
$courseSql = 'SELECT c.*, d.code AS department_code FROM courses c JOIN departments d ON d.id = c.department_id WHERE c.is_active = 1';
$teacherSql = "SELECT u.id, u.last_name, u.first_name, u.academic_rank, u.max_load, d.code AS department_code
               FROM users u LEFT JOIN departments d ON d.id = u.department_id
               WHERE u.is_active = 1 AND u.role IN ('teacher', 'head')";
if ($scopeDepartment) {
    $courses = db_all($courseSql . ' AND c.department_id = ? ORDER BY c.code', 'i', [$scopeDepartment]);
    $teachers = db_all($teacherSql . ' AND u.department_id = ? ORDER BY u.last_name, u.first_name', 'i', [$scopeDepartment]);
} else {
    $courses = db_all($courseSql . ' ORDER BY c.code');
    $teachers = db_all($teacherSql . ' ORDER BY u.last_name, u.first_name');
}

$page_title = $existing ? 'Edit an assignment' : 'New assignment';
$nav_active = $listPath;
$page_scripts = ['assignments.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('person-plus-fill', 'greeting-icon') ?><?= e($page_title) ?></h1>
        <p class="lead-text">Year <?= e($year['label']) ?><?= $isAdmin ? '' : ' · department ' . e($user['department_code']) ?></p>
    </div>
    <a href="<?= e(url($listPath)) ?>" class="btn btn-outline-secondary">Back to the list</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($locked): ?>
    <div class="alert alert-info">Sessions are already declared on this assignment: only the number of hours and the comment can change.</div>
<?php endif; ?>

<form method="post" class="card needs-validation" novalidate id="assignmentForm" style="max-width: 900px"
      data-api-load="<?= e(url('api/check_load.php')) ?>" data-api-search="<?= e(url('api/search_courses.php')) ?>"
      data-exclude="<?= (int) $id ?>">
    <div class="card-body row g-3">
        <?= csrf_field() ?>
        <div class="col-12">
            <label for="course_id" class="form-label">Courses</label>
            <?php if (!$locked): ?>
                <input type="search" class="form-control form-control-sm mb-2" id="courseSearch"
                       placeholder="Filter the list: code or title…" aria-label="Search for a course">
            <?php endif; ?>
            <select class="form-select" id="course_id" name="course_id" required <?= $locked ? 'disabled' : '' ?>>
                <option value="">Choose a course…</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($data['course_id'], $c['id']) ?>
                        <?php foreach (array_keys(TYPES) as $type):
                            $key = strtolower($type); ?>
                            data-<?= $key ?>="<?= (int) $c[$key . '_hours'] ?>"
                            data-assigned-<?= $key ?>="<?= e(course_assigned_hours((int) $c['id'], (int) $year['id'], $type, $id)) ?>"
                        <?php endforeach; ?>>
                        <?= e($c['code'] . ' — ' . $c['title']) ?> (<?= e($c['level']) ?>, S<?= (int) $c['semester'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($locked): ?><input type="hidden" name="course_id" value="<?= (int) $data['course_id'] ?>"><?php endif; ?>
            <div class="invalid-feedback">Choose a course.</div>
        </div>
        <div class="col-md-4">
            <label for="type" class="form-label">Type</label>
            <select class="form-select" id="type" name="type" required <?= $locked ? 'disabled' : '' ?>>
                <?php foreach (TYPES as $type => $label): ?>
                    <option value="<?= e($type) ?>"<?= selected($data['type'], $type) ?>><?= e($type . ' — ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($locked): ?><input type="hidden" name="type" value="<?= e($data['type']) ?>"><?php endif; ?>
        </div>
        <div class="col-md-8">
            <div class="info-box h-100 d-flex align-items-center" id="courseHoursInfo">Choose a course to see the available hours.</div>
        </div>
        <div class="col-md-8">
            <label for="teacher_id" class="form-label">Teacher</label>
            <select class="form-select" id="teacher_id" name="teacher_id" required <?= $locked ? 'disabled' : '' ?>>
                <option value="">Choose a teacher…</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"<?= selected($data['teacher_id'], $t['id']) ?>>
                        <?= e($t['last_name'] . ' ' . $t['first_name']) ?> — <?= e($t['academic_rank']) ?> (<?= e($t['department_code']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($locked): ?><input type="hidden" name="teacher_id" value="<?= (int) $data['teacher_id'] ?>"><?php endif; ?>
            <div class="invalid-feedback">Choose a teacher.</div>
        </div>
        <div class="col-md-4">
            <label for="hours" class="form-label">Number of hours</label>
            <input type="number" class="form-control" id="hours" name="hours" min="0.5" step="0.5" max="500"
                   value="<?= e($data['hours'] !== '' ? (float) $data['hours'] : '') ?>" required>
            <div class="invalid-feedback">Enter a number of hours available for this type.</div>
        </div>
        <div class="col-12">
            <div class="info-box" id="loadInfo" hidden></div>
        </div>
        <div class="col-12">
            <label for="comment" class="form-label">Comment <span class="text-body-secondary">(optional)</span></label>
            <input type="text" class="form-control" id="comment" name="comment" maxlength="200"
                   value="<?= e(preg_replace('/^\[Overload approved: [^\]]*\]\s*/u', '', (string) $data['comment'])) ?>">
        </div>
        <?php if ($isAdmin): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="force" name="force"<?= checked((bool) $data['force']) ?>>
                    <label class="form-check-label" for="force">Override if the teacher's maximum workload is exceeded</label>
                </div>
                <div class="mt-2" data-show-if="#force:1">
                    <label for="justification" class="form-label">Reason for the override</label>
                    <input type="text" class="form-control" id="justification" name="justification" maxlength="150"
                           value="<?= e($data['justification']) ?>" data-required-when-visible>
                    <div class="invalid-feedback">A reason is required to override.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer bg-white d-flex justify-content-end gap-2">
        <a href="<?= e(url($listPath)) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check-lg') ?>Save the assignment</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
