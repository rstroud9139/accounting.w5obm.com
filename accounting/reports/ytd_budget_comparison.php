 <!-- /accounting/reports/ytd_budget_comparison.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/report_controller.php';

    // Validate session
    validate_session();

    // Get parameters
    $year = $_GET['year'] ?? date('Y');

    // Check if budgets table exists
    $budget_exists = false;
    $result = $conn->query("SHOW TABLES LIKE 'acc_budgets'");
    if ($result->num_rows > 0) {
        $budget_exists = true;
    }

    // If budget table exists, fetch budget data
    $budget_data = [];
    if ($budget_exists) {
        $query = "SELECT 
                c.id AS category_id,
                c.name AS category_name,
                c.type AS category_type,
                SUM(b.amount) AS budget_amount
              FROM acc_transaction_categories c
              LEFT JOIN acc_budgets b ON c.id = b.category_id AND b.year = ?
              GROUP BY c.id, c.name, c.type
              ORDER BY c.type, c.name";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $budget_data[$row['category_id']] = $row;
        }
    }

    // Fetch actual spending data
    $actual_data = [];
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";

    $query = "SELECT 
            c.id AS category_id,
            c.name AS category_name,
            c.type AS category_type,
            SUM(t.amount) AS actual_amount
          FROM acc_transaction_categories c
          LEFT JOIN acc_transactions t ON c.id = t.category_id AND t.transaction_date BETWEEN ? AND ?
          GROUP BY c.id, c.name, c.type
          ORDER BY c.type, c.name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $actual_data[$row['category_id']] = $row;
    }

    // Merge budget and actual data
    $categories = [];
    $total_budget_income = 0;
    $total_budget_expense = 0;
    $total_actual_income = 0;
    $total_actual_expense = 0;

    // First, combine data from both arrays
    foreach (array_merge(array_keys($budget_data), array_keys($actual_data)) as $category_id) {
        if (!isset($categories[$category_id])) {
            $categories[$category_id] = [
                'id' => $category_id,
                'name' => $budget_data[$category_id]['category_name'] ?? $actual_data[$category_id]['category_name'],
                'type' => $budget_data[$category_id]['category_type'] ?? $actual_data[$category_id]['category_type'],
                'budget' => $budget_data[$category_id]['budget_amount'] ?? 0,
                'actual' => $actual_data[$category_id]['actual_amount'] ?? 0
            ];

            // Calculate variance
            $categories[$category_id]['variance'] = $categories[$category_id]['actual'] - $categories[$category_id]['budget'];

            // Calculate percentage if budget is not zero
            if ($categories[$category_id]['budget'] > 0) {
                $categories[$category_id]['percentage'] = ($categories[$category_id]['actual'] / $categories[$category_id]['budget']) * 100;
            } else {
                $categories[$category_id]['percentage'] = 0;
            }

            // Add to totals
            if ($categories[$category_id]['type'] == 'Income') {
                $total_budget_income += $categories[$category_id]['budget'];
                $total_actual_income += $categories[$category_id]['actual'];
            } else {
                $total_budget_expense += $categories[$category_id]['budget'];
                $total_actual_expense += $categories[$category_id]['actual'];
            }
        }
    }

    // Separate income and expense categories
    $income_categories = array_filter($categories, function ($cat) {
        return $cat['type'] == 'Income';
    });

    $expense_categories = array_filter($categories, function ($cat) {
        return $cat['type'] == 'Expense';
    });

    // Calculate net income
    $budget_net_income = $total_budget_income - $total_budget_expense;
    $actual_net_income = $total_actual_income - $total_actual_expense;
    $net_variance = $actual_net_income - $budget_net_income;
    $net_percentage = $budget_net_income != 0 ? ($actual_net_income / $budget_net_income) * 100 : 0;

    // Standardized export handling for CSV/Excel via admin shared exporter
    if (isset($_GET['export'])) {
        $section = $_GET['section'] ?? 'summary';
        $rows = [];
        $meta_title = '';

        switch ($section) {
            case 'income_categories':
                foreach ($income_categories as $cat) {
                    $rows[] = [
                        'Category' => (string)$cat['name'],
                        'Budget' => number_format((float)$cat['budget'], 2, '.', ''),
                        'Actual' => number_format((float)$cat['actual'], 2, '.', ''),
                        'Variance' => number_format((float)$cat['variance'], 2, '.', ''),
                        '% of Budget' => number_format((float)$cat['percentage'], 1, '.', '')
                    ];
                }
                $meta_title = 'YTD Budget Comparison - Income by Category';
                break;
            case 'expense_categories':
                foreach ($expense_categories as $cat) {
                    $rows[] = [
                        'Category' => (string)$cat['name'],
                        'Budget' => number_format((float)$cat['budget'], 2, '.', ''),
                        'Actual' => number_format((float)$cat['actual'], 2, '.', ''),
                        'Variance' => number_format((float)$cat['variance'], 2, '.', ''),
                        '% of Budget' => number_format((float)$cat['percentage'], 1, '.', '')
                    ];
                }
                $meta_title = 'YTD Budget Comparison - Expenses by Category';
                break;
            case 'summary':
            default:
                $rows[] = [
                    'Budget Income' => number_format((float)$total_budget_income, 2, '.', ''),
                    'Actual Income' => number_format((float)$total_actual_income, 2, '.', ''),
                    'Income Variance' => number_format((float)($total_actual_income - $total_budget_income), 2, '.', ''),
                    'Budget Expenses' => number_format((float)$total_budget_expense, 2, '.', ''),
                    'Actual Expenses' => number_format((float)$total_actual_expense, 2, '.', ''),
                    'Expense Variance' => number_format((float)($total_actual_expense - $total_budget_expense), 2, '.', ''),
                    'Budget Net Income' => number_format((float)$budget_net_income, 2, '.', ''),
                    'Actual Net Income' => number_format((float)$actual_net_income, 2, '.', ''),
                    'Net Variance' => number_format((float)$net_variance, 2, '.', ''),
                    'Net % of Budget' => number_format((float)$net_percentage, 1, '.', '')
                ];
                $meta_title = 'YTD Budget Comparison - Summary';
                break;
        }

        $report_meta = [
            ['Report', $meta_title],
            ['Generated', date('Y-m-d H:i:s')],
            ['Year', (string)$year]
        ];

        require_once __DIR__ . '/../lib/export_bridge.php';
        accounting_export('ytd_budget_comparison_' . $section, $rows, $report_meta);
    }
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Year-to-Date Budget Comparison</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>

     <div class="container mt-5">
         <!-- Header -->
         <div class="d-flex align-items-center mb-4">
             <?php $logoSrc = accounting_logo_src_for(__DIR__); ?>
             <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Club Logo" class="img-card-175">
             <div>
                 <h2>Year-to-Date Budget Comparison</h2>
                 <p>Budget vs. Actual for <?php echo $year; ?></p>
             </div>
         </div>

         <?php if (!$budget_exists): ?>
             <div class="alert alert-warning">
                 <strong>Note:</strong> The budget table doesn't exist in the database. This report is showing only actual transaction data.
             </div>
         <?php endif; ?>

         <!-- Year Selector -->
         <div class="card shadow mb-4">
             <div class="card-header">
                 <h3>Select Year</h3>
             </div>
             <div class="card-body">
                 <form action="ytd_budget_comparison.php" method="GET" class="row">
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
                         <button type="submit" class="btn btn-primary">Update Report</button>
                         <a href="download.php?type=ytd_budget_comparison&year=<?php echo $year; ?>" class="btn btn-secondary ms-2">Download PDF</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Summary -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Summary</h3>
                 <div class="btn-group btn-group-sm no-print">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=summary"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=summary"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <div class="row">
                     <div class="col-md-3 mb-3">
                         <div class="card bg-success text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Income</h5>
                                 <p class="card-text">Budget: $<?php echo number_format($total_budget_income, 2); ?></p>
                                 <p class="card-text">Actual: $<?php echo number_format($total_actual_income, 2); ?></p>
                                 <p class="card-text">
                                     <?php
                                        $income_variance = $total_actual_income - $total_budget_income;
                                        echo $income_variance >= 0 ? '+' : '';
                                        echo '$' . number_format($income_variance, 2);
                                        ?>
                                 </p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-3 mb-3">
                         <div class="card bg-danger text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Expenses</h5>
                                 <p class="card-text">Budget: $<?php echo number_format($total_budget_expense, 2); ?></p>
                                 <p class="card-text">Actual: $<?php echo number_format($total_actual_expense, 2); ?></p>
                                 <p class="card-text">
                                     <?php
                                        $expense_variance = $total_actual_expense - $total_budget_expense;
                                        echo $expense_variance >= 0 ? '+' : '';
                                        echo '$' . number_format($expense_variance, 2);
                                        ?>
                                 </p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-6 mb-3">
                         <div class="card bg-primary text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Net Income</h5>
                                 <div class="row">
                                     <div class="col-6">
                                         <p class="card-text">Budget: $<?php echo number_format($budget_net_income, 2); ?></p>
                                     </div>
                                     <div class="col-6">
                                         <p class="card-text">Actual: $<?php echo number_format($actual_net_income, 2); ?></p>
                                     </div>
                                 </div>
                                 <div class="row">
                                     <div class="col-6">
                                         <p class="card-text">
                                             Variance:
                                             <?php
                                                echo $net_variance >= 0 ? '+' : '';
                                                echo '$' . number_format($net_variance, 2);
                                                ?>
                                         </p>
                                     </div>
                                     <div class="col-6">
                                         <p class="card-text">
                                             Percentage:
                                             <?php echo number_format($net_percentage, 1); ?>%
                                         </p>
                                     </div>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Income Categories -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Income</h3>
                 <div class="btn-group btn-group-sm no-print">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=income_categories"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=income_categories"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <table class="table table-striped">
                     <thead>
                         <tr>
                             <th>Category</th>
                             <th>Budget</th>
                             <th>Actual</th>
                             <th>Variance</th>
                             <th>% of Budget</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($income_categories as $category): ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($category['name']); ?></td>
                                 <td>$<?php echo number_format($category['budget'], 2); ?></td>
                                 <td>$<?php echo number_format($category['actual'], 2); ?></td>
                                 <td class="<?php echo $category['variance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                     <?php
                                        echo $category['variance'] >= 0 ? '+' : '';
                                        echo '$' . number_format($category['variance'], 2);
                                        ?>
                                 </td>
                                 <td><?php echo number_format($category['percentage'], 1); ?>%</td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                     <tfoot>
                         <tr class="table-primary">
                             <th>Total</th>
                             <th>$<?php echo number_format($total_budget_income, 2); ?></th>
                             <th>$<?php echo number_format($total_actual_income, 2); ?></th>
                             <th class="<?php echo $income_variance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                 <?php
                                    echo $income_variance >= 0 ? '+' : '';
                                    echo '$' . number_format($income_variance, 2);
                                    ?>
                             </th>
                             <th>
                                 <?php
                                    $income_percentage = $total_budget_income > 0 ? ($total_actual_income / $total_budget_income) * 100 : 0;
                                    echo number_format($income_percentage, 1);
                                    ?>%
                             </th>
                         </tr>
                     </tfoot>
                 </table>
             </div>
         </div>

         <!-- Expense Categories -->
         <div class="card shadow">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Expenses</h3>
                 <div class="btn-group btn-group-sm no-print">
                     <a class="btn btn-outline-success" href="?year=<?php echo urlencode($year); ?>&export=csv&section=expense_categories"><i class="fas fa-file-csv me-1"></i>CSV</a>
                     <a class="btn btn-outline-primary" href="?year=<?php echo urlencode($year); ?>&export=excel&section=expense_categories"><i class="fas fa-file-excel me-1"></i>Excel</a>
                 </div>
             </div>
             <div class="card-body">
                 <table class="table table-striped">
                     <thead>
                         <tr>
                             <th>Category</th>
                             <th>Budget</th>
                             <th>Actual</th>
                             <th>Variance</th>
                             <th>% of Budget</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($expense_categories as $category): ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($category['name']); ?></td>
                                 <td>$<?php echo number_format($category['budget'], 2); ?></td>
                                 <td>$<?php echo number_format($category['actual'], 2); ?></td>
                                 <td class="<?php echo $category['variance'] <= 0 ? 'text-success' : 'text-danger'; ?>">
                                     <?php
                                        echo $category['variance'] >= 0 ? '+' : '';
                                        echo '$' . number_format($category['variance'], 2);
                                        ?>
                                 </td>
                                 <td><?php echo number_format($category['percentage'], 1); ?>%</td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                     <tfoot>
                         <tr class="table-primary">
                             <th>Total</th>
                             <th>$<?php echo number_format($total_budget_expense, 2); ?></th>
                             <th>$<?php echo number_format($total_actual_expense, 2); ?></th>
                             <th class="<?php echo $expense_variance <= 0 ? 'text-success' : 'text-danger'; ?>">
                                 <?php
                                    echo $expense_variance >= 0 ? '+' : '';
                                    echo '$' . number_format($expense_variance, 2);
                                    ?>
                             </th>
                             <th>
                                 <?php
                                    $expense_percentage = $total_budget_expense > 0 ? ($total_actual_expense / $total_budget_expense) * 100 : 0;
                                    echo number_format($expense_percentage, 1);
                                    ?>%
                             </th>
                         </tr>
                     </tfoot>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>