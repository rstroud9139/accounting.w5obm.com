<?php

/** @var array $categories */
/** @var array $accounts */
/** @var string $csrf */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-seedling me-2"></i>Ledger Data Builder</span>
        <div class="no-print">
            <a href="<?= route('dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <p class="text-muted mb-3">Normalize external exports (bank CSVs, QuickBooks IIF, GnuCash books, and more) into transactions plus suggested chart-of-accounts and category entries. The preview step lets you verify every row before anything posts to the ledger.</p>
        <div class="alert alert-secondary"><i class="fas fa-layer-group me-2"></i>The importer will stage transactions, infer new accounts/categories, detect duplicates, and only commit once you approve the preview. If you are redirected to login during preview, your session likely expired—sign in again and re-upload.</div>

        <form action="<?= route('import_upload') ?>" method="post" enctype="multipart/form-data" class="row g-3">
            <?= csrf_input() ?>
            <div class="col-12">
                <label for="import_file" class="form-label">File</label>
                <input type="file" class="form-control" id="import_file" name="import_file" required>
                <div class="form-text">Supported: CSV, QIF, OFX/QBO/QFX, QuickBooks IIF, and GnuCash saved books (.gnucash). Type can be auto-detected.</div>
            </div>
            <div class="col-md-4">
                <label for="import_type" class="form-label">Type</label>
                <select id="import_type" name="import_type" class="form-select">
                    <option value="auto" selected>Auto-detect</option>
                    <option value="csv">CSV</option>
                    <option value="qif">QIF</option>
                    <option value="ofx">OFX/QBO/QFX</option>
                    <option value="iif">QuickBooks IIF</option>
                    <option value="gnucash">GnuCash Saved Book</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="default_income_cat" class="form-label">Default Income Category</label>
                <select id="default_income_cat" name="default_income_cat" class="form-select">
                    <option value="0">-- None --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="default_expense_cat" class="form-label">Default Expense Category</label>
                <select id="default_expense_cat" name="default_expense_cat" class="form-select">
                    <option value="0">-- None --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <label class="form-label">Default Cash/Bank Account</label>
                <select class="form-select" disabled>
                    <option>Choose on preview screen</option>
                </select>
                <div class="form-text">You’ll pick accounts on the next step.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Default Offset Account</label>
                <select class="form-select" disabled>
                    <option>Choose on preview screen</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i>Stage &amp; Preview</button>
            </div>
        </form>

        <hr>
        <p class="text-muted small mb-0">Tip: Defaults here are only used when the source data leaves a category blank—the preview still lets you refine every record.</p>
    </div>
</div>