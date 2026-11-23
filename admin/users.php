<?php
/**
 * Account list, activation / deactivation (F3, BR2, BR8),
 * and the account requests made on the sign-up page (accept or refuse).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);

if (is_post()) {
    verify_csrf();
    $targetId = (int) post('id');
    if (post('action') === 'toggle' && $targetId) {
        $target = db_one('SELECT * FROM users WHERE id = ?', 'i', [$targetId]);
        if (!$target) {
            flash('danger', 'Account not found.');
        } elseif ($targetId === (int) $user['id']) {
            flash('warning', 'You cannot deactivate your own account.');
        } else {
            $newState = (int) $target['is_active'] === 1 ? 0 : 1;
            db_exec('UPDATE users SET is_active = ? WHERE id = ?', 'ii', [$newState, $targetId]);
            if ($newState) {
                db_exec("UPDATE activity_log SET action = 'Sign-up approved' WHERE user_id = ? AND action = 'Sign-up request'", 'i', [$targetId]);
            }
            log_action($newState ? 'Account activated' : 'Account deactivated', full_name($target));
            flash('success', 'The account of ' . full_name($target) . ($newState ? ' has been reactivated.' : ' has been deactivated.'));
        }
    }

    // Account requests from register.php
    if (in_array(post('action'), ['approve', 'refuse'], true) && $targetId) {
        $back = post('return') === 'dashboard' ? 'admin/dashboard.php' : 'admin/users.php';
        $request = is_pending_signup($targetId) ? db_one('SELECT * FROM users WHERE id = ?', 'i', [$targetId]) : null;
        if (!$request) {
            flash('warning', 'This request has already been handled.');
        } elseif (post('action') === 'approve') {
            db_exec('UPDATE users SET is_active = 1 WHERE id = ?', 'i', [$targetId]);
            // The request is closed: the account no longer counts as “waiting”
            db_exec("UPDATE activity_log SET action = 'Sign-up approved' WHERE user_id = ? AND action = 'Sign-up request'", 'i', [$targetId]);
            log_action('Account request accepted', full_name($request) . ' · ' . $request['email']);
            flash('success', full_name($request) . ' can now log in. You can give them courses in Assignments.');
        } else {
            // A request has no assignment, session or payment yet: it can be deleted (BR8 is respected)
            db_exec('DELETE FROM users WHERE id = ?', 'i', [$targetId]);
            log_action('Account request refused', full_name($request) . ' · ' . $request['email']);
            flash('info', 'The request of ' . full_name($request) . ' has been refused and deleted.');
        }
        redirect($back);
    }
    redirect_self();
}

$requests = pending_signups();
$pendingIds = array_map('intval', array_column($requests, 'id'));
$users = db_all(
    'SELECT u.*, d.code AS department_code FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     ORDER BY FIELD(u.role, "admin", "accountant", "head", "teacher"), u.last_name, u.first_name'
);
$departments = db_all('SELECT * FROM departments ORDER BY code');

$page_title = 'Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= icon('people', 'greeting-icon') ?>Users</h1>
        <p class="lead-text"><span data-count-for="#usersTable"><?= count($users) ?></span> accounts shown</p>
    </div>
    <a href="<?= e(url('admin/user_form.php')) ?>" class="btn btn-primary"><?= icon('person-plus') ?>New account</a>
</div>

<?php $return_to = 'users'; require __DIR__ . '/../includes/views/signup_requests.php'; ?>

<div class="filters row g-2">
    <div class="col-md-5">
        <input type="search" class="form-control" placeholder="Search a name, an email…" aria-label="Search"
               data-filter-for="#usersTable" data-field="text">
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" data-filter-for="#usersTable" data-field="role" aria-label="Filter by role">
            <option value="">All roles</option>
            <?php foreach (ROLES as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select class="form-select" data-filter-for="#usersTable" data-field="dept" aria-label="Filter by department">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d['code']) ?>"><?= e($d['code']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="onlyInactive" data-filter-for="#usersTable" data-field="inactive">
            <label class="form-check-label" for="onlyInactive">Deactivated</label>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-card" id="usersTable">
            <thead>
            <tr>
                <th>Last name</th>
                <th>E-mail</th>
                <th>Role</th>
                <th>Dept</th>
                <th>Academic rank</th>
                <th class="num">Max workload</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $isRequest = in_array((int) $u['id'], $pendingIds, true); ?>
                <tr data-row data-role="<?= e($u['role']) ?>" data-dept="<?= e($u['department_code']) ?>"
                    data-inactive="<?= (int) $u['is_active'] ? '0' : '1' ?>">
                    <td class="fw-semibold"><?= e(full_name($u)) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e(ROLES[$u['role']] ?? $u['role']) ?></td>
                    <td><?= e($u['department_code'] ?? '—') ?></td>
                    <td class="small"><?= e($u['academic_rank'] ?? '—') ?></td>
                    <td class="num"><?= is_teacher_role($u['role']) ? hours($u['max_load']) : '—' ?></td>
                    <td>
                        <?php if ($isRequest): ?>
                            <span class="badge text-bg-warning"><?= icon('hourglass-split') ?> Waiting for approval</span>
                        <?php elseif ((int) $u['is_active']): ?>
                            <span class="badge text-bg-success"><?= icon('check-circle-fill') ?> Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= icon('slash-circle') ?> Deactivated</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url('admin/user_form.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-primary"><?= icon('pencil') ?>Edit</a>
                        <?php if ((int) $u['id'] !== (int) $user['id'] && !$isRequest): ?>
                            <form method="post" class="d-inline"
                                  data-confirm="<?= (int) $u['is_active'] ? 'Deactivate this account? The person will no longer be able to log in.' : 'Reactivate this account?' ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-sm <?= (int) $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= (int) $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr data-empty hidden><td colspan="8" class="empty-state">No account matches the filters.</td></tr>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
