<?php

/** @var array $items */
/** @var array $categories */
/** @var array $accounts */
/** @var array $defaults */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-eye me-2"></i>Preview Import (<?= count($items) ?>)</span>
        <div class="no-print">
            <a href="<?= route('import') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Please verify the parsed transactions. Select default categories for Income and Expense, then Import.</div>
        <?php if (!empty($dup_count)): ?>
            <div class="alert alert-warning mt-2"><i class="fas fa-clone me-2"></i><?= (int)$dup_count ?> likely duplicate<?= $dup_count == 1 ? '' : 's' ?> detected (matched on date + amount + description). The “Skip likely duplicates” option below is enabled by default.</div>
        <?php endif; ?>
        <?php if (!empty($errors) && is_array($errors)): ?>
            <div class="alert alert-danger mt-2">
                <i class="fas fa-exclamation-triangle me-2"></i>
                The following issues must be resolved before importing:
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success mt-2"><i class="fas fa-check me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form action="<?= route('import_commit') ?>" method="post">
            <?= csrf_input() ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="income_cat" class="form-label">Default Income Category</label>
                    <select id="income_cat" name="income_cat" class="form-select">
                        <option value="0">-- None --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (!empty($defaults['income_cat']) && (int)$defaults['income_cat'] === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="expense_cat" class="form-label">Default Expense Category</label>
                    <select id="expense_cat" name="expense_cat" class="form-select">
                        <option value="0">-- None --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (!empty($defaults['expense_cat']) && (int)$defaults['expense_cat'] === (int)$c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    <label for="cash_account_id" class="form-label">Default Cash/Bank Account</label>
                    <select id="cash_account_id" name="cash_account_id" class="form-select">
                        <option value="0">-- None --</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= isset($a['type']) && $a['type'] ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="offset_account_id" class="form-label">Default Offset Account (Income/Expense)</label>
                    <button type="button" id="saveMappingsBtn" class="btn btn-sm btn-outline-success"><i class="fas fa-save me-1"></i>Save current split mappings</button>
                    <select id="offset_account_id" name="offset_account_id" class="form-select">
                        <option value="0">-- None --</option>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['name']) ?><?= isset($a['type']) && $a['type'] ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
                        <label class="form-check-label" for="skip_duplicates">Skip likely duplicates (match date + amount + description)</label>
                    </div>
                    <div class="form-text">Apply tools for split rows below.</div>
                    <div class="d-flex gap-2 mt-1">
                        <button type="button" id="applyOffsetAll" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-down me-1"></i>Apply default offset</button>
                        <button type="button" id="applyMappingAll" class="btn btn-sm btn-outline-secondary"><i class="fas fa-magic me-1"></i>Apply mapping to splits</button>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button id="importBtn" type="submit" class="btn btn-success w-100"><i class="fas fa-check me-2"></i>Import <?= count($items) ?> Transactions</button>
                </div>
            </div>


            <div class="table-responsive mt-4">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Date</th>
                            <th class="text-end">Amount</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Payee</th>
                            <th>Memo</th>
                            <th>Category</th>
                            <th class="text-center">Splits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 0;
                        foreach ($items as $r): $isDup = !empty($duplicates[$i]); ?>
                            <tr<?= $isDup ? ' class="table-warning"' : '' ?>>
                                <td>
                                    <input type="date" class="form-control form-control-sm" name="rows[<?= $i ?>][date]" value="<?= htmlspecialchars(isset($r['date']) ? $r['date'] : date('Y-m-d')) ?>">
                                </td>
                                <td class="text-end" style="width: 140px;">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control" name="rows[<?= $i ?>][amount]" value="<?= htmlspecialchars((string)(isset($r['amount']) ? $r['amount'] : 0)) ?>">
                                    </div>
                                </td>
                                <td style="width: 130px;">
                                    <select class="form-select form-select-sm" name="rows[<?= $i ?>][type]">
                                        <?php $t = isset($r['type']) ? $r['type'] : ''; ?>
                                        <option value="" <?= $t === '' ? 'selected' : '' ?>>Auto</option>
                                        <option value="Income" <?= $t === 'Income' ? 'selected' : '' ?>>Income</option>
                                        <option value="Expense" <?= $t === 'Expense' ? 'selected' : '' ?>>Expense</option>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <?php if ($isDup): ?>
                                            <span class="input-group-text bg-warning text-dark" title="Likely duplicate based on existing transactions">Dup</span>
                                        <?php endif; ?>
                                        <input type="text" class="form-control" name="rows[<?= $i ?>][description]" value="<?= htmlspecialchars(isset($r['description']) ? $r['description'] : '') ?>">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars(isset($r['payee']) ? $r['payee'] : '') ?>" disabled>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="rows[<?= $i ?>][memo]" value="<?= htmlspecialchars(isset($r['memo']) ? $r['memo'] : '') ?>">
                                </td>
                                <td style="width: 260px;">
                                    <div class="input-group input-group-sm">
                                        <select class="form-select" name="rows[<?= $i ?>][category_id]">
                                            <option value="0">-- Default by type --</option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" title="Copy this category to all rows" data-copy-cat="<?= $i ?>"><i class="fas fa-arrow-down"></i></button>
                                    </div>
                                </td>
                                <td class="text-center" style="width: 140px;">
                                    <div class="d-grid gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#splits-<?= $i ?>"><i class="fas fa-code-branch me-1"></i>Splits</button>
                                        <span class="badge bg-secondary" data-sum-badge="<?= $i ?>">No splits</span>
                                    </div>
                                </td>
                                </tr>
                                <tr class="collapse" id="splits-<?= $i ?>">
                                    <td colspan="8">
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 220px;">Category</th>
                                                        <th style="width: 220px;">Offset Account</th>
                                                        <th style="width: 160px;" class="text-end">Amount</th>
                                                        <th>Notes</th>
                                                        <th style="width: 60px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody data-rows-container="<?= $i ?>">
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-add-split="<?= $i ?>"><i class="fas fa-plus me-1"></i>Add Split</button>
                                            <small class="text-muted ms-2">Sum of splits must match the row amount. Mismatches will disable Import.</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php $i++;
                        endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-muted small">Note: If a row’s category is blank, the defaults above will be applied by type (Income vs Expense).</p>
        </form>
    </div>
</div>
<script>
    (function() {
        function addSplitRow(idx) {
            var tbody = document.querySelector('tbody[data-rows-container="' + idx + '"]');
            if (!tbody) return;
            var rowCount = tbody.querySelectorAll('tr').length;
            var tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select class="form-select form-select-sm" name="rows[${idx}][splits][${rowCount}][category_id]">
                        <option value="0">-- None --</option>
                        ${categoryOptionsHtml}
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" name="rows[${idx}][splits][${rowCount}][offset_account_id]">
                        <option value="0">-- Default Offset --</option>
                        ${accountOptionsHtml}
                    </select>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" class="form-control text-end" name="rows[${idx}][splits][${rowCount}][amount]" value="0.00">
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" name="rows[${idx}][splits][${rowCount}][notes]">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove="1">&times;</button>
                </td>
            `;
            tr.querySelector('[data-remove]')?.addEventListener('click', function() {
                tr.remove();
                updateRowSum(idx);
            });
            tbody.appendChild(tr);
            // React to changes
            tr.querySelectorAll('input,select').forEach(function(el) {
                el.addEventListener('input', function() {
                    updateRowSum(idx);
                });
            });
            updateRowSum(idx);
        }
        // Build options from PHP arrays injected as data attributes
        var categoryOptionsHtml = `<?php foreach ($categories as $c) {
                                        echo '<option value="' . (int)$c['id'] . '">' . htmlspecialchars($c['name']) . '</option>';
                                    } ?>`;
        var accountOptionsHtml = `<?php foreach ($accounts as $a) {
                                        $label = htmlspecialchars($a['name']) . (isset($a['type']) && $a['type'] ? ' (' . htmlspecialchars($a['type']) . ')' : '');
                                        echo '<option value="' . (int)$a['id'] . '">' . $label . '</option>';
                                    } ?>`;
        var categoryMap = <?php echo json_encode(isset($category_map) && is_array($category_map) ? $category_map : array()); ?>;
        document.querySelectorAll('[data-add-split]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                addSplitRow(this.getAttribute('data-add-split'));
            });
        });
        // Auto-set offset account in a split when category changes based on mapping
        document.addEventListener('change', function(e) {
            var el = e.target;
            if (!el || el.tagName !== 'SELECT') return;
            var name = el.getAttribute('name') || '';
            if (name.indexOf('[splits]') !== -1 && name.endsWith('[category_id]')) {
                var m = name.match(/^rows\[(\d+)\]\[splits\]\[(\d+)\]\[category_id\]$/);
                if (m) {
                    var idx = m[1],
                        sidx = m[2];
                    var cid = parseInt(el.value || '0');
                    var off = categoryMap[cid] || 0;
                    if (off) {
                        var offSel = document.querySelector('select[name="rows[' + idx + '][splits][' + sidx + '][offset_account_id]"]');
                        if (offSel) {
                            offSel.value = String(off);
                        }
                    }
                }
            }
        });
        // Copy a row's category to all rows
        document.querySelectorAll('[data-copy-cat]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var i = this.getAttribute('data-copy-cat');
                var src = document.querySelector('select[name="rows[' + i + '][category_id]"]');
                if (!src) return;
                var val = src.value;
                document.querySelectorAll('select[name^="rows["][name$="[category_id]"]').forEach(function(sel) {
                    sel.value = val;
                });
            });
        });
        // Sum validation helpers
        function parseAmount(v) {
            var n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }

        function updateRowSum(idx) {
            var badge = document.querySelector('[data-sum-badge="' + idx + '"]');
            var rowAmt = document.querySelector('input[name="rows[' + idx + '][amount]"]');
            var required = rowAmt ? Math.abs(parseAmount(rowAmt.value)) : 0;
            var sum = 0;
            document.querySelectorAll('tbody[data-rows-container="' + idx + '"] input[name^="rows[' + idx + '][splits]"][name$="[amount]"]').forEach(function(inp) {
                sum += Math.abs(parseAmount(inp.value));
            });
            var ok = (sum === 0) || (Math.abs(sum - required) < 0.005);
            if (badge) {
                if (sum === 0) {
                    badge.className = 'badge bg-secondary';
                    badge.textContent = 'No splits';
                } else if (ok) {
                    badge.className = 'badge bg-success';
                    badge.textContent = 'OK: $' + sum.toFixed(2);
                } else {
                    badge.className = 'badge bg-danger';
                    badge.textContent = 'Mismatch: $' + sum.toFixed(2) + ' / $' + required.toFixed(2);
                }
            }
            validateAll();
        }

        function validateAll() {
            var bad = false;
            document.querySelectorAll('span[data-sum-badge]').forEach(function(b) {
                if (b.textContent.indexOf('Mismatch') !== -1) {
                    bad = true;
                }
            });
            var btn = document.getElementById('importBtn');
            if (btn) {
                btn.disabled = bad;
                btn.title = bad ? 'Fix split mismatches before importing' : '';
            }
        }
        // Update sums when row amounts change
        document.querySelectorAll('input[name$="[amount]"]').forEach(function(inp) {
            inp.addEventListener('input', function() {
                var m = this.name.match(/^rows\[(\d+)\]\[amount\]$/);
                if (m) {
                    updateRowSum(m[1]);
                }
            });
        });
        // Apply default offset account to all split selects
        var applyBtn = document.getElementById('applyOffsetAll');
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                var def = document.getElementById('offset_account_id');
                if (!def) return;
                var val = def.value;
                document.querySelectorAll('select[name$="[offset_account_id]"]').forEach(function(sel) {
                    sel.value = val;
                });
            });
        }
        // Apply saved category->offset mapping to all existing split lines
        var applyMapBtn = document.getElementById('applyMappingAll');
        if (applyMapBtn) {
            applyMapBtn.addEventListener('click', function() {
                document.querySelectorAll('select[name$="[category_id]"]').forEach(function(catSel) {
                    var nm = catSel.getAttribute('name') || '';
                    if (nm.indexOf('[splits]') === -1) return;
                    var m = nm.match(/^rows\[(\d+)\]\[splits\]\[(\d+)\]\[category_id\]$/);
                    if (!m) return;
                    var idx = m[1],
                        sidx = m[2];
                    var cid = parseInt(catSel.value || '0');
                    var off = categoryMap[cid] || 0;
                    if (off) {
                        var offSel = document.querySelector('select[name="rows[' + idx + '][splits][' + sidx + '][offset_account_id]"]');
                        if (offSel) {
                            offSel.value = String(off);
                        }
                    }
                });
            });
        }
        // Save current split mappings by posting cat->offset pairs to inline save route
        var saveMapBtn = document.getElementById('saveMappingsBtn');
        if (saveMapBtn) {
            saveMapBtn.addEventListener('click', function() {
                // Collect unique category->offset pairs
                var pairs = {};
                document.querySelectorAll('select[name$="[category_id]"]').forEach(function(catSel) {
                    var nm = catSel.getAttribute('name') || '';
                    if (nm.indexOf('[splits]') === -1) return;
                    var m = nm.match(/^rows\[(\d+)\]\[splits\]\[(\d+)\]\[category_id\]$/);
                    if (!m) return;
                    var idx = m[1],
                        sidx = m[2];
                    var cid = parseInt(catSel.value || '0');
                    if (!cid) return;
                    var offSel = document.querySelector('select[name="rows[' + idx + '][splits][' + sidx + '][offset_account_id]"]');
                    var off = offSel ? parseInt(offSel.value || '0') : 0;
                    if (!off) return;
                    pairs[cid] = off; // last wins
                });
                if (Object.keys(pairs).length === 0) {
                    alert('No category→offset pairs found in current splits.');
                    return;
                }
                // Build and submit a form
                var form = document.createElement('form');
                form.method = 'post';
                form.action = '<?= route('category_map_save_inline') ?>';
                // CSRF
                var csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = '<?= htmlspecialchars(getCsrfToken()) ?>';
                form.appendChild(csrf);
                // Pairs
                for (var k in pairs) {
                    if (!pairs.hasOwnProperty(k)) continue;
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'map[' + k + ']';
                    inp.value = String(pairs[k]);
                    form.appendChild(inp);
                }
                document.body.appendChild(form);
                form.submit();
            });
        }
        // Initialize badges
        document.querySelectorAll('span[data-sum-badge]').forEach(function(b) {
            var idx = b.getAttribute('data-sum-badge');
            updateRowSum(idx);
        });
    })();
</script>