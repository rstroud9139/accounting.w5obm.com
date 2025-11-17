 <!-- /accounting/reports/ytd_income_statement.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/report_controller.php';

    // Validate session
    validate_session();

    // Get parameters
    $year = $_GET['year'] ?? date('Y');

    // Generate date range
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";

    // Get monthly income and expense data
    $monthly_data = [];

    for ($month = 1; $month <= 12; $month++) {
        // Skip future months
        if ($year == date('Y') && $month > date('n')) {
            break;
        }

        $month_start = sprintf("%s-%02d-01", $year, $month);
        $month_end = date('Y-m-t', strtotime($month_start));

        $query = "SELECT 
                SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS expense
              FROM acc_transactions 
              WHERE transaction_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $month_start, $month_end);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $monthly_data[$month] = [
            'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
            'income' => $result['income'] ?? 0,
            'expense' => $result['expense'] ?? 0,
            'net' => ($result['income'] ?? 0) - ($result['expense'] ?? 0)
        ];
    }

    // Get income categories breakdown
    $income_categories_query = "SELECT 
                             c.id, c.name,
                             SUM(t.amount) AS total
                           FROM acc_transaction_categories c
                           JOIN acc_transactions t ON c.id = t.category_id
                           WHERE t.type = 'Income' AND t.transaction_date BETWEEN ? AND ?
                           GROUP BY c.id, c.name
                           ORDER BY total DESC";
    $stmt = $conn->prepare($income_categories_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $income_categories_result = $stmt->get_result();
    $income_categories = [];
    while ($row = $income_categories_result->fetch_assoc()) {
        $income_categories[] = $row;
    }

    // Get expense categories breakdown
    $expense_categories_query = "SELECT 
                              c.id, c.name,
                              SUM(t.amount) AS total
                            FROM acc_transaction_categories c
                            JOIN acc_transactions t ON c.id = t.category_id
                            WHERE t.type = 'Expense' AND t.transaction_date BETWEEN ? AND ?
                            GROUP BY c.id, c.name
                            ORDER BY total DESC";
    $stmt = $conn->prepare($expense_categories_query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $expense_categories_result = $stmt->get_result();
    $expense_categories = [];
    while ($row = $expense_categories_result->fetch_assoc()) {
        $expense_categories[] = $row;
    }

    // Calculate year totals
    $total_income = array_sum(array_column($monthly_data, 'income'));
    $total_expense = array_sum(array_column($monthly_data, 'expense'));
    $total_net = $total_income - $total_expense;

    // Standardized export handling for CSV/Excel via admin shared exporter
    if (isset($_GET['export'])) {
        $section = $_GET['section'] ?? 'monthly';
        $report_data = [];
        $meta_title = '';

        switch ($section) {
            case 'monthly':
                foreach ($monthly_data as $m) {
                    $report_data[] = [
                        'Month' => (string)$m['month_name'],
                        'Income' => number_format((float)$m['income'], 2, '.', ''),
                        'Expenses' => number_format((float)$m['expense'], 2, '.', ''),
                        'Net' => number_format((float)$m['net'], 2, '.', '')
                    ];
                }
                $meta_title = 'YTD Income Statement - Monthly Breakdown';
                break;
            case 'income_categories':
                foreach ($income_categories as $row) {
                    $report_data[] = [
                        'Category' => (string)$row['name'],
                        'Amount' => number_format((float)$row['total'], 2, '.', '')
                    ];
                }
                $meta_title = 'YTD Income Statement - Income by Category';
                break;
            case 'expense_categories':
                foreach ($expense_categories as $row) {
                    $report_data[] = [
                        'Category' => (string)$row['name'],
                        'Amount' => number_format((float)$row['total'], 2, '.', '')
                    ];
                }
                $meta_title = 'YTD Income Statement - Expenses by Category';
                break;
            case 'summary':
                $report_data[] = [
                    'Total Income' => number_format((float)$total_income, 2, '.', ''),
                    'Total Expenses' => number_format((float)$total_expense, 2, '.', ''),
                    'Net Income' => number_format((float)$total_net, 2, '.', '')
                ];
                $meta_title = 'YTD Income Statement - Annual Summary';
                break;
            default:
                foreach ($monthly_data as $m) {
                    $report_data[] = [
                        'Month' => (string)$m['month_name'],
                        'Income' => number_format((float)$m['income'], 2, '.', ''),
                        'Expenses' => number_format((float)$m['expense'], 2, '.', ''),
                        'Net' => number_format((float)$m['net'], 2, '.', '')
                    ];
                }
                $meta_title = 'YTD Income Statement - Monthly Breakdown';
                break;
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Year', (string)$year]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('ytd_income_statement_' . $section, $report_data, $report_meta);
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Year-to-Date Income Statement</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Year-to-Date Income Statement', 'Financial summary for ' . htmlspecialchars($year), ['Year' => $year]); ?>

         <!-- Year Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Year</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="ytd_income_statement.php" method="GET" class="row">
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
                         <a href="download.php?type=ytd_income_statement&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Summary Cards -->
         <div class="card shadow mb-4">
             <div class="card-header">
                 <h3>Annual Summary</h3>
             </div>
             <div class="card-body">
                 <div class="row">
                     <div class="col-md-4 mb-3">
                         <div class="card bg-success text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Total Income</h5>
                                 <p class="h3">$<?php echo number_format($total_income, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-4 mb-3">
                         <div class="card bg-danger text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Total Expenses</h5>
                                 <p class="h3">$<?php echo number_format($total_expense, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-4 mb-3">
                         <div class="card <?php echo $total_net >= 0 ? 'bg-primary' : 'bg-warning'; ?> text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Net Income</h5>
                                 <p class="h3">$<?php echo number_format($total_net, 2); ?></p>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Monthly Breakdown -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Monthly Breakdown</h3>
                 <div class="btn-group btn-group-sm">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=monthly"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=monthly"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <div class="table-responsive">
                     <table class="table table-striped">
                         <thead>
                             <tr>
                                 <th>Month</th>
                                 <th>Income</th>
                                 <th>Expenses</th>
                                 <th>Net Income</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($monthly_data as $month): ?>
                                 <tr>
                                     <td><?php echo $month['month_name']; ?></td>
                                     <td>$<?php echo number_format($month['income'], 2); ?></td>
                                     <td>$<?php echo number_format($month['expense'], 2); ?></td>
                                     <td class="<?php echo $month['net'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                         $<?php echo number_format($month['net'], 2); ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                         <tfoot>
                             <tr class="table-primary">
                                 <th>Total</th>
                                 <th>$<?php echo number_format($total_income, 2); ?></th>
                                 <th>$<?php echo number_format($total_expense, 2); ?></th>
                                 <th class="<?php echo $total_net >= 0 ? 'text-success' : 'text-danger'; ?>">
                                     $<?php echo number_format($total_net, 2); ?>
                                 </th>
                             </tr>
                         </tfoot>
                     </table>
                 </div>
             </div>
         </div>

         <!-- Income Breakdown -->
         <div class="row">
             <div class="col-md-6 mb-4">
                 <div class="card shadow h-100">
                     <div class="card-header d-flex justify-content-between align-items-center">
                         <h3 class="mb-0">Income by Category</h3>
                         <div class="btn-group btn-group-sm">
                             <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=income_categories"><i class="fas fa-file-csv me-1"></i>CSV</a>
                             <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=income_categories"><i class="fas fa-file-excel me-1"></i>Excel</a>
                         </div>
                     </div>
                     <div class="card-body">
                         <table class="table table-striped">
                             <thead>
                                 <tr>
                                     <th>Category</th>
                                     <th>Amount</th>
                                     <th>% of Total</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($income_categories as $category): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($category['name']); ?></td>
                                         <td>$<?php echo number_format($category['total'], 2); ?></td>
                                         <td>
                                             <?php
                                                $percentage = $total_income > 0 ? ($category['total'] / $total_income) * 100 : 0;
                                                echo number_format($percentage, 1) . '%';
                                                ?>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                             <tfoot>
                                 <tr class="table-success">
                                     <th>Total Income</th>
                                     <th>$<?php echo number_format($total_income, 2); ?></th>
                                     <th>100%</th>
                                 </tr>
                             </tfoot>
                         </table>
                     </div>
                 </div>
             </div>

             <div class="col-md-6 mb-4">
                 <div class="card shadow h-100">
                     <div class="card-header d-flex justify-content-between align-items-center">
                         <h3 class="mb-0">Expenses by Category</h3>
                         <div class="btn-group btn-group-sm">
                             <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=expense_categories"><i class="fas fa-file-csv me-1"></i>CSV</a>
                             <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=expense_categories"><i class="fas fa-file-excel me-1"></i>Excel</a>
                         </div>
                     </div>
                     <div class="card-body">
                         <table class="table table-striped">
                             <thead>
                                 <tr>
                                     <th>Category</th>
                                     <th>Amount</th>
                                     <th>% of Total</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($expense_categories as $category): ?>
                                     <tr>
                                         <td><?php echo htmlspecialchars($category['name']); ?></td>
                                         <td>$<?php echo number_format($category['total'], 2); ?></td>
                                         <td>
                                             <?php
                                                $percentage = $total_expense > 0 ? ($category['total'] / $total_expense) * 100 : 0;
                                                echo number_format($percentage, 1) . '%';
                                                ?>
                                         </td>
                                     </tr>
                                 <?php endforeach; ?>
                             </tbody>
                             <tfoot>
                                 <tr class="table-danger">
                                     <th>Total Expenses</th>
                                     <th>$<?php echo number_format($total_expense, 2); ?></th>
                                     <th>100%</th>
                                 </tr>
                             </tfoot>
                         </table>
                     </div>
                 </div>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>