<?php

/** @var array $rec */ ?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-file-invoice-dollar me-2"></i>Reconciliation Report</span>
        <div class="no-print d-flex gap-2">
            <a href="<?= route('reconciliation') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <a href="<?= route('reconciliation_export_csv') . '&rid=' . (int)($rec['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
            <button class="btn btn-sm btn-light" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><strong>Account:</strong> <?= htmlspecialchars($rec['account_name'] ?? ('#' . $rec['account_id'])) ?></div>
            <div class="col-md-4"><strong>Period:</strong> <?= htmlspecialchars($rec['start_date']) ?> to <?= htmlspecialchars($rec['end_date']) ?></div>
            <div class="col-md-2"><strong>Opening:</strong> $<?= number_format((float)($rec['opening_balance'] ?? 0), 2) ?></div>
            <div class="col-md-2"><strong>Ending:</strong> $<?= number_format((float)$rec['ending_balance'], 2) ?></div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th style="width:120px;">Date</th>
                        <th>Description</th>
                        <th class="text-end" style="width:160px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sum = 0.0;
                    if (!empty($lines)): foreach ($lines as $r): $sum += (float)$r['amount']; ?>
                            <tr>
                                <td><?= htmlspecialchars($r['journal_date']) ?></td>
                                <td><?= htmlspecialchars($r['description']) ?></td>
                                <td class="text-end">$<?= number_format((float)$r['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="3" class="text-muted">No cleared items recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-end">Cleared total</th>
                        <th class="text-end">$<?= number_format((float)$sum, 2) ?></th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-end">Opening + Cleared</th>
                        <th class="text-end">$<?= number_format((float)($sum + (float)($rec['opening_balance'] ?? 0)), 2) ?></th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-end">Difference vs Ending</th>
                        <th class="text-end">$<?= number_format((float)$rec['ending_balance'] - ($sum + (float)($rec['opening_balance'] ?? 0)), 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>