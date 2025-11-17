<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Account Register</h5>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= route('account_register_export_csv') . '&account_id=' . (int)($account_id ?? 0) . ($start ? '&start=' . urlencode($start) : '') . ($end ? '&end=' . urlencode($end) : '') ?>">
                <i class="fas fa-file-csv me-1"></i>Export CSV
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= route('accounts') ?>">Accounts</a>
            <button class="btn btn-sm btn-light" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="get" action="<?= route('account_register') ?>">
            <div class="col-md-4">
                <label class="form-label">Account</label>
                <select class="form-select" name="account_id" required>
                    <option value="">-- Select account --</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= ($account_id ?? 0) == (int)$a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?><?= !empty($a['type']) ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Start</label>
                <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End</label>
                <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100"><i class="fas fa-eye me-1"></i>View</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="width:120px;">Date</th>
                        <th>Description</th>
                        <th class="text-end" style="width:140px;">Debit</th>
                        <th class="text-end" style="width:140px;">Credit</th>
                        <th class="text-end" style="width:160px;">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lines)): foreach ($lines as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['journal_date']) ?></td>
                                <td><?= htmlspecialchars($r['description'] ?? '') ?></td>
                                <td class="text-end text-success">$<?= number_format((float)$r['debit'], 2) ?></td>
                                <td class="text-end text-danger">$<?= number_format((float)$r['credit'], 2) ?></td>
                                <td class="text-end fw-semibold">$<?= number_format((float)$r['running'], 2) ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-muted">No entries found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>