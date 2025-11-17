<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Transactions</h5>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="<?= route('transaction_new') ?>"><i class="fas fa-plus me-1"></i>New</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= route('transactions_export_csv') . (empty($_SERVER['QUERY_STRING']) ? '' : '&' . $_SERVER['QUERY_STRING']) ?>"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= route('dashboard') ?>">Dashboard</a>
            <button class="btn btn-sm btn-light" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="row g-2 align-items-end no-print mb-3" method="get" action="<?= route('transactions') ?>">
            <div class="col-md-3">
                <label class="form-label">Start</label>
                <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End</label>
                <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <?php $t = $filters['type'] ?? ''; ?>
                    <option value="">All</option>
                    <option value="Income" <?= $t === 'Income' ? 'selected' : '' ?>>Income</option>
                    <option value="Expense" <?= $t === 'Expense' ? 'selected' : '' ?>>Expense</option>
                    <option value="Transfer" <?= $t === 'Transfer' ? 'selected' : '' ?>>Transfer</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <?php $cid = (int)($filters['category_id'] ?? 0); ?>
                    <option value="0">All</option>
                    <?php foreach (($categories ?? []) as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $cid === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Description contains...">
                </div>
            </div>
            <div class="col-md-6 d-flex justify-content-end">
                <button class="btn btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
                <a class="btn btn-outline-secondary ms-2" href="<?= route('transactions') ?>">Reset</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): foreach ($transactions as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($t['transaction_date']))) ?></td>
                                <td><?= htmlspecialchars($t['type']) ?></td>
                                <td><?= htmlspecialchars($t['category_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($t['description'] ?? '') ?></td>
                                <td class="text-end <?= ($t['type'] === 'Expense') ? 'text-danger' : 'text-success' ?>">
                                    $<?= number_format($t['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-muted">No transactions.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>