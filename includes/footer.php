<?php
/**
 * Shared footer. A page can set $page_scripts = ['assignments.js'] before including it.
 */
$page_scripts = $page_scripts ?? [];
?>
</main>
<footer class="app-footer no-print">
    <div class="container-xl small text-body-secondary py-3 d-flex flex-wrap justify-content-between gap-2">
        <span><?= icon('mortarboard') ?> <?= e(APP_NAME) ?></span>
        <span>Assign courses · Track the classes taught · Pay teachers</span>
    </div>
</footer>

<!-- Confirmation window used instead of the browser's confirm() (see main.js) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="confirm-icon mb-3"><?= icon('question-lg') ?></div>
                <h2 class="h5 mb-2" id="confirmModalTitle">Are you sure?</h2>
                <p class="mb-0 text-body-secondary" id="confirmModalText"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
                <button type="button" class="btn btn-lg btn-outline-secondary" data-bs-dismiss="modal">No, go back</button>
                <button type="button" class="btn btn-lg btn-primary" id="confirmModalOk">Yes, continue</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<script src="<?= e(url('assets/js/validation.js')) ?>"></script>
<?php foreach ($page_scripts as $script): ?>
<script src="<?= e(url('assets/js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
