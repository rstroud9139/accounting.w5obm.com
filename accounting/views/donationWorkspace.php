<?php

if (!function_exists('renderDonationWorkspace')) {
    function renderDonationWorkspace(
        array $donations,
        array $filters,
        array $summary,
        array $contacts,
        bool $canAdd,
        bool $canManage
    ): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $csrfToken = $_SESSION['csrf_token'];
        $receiptOptions = [
            'all' => 'All Receipts',
            'pending' => 'Receipt Pending',
            'sent' => 'Receipt Sent',
        ];
        $taxOptions = [
            'all' => 'All Types',
            'yes' => 'Tax Deductible',
            'no' => 'Non-deductible',
        ];
        $formatDateLabel = static function ($value, $format = 'M d, Y') {
            if (empty($value) || $value === '0000-00-00') {
                return null;
            }
            $timestamp = strtotime($value);
            return $timestamp ? date($format, $timestamp) : $value;
        };
?>
        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Donations</h5>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-light btn-sm" id="donationClearFilters">
                            <i class="fas fa-times me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Preset Filters</h6>
                        <p class="text-muted small mb-3">Jump to common ranges or statuses. These buttons update the form automatically.</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-range="today">Today</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-range="30">Last 30 Days</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-range="month">This Month</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-receipt="pending">Pending Receipts</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-tax="yes">Tax Deductible</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm donation-chip text-start" data-clear="true">Reset All</button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="donationFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="start_date" class="form-label text-muted text-uppercase small mb-1">Start Date</label>
                                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date"
                                        value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                                </div>
                                <div class="col">
                                    <label for="end_date" class="form-label text-muted text-uppercase small mb-1">End Date</label>
                                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date"
                                        value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                                </div>
                                <div class="col">
                                    <label for="contact_id" class="form-label text-muted text-uppercase small mb-1">Donor</label>
                                    <select class="form-select form-select-sm" id="contact_id" name="contact_id">
                                        <option value="">All Donors</option>
                                        <?php foreach ($contacts as $contact): ?>
                                            <option value="<?= (int)$contact['id'] ?>" <?= !empty($filters['contact_id']) && (int)$filters['contact_id'] === (int)$contact['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($contact['name'] ?? 'Unknown') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="receipt_status" class="form-label text-muted text-uppercase small mb-1">Receipt Status</label>
                                    <select class="form-select form-select-sm" id="receipt_status" name="receipt_status">
                                        <?php foreach ($receiptOptions as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($filters['receipt_status'] ?? 'all') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="tax_deductible" class="form-label text-muted text-uppercase small mb-1">Tax Type</label>
                                    <select class="form-select form-select-sm" id="tax_deductible" name="tax_deductible">
                                        <?php foreach ($taxOptions as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($filters['tax_deductible'] ?? 'all') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="min_amount" class="form-label text-muted text-uppercase small mb-1">Min Amount</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="min_amount" name="min_amount"
                                        value="<?= htmlspecialchars($filters['min_amount'] ?? '') ?>" placeholder="0.00">
                                </div>
                                <div class="col">
                                    <label for="max_amount" class="form-label text-muted text-uppercase small mb-1">Max Amount</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="max_amount" name="max_amount"
                                        value="<?= htmlspecialchars($filters['max_amount'] ?? '') ?>" placeholder="500.00">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Description, notes, donor">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="donationExportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-0"><i class="fas fa-hand-holding-heart me-2 text-danger"></i>Donations
                        <small class="text-muted">(<?= number_format($summary['total_count'] ?? 0) ?> results)</small>
                    </h4>
                    <small class="text-muted">Filtered donations with inline actions</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-secondary btn-sm" data-batch-action="generate_all">
                        <i class="fas fa-receipt me-1"></i>Generate Receipts
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" data-batch-action="email_all">
                        <i class="fas fa-envelope me-1"></i>Email Receipts
                    </button>
                    <?php if ($canAdd): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                            <i class="fas fa-plus-circle me-1"></i>Record Donation
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($donations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-2">No donations match the selected filters.</p>
                        <button class="btn btn-outline-primary" id="donationEmptyReset">Reset Filters</button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Donor</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                    <th>Details</th>
                                    <th class="text-center">Tax</th>
                                    <th class="text-center">Receipt</th>
                                    <?php if ($canManage): ?>
                                        <th class="text-center">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation):
                                    $payload = htmlspecialchars(json_encode([
                                        'id' => (int)($donation['id'] ?? 0),
                                        'contact_id' => (int)($donation['contact_id'] ?? 0),
                                        'contact_name' => $donation['contact_name'] ?? '',
                                        'amount' => $donation['amount'] ?? 0,
                                        'donation_date' => $donation['donation_date'] ?? '',
                                        'description' => $donation['description'] ?? '',
                                        'notes' => $donation['notes'] ?? '',
                                        'tax_deductible' => (int)($donation['tax_deductible'] ?? 1),
                                        'receipt_sent' => (int)($donation['receipt_sent'] ?? 0),
                                        'receipt_date' => $donation['receipt_date'] ?? '',
                                        'contact_email' => $donation['contact_email'] ?? '',
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($donation['contact_name'] ?? 'Unknown Donor') ?></div>
                                            <small class="text-muted">ID: <?= (int)$donation['id'] ?></small>
                                        </td>
                                        <td class="text-end fw-semibold text-success">
                                            $<?= number_format((float)($donation['amount'] ?? 0), 2) ?>
                                        </td>
                                        <td>
                                            <?php $donationDateLabel = $formatDateLabel($donation['donation_date'] ?? null); ?>
                                            <?= htmlspecialchars($donationDateLabel ?? '--') ?>
                                        </td>
                                        <td>
                                            <div class="text-muted small mb-1"><?= htmlspecialchars($donation['description'] ?: 'No description provided') ?></div>
                                            <?php if (!empty($donation['notes'])): ?>
                                                <span class="badge bg-secondary">Notes on file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= !empty($donation['tax_deductible']) ? 'success' : 'warning' ?>">
                                                <?= !empty($donation['tax_deductible']) ? 'Deductible' : 'Non-deductible' ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($donation['receipt_sent'])):
                                                $receiptDateLabel = $formatDateLabel($donation['receipt_date'] ?? null, 'M d');
                                            ?>
                                                <span class="badge bg-success">Sent<?= $receiptDateLabel ? '<br><small>' . htmlspecialchars($receiptDateLabel) . '</small>' : '' ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <a href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>" class="btn btn-link btn-sm p-0" target="_blank">View</a>
                                                <?php if ($canManage): ?>
                                                    <span class="text-muted">·</span>
                                                    <a href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>&email=1" class="btn btn-link btn-sm p-0">Email</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php if ($canManage): ?>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                                        data-bs-target="#editDonationModal" data-donation='<?= $payload ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" data-bs-toggle="modal"
                                                        data-bs-target="#deleteDonationModal" data-donation='<?= $payload ?>'>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>" target="_blank">View Receipt</a></li>
                                                        <li><a class="dropdown-item" href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>&email=1">Email Receipt</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-lg-none">
                        <?php foreach ($donations as $donation):
                            $payload = htmlspecialchars(json_encode([
                                'id' => (int)($donation['id'] ?? 0),
                                'contact_id' => (int)($donation['contact_id'] ?? 0),
                                'contact_name' => $donation['contact_name'] ?? '',
                                'amount' => $donation['amount'] ?? 0,
                                'donation_date' => $donation['donation_date'] ?? '',
                                'description' => $donation['description'] ?? '',
                                'notes' => $donation['notes'] ?? '',
                                'tax_deductible' => (int)($donation['tax_deductible'] ?? 1),
                                'receipt_sent' => (int)($donation['receipt_sent'] ?? 0),
                                'receipt_date' => $donation['receipt_date'] ?? '',
                                'contact_email' => $donation['contact_email'] ?? '',
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="card border-0 border-bottom rounded-0">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($donation['contact_name'] ?? 'Unknown Donor') ?></div>
                                            <?php $mobileDateLabel = $formatDateLabel($donation['donation_date'] ?? null); ?>
                                            <small class="text-muted">$<?= number_format((float)($donation['amount'] ?? 0), 2) ?> • <?= htmlspecialchars($mobileDateLabel ?? '--') ?></small>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                                    data-bs-target="#editDonationModal" data-donation='<?= $payload ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal"
                                                    data-bs-target="#deleteDonationModal" data-donation='<?= $payload ?>'>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2 mb-2">
                                        <span class="badge bg-<?= !empty($donation['tax_deductible']) ? 'success' : 'warning' ?>">
                                            <?= !empty($donation['tax_deductible']) ? 'Deductible' : 'Taxable' ?>
                                        </span>
                                        <span class="badge bg-<?= !empty($donation['receipt_sent']) ? 'success' : 'warning text-dark' ?>">
                                            <?= !empty($donation['receipt_sent']) ? 'Receipt Sent' : 'Receipt Pending' ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small"><?= htmlspecialchars($donation['description'] ?: 'No description provided') ?></div>
                                    <?php if (!empty($donation['notes'])): ?>
                                        <div class="text-muted small mt-1"><i class="fas fa-sticky-note me-1"></i>Notes available</div>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <a href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                                            <i class="fas fa-file-pdf me-1"></i>Receipt
                                        </a>
                                        <a href="/accounting/donations/receipt.php?id=<?= (int)$donation['id'] ?>&email=1" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-envelope me-1"></i>Email
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canAdd): ?>
            <div class="modal fade" id="addDonationModal" tabindex="-1" aria-labelledby="addDonationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST" action="/accounting/donations/donation_actions.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="addDonationModalLabel"><i class="fas fa-plus-circle me-2"></i>Record Donation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Donor *</label>
                                <select class="form-select" name="contact_id" required>
                                    <option value="">Select donor</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= (int)$contact['id'] ?>">
                                            <?= htmlspecialchars($contact['name'] ?? 'Unknown Donor') ?>
                                            <?php if (!empty($contact['email'])): ?>
                                                (<?= htmlspecialchars($contact['email']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Amount *</label>
                                    <input type="number" class="form-control" name="amount" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Donation Date *</label>
                                    <input type="date" class="form-control" name="donation_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="Purpose or notes for acknowledgment"></textarea>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Internal Notes</label>
                                <textarea class="form-control" name="notes" rows="2" placeholder="Private details visible to staff only"></textarea>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="tax_deductible" id="addTaxDeductible" checked>
                                <label class="form-check-label" for="addTaxDeductible">Tax deductible contribution</label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="send_receipt" id="addSendReceipt" checked>
                                <label class="form-check-label" for="addSendReceipt">Email receipt immediately (if donor email exists)</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Record Donation</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <div class="modal fade" id="editDonationModal" tabindex="-1" aria-labelledby="editDonationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST" action="/accounting/donations/donation_actions.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="editDonationId">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="editDonationModalLabel"><i class="fas fa-edit me-2"></i>Edit Donation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Donor *</label>
                                <select class="form-select" name="contact_id" id="editDonationContact" required>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= (int)$contact['id'] ?>">
                                            <?= htmlspecialchars($contact['name'] ?? 'Unknown Donor') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="editDonationAmount" name="amount" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Donation Date *</label>
                                    <input type="date" class="form-control" id="editDonationDate" name="donation_date" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editDonationDescription" rows="2"></textarea>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Internal Notes</label>
                                <textarea class="form-control" name="notes" id="editDonationNotes" rows="2"></textarea>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="tax_deductible" id="editDonationTax">
                                <label class="form-check-label" for="editDonationTax">Tax deductible</label>
                            </div>
                            <div class="alert alert-light border mt-3" id="receiptStatusBlock">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Receipt Status:</strong>
                                        <span id="receiptStatusText" class="ms-1">Unknown</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="editReceiptToggle" name="receipt_sent">
                                        <label class="form-check-label" for="editReceiptToggle">Mark as sent</label>
                                    </div>
                                </div>
                                <small class="text-muted" id="receiptStatusDate"></small>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="send_receipt" id="editSendReceipt">
                                <label class="form-check-label" for="editSendReceipt">Email updated receipt after saving</label>
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="deleteDonationModal" tabindex="-1" aria-labelledby="deleteDonationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content border-0 shadow" method="POST" action="/accounting/donations/donation_actions.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteDonationId">
                        <div class="modal-header bg-dark text-white border-0">
                            <h5 class="modal-title" id="deleteDonationModalLabel">Delete Donation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">You are deleting donation <strong id="deleteDonationSummary">#</strong>.</p>
                            <div class="alert alert-warning">
                                This action cannot be undone. The record will be permanently removed from the ledger.
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete Donation</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const filterForm = document.getElementById('donationFilterForm');
                const clearBtn = document.getElementById('donationClearFilters');
                const emptyReset = document.getElementById('donationEmptyReset');
                const exportBtn = document.getElementById('donationExportBtn');
                const chips = document.querySelectorAll('.donation-chip');
                const batchButtons = document.querySelectorAll('[data-batch-action]');

                const buildQueryString = () => {
                    if (!filterForm) {
                        return '';
                    }
                    const params = new URLSearchParams(new FormData(filterForm));
                    params.delete('export');
                    return params.toString();
                };

                clearBtn?.addEventListener('click', () => {
                    filterForm?.reset();
                    window.location = window.location.pathname;
                });

                emptyReset?.addEventListener('click', () => {
                    filterForm?.reset();
                    filterForm?.submit();
                });

                exportBtn?.addEventListener('click', () => {
                    const query = buildQueryString();
                    const url = window.location.pathname + (query ? '?' + query + '&' : '?') + 'export=csv';
                    window.location = url;
                });

                batchButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const query = buildQueryString();
                        const url = '/accounting/donations/receipt.php?action=' + btn.dataset.batchAction + (query ? '&' + query : '');
                        window.location = url;
                    });
                });

                const setDateRange = (range) => {
                    const startInput = document.getElementById('start_date');
                    const endInput = document.getElementById('end_date');
                    const today = new Date();
                    let start = new Date(today);

                    if (range === 'today') {
                        start = new Date(today);
                    } else if (range === '30') {
                        start.setDate(start.getDate() - 30);
                    } else if (range === 'month') {
                        start = new Date(today.getFullYear(), today.getMonth(), 1);
                    }

                    const formatDate = (d) => d.toISOString().split('T')[0];

                    if (startInput) {
                        startInput.value = formatDate(start);
                    }
                    if (endInput) {
                        endInput.value = formatDate(today);
                    }
                };

                chips.forEach((chip) => {
                    chip.addEventListener('click', () => {
                        if (chip.dataset.range) {
                            setDateRange(chip.dataset.range);
                        }
                        if (chip.dataset.receipt) {
                            const receiptSelect = document.getElementById('receipt_status');
                            if (receiptSelect) {
                                receiptSelect.value = chip.dataset.receipt;
                            }
                        }
                        if (chip.dataset.tax) {
                            const taxSelect = document.getElementById('tax_deductible');
                            if (taxSelect) {
                                taxSelect.value = chip.dataset.tax;
                            }
                        }
                        if (chip.dataset.clear) {
                            filterForm?.reset();
                        }
                    });
                });

                const parsePayload = (button) => {
                    try {
                        return JSON.parse(button.getAttribute('data-donation') || '');
                    } catch (e) {
                        return null;
                    }
                };

                const editModal = document.getElementById('editDonationModal');
                editModal?.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const payload = parsePayload(button);
                    if (!payload) {
                        return;
                    }
                    document.getElementById('editDonationId').value = payload.id || '';
                    document.getElementById('editDonationAmount').value = payload.amount || '';
                    document.getElementById('editDonationDate').value = payload.donation_date || '';
                    document.getElementById('editDonationDescription').value = payload.description || '';
                    document.getElementById('editDonationNotes').value = payload.notes || '';
                    document.getElementById('editDonationTax').checked = !!payload.tax_deductible;
                    document.getElementById('editDonationContact').value = payload.contact_id || '';

                    const receiptToggle = document.getElementById('editReceiptToggle');
                    const receiptText = document.getElementById('receiptStatusText');
                    const receiptDate = document.getElementById('receiptStatusDate');

                    if (receiptToggle) {
                        receiptToggle.checked = !!payload.receipt_sent;
                    }
                    if (receiptText) {
                        receiptText.textContent = payload.receipt_sent ? 'Sent' : 'Pending';
                    }
                    if (receiptDate) {
                        receiptDate.textContent = payload.receipt_date ? 'Sent on ' + payload.receipt_date : '';
                    }

                    const sendReceiptCheckbox = document.getElementById('editSendReceipt');
                    if (sendReceiptCheckbox) {
                        sendReceiptCheckbox.checked = false;
                    }
                });

                const deleteModal = document.getElementById('deleteDonationModal');
                deleteModal?.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const payload = parsePayload(button);
                    if (!payload) {
                        return;
                    }
                    document.getElementById('deleteDonationId').value = payload.id || '';
                    const summary = '# ' + payload.id + ' • ' + (payload.contact_name || 'Donor') + ' • $' + Number(payload.amount || 0).toFixed(2);
                    document.getElementById('deleteDonationSummary').textContent = summary;
                });
            });
        </script>
<?php
    }
}
