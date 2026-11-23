<?php
/**
 * Account requests waiting for the administrator (made on register.php).
 * Expects: $requests (result of pending_signups()), $return_to ('dashboard' or 'users')
 */
$return_to = $return_to ?? 'users';
if (!$requests) {
    return;
}
?>
<div class="card request-card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><?= icon('person-plus-fill') ?><?= count($requests) === 1 ? '1 person is' : count($requests) . ' people are' ?> asking for an account</span>
        <span class="small fw-normal text-body-secondary">Check the name and department, then accept or refuse.</span>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($requests as $r): ?>
            <li class="list-group-item bg-transparent d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
                <div>
                    <div class="fw-bold"><?= e(full_name($r)) ?></div>
                    <div class="small text-body-secondary">
                        <?= icon('envelope') ?> <?= e($r['email']) ?>
                        <?php if ($r['phone']): ?> · <?= icon('telephone') ?> <?= e($r['phone']) ?><?php endif; ?>
                    </div>
                    <div class="small">
                        <?= icon('building') ?> <?= e($r['department_name'] ?? '—') ?> · <?= e($r['academic_rank'] ?? '—') ?>
                        · asked on <?= e(format_date($r['created_at'])) ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form method="post" action="<?= e(url('admin/users.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="return" value="<?= e($return_to) ?>">
                        <button class="btn btn-success"><?= icon('check-lg') ?>Accept</button>
                    </form>
                    <form method="post" action="<?= e(url('admin/users.php')) ?>"
                          data-confirm="Refuse the request of <?= e(full_name($r)) ?>? The request will be deleted.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="refuse">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <input type="hidden" name="return" value="<?= e($return_to) ?>">
                        <button class="btn btn-outline-danger"><?= icon('x-lg') ?>Refuse</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
