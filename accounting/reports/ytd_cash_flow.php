 <!-- /accounting/reports/ytd_cash_flow.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/reportController.php';

    // Validate session
    validate_session();

    // Get parameters
    $year = $_GET['year'] ?? date('Y');

    // Generate date range
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";

    // Calculate beginning balance (as of start of year)
    $beginning_query = "SELECT 
                      SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                      SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
                    FROM acc_transactions 
                    WHERE transaction_date < ?";
    $stmt = $conn->prepare($beginning_query);
    $stmt->bind_param('s', $start_date);
    $stmt->execute();
    $beginning_balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;

    // Get monthly data
    $months = [];
    $cumulative_balance = $beginning_balance;

    for ($month = 1; $month <= 12; $month++) {
        // Skip future months
        if ($year == date('Y') && $month > date('n')) {
            break;
        }

        $month_start = sprintf("%s-%02d-01", $year, $month);
        $month_end = date('Y-m-t', strtotime($month_start));

        // Get month's transactions
        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense
              FROM acc_transactions 
              WHERE transaction_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $month_start, $month_end);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $income = $result['income'] ?? 0;
        $expense = $result['expense'] ?? 0;
        $net = $income - $expense;

        $cumulative_balance += $net;

        $months[] = [
            'month' => date('F', mktime(0, 0, 0, $month, 1)),
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'balance' => $cumulative_balance
        ];
    }

    // Calculate year totals
    $total_income = array_sum(array_column($months, 'income'));
    $total_expense = array_sum(array_column($months, 'expense'));
    $total_net = $total_income - $total_expense;

    // Standardized export handling for CSV/Excel via admin shared exporter
    if (isset($_GET['export'])) {
        $section = $_GET['section'] ?? 'monthly';
        $export_rows = [];
        $meta_title = '';

        if ($section === 'monthly') {
            foreach ($months as $m) {
                $export_rows[] = [
                    'Month' => (string)$m['month'],
                    'Income' => number_format((float)$m['income'], 2, '.', ''),
                    'Expenses' => number_format((float)$m['expense'], 2, '.', ''),
                    'Net' => number_format((float)$m['net'], 2, '.', ''),
                    'Running Balance' => number_format((float)$m['balance'], 2, '.', '')
                ];
            }
            $meta_title = 'YTD Cash Flow - Monthly Breakdown';
        } else {
            $export_rows[] = [
                'Beginning Balance' => number_format((float)$beginning_balance, 2, '.', ''),
                'Total Income' => number_format((float)$total_income, 2, '.', ''),
                'Total Expenses' => number_format((float)$total_expense, 2, '.', ''),
                'Ending Balance' => number_format((float)($beginning_balance + $total_net), 2, '.', '')
            ];
            $meta_title = 'YTD Cash Flow - Annual Summary';
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Year', (string)$year]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('ytd_cash_flow_' . ($section === 'monthly' ? 'monthly' : 'summary'), $export_rows, $report_meta);
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Year-to-Date Cash Flow</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Year-to-Date Cash Flow', 'Monthly cash flow for ' . $year); ?>

         <!-- Year Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Year</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="ytd_cash_flow.php" method="GET" class="row">
                     <div class="col-md-6 mb-3">
                         <label for="year" class="form-label">Year</label>
                         <select id="year" name="year" class="form-control">
                             <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                 <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                     <?php echo $y; ?>
                                 </option>
                             <?php endfor; ?>
                         </select>
                     </div>
                     <div class="col-md-6 mb-3 d-flex align-items-end">
                         <button type="submit" class="btn btn-primary">Generate Report</button>
                         <a href="download.php?type=ytd_cash_flow&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Summary Cards -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Annual Summary</h3>
                 <div class="btn-group btn-group-sm no-print">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <div class="row">
                     <div class="col-md-3 mb-3">
                         <div class="card bg-primary text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Beginning Balance</h5>
                                 <p class="h3">$<?php echo number_format($beginning_balance, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-3 mb-3">
                         <div class="card bg-success text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Total Income</h5>
                                 <p class="h3">$<?php echo number_format($total_income, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-3 mb-3">
                         <div class="card bg-danger text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Total Expenses</h5>
                                 <p class="h3">$<?php echo number_format($total_expense, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-3 mb-3">
                         <div class="card bg-info text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Ending Balance</h5>
                                 <p class="h3">$<?php echo number_format($beginning_balance + $total_net, 2); ?></p>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Monthly Breakdown -->
         <div class="card shadow">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Monthly Cash Flow</h3>
                 <div class="btn-group btn-group-sm no-print">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=monthly"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=monthly"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <table class="table table-striped">
                     <thead>
                         <tr>
                             <th>Month</th>
                             <th>Income</th>
                             <th>Expenses</th>
                             <th>Net</th>
                             <th>Running Balance</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($months as $month): ?>
                             <tr>
                                 <td><?php echo $month['month']; ?></td>
                                 <td>$<?php echo number_format($month['income'], 2); ?></td>
                                 <td>$<?php echo number_format($month['expense'], 2); ?></td>
                                 <td class="<?php echo $month['net'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                     $<?php echo number_format($month['net'], 2); ?>
                                 </td>
                                 <td>$<?php echo number_format($month['balance'], 2); ?></td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                     <tfoot>
                         <tr class="table-primary">
                             <th>Year-to-Date Total</th>
                             <th>$<?php echo number_format($total_income, 2); ?></th>
                             <th>$<?php echo number_format($total_expense, 2); ?></th>
                             <th class="<?php echo $total_net >= 0 ? 'text-success' : 'text-danger'; ?>">
                                 $<?php echo number_format($total_net, 2); ?>
                             </th>
                             <th>$<?php echo number_format($beginning_balance + $total_net, 2); ?></th>
                         </tr>
                     </tfoot>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>