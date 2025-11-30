<?php
$workspace = $workspace ?? ['ready' => false];
$active = $workspace['active'] ?? [];
$hasActive = !empty($workspace['ready']) && !empty($active);
$progressPercent = $hasActive ? max(0, min(100, (int)$active['progress_percent'])) : 0;
$fmtMoney = static function ($value) {
    $number = number_format((float)$value, 2);
    if ($number === '-0.00') {
        $number = '0.00';
    }
    return '$' . $number;
};
if (!function_exists('recon_format_short_date')) {
    function recon_format_short_date(?string $date): string
    {
        if (empty($date)) {
            return '—';
        }
        $ts = strtotime($date);
        if (!$ts) {
            return (string)$date;
        }
        return date('M d', $ts);
    }
}
$unclearedDisplay = $workspace['uncleared']['display'] ?? [];
$clearedDisplay = $workspace['cleared']['display'] ?? [];
$statusMessage = $workspace['status'] ?? null;
?>

<div class="recon-workspace">
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$has_double): ?>
        <div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>Reconciliation requires the double-entry journal tables. Please run migrations.</div>
    <?php endif; ?>

    <section class="recon-hero">
        <div class="recon-hero-header">
            <div>
                <h1>Reconciliation Workspace</h1>
                <p class="annotation">Guided three-step flow to lock statements, clear transactions, and document adjustments.</p>
            </div>
            <div class="status-pill <?= htmlspecialchars($active['status_class'] ?? 'neutral') ?>">
                <?= htmlspecialchars($active['status_label'] ?? 'Setup Required') ?>
                <?php if (!empty($active['progress_label'])): ?>
                    <span> · <?= htmlspecialchars($active['progress_label']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="recon-hero-body">
            <div>
                <h2><?= htmlspecialchars($hasActive ? $active['statement_title'] : 'Select a statement to begin') ?></h2>
                <p class="annotation" style="color:rgba(255,255,255,0.75);">Statement ending balance is locked once reconciliation begins. Any delta must be resolved with adjustments plus audit trail.</p>
            </div>
            <div>
                <span class="hero-meta-label">Open Since</span>
                <div class="hero-meta-value"><?= htmlspecialchars($hasActive ? $active['open_since'] : '—') ?></div>
            </div>
        </div>
        <div class="progress-ribbon" aria-label="<?= htmlspecialchars($active['progress_label'] ?? '0% cleared') ?>">
            <div class="progress-ribbon-fill" style="width:<?= $progressPercent ?>%;"></div>
        </div>
        <div class="hero-meta">
            <div class="hero-meta-block">
                <div class="hero-meta-label">Statement Ending Balance</div>
                <div class="hero-meta-value"><?= $fmtMoney($active['statement_balance'] ?? 0) ?></div>
            </div>
            <div class="hero-meta-block">
                <div class="hero-meta-label">Ledger Balance</div>
                <div class="hero-meta-value"><?= $fmtMoney($active['ledger_balance'] ?? 0) ?></div>
            </div>
            <div class="hero-meta-block">
                <div class="hero-meta-label">Difference</div>
                <div class="hero-meta-value"><?= $fmtMoney($active['difference'] ?? 0) ?></div>
            </div>
            <div class="hero-meta-block">
                <div class="hero-meta-label">Last Cleared</div>
                <div class="hero-meta-value"><?= htmlspecialchars($active['last_cleared_label'] ?? '—') ?></div>
            </div>
        </div>
    </section>

    <?php if (!empty($statusMessage)): ?>
        <div class="alert alert-info recon-status-alert"><i class="fas fa-lightbulb me-2"></i><?= htmlspecialchars($statusMessage) ?></div>
    <?php endif; ?>

    <?php
    $stepStates = [
        'lock' => $hasActive ? 'completed' : 'pending',
        'clear' => $hasActive ? ($progressPercent >= 95 ? 'completed' : 'active') : 'pending',
        'adjust' => ($hasActive && $progressPercent >= 99 && abs(($active['difference'] ?? 0)) < 1) ? 'completed' : 'pending',
    ];
    $stepBadges = [
        'lock' => $stepStates['lock'] === 'completed' ? ['class' => 'success', 'label' => 'Locked'] : ['class' => '', 'label' => 'Pending'],
        'clear' => $stepStates['clear'] === 'completed' ? ['class' => 'success', 'label' => 'Cleared'] : ($stepStates['clear'] === 'active' ? ['class' => 'info', 'label' => 'In Progress'] : ['class' => '', 'label' => 'Pending']),
        'adjust' => $stepStates['adjust'] === 'completed' ? ['class' => 'success', 'label' => 'Ready'] : ['class' => '', 'label' => 'Pending'],
    ];
    ?>
    <div class="steps-grid">
        <div class="step-card <?= $stepStates['lock'] === 'completed' ? 'completed' : '' ?>">
            <div class="step-top">
                <div class="step-ident">
                    <span class="step-number">1</span>
                    <span class="step-icon" aria-hidden="true">&#128196;</span>
                    <span class="step-label">Lock Statement</span>
                </div>
                <span class="status-badge <?= $stepBadges['lock']['class'] ?>"><?= htmlspecialchars($stepBadges['lock']['label']) ?></span>
            </div>
            <div class="step-body">
                Import statement totals and capture the ending balance. Edits require reviewer approval and generate audit entries.
            </div>
            <div class="step-actions">
                <a class="btn-outline" href="<?= $hasActive ? route('reconciliation_view', ['rid' => $active['id']]) : '#' ?>">View Statement</a>
            </div>
        </div>
        <div class="step-card <?= $stepStates['clear'] === 'completed' ? 'completed' : ($stepStates['clear'] === 'active' ? 'active' : '') ?>">
            <div class="step-top">
                <div class="step-ident">
                    <span class="step-number">2</span>
                    <span class="step-icon" aria-hidden="true">&#9989;</span>
                    <span class="step-label">Clear Transactions</span>
                </div>
                <span class="status-badge <?= $stepBadges['clear']['class'] ?>"><?= htmlspecialchars($stepBadges['clear']['label']) ?></span>
            </div>
            <div class="step-body">
                Clear transactions in batches. Uncleared items remain on the left while confirmed items move right with running totals.
            </div>
            <div class="step-actions">
                <a class="btn-primary" href="#unclearedTransactions">Continue Clearing</a>
                <a class="btn-outline" href="#notesTasks">Attach Audit Note</a>
            </div>
        </div>
        <div class="step-card <?= $stepStates['adjust'] === 'completed' ? 'completed' : '' ?>">
            <div class="step-top">
                <div class="step-ident">
                    <span class="step-number">3</span>
                    <span class="step-icon" aria-hidden="true">&#128295;</span>
                    <span class="step-label">Adjust & Close</span>
                </div>
                <span class="status-badge"><?= htmlspecialchars($stepBadges['adjust']['label']) ?></span>
            </div>
            <div class="step-body">
                Post adjustments for any remaining difference and finalize the reconciliation with reviewer initials and notes.
            </div>
            <div class="step-actions">
                <a class="btn-outline" href="<?= route('transaction_new') ?>">Prep Adjustment</a>
            </div>
        </div>
    </div>

    <div class="recon-layout row g-4">
        <div class="col-lg-8">
            <div class="recon-card mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Statement Inputs</h2>
                        <p class="annotation mb-0">Values originate from uploaded statements; edits are logged and require approval.</p>
                    </div>
                    <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#reconStatementForm">
                        <i class="fas fa-sliders-h me-1"></i>Adjust Inputs
                    </button>
                </div>
                <div class="ghost-inputs mb-3">
                    <div class="ghost-field">
                        <label>Statement Date</label>
                        <span><?= htmlspecialchars($hasActive ? date('M d, Y', strtotime($active['end_date'])) : '—') ?></span>
                    </div>
                    <div class="ghost-field">
                        <label>Opening Balance</label>
                        <span><?= $fmtMoney($active['opening_balance'] ?? 0) ?></span>
                    </div>
                    <div class="ghost-field">
                        <label>Ending Balance</label>
                        <span><?= $fmtMoney($active['statement_balance'] ?? 0) ?></span>
                    </div>
                    <div class="ghost-field">
                        <label>Reviewed By</label>
                        <span><?= htmlspecialchars($active['reviewer_initials'] ?? 'Pending · Add initials') ?></span>
                    </div>
                </div>
                <div class="collapse" id="reconStatementForm">
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

            <div class="dual-pane" id="unclearedTransactions">
                <div class="pane-card">
                    <div class="pane-header">
                        <div>
                            <h3>Uncleared Transactions</h3>
                            <span class="annotation"><?= number_format($workspace['uncleared']['count'] ?? 0) ?> items · <?= $fmtMoney($workspace['uncleared']['total'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="recon-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-cell"></th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Notes</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($unclearedDisplay)): ?>
                                    <?php foreach ($unclearedDisplay as $row): ?>
                                        <tr>
                                            <td class="checkbox-cell"><input class="form-check-input" type="checkbox" <?= !empty($row['prechecked']) ? 'checked' : '' ?> disabled></td>
                                            <td><?= htmlspecialchars(recon_format_short_date($row['date'] ?? null)) ?></td>
                                            <td><?= htmlspecialchars($row['description'] ?? 'Unlabeled entry') ?></td>
                                            <td class="annotation"><?= htmlspecialchars($row['notes'] ?? '') ?></td>
                                            <td class="amount-cell"><?= $fmtMoney($row['amount'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-muted">No uncleared items for this period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="running-total">
                        <small>Checked items ready to move</small>
                        <span><?= $fmtMoney($workspace['uncleared']['prechecked_total'] ?? 0) ?></span>
                    </div>
                    <p class="pane-caption">Step 2a · Move ready items once supporting documentation is verified.</p>
                </div>

                <div class="pane-card">
                    <div class="pane-header">
                        <div>
                            <h3>Cleared This Cycle</h3>
                            <span class="annotation"><?= number_format($workspace['cleared']['count'] ?? 0) ?> items · <?= $fmtMoney($workspace['cleared']['total'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="recon-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Cleared By</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($clearedDisplay)): ?>
                                    <?php foreach ($clearedDisplay as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(recon_format_short_date($row['date'] ?? null)) ?></td>
                                            <td><?= htmlspecialchars($row['description'] ?? 'Unlabeled entry') ?></td>
                                            <td><?= htmlspecialchars($row['cleared_by'] ?? 'Automation') ?></td>
                                            <td class="amount-cell"><?= $fmtMoney($row['amount'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">No cleared items recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="running-total">
                        <small>Running cleared total</small>
                        <span><?= $fmtMoney($workspace['cleared']['total'] ?? 0) ?></span>
                    </div>
                    <p class="pane-caption">Step 2b · Cleared totals feed the cash available KPIs and board packet metrics.</p>
                    <p class="annotation mt-2">Auto-matched entries display once supporting documents are attached.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <aside class="mini-stack" id="notesTasks">
                <div class="history-card">
                    <h3>Progress Snapshot</h3>
                    <div class="stat-block">
                        <div class="stat-label">Difference Remaining</div>
                        <div class="stat-value"><?= $fmtMoney($workspace['snapshot']['difference'] ?? 0) ?></div>
                        <p class="annotation">Post an adjustment only after uncleared items are resolved.</p>
                        <div class="metric-note">Ledger KPIs subtract this delta until adjustments post.</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-label">Cleared Transactions</div>
                        <div class="stat-value"><?= htmlspecialchars($workspace['snapshot']['cleared_ratio'] ?? '0 / 0') ?></div>
                        <p class="annotation">Batch actions unlock when 5+ selections share a payee.</p>
                        <div class="metric-note">Board packet flags accounts under 90% cleared.</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-label">Next Review</div>
                        <div class="stat-value"><?= htmlspecialchars($workspace['snapshot']['next_review'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="history-card">
                    <h3>Recent Reconciliations</h3>
                    <ul class="history-list">
                        <?php if (!empty($workspace['recent'])): ?>
                            <?php foreach ($workspace['recent'] as $recent): ?>
                                <li>
                                    <strong><?= htmlspecialchars(date('M Y', strtotime($recent['end_date'] ?? $recent['start_date'] ?? 'now'))) ?></strong>
                                    <?= htmlspecialchars($recent['account_name'] ?? 'Account #' . $recent['id']) ?> · Ending <?= $fmtMoney($recent['ending_balance'] ?? 0) ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>No historical reconciliations yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="history-card">
                    <h3>Notes & Tasks</h3>
                    <ul class="history-list">
                        <?php if (!empty($workspace['notes'])): ?>
                            <?php foreach ($workspace['notes'] as $note): ?>
                                <li>
                                    <strong><?= htmlspecialchars($note['title']) ?></strong>
                                    <?= htmlspecialchars($note['body']) ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><strong>Reminder</strong> Add notes for outstanding reconciliations.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</div><?php

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