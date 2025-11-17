<?php

/** @var array $accounts */ ?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-balance-scale me-2"></i>Reconciliation</span>
        <div class="no-print">
            <a href="<?= route('dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (empty($has_double)): ?>
            <div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>Reconciliation requires the double-entry journal tables. Please run migrations.</div>
        <?php endif; ?>
        <form action="<?= route('reconciliation_review') ?>" method="post" class="row g-3">
            <?= csrf_input() ?>
            <div class="col-md-6">
                <label class="form-label">Account</label>
                <select name="account_id" class="form-select" required>
                    <option value="">-- Select account --</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= isset($a['type']) && $a['type'] ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start date</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">End date</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statement opening balance</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00" required>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statement ending balance</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="ending_balance" class="form-control" required>
                </div>
            </div>
            <div class="col-md-8 d-flex align-items-end">
                <button class="btn btn-primary"><i class="fas fa-eye me-2"></i>Review</button>
            </div>
        </form>
    </div>
</div>