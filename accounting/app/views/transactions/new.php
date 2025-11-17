<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>New Transaction</h5>
        <div>
            <a class="btn btn-sm btn-outline-secondary me-2" href="<?= route('transactions') ?>">Back</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form action="<?= route('transaction_create') ?>" method="post" class="row g-3">
            <?= csrf_input() ?>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" id="type" class="form-select">
                    <option value="Income">Income</option>
                    <option value="Expense">Expense</option>
                    <option value="Transfer">Transfer</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Amount</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="amount" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category_id" id="category" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach (($categories ?? []) as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= !empty($c['type']) ? ' (' . htmlspecialchars($c['type']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Ignored for Transfers.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Cash/Bank (source for Transfer)</label>
                <select name="cash_account_id" id="cash" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach (($accounts ?? []) as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" data-type="<?= htmlspecialchars(strtolower($a['type'] ?? '')) ?>"><?= htmlspecialchars($a['name']) ?><?= !empty($a['type']) ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><span class="badge bg-light text-dark">Hint</span> Choose a Cash/Bank type account.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Offset/Other (destination for Transfer)</label>
                <select name="offset_account_id" id="offset" class="form-select">
                    <option value="">-- Select --</option>
                    <?php foreach (($accounts ?? []) as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" data-type="<?= htmlspecialchars(strtolower($a['type'] ?? '')) ?>"><?= htmlspecialchars($a['name']) ?><?= !empty($a['type']) ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="offset-hint" class="form-text"><span class="badge bg-light text-dark">Hint</span> For Income select an Income account; for Expense select an Expense account.</div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-success"><i class="fas fa-check me-2"></i>Save</button>
            </div>
        </form>
    </div>
</div>
<script>
    (function() {
        function onTypeChange() {
            var t = document.getElementById('type').value;
            var cat = document.getElementById('category');
            var offset = document.getElementById('offset');
            cat.disabled = (t === 'Transfer');
            if (t === 'Transfer') {
                offset.setAttribute('required', 'required');
            } else {
                offset.removeAttribute('required');
            }
            filterAccounts();
            updateHints();
        }
        document.getElementById('type').addEventListener('change', onTypeChange);
        onTypeChange();

        // Prevent equal source/destination on Transfer
        function validateAccounts() {
            var t = document.getElementById('type').value;
            var cash = document.getElementById('cash').value;
            var offset = document.getElementById('offset').value;
            if (t === 'Transfer' && cash && offset && cash === offset) {
                alert('Source and destination accounts cannot be the same for a Transfer.');
                return false;
            }
            return true;
        }
        document.querySelector('form')?.addEventListener('submit', function(e) {
            if (!validateAccounts()) {
                e.preventDefault();
            }
        });

        // Account-type filtering per transaction type
        var cashSelect = document.getElementById('cash');
        var offsetSelect = document.getElementById('offset');
        var allCashOptions = Array.from(cashSelect.options);
        var allOffsetOptions = Array.from(offsetSelect.options);

        function typeSetContains(type, set) {
            return set.indexOf((type || '').toLowerCase()) !== -1;
        }

        function filterSelect(selectEl, allOptions, allowedTypes) {
            var current = selectEl.value;
            // Keep placeholder
            selectEl.innerHTML = '';
            allOptions.forEach(function(opt) {
                if (!opt.value) {
                    selectEl.appendChild(opt.cloneNode(true));
                    return;
                }
                var t = (opt.getAttribute('data-type') || '').toLowerCase();
                if (allowedTypes.length === 0 || typeSetContains(t, allowedTypes)) {
                    selectEl.appendChild(opt.cloneNode(true));
                }
            });
            // Try to preserve selection if still allowed
            var exists = Array.from(selectEl.options).some(function(o) {
                return o.value === current;
            });
            selectEl.value = exists ? current : '';
        }

        function filterAccounts() {
            var t = document.getElementById('type').value;
            // Heuristics for account types
            var cashTypes = ['asset', 'bank', 'cash'];
            var incomeTypes = ['income', 'revenue'];
            var expenseTypes = ['expense'];

            if (t === 'Transfer') {
                filterSelect(cashSelect, allCashOptions, cashTypes);
                filterSelect(offsetSelect, allOffsetOptions, cashTypes);
            } else if (t === 'Income') {
                filterSelect(cashSelect, allCashOptions, cashTypes);
                filterSelect(offsetSelect, allOffsetOptions, incomeTypes);
            } else if (t === 'Expense') {
                filterSelect(cashSelect, allCashOptions, cashTypes);
                filterSelect(offsetSelect, allOffsetOptions, expenseTypes);
            } else {
                // Fallback: no filtering
                filterSelect(cashSelect, allCashOptions, []);
                filterSelect(offsetSelect, allOffsetOptions, []);
            }
        }

        function updateHints() {
            var t = document.getElementById('type').value;
            var hint = document.getElementById('offset-hint');
            if (t === 'Transfer') {
                hint.textContent = 'For Transfer, choose another Cash/Bank account as destination.';
            } else if (t === 'Income') {
                hint.textContent = 'For Income select an Income account as offset.';
            } else if (t === 'Expense') {
                hint.textContent = 'For Expense select an Expense account as offset.';
            } else {
                hint.textContent = 'Select an appropriate offset account.';
            }
        }
    })();
</script>