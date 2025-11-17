<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Last Import Summary</h5>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= route('import') ?>"><i class="fas fa-file-import me-1"></i>Import</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= route('transactions') ?>"><i class="fas fa-list me-1"></i>Transactions</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$last): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No previous imports found.</div>
        <?php else: ?>
            <dl class="row">
                <dt class="col-sm-3">Filename</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($last['filename'] ?? '') ?></dd>

                <dt class="col-sm-3">Imported Rows</dt>
                <dd class="col-sm-9"><?= (int)($last['rows_imported'] ?? 0) ?></dd>

                <dt class="col-sm-3">Duplicates Skipped</dt>
                <dd class="col-sm-9"><?= (int)($last['duplicates_skipped'] ?? 0) ?></dd>

                <dt class="col-sm-3">Imported At</dt>
                <dd class="col-sm-9"><?= htmlspecialchars($last['created_at'] ?? '') ?></dd>
            </dl>
            <div class="mt-3">
                <a class="btn btn-primary" href="<?= route('import') ?>"><i class="fas fa-upload me-1"></i>Import Another File</a>
                <a class="btn btn-outline-secondary ms-2" href="<?= route('transactions') ?>"><i class="fas fa-list me-1"></i>View Transactions</a>
            </div>
        <?php endif; ?>
    </div>
</div>