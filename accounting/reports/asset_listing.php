  <!-- /accounting/reports/asset_listing.php -->
  <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/assetController.php';

    // Validate session
    validate_session();

    // Get current date for depreciation calculation
    $current_date = date('Y-m-d');

    // Fetch all assets
    $assets = fetch_all_assets();

    // Calculate current values with depreciation
    $total_original_value = 0;
    $total_current_value = 0;
    $total_depreciation = 0;

    foreach ($assets as &$asset) {
        $total_original_value += $asset['value'];

        // Calculate current value after depreciation
        $current_value = calculate_current_value($asset);
        $asset['current_value'] = $current_value;
        $asset['depreciation'] = $asset['value'] - $current_value;

        $total_current_value += $current_value;
        $total_depreciation += $asset['depreciation'];
    }
    ?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
      <title>Asset Listing</title>
      <?php include '../../include/header.php'; ?>
  </head>

  <body>
      <?php include '../../include/menu.php'; ?>
      <?php include '../../include/report_header.php'; ?>

      <div class="container mt-5">
          <!-- Standard Report Header -->
          <?php renderReportHeader('Asset Listing', 'Complete list with current values as of ' . date('F j, Y')); ?>

          <!-- Asset Summary -->
          <div class="card shadow mb-4">
              <div class="card-header">
                  <h3>Asset Summary</h3>
              </div>
              <div class="card-body">
                  <div class="row">
                      <div class="col-md-4 mb-3">
                          <div class="card bg-primary text-white">
                              <div class="card-body text-center">
                                  <h5 class="card-title">Original Value</h5>
                                  <p class="h3">$<?php echo number_format($total_original_value, 2); ?></p>
                              </div>
                          </div>
                      </div>
                      <div class="col-md-4 mb-3">
                          <div class="card bg-danger text-white">
                              <div class="card-body text-center">
                                  <h5 class="card-title">Total Depreciation</h5>
                                  <p class="h3">$<?php echo number_format($total_depreciation, 2); ?></p>
                              </div>
                          </div>
                      </div>
                      <div class="col-md-4 mb-3">
                          <div class="card bg-success text-white">
                              <div class="card-body text-center">
                                  <h5 class="card-title">Current Value</h5>
                                  <p class="h3">$<?php echo number_format($total_current_value, 2); ?></p>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Asset Listing -->
          <div class="card shadow">
              <div class="card-header d-flex justify-content-between align-items-center">
                  <h3 class="mb-0">Asset List</h3>
                  <div class="d-flex gap-2">
                      <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                      <a href="../assets/add.php" class="btn btn-success no-print">Add New Asset</a>
                      <a href="download.php?type=asset_listing" class="btn btn-primary no-print">Download PDF</a>
                  </div>
              </div>
              <div class="card-body">
                  <table class="table table-striped" id="assetTable">
                      <thead>
                          <tr>
                              <th>Asset Name</th>
                              <th>Acquisition Date</th>
                              <th>Original Value</th>
                              <th>Depreciation Rate</th>
                              <th>Current Value</th>
                              <th>Total Depreciation</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($assets as $asset): ?>
                              <tr>
                                  <td>
                                      <?php echo htmlspecialchars($asset['name']); ?>
                                      <?php if (!empty($asset['description'])): ?>
                                          <small class="d-block text-muted"><?php echo htmlspecialchars($asset['description']); ?></small>
                                      <?php endif; ?>
                                  </td>
                                  <td><?php echo date('m/d/Y', strtotime($asset['acquisition_date'])); ?></td>
                                  <td>$<?php echo number_format($asset['value'], 2); ?></td>
                                  <td><?php echo number_format($asset['depreciation_rate'], 1); ?>%</td>
                                  <td>$<?php echo number_format($asset['current_value'], 2); ?></td>
                                  <td>$<?php echo number_format($asset['depreciation'], 2); ?></td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                          <tr class="table-primary">
                              <th colspan="2">Total</th>
                              <th>$<?php echo number_format($total_original_value, 2); ?></th>
                              <th></th>
                              <th>$<?php echo number_format($total_current_value, 2); ?></th>
                              <th>$<?php echo number_format($total_depreciation, 2); ?></th>
                          </tr>
                      </tfoot>
                  </table>
              </div>
          </div>
      </div>

      <?php include '../../include/footer.php'; ?>
      <script>
          $(document).ready(function() {
              $('#assetTable').DataTable({
                  order: [
                      [1, 'desc']
                  ], // Sort by acquisition date descending
                  pageLength: 25
              });
          });
      </script>
  </body>

  </html>