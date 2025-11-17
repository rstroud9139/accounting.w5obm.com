<?php

/** @var array $items */ ?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-clipboard-check me-2"></i>Reconciliation Review</span>
        <div class="no-print">
            <a href="<?= route('reconciliation') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><strong>Account ID:</strong> <?= (int)$account_id ?></div>
            <div class="col-md-3"><strong>Period:</strong> <?= htmlspecialchars($start) ?> to <?= htmlspecialchars($end) ?></div>
            <div class="col-md-3"><strong>Opening balance:</strong> $<?= number_format((float)($opening_balance ?? 0), 2) ?></div>
            <div class="col-md-3"><strong>Ending balance:</strong> $<?= number_format((float)$ending_balance, 2) ?></div>
        </div>
        <form action="<?= route('reconciliation_commit') ?>" method="post" id="recForm">
            <?= csrf_input() ?>
            <input type="hidden" name="account_id" value="<?= (int)$account_id ?>">
            <input type="hidden" name="start" value="<?= htmlspecialchars($start) ?>">
            <input type="hidden" name="end" value="<?= htmlspecialchars($end) ?>">
            <input type="hidden" name="opening_balance" value="<?= htmlspecialchars((string)($opening_balance ?? 0)) ?>">
            <input type="hidden" name="ending_balance" value="<?= htmlspecialchars((string)$ending_balance) ?>">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="toggleAll"></th>
                            <th style="width:120px;">Date</th>
                            <th>Description</th>
                            <th class="text-end" style="width:160px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="4" class="text-muted">No transactions found for this period.</td>
                            </tr>
                            <?php else: foreach ($items as $r): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input" name="line[<?= (int)$r['id'] ?>]" value="1"></td>
                                    <td><?= htmlspecialchars($r['date']) ?></td>
                                    <td><?= htmlspecialchars($r['description']) ?></td>
                                    <td class="text-end" data-amt="<?= htmlspecialchars((string)$r['amount']) ?>">$<?= number_format((float)$r['amount'], 2) ?></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between mt-3">
                <div>
                    <span class="badge bg-secondary me-2">Selected: <span id="selCount">0</span></span>
                    <span class="badge bg-info text-dark me-2">Selected total: $<span id="selTotal">0.00</span></span>
                    <span class="badge bg-light text-dark">Opening + Selected: $<span id="openPlus">0.00</span></span>
                </div>
                <div>
                    <span class="badge bg-warning text-dark me-2">Difference vs ending: $<span id="diff">0.00</span></span>
                    <button class="btn btn-success"><i class="fas fa-save me-2"></i>Save Reconciliation</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    (function() {
        function recalc() {
            var total = 0,
                count = 0;
            document.querySelectorAll('tbody input[type="checkbox"]').forEach(function(cb) {
                if (cb.checked) {
                    count++;
                    var tr = cb.closest('tr');
                    var td = tr.querySelector('td[data-amt]');
                    var amt = parseFloat(td.getAttribute('data-amt') || '0');
                    if (!isNaN(amt)) total += amt;
                }
            });
            document.getElementById('selCount').textContent = String(count);
            document.getElementById('selTotal').textContent = total.toFixed(2);
            var opening = parseFloat('<?= json_encode((float)($opening_balance ?? 0)) ?>');
            var ending = parseFloat('<?= json_encode((float)$ending_balance) ?>');
            var openPlus = opening + total;
            document.getElementById('openPlus').textContent = openPlus.toFixed(2);
            var diff = ending - openPlus;
            document.getElementById('diff').textContent = diff.toFixed(2);
        }
        document.getElementById('toggleAll')?.addEventListener('change', function() {
            var on = this.checked;
            document.querySelectorAll('tbody input[type="checkbox"]').forEach(function(cb) {
                cb.checked = on;
            });
            recalc();
        });
        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'checkbox') {
                recalc();
            }
        });
        recalc();
    })();
</script>