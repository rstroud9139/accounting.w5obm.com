<?php

/**
 * Quick Post Widget - Cash Donation Routine
 * File: /accounting/include/quick_post_widget.php
 * Purpose: Rapid cash donation entry for events without donor tracking
 * SECURITY: Requires authentication and accounting_add permission
 */

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        $query = http_build_query(array_merge(['route' => $name], $params));
        return '/accounting/app/index.php?' . $query;
    }
}

if (!function_exists('render_quick_post_cash_donation_widget')) {
    /**
     * Render the quick-post cash donation widget
     * Provides a streamlined form for recording anonymous/cash donations at events
     * 
     * @param array $options Widget configuration options
     * @return void
     */
    function render_quick_post_cash_donation_widget(array $options = []): void
    {
        $userId = $options['user_id'] ?? (function_exists('getCurrentUserId') ? getCurrentUserId() : null);
        $canAdd = $options['can_add'] ?? (function_exists('hasPermission') ? hasPermission($userId, 'accounting_add') : true);

        if (!$canAdd) {
            return;
        }

        // Get default cash account for deposits
        global $conn;
        $defaultAccount = null;
        $cashAccounts = [];

        try {
            if ($conn) {
                // Fetch cash/checking accounts for deposit target
                $stmt = $conn->prepare("
                    SELECT id, name, account_number, account_type 
                    FROM acc_ledger_accounts 
                    WHERE active = 1 
                      AND account_type = 'Asset'
                      AND (name LIKE '%Cash%' OR name LIKE '%Checking%' OR account_number LIKE '11%')
                    ORDER BY 
                        CASE WHEN name LIKE '%Cash%' THEN 1 
                             WHEN name LIKE '%Checking%' THEN 2 
                             ELSE 3 END,
                        name
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $cashAccounts[] = $row;
                }
                $stmt->close();

                if (!empty($cashAccounts)) {
                    $defaultAccount = $cashAccounts[0];
                }
            }
        } catch (Exception $e) {
            error_log("Quick post widget: error fetching cash accounts: " . $e->getMessage());
        }

        // Generate CSRF token
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];

        $widgetId = 'quickPostCashDonation';
        $defaultDate = date('Y-m-d');
        $defaultDescription = 'Cash donation - Event collection';
?>

        <div class="card shadow-sm mb-4 quick-post-widget" id="<?= htmlspecialchars($widgetId) ?>">
            <div class="card-header bg-gradient-success text-white d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-bolt me-2"></i>
                    <strong>Quick Post: Cash Donation</strong>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($widgetId) ?>Form" aria-expanded="true">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="collapse show" id="<?= htmlspecialchars($widgetId) ?>Form">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Fast entry for anonymous cash donations at events. No donor tracking; posts directly to cash account.
                    </p>

                    <form id="quickPostCashDonationForm" method="POST" action="/accounting/donations/quick_post_handler.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="quick_post_type" value="cash_donation">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="qp_amount" class="form-label">
                                    Amount <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        class="form-control"
                                        id="qp_amount"
                                        name="amount"
                                        placeholder="0.00"
                                        required
                                        autofocus>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="qp_donation_date" class="form-label">
                                    Date <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="date"
                                    class="form-control"
                                    id="qp_donation_date"
                                    name="donation_date"
                                    value="<?= htmlspecialchars($defaultDate) ?>"
                                    required>
                            </div>

                            <div class="col-md-4">
                                <label for="qp_deposit_account" class="form-label">
                                    Deposit To <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="qp_deposit_account" name="deposit_account_id" required>
                                    <?php if (!empty($cashAccounts)): ?>
                                        <?php foreach ($cashAccounts as $account): ?>
                                            <option value="<?= (int)$account['id'] ?>" <?= $account['id'] === ($defaultAccount['id'] ?? null) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($account['name']) ?>
                                                <?php if (!empty($account['account_number'])): ?>
                                                    (<?= htmlspecialchars($account['account_number']) ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No cash accounts available</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="qp_description" class="form-label">Description (optional)</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="qp_description"
                                    name="description"
                                    placeholder="<?= htmlspecialchars($defaultDescription) ?>"
                                    value="<?= htmlspecialchars($defaultDescription) ?>">
                            </div>

                            <div class="col-12">
                                <label for="qp_notes" class="form-label">Notes (optional)</label>
                                <textarea
                                    class="form-control"
                                    id="qp_notes"
                                    name="notes"
                                    rows="2"
                                    placeholder="Event name, location, or other context..."></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="qp_tax_deductible" name="tax_deductible" value="1" checked>
                                <label class="form-check-label small text-muted" for="qp_tax_deductible">
                                    Tax deductible
                                </label>
                            </div>
                            <div>
                                <button type="reset" class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="fas fa-undo me-1"></i>Clear
                                </button>
                                <button type="submit" class="btn btn-sm btn-success" <?= empty($cashAccounts) ? 'disabled' : '' ?>>
                                    <i class="fas fa-check me-1"></i>Post Cash Donation
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($cashAccounts)): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Setup Required:</strong> No cash/checking accounts found.
                            <a href="<?= route('accounts'); ?>" class="alert-link">Configure accounts</a> to enable quick posting.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('quickPostCashDonationForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        const amount = parseFloat(document.getElementById('qp_amount').value);
                        if (amount <= 0) {
                            e.preventDefault();
                            alert('Please enter a valid donation amount greater than $0.00');
                            return false;
                        }
                    });

                    // Auto-clear form after successful submission (handled by redirect)
                    form.addEventListener('reset', function() {
                        document.getElementById('qp_amount').focus();
                    });
                }
            });
        </script>
<?php
    }
}
