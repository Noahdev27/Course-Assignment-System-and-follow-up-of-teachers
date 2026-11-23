<?php
/**
 * Reusable table: teacher workload and payments.
 * Expects: $teachers (result of teachers_overview()), $show_money (bool), $table_id (string)
 */
$show_money = $show_money ?? true;
$table_id = $table_id ?? 'teachersTable';
?>
<div class="table-responsive">
    <table class="table table-hover table-card" id="<?= e($table_id) ?>">
        <thead>
        <tr>
            <th>Teacher</th>
            <th>Dept</th>
            <th>Academic rank</th>
            <th class="num">Workload</th>
            <th class="cell-progress">Workload / max</th>
            <th class="num">Hours taught</th>
            <?php if ($show_money): ?>
                <th class="num">Due</th>
                <th class="num">Balance due</th>
                <th>Payment</th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($teachers as $t): ?>
            <tr data-row data-dept="<?= e($t['department_code']) ?>" data-overload="<?= $t['overloaded'] ? '1' : '0' ?>">
                <td>
                    <div class="fw-semibold"><?= e(full_name($t)) ?></div>
                    <?php if ($t['role'] === 'head'): ?><span class="badge text-bg-light border">Head of department</span><?php endif; ?>
                    <?php if (!(int) $t['is_active']): ?><span class="badge text-bg-secondary">Deactivated</span><?php endif; ?>
                </td>
                <td><?= e($t['department_code'] ?? '—') ?></td>
                <td class="small"><?= e($t['academic_rank'] ?? '—') ?></td>
                <td class="num"><?= hours($t['teaching_load']) ?> / <?= hours($t['max_load']) ?></td>
                <td>
                    <?= progress_bar((float) $t['teaching_load'], (float) $t['max_load']) ?>
                    <?php if ($t['overloaded']): ?><span class="small text-danger fw-semibold">Overloaded</span><?php endif; ?>
                </td>
                <td class="num"><?= hours($t['approved_hours']) ?></td>
                <?php if ($show_money): ?>
                    <td class="num"><?= money($t['due']) ?></td>
                    <td class="num"><?= money($t['balance']) ?></td>
                    <td><span class="badge <?= e($t['status_class']) ?>"><?= e($t['status_label']) ?></span></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <tr data-empty <?= $teachers ? 'hidden' : '' ?>>
            <td colspan="<?= $show_money ? 9 : 6 ?>" class="empty-state">No teacher to display.</td>
        </tr>
        </tbody>
    </table>
</div>
