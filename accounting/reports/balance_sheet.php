 <!-- /accounting/reports/balance_sheet.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/report_controller.php';

    // Validate session
    validate_session();

    // Get report date
    $date = $_GET['date'] ?? date('Y-m-d');

    // Generate balance sheet data
    $balance_sheet = generate_balance_sheet($date);

    // Format date for display
    $display_date = date('F j, Y', strtotime($date));

    // Standardized export handling for CSV/Excel via admin shared exporter
    if (isset($_GET['export'])) {
        $section = $_GET['section'] ?? 'summary';
        $rows = [];
        $meta_title = '';

        switch ($section) {
            case 'assets':
                $rows = [
                    ['Label' => 'Cash and Equivalents', 'Amount' => number_format((float)($balance_sheet['assets']['cash'] ?? 0), 2, '.', '')],
                    ['Label' => 'Physical Assets', 'Amount' => number_format((float)($balance_sheet['assets']['physical_assets'] ?? 0), 2, '.', '')],
                    ['Label' => 'Total Assets', 'Amount' => number_format((float)($balance_sheet['assets']['total'] ?? 0), 2, '.', '')],
                ];
                $meta_title = 'Balance Sheet - Assets';
                break;
            case 'liabilities':
                $rows = [
                    ['Label' => 'Current Liabilities', 'Amount' => number_format((float)($balance_sheet['liabilities'] ?? 0), 2, '.', '')],
                    ['Label' => 'Total Liabilities', 'Amount' => number_format((float)($balance_sheet['liabilities'] ?? 0), 2, '.', '')],
                ];
                $meta_title = 'Balance Sheet - Liabilities';
                break;
            case 'equity':
                $rows = [
                    ['Label' => 'Net Assets', 'Amount' => number_format((float)($balance_sheet['equity'] ?? 0), 2, '.', '')],
                    ['Label' => 'Total Equity', 'Amount' => number_format((float)($balance_sheet['equity'] ?? 0), 2, '.', '')],
                ];
                $meta_title = 'Balance Sheet - Equity';
                break;
            case 'summary':
            default:
                $rows = [
                    ['Label' => 'Total Assets', 'Amount' => number_format((float)($balance_sheet['assets']['total'] ?? 0), 2, '.', '')],
                    ['Label' => 'Total Liabilities', 'Amount' => number_format((float)($balance_sheet['liabilities'] ?? 0), 2, '.', '')],
                    ['Label' => 'Total Equity', 'Amount' => number_format((float)($balance_sheet['equity'] ?? 0), 2, '.', '')],
                    ['Label' => 'Liabilities + Equity', 'Amount' => number_format((float)(($balance_sheet['liabilities'] ?? 0) + ($balance_sheet['equity'] ?? 0)), 2, '.', '')],
                ];
                $meta_title = 'Balance Sheet - Summary';
                break;
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['As Of', (string)$display_date]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('balance_sheet_' . $section, $rows, $report_meta);
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Balance Sheet</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Balance Sheet', 'As of ' . $display_date); ?>

         <!-- Date Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Date</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="balance_sheet.php" method="GET" class="row">
                     <div class="col-md-6 mb-3">
                         <label for="date" class="form-label">As of Date</label>
                         <input type="date" id="date" name="date" class="form-control" value="<?php echo $date; ?>">
                     </div>
                     <div class="col-md-6 mb-3 d-flex align-items-end">
                         <button type="submit" class="btn btn-primary">Generate Report</button>
                         <a href="download.php?type=balance_sheet&date=<?php echo $date; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Balance Sheet -->
         <div class="card shadow">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Balance Sheet - <?php echo $display_date; ?></h3>
                 <div class="btn-toolbar gap-2 no-print">
                     <div class="btn-group btn-group-sm me-2">
                         <a class="btn btn-outline-secondary" href="?date=<?php echo urlencode($date); ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>Summary CSV</a>
                         <a class="btn btn-outline-primary" href="?date=<?php echo urlencode($date); ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Summary Excel</a>
                     </div>
                     <div class="btn-group btn-group-sm me-2">
                         <a class="btn btn-outline-success" href="?date=<?php echo urlencode($date); ?>&export=csv&section=assets"><i class="fas fa-file-csv me-1"></i>Assets CSV</a>
                         <a class="btn btn-outline-success" href="?date=<?php echo urlencode($date); ?>&export=excel&section=assets"><i class="fas fa-file-excel me-1"></i>Assets Excel</a>
                     </div>
                     <div class="btn-group btn-group-sm me-2">
                         <a class="btn btn-outline-warning" href="?date=<?php echo urlencode($date); ?>&export=csv&section=liabilities"><i class="fas fa-file-csv me-1"></i>Liabilities CSV</a>
                         <a class="btn btn-outline-warning" href="?date=<?php echo urlencode($date); ?>&export=excel&section=liabilities"><i class="fas fa-file-excel me-1"></i>Liabilities Excel</a>
                     </div>
                     <div class="btn-group btn-group-sm">
                         <a class="btn btn-outline-info" href="?date=<?php echo urlencode($date); ?>&export=csv&section=equity"><i class="fas fa-file-csv me-1"></i>Equity CSV</a>
                         <a class="btn btn-outline-info" href="?date=<?php echo urlencode($date); ?>&export=excel&section=equity"><i class="fas fa-file-excel me-1"></i>Equity Excel</a>
                     </div>
                 </div>
             </div>
             <div class="card-body">
                 <!-- Assets Section -->
                 <h4>Assets</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Cash and Equivalents</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['assets']['cash'], 2); ?></td>
                     </tr>
                     <tr>
                         <td>Physical Assets</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['assets']['physical_assets'], 2); ?></td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Total Assets</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['assets']['total'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Liabilities Section -->
                 <h4>Liabilities</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Current Liabilities</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['liabilities'], 2); ?></td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Total Liabilities</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['liabilities'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Equity Section -->
                 <h4>Equity</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Net Assets</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['equity'], 2); ?></td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Total Equity</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['equity'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Total Liabilities and Equity -->
                 <table class="table table-primary">
                     <tr class="fw-bold">
                         <td>Total Liabilities and Equity</td>
                         <td class="text-end">$<?php echo number_format($balance_sheet['liabilities'] + $balance_sheet['equity'], 2); ?></td>
                     </tr>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>