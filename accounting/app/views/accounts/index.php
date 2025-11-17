<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Chart of Accounts</h5>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= route('dashboard') ?>">Dashboard</a>
            <button class="btn btn-sm btn-light" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3 no-print">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="accSearch" class="form-control" placeholder="Search accounts by name or code...">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="accountsTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($accounts)): foreach ($accounts as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['code'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['name'] ?? '') ?></td>
                                <td>
                                    <?php $t = strtolower((string)($a['type'] ?? ''));
                                    $cls = 'badge badge-type ';
                                    if (strpos($t, 'income') !== false || $t === 'revenue') $cls .= 'income';
                                    elseif (strpos($t, 'expense') !== false) $cls .= 'expense';
                                    elseif (strpos($t, 'asset') !== false) $cls .= 'asset';
                                    elseif (strpos($t, 'liab') !== false) $cls .= 'liability';
                                    elseif (strpos($t, 'equity') !== false) $cls .= 'equity'; ?>
                                    <span class="<?= $cls ?>"><?= htmlspecialchars($a['type'] ?? '') ?></span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= route('account_register') . '&account_id=' . (int)$a['id'] ?>"><i class="fas fa-book-open me-1"></i>Register</a>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="4" class="text-muted">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    (function() {
        var inp = document.getElementById('accSearch');
        if (!inp) return;
        inp.addEventListener('input', function() {
            var q = (this.value || '').toLowerCase();
            document.querySelectorAll('#accountsTable tbody tr').forEach(function(tr) {
                var txt = tr.innerText.toLowerCase();
                tr.style.display = txt.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    })();
</script>