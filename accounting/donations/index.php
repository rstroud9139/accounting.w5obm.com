<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/donation_controller.php';
require_once __DIR__ . '/../views/donationWorkspace.php';

if (!isAuthenticated()) {
  header('Location: /authentication/login.php');
  exit();
}

$userId = getCurrentUserId();
$canView = hasPermission($userId, 'accounting_view') || hasPermission($userId, 'accounting_manage');
$canAdd = hasPermission($userId, 'accounting_add') || hasPermission($userId, 'accounting_manage');
$canManage = hasPermission($userId, 'accounting_manage');

if (!$canView) {
  setToastMessage('danger', 'Access Denied', 'You do not have permission to view donations.', 'club-logo');
  header('Location: /accounting/dashboard.php');
  exit();
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Donations Workspace - W5OBM Accounting';

$normalizeDate = static function ($value, $fallback) {
  if (empty($value)) {
    return $fallback;
  }
  $date = DateTime::createFromFormat('Y-m-d', $value);
  return $date ? $date->format('Y-m-d') : $fallback;
};

$filters = [
  'start_date' => $normalizeDate($_GET['start_date'] ?? date('Y-01-01'), date('Y-01-01')),
  'end_date' => $normalizeDate($_GET['end_date'] ?? date('Y-m-d'), date('Y-m-d')),
  'contact_id' => sanitizeInput($_GET['contact_id'] ?? '', 'int'),
  'receipt_status' => sanitizeInput($_GET['receipt_status'] ?? 'all', 'string'),
  'tax_deductible' => sanitizeInput($_GET['tax_deductible'] ?? 'all', 'string'),
  'min_amount' => sanitizeInput($_GET['min_amount'] ?? '', 'double'),
  'max_amount' => sanitizeInput($_GET['max_amount'] ?? '', 'double'),
  'search' => sanitizeInput($_GET['search'] ?? '', 'string'),
];

if ($filters['start_date'] > $filters['end_date']) {
  $tmp = $filters['start_date'];
  $filters['start_date'] = $filters['end_date'];
  $filters['end_date'] = $tmp;
}

$queryFilters = [
  'start_date' => $filters['start_date'],
  'end_date' => $filters['end_date'],
];

if (!empty($filters['contact_id'])) {
  $queryFilters['contact_id'] = $filters['contact_id'];
}
if (!empty($filters['search'])) {
  $queryFilters['search'] = $filters['search'];
}
if (!empty($filters['min_amount'])) {
  $queryFilters['min_amount'] = $filters['min_amount'];
}
if (!empty($filters['max_amount'])) {
  $queryFilters['max_amount'] = $filters['max_amount'];
}
if (!empty($filters['receipt_status']) && $filters['receipt_status'] !== 'all') {
  $queryFilters['receipt_status'] = $filters['receipt_status'];
}
if (!empty($filters['tax_deductible']) && $filters['tax_deductible'] !== 'all') {
  $queryFilters['tax_deductible'] = $filters['tax_deductible'];
}

try {
  $donations = get_donations($queryFilters);
} catch (Exception $e) {
  $donations = [];
  setToastMessage('danger', 'Donations', 'Unable to load donations: ' . $e->getMessage(), 'club-logo');
  logError('Error loading donations: ' . $e->getMessage(), 'accounting');
}

$summary = get_donation_summary($donations);

try {
  $contacts = get_donation_contacts();
} catch (Exception $e) {
  $contacts = [];
  logError('Error loading contacts for donations: ' . $e->getMessage(), 'accounting');
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="donations_' . date('Y-m-d') . '.csv"');

  $output = fopen('php://output', 'w');
  fputcsv($output, ['ID', 'Donor', 'Amount', 'Date', 'Description', 'Tax Deductible', 'Receipt Sent', 'Receipt Date']);
  foreach ($donations as $donation) {
    fputcsv($output, [
      $donation['id'] ?? '',
      $donation['contact_name'] ?? '',
      number_format((float)($donation['amount'] ?? 0), 2, '.', ''),
      $donation['donation_date'] ?? '',
      $donation['description'] ?? '',
      !empty($donation['tax_deductible']) ? 'Yes' : 'No',
      !empty($donation['receipt_sent']) ? 'Yes' : 'No',
      $donation['receipt_date'] ?? '',
    ]);
  }
  fclose($output);
  exit();
}

$status = $_GET['status'] ?? null;
$emailStatus = $_GET['email'] ?? null;

$statusMap = [
  'success' => ['success', 'Donation Recorded', 'The donation has been saved.', 'club-logo'],
  'updated' => ['success', 'Donation Updated', 'Changes saved successfully.', 'club-logo'],
  'deleted' => ['success', 'Donation Deleted', 'The donation has been removed.', 'club-logo'],
  'email_sent' => ['success', 'Receipt Sent', 'Email sent to donor successfully.', 'club-logo'],
  'email_error' => ['warning', 'Email Failed', 'Receipt email could not be delivered.', 'club-logo'],
  'no_donations' => ['info', 'No Donations', 'No donations matched the selected filters.', 'club-logo'],
  'invalid_request' => ['danger', 'Donations', 'Invalid request. Please try again.', 'club-logo'],
  'csrf_error' => ['danger', 'Security', 'Security token mismatch. Please retry.', 'club-logo'],
];

if (!empty($status)) {
  if ($status === 'receipts_generated') {
    $count = (int)($_GET['count'] ?? 0);
    setToastMessage('success', 'Receipts Generated', $count . ' receipt(s) generated successfully.', 'club-logo');
  } elseif ($status === 'emails_sent') {
    $count = (int)($_GET['count'] ?? 0);
    setToastMessage('success', 'Emails Sent', $count . ' receipt email(s) sent.', 'club-logo');
  } elseif (isset($statusMap[$status])) {
    call_user_func_array('setToastMessage', $statusMap[$status]);
  }
}

if ($emailStatus === 'sent') {
  setToastMessage('success', 'Receipt Sent', 'Receipt emailed to donor.', 'club-logo');
} elseif ($emailStatus === 'failed') {
  setToastMessage('warning', 'Email Failed', 'Donation saved but the receipt email failed.', 'club-logo');
}

require_once __DIR__ . '/../../include/header.php';
?>

<div class="page-container" style="margin-top:0;padding-top:0;">
  <section class="hero hero-small mb-4">
    <div class="hero-body py-3">
      <div class="container-fluid">
        <div class="row align-items-center">
          <div class="col-md-2 d-none d-md-flex justify-content-center">
            <img src="https://w5obm.com/images/badges/club_logo.png" alt="W5OBM Logo" class="img-fluid no-shadow" style="max-height:64px;">
          </div>
          <div class="col-md-6 text-center text-md-start text-white">
            <h1 class="h4 mb-1">Donations Workspace</h1>
            <p class="mb-0 small">Track generosity, receipts, and compliance from a single view</p>
          </div>
          <div class="col-md-4 text-center text-md-end mt-3 mt-md-0">
            <?php if ($canAdd): ?>
              <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                <i class="fas fa-plus-circle me-1"></i>Record Donation
              </button>
            <?php endif; ?>
            <a href="/accounting/manual.php#donations" class="btn btn-outline-light btn-sm">
              <i class="fas fa-book me-1"></i>Documentation
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="row mb-3 hero-summary-row">
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="border rounded p-3 h-100 bg-light hero-summary-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Total Giving</span>
          <i class="fas fa-donate text-success"></i>
        </div>
        <h4 class="mb-0">$<?= number_format($summary['total_amount'] ?? 0, 2) ?></h4>
        <small class="text-muted">Across <?= number_format($summary['total_count'] ?? 0) ?> records</small>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="border rounded p-3 h-100 bg-light hero-summary-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Average Gift</span>
          <i class="fas fa-chart-line text-primary"></i>
        </div>
        <h4 class="mb-0">$<?= number_format($summary['average_amount'] ?? 0, 2) ?></h4>
        <small class="text-muted">Largest $<?= number_format($summary['largest_single'] ?? 0, 2) ?></small>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="border rounded p-3 h-100 bg-light hero-summary-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Receipt Coverage</span>
          <i class="fas fa-receipt text-info"></i>
        </div>
        <h4 class="mb-0">
          <?= number_format($summary['receipt_sent'] ?? 0) ?> sent
        </h4>
        <small class="text-muted"><?= number_format($summary['receipt_pending'] ?? 0) ?> pending</small>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="border rounded p-3 h-100 bg-light hero-summary-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="text-muted small">Unique Donors</span>
          <i class="fas fa-user-friends text-warning"></i>
        </div>
        <h4 class="mb-0"><?= number_format($summary['unique_donors'] ?? 0) ?></h4>
        <small class="text-muted">Latest <?= htmlspecialchars($summary['latest_date'] ?? '—') ?></small>
      </div>
    </div>
  </div>

  <?php if (function_exists('displayToastMessage')): ?>
    <?php displayToastMessage(); ?>
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-3 mb-4">
      <nav class="bg-light border rounded h-100 p-0 shadow-sm">
        <div class="px-3 py-2 border-bottom">
          <span class="text-muted text-uppercase small">Workspace</span>
        </div>
        <div class="list-group list-group-flush">
          <a href="/accounting/dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-chart-pie me-2 text-primary"></i>Dashboard</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <a href="/accounting/transactions/transactions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-exchange-alt me-2 text-success"></i>Transactions</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <a href="/accounting/reports_dashboard.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-chart-bar me-2 text-info"></i>Reports</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <a href="/accounting/ledger/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-book me-2 text-warning"></i>Chart of Accounts</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <a href="/accounting/categories/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-tags me-2 text-secondary"></i>Categories</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <a href="/accounting/vendors/" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span><i class="fas fa-store me-2 text-danger"></i>Vendors</span>
            <i class="fas fa-chevron-right small text-muted"></i>
          </a>
          <div class="list-group-item small text-muted text-uppercase">Other</div>
          <a href="/accounting/assets/" class="list-group-item list-group-item-action">
            <i class="fas fa-boxes me-2"></i>Assets
          </a>
          <a href="/accounting/donations/" class="list-group-item list-group-item-action active">
            <i class="fas fa-hand-holding-heart me-2"></i>Donations
          </a>
        </div>
      </nav>
    </div>
    <div class="col-lg-9 mb-4">
      <?php
      renderDonationWorkspace(
        $donations,
        $filters,
        $summary,
        $contacts,
        $canAdd,
        $canManage
      );
      ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../include/footer.php';
