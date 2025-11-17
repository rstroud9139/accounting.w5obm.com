<?php

/** @var array $batches */
/** @var int $year */
/** @var int $month */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-layer-group me-2"></i>Batch Reports</span>
        <div class="no-print">
            <a href="<?= route('dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= route('batch_reports_run') ?>" class="row g-3">
            <?= csrf_input() ?>
            <div class="col-md-4">
                <label for="batch" class="form-label">Batch</label>
                <select id="batch" name="batch" class="form-select">
                    <?php foreach ($batches as $key => $cfg): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($cfg['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="year" class="form-label">Year</label>
                <input type="number" class="form-control" id="year" name="year" value="<?= (int)$year ?>" min="2000" max="2100">
            </div>
            <div class="col-md-4">
                <label for="month" class="form-label">Month (for Monthly)</label>
                <input type="number" class="form-control" id="month" name="month" value="<?= (int)$month ?>" min="1" max="12">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="fas fa-play me-2"></i>Run Batch</button>
            </div>
        </form>

        <hr>
        <p class="text-muted small mb-1">Sets are defined in <code>/accounting/reports/batches.php</code>. Add or adjust report URLs as needed.</p>
        <p class="text-muted small">Placeholders supported: <code>{year}</code>, <code>{month}</code>, <code>{start}</code>, <code>{end}</code>.</p>
    </div>
</div>