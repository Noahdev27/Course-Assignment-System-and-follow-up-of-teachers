<?php
/**
 * Department list and deletion (F4).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

if (is_post()) {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'delete' && $id) {
        $department = db_one('SELECT * FROM departments WHERE id = ?', 'i', [$id]);
        $courses = (int) db_value('SELECT COUNT(*) FROM courses WHERE department_id = ?', 'i', [$id]);
        $members = (int) db_value('SELECT COUNT(*) FROM users WHERE department_id = ?', 'i', [$id]);
        if (!$department) {
            flash('danger', 'Department not found.');
        } elseif ($courses > 0 || $members > 0) {
            flash('warning', 'This department contains ' . $courses . ' course(s) and ' . $members . ' teacher(s): move them before deleting it.');
        } else {
            db_exec('DELETE FROM departments WHERE id = ?', 'i', [$id]);
            log_action('Department deleted', $department['name']);
            flash('success', 'Department ' . $department['name'] . ' has been deleted.');
        }
    }
    redirect_self();
}

$departments = db_all(
    "SELECT d.*, u.last_name AS head_last_name, u.first_name AS head_first_name,
            (SELECT COUNT(*) FROM users x WHERE x.department_id = d.id AND x.role IN ('teacher', 'head') AND x.is_active = 1) AS teacher_count,
            (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.id AND c.is_active = 1) AS course_count
     FROM departments d LEFT JOIN users u ON u.id = d.head_id
     ORDER BY d.name"
);

$page_title = 'Departments';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('building', 'greeting-icon') ?>Departments</h1>
        <p class="lead-text"><?= count($departments) ?> departments</p>
    </div>
    <a href="<?= e(url('admin/department_form.php')) ?>" class="btn btn-primary"><?= icon('plus-lg') ?>New department</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-card">
            <thead>
            <tr>
                <th>Code</th>
                <th>Last name</th>
                <th>Head of department</th>
                <th class="num">Teachers</th>
                <th class="num">Courses</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($departments as $d): ?>
                <tr>
                    <td><span class="code-badge"><?= e($d['code']) ?></span></td>
                    <td class="fw-semibold"><?= e($d['name']) ?></td>
                    <td><?= $d['head_id'] ? e($d['head_last_name'] . ' ' . $d['head_first_name']) : '<span class="text-body-secondary">Not appointed</span>' ?></td>
                    <td class="num"><?= (int) $d['teacher_count'] ?></td>
                    <td class="num"><?= (int) $d['course_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/department_form.php?id=' . $d['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form method="post" class="d-inline" data-confirm="Delete the <?= e($d['name']) ?> department?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$departments): ?>
                <tr><td colspan="6" class="empty-state">No department yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
