 <!-- /accounting/reports/ytd_income_statement_monthly.php -->
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

    // Get category data for income and expenses by month
    $query = "SELECT 
            c.id, 
            c.name, 
            c.type,
            MONTH(t.transaction_date) AS month,
            SUM(t.amount) AS amount
          FROM acc_transaction_categories c
          LEFT JOIN acc_transactions t ON c.id = t.category_id AND YEAR(t.transaction_date) = ? 
          WHERE c.type IN ('Income', 'Expense') 
          GROUP BY c.id, c.name, c.type, MONTH(t.transaction_date)
          ORDER BY c.type, c.name, MONTH(t.transaction_date)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    // Initialize arrays for income and expense categories
    $income_categories = [];
    $expense_categories = [];

    // Initialize monthly totals
    $monthly_income = array_fill(1, 12, 0);
    $monthly_expense = array_fill(1, 12, 0);
    $monthly_net = array_fill(1, 12, 0);

    // Process results
    while ($row = $result->fetch_assoc()) {
        $category_id = $row['id'];
        $month = $row['month'];
        $amount = $row['amount'] ?? 0;

        // Skip null months (when no transactions for a category in a month)
        if ($month === null) continue;

        if ($row['type'] === 'Income') {
            // Initialize category if not exists
            if (!isset($income_categories[$category_id])) {
                $income_categories[$category_id] = [
                    'name' => $row['name'],
                    'months' => array_fill(1, 12, 0),
                    'total' => 0
                ];
            }

            // Add amount to the specific month
            $income_categories[$category_id]['months'][$month] = $amount;
            $income_categories[$category_id]['total'] += $amount;

            // Add to monthly totals
            $monthly_income[$month] += $amount;
        } else {
            // Initialize category if not exists
            if (!isset($expense_categories[$category_id])) {
                $expense_categories[$category_id] = [
                    'name' => $row['name'],
                    'months' => array_fill(1, 12, 0),
                    'total' => 0
                ];
            }

            // Add amount to the specific month
            $expense_categories[$category_id]['months'][$month] = $amount;
            $expense_categories[$category_id]['total'] += $amount;

            // Add to monthly totals
            $monthly_expense[$month] += $amount;
        }
    }

    // Calculate monthly net income
    for ($i = 1; $i <= 12; $i++) {
        $monthly_net[$i] = $monthly_income[$i] - $monthly_expense[$i];
    }

    // Calculate year totals
    $total_income = array_sum($monthly_income);
    $total_expense = array_sum($monthly_expense);
    $total_net = $total_income - $total_expense;

    // Sort categories by total amount (descending)
    usort($income_categories, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    usort($expense_categories, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Monthly Income Statement</title>
     <?php include '../../include/header.php'; ?>
     <style>
         /* Responsive table for many columns */
         .table-responsive {
             overflow-x: auto;
         }

         .month-column {
             min-width: 100px;
         }

         .category-column {
             min-width: 200px;
         }

         .negative-value {
             color: #dc3545;
         }

         .positive-value {
             color: #28a745;
         }
     </style>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <div class="container-fluid mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader('Monthly Income Statement', 'Monthly breakdown for ' . htmlspecialchars($year), ['Year' => $year]); ?>

         <!-- Year Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Year</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="ytd_income_statement_monthly.php" method="GET" class="row">
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
                         <a href="download.php?type=ytd_income_statement_monthly&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
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

         <!-- Income Statement -->
         <div class="card shadow mb-4">
             <div class="card-header">
                 <h3>Income Statement by Month</h3>
             </div>
             <div class="card-body">
                 <div class="table-responsive">
                     <table class="table table-bordered table-striped">
                         <thead class="thead-dark">
                             <tr>
                                 <th class="category-column">Category</th>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <th class="month-column text-center"><?php echo date('M', mktime(0, 0, 0, $i, 1)); ?></th>
                                 <?php endfor; ?>
                                 <th class="month-column text-center">YTD Total</th>
                             </tr>
                         </thead>
                         <tbody>
                             <!-- Income Categories -->
                             <tr class="table-primary">
                                 <th colspan="14">Income</th>
                             </tr>
                             <?php foreach ($income_categories as $category): ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($category['name']); ?></td>
                                     <?php for ($i = 1; $i <= 12; $i++): ?>
                                         <td class="text-end">
                                             <?php if ($category['months'][$i] > 0): ?>
                                                 $<?php echo number_format($category['months'][$i], 2); ?>
                                             <?php endif; ?>
                                         </td>
                                     <?php endfor; ?>
                                     <td class="text-end fw-bold">$<?php echo number_format($category['total'], 2); ?></td>
                                 </tr>
                             <?php endforeach; ?>

                             <!-- Income Totals -->
                             <tr class="table-success fw-bold">
                                 <td>Total Income</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($monthly_income[$i] > 0): ?>
                                             $<?php echo number_format($monthly_income[$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end">$<?php echo number_format($total_income, 2); ?></td>
                             </tr>

                             <!-- Expense Categories -->
                             <tr class="table-danger">
                                 <th colspan="14">Expenses</th>
                             </tr>
                             <?php foreach ($expense_categories as $category): ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($category['name']); ?></td>
                                     <?php for ($i = 1; $i <= 12; $i++): ?>
                                         <td class="text-end">
                                             <?php if ($category['months'][$i] > 0): ?>
                                                 $<?php echo number_format($category['months'][$i], 2); ?>
                                             <?php endif; ?>
                                         </td>
                                     <?php endfor; ?>
                                     <td class="text-end fw-bold">$<?php echo number_format($category['total'], 2); ?></td>
                                 </tr>
                             <?php endforeach; ?>

                             <!-- Expense Totals -->
                             <tr class="table-danger fw-bold">
                                 <td>Total Expenses</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($monthly_expense[$i] > 0): ?>
                                             $<?php echo number_format($monthly_expense[$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end">$<?php echo number_format($total_expense, 2); ?></td>
                             </tr>

                             <!-- Net Income -->
                             <tr class="fw-bold">
                                 <td>Net Income</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $monthly_net[$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($monthly_income[$i] > 0 || $monthly_expense[$i] > 0): ?>
                                             $<?php echo number_format($monthly_net[$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $total_net >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($total_net, 2); ?>
                                 </td>
                             </tr>
                         </tbody>
                     </table>
                 </div>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>