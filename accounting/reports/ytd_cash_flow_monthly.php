 <!-- /accounting/reports/ytd_cash_flow_monthly.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../controllers/report_controller.php';

    // Validate session
    validate_session();

    // Get parameters
    $year = $_GET['year'] ?? date('Y');

    // Calculate beginning balance (as of start of year)
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";

    $beginning_query = "SELECT 
                      SUM(CASE WHEN type = 'Income' THEN amount ELSE 0 END) - 
                      SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END) AS balance
                    FROM acc_transactions 
                    WHERE transaction_date < ?";
    $stmt = $conn->prepare($beginning_query);
    $stmt->bind_param('s', $start_date);
    $stmt->execute();
    $beginning_balance = $stmt->get_result()->fetch_assoc()['balance'] ?? 0;

    // Initialize array to hold monthly data for each category
    // We'll track operating, investing, and financing activities
    $cash_flow_data = [
        'operating' => [
            'income' => array_fill(1, 12, 0),
            'expense' => array_fill(1, 12, 0),
            'net' => array_fill(1, 12, 0),
            'total_income' => 0,
            'total_expense' => 0,
            'total_net' => 0
        ],
        'investing' => [
            'income' => array_fill(1, 12, 0),
            'expense' => array_fill(1, 12, 0),
            'net' => array_fill(1, 12, 0),
            'total_income' => 0,
            'total_expense' => 0,
            'total_net' => 0
        ],
        'financing' => [
            'income' => array_fill(1, 12, 0),
            'expense' => array_fill(1, 12, 0),
            'net' => array_fill(1, 12, 0),
            'total_income' => 0,
            'total_expense' => 0,
            'total_net' => 0
        ],
        'unclassified' => [
            'income' => array_fill(1, 12, 0),
            'expense' => array_fill(1, 12, 0),
            'net' => array_fill(1, 12, 0),
            'total_income' => 0,
            'total_expense' => 0,
            'total_net' => 0
        ]
    ];

    // Get transaction data by activity type and month
    $query = "SELECT 
            MONTH(t.transaction_date) AS month,
            c.type AS category_type,
            t.type AS transaction_type,
            SUM(t.amount) AS amount
          FROM acc_transactions t
          JOIN acc_transaction_categories c ON t.category_id = c.id
          WHERE YEAR(t.transaction_date) = ?
          GROUP BY MONTH(t.transaction_date), c.type, t.type
          ORDER BY MONTH(t.transaction_date)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $result = $stmt->get_result();

    // Process results
    while ($row = $result->fetch_assoc()) {
        $month = $row['month'];
        $category_type = strtolower($row['category_type']);
        $transaction_type = strtolower($row['transaction_type']);
        $amount = $row['amount'] ?? 0;

        // If the category type is not one of our tracked types, put it in unclassified
        if (!in_array($category_type, ['operating', 'investing', 'financing'])) {
            $category_type = 'unclassified';
        }

        // Add to the appropriate category
        if ($transaction_type === 'income') {
            $cash_flow_data[$category_type]['income'][$month] += $amount;
            $cash_flow_data[$category_type]['total_income'] += $amount;
        } else {
            $cash_flow_data[$category_type]['expense'][$month] += $amount;
            $cash_flow_data[$category_type]['total_expense'] += $amount;
        }
    }

    // Calculate net flows and monthly balances
    $monthly_net_total = array_fill(1, 12, 0);
    $monthly_balance = array_fill(1, 12, 0);
    $running_balance = $beginning_balance;

    foreach (['operating', 'investing', 'financing', 'unclassified'] as $activity) {
        for ($i = 1; $i <= 12; $i++) {
            $cash_flow_data[$activity]['net'][$i] =
                $cash_flow_data[$activity]['income'][$i] -
                $cash_flow_data[$activity]['expense'][$i];

            $monthly_net_total[$i] += $cash_flow_data[$activity]['net'][$i];
        }

        $cash_flow_data[$activity]['total_net'] =
            $cash_flow_data[$activity]['total_income'] -
            $cash_flow_data[$activity]['total_expense'];
    }

    // Calculate running balance for each month
    for ($i = 1; $i <= 12; $i++) {
        $running_balance += $monthly_net_total[$i];
        $monthly_balance[$i] = $running_balance;
    }

    // Total cash flow
    $total_net_cash_flow = array_sum($monthly_net_total);
    $ending_balance = $beginning_balance + $total_net_cash_flow;
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Monthly Cash Flow</title>
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
         <?php renderReportHeader('Monthly Cash Flow Statement', 'Monthly cash flow breakdown for ' . $year); ?>

         <!-- Year Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Year</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="ytd_cash_flow_monthly.php" method="GET" class="row">
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
                         <a href="download.php?type=ytd_cash_flow_monthly&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
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
                     <div class="col-md-3 mb-3">
                         <div class="card bg-primary text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Beginning Balance</h5>
                                 <p class="h3">$<?php echo number_format($beginning_balance, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-3 mb-3">
                         <div class="card <?php echo $total_net_cash_flow >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Net Cash Flow</h5>
                                 <p class="h3">$<?php echo number_format($total_net_cash_flow, 2); ?></p>
                             </div>
                         </div>
                     </div>
                     <div class="col-md-6 mb-3">
                         <div class="card bg-info text-white">
                             <div class="card-body text-center">
                                 <h5 class="card-title">Ending Balance</h5>
                                 <p class="h3">$<?php echo number_format($ending_balance, 2); ?></p>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <!-- Cash Flow Statement -->
         <div class="card shadow mb-4">
             <div class="card-header">
                 <h3>Cash Flow Statement by Month</h3>
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
                             <!-- Beginning Balance Row -->
                             <tr class="table-primary fw-bold">
                                 <td>Beginning Balance</td>
                                 <td class="text-end" colspan="12">$<?php echo number_format($beginning_balance, 2); ?></td>
                                 <td class="text-end">$<?php echo number_format($beginning_balance, 2); ?></td>
                             </tr>

                             <!-- Operating Activities -->
                             <tr class="table-secondary">
                                 <th colspan="14">Operating Activities</th>
                             </tr>

                             <tr>
                                 <td>Cash In - Operating</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['operating']['income'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['operating']['income'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['operating']['total_income'], 2); ?>
                                 </td>
                             </tr>

                             <tr>
                                 <td>Cash Out - Operating</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['operating']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['operating']['expense'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['operating']['total_expense'], 2); ?>
                                 </td>
                             </tr>

                             <tr class="fw-bold">
                                 <td>Net Operating Cash Flow</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $cash_flow_data['operating']['net'][$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($cash_flow_data['operating']['income'][$i] > 0 || $cash_flow_data['operating']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['operating']['net'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $cash_flow_data['operating']['total_net'] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($cash_flow_data['operating']['total_net'], 2); ?>
                                 </td>
                             </tr>

                             <!-- Investing Activities -->
                             <tr class="table-secondary">
                                 <th colspan="14">Investing Activities</th>
                             </tr>

                             <tr>
                                 <td>Cash In - Investing</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['investing']['income'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['investing']['income'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['investing']['total_income'], 2); ?>
                                 </td>
                             </tr>

                             <tr>
                                 <td>Cash Out - Investing</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['investing']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['investing']['expense'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['investing']['total_expense'], 2); ?>
                                 </td>
                             </tr>

                             <tr class="fw-bold">
                                 <td>Net Investing Cash Flow</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $cash_flow_data['investing']['net'][$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($cash_flow_data['investing']['income'][$i] > 0 || $cash_flow_data['investing']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['investing']['net'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $cash_flow_data['investing']['total_net'] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($cash_flow_data['investing']['total_net'], 2); ?>
                                 </td>
                             </tr>

                             <!-- Financing Activities -->
                             <tr class="table-secondary">
                                 <th colspan="14">Financing Activities</th>
                             </tr>

                             <tr>
                                 <td>Cash In - Financing</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['financing']['income'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['financing']['income'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['financing']['total_income'], 2); ?>
                                 </td>
                             </tr>

                             <tr>
                                 <td>Cash Out - Financing</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end">
                                         <?php if ($cash_flow_data['financing']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['financing']['expense'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class=" text-end fw-bold">
                                     $<?php echo number_format($cash_flow_data['financing']['total_expense'], 2); ?>
                                 </td>
                             </tr>

                             <tr class="fw-bold">
                                 <td>Net Financing Cash Flow</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $cash_flow_data['financing']['net'][$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($cash_flow_data['financing']['income'][$i] > 0 || $cash_flow_data['financing']['expense'][$i] > 0): ?>
                                             $<?php echo number_format($cash_flow_data['financing']['net'][$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $cash_flow_data['financing']['total_net'] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($cash_flow_data['financing']['total_net'], 2); ?>
                                 </td>
                             </tr>

                             <!-- Unclassified Activities (if any) -->
                             <?php if ($cash_flow_data['unclassified']['total_income'] > 0 || $cash_flow_data['unclassified']['total_expense'] > 0): ?>
                                 <tr class="table-secondary">
                                     <th colspan="14">Unclassified Activities</th>
                                 </tr>

                                 <tr>
                                     <td>Cash In - Unclassified</td>
                                     <?php for ($i = 1; $i <= 12; $i++): ?>
                                         <td class="text-end">
                                             <?php if ($cash_flow_data['unclassified']['income'][$i] > 0): ?>
                                                 $<?php echo number_format($cash_flow_data['unclassified']['income'][$i], 2); ?>
                                             <?php endif; ?>
                                         </td>
                                     <?php endfor; ?>
                                     <td class="text-end fw-bold">
                                         $<?php echo number_format($cash_flow_data['unclassified']['total_income'], 2); ?>
                                     </td>
                                 </tr>

                                 <tr>
                                     <td>Cash Out - Unclassified</td>
                                     <?php for ($i = 1; $i <= 12; $i++): ?>
                                         <td class="text-end">
                                             <?php if ($cash_flow_data['unclassified']['expense'][$i] > 0): ?>
                                                 $<?php echo number_format($cash_flow_data['unclassified']['expense'][$i], 2); ?>
                                             <?php endif; ?>
                                         </td>
                                     <?php endfor; ?>
                                     <td class="text-end fw-bold">
                                         $<?php echo number_format($cash_flow_data['unclassified']['total_expense'], 2); ?>
                                     </td>
                                 </tr>

                                 <tr class="fw-bold">
                                     <td>Net Unclassified Cash Flow</td>
                                     <?php for ($i = 1; $i <= 12; $i++): ?>
                                         <td class="text-end <?php echo $cash_flow_data['unclassified']['net'][$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                             <?php if ($cash_flow_data['unclassified']['income'][$i] > 0 || $cash_flow_data['unclassified']['expense'][$i] > 0): ?>
                                                 $<?php echo number_format($cash_flow_data['unclassified']['net'][$i], 2); ?>
                                             <?php endif; ?>
                                         </td>
                                     <?php endfor; ?>
                                     <td class="text-end <?php echo $cash_flow_data['unclassified']['total_net'] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         $<?php echo number_format($cash_flow_data['unclassified']['total_net'], 2); ?>
                                     </td>
                                 </tr>
                             <?php endif; ?>

                             <!-- Total Net Cash Flow -->
                             <tr class="table-primary fw-bold">
                                 <td>Total Net Cash Flow</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $monthly_net_total[$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($monthly_net_total[$i] != 0): ?>
                                             $<?php echo number_format($monthly_net_total[$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $total_net_cash_flow >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($total_net_cash_flow, 2); ?>
                                 </td>
                             </tr>

                             <!-- Ending Balance -->
                             <tr class="table-info fw-bold">
                                 <td>Monthly Ending Balance</td>
                                 <?php for ($i = 1; $i <= 12; $i++): ?>
                                     <td class="text-end <?php echo $monthly_balance[$i] >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                         <?php if ($monthly_balance[$i] != 0): ?>
                                             $<?php echo number_format($monthly_balance[$i], 2); ?>
                                         <?php endif; ?>
                                     </td>
                                 <?php endfor; ?>
                                 <td class="text-end <?php echo $ending_balance >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                     $<?php echo number_format($ending_balance, 2); ?>
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