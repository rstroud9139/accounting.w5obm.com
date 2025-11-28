<?php
// /accounting/reports/cash_flow.php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once __DIR__ . '/../../include/dbconn.php';
    require_once __DIR__ . '/../lib/helpers.php';
    require_once __DIR__ . '/../controllers/reportController.php';
    require_once __DIR__ . '/../../include/premium_hero.php';

    // Validate session
    validate_session();

    // Get parameters
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');

    // Generate date range
    $start_date = "$year-$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));

    // Generate cash flow data
    $cash_flow = generate_cash_flow_statement($start_date, $end_date);

    // Format dates for display
    $display_start = date('F 1, Y', strtotime($start_date));
    $display_end = date('F j, Y', strtotime($end_date));

    $beginning_balance = (float)($cash_flow['beginning_balance'] ?? 0);
    $ending_balance = (float)($cash_flow['ending_balance'] ?? 0);
    $net_change = $ending_balance - $beginning_balance;

    $cashFlowHeroChips = [
        'Period: ' . date('M Y', strtotime($start_date)),
        'Range: ' . $display_start . ' – ' . $display_end,
        $net_change >= 0 ? 'Trend: Positive' : 'Trend: Negative',
    ];

    $cashFlowHeroHighlights = [
        [
            'label' => 'Beginning Balance',
            'value' => '$' . number_format($beginning_balance, 2),
            'meta' => 'Start of period',
        ],
        [
            'label' => 'Net Change',
            'value' => ($net_change >= 0 ? '+' : '−') . '$' . number_format(abs($net_change), 2),
            'meta' => 'Operating + Investing + Financing',
        ],
        [
            'label' => 'Ending Balance',
            'value' => '$' . number_format($ending_balance, 2),
            'meta' => 'End of period',
        ],
    ];

    $cashFlowHeroActions = [
        [
            'label' => 'Download PDF',
            'url' => '/accounting/reports/download.php?type=cash_flow&month=' . urlencode($month) . '&year=' . urlencode($year),
            'variant' => 'outline',
            'icon' => 'fa-file-pdf',
        ],
        [
            'label' => 'Reports Dashboard',
            'url' => '/accounting/reports/reports_dashboard.php',
            'variant' => 'outline',
            'icon' => 'fa-chart-line',
        ],
        [
            'label' => 'Accounting Dashboard',
            'url' => '/accounting/dashboard.php',
            'variant' => 'outline',
            'icon' => 'fa-arrow-left',
        ],
    ];

    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <title>Cash Flow Statement</title>
     <?php include '../../include/header.php'; ?>
 </head>

 <body>
     <?php include '../../include/menu.php'; ?>
     <?php include '../../include/report_header.php'; ?>

     <?php
     if (function_exists('displayToastMessage')) {
         displayToastMessage();
     }
     ?>

     <div class="container mt-5">
         <!-- Standard Report Header -->
         <?php renderReportHeader(
             'Cash Flow Statement',
             'For the period ' . $display_start . ' to ' . $display_end,
             [],
             [
                 'eyebrow' => 'Liquidity Overview',
                 'chips' => $cashFlowHeroChips,
                 'highlights' => $cashFlowHeroHighlights,
                 'actions' => $cashFlowHeroActions,
                 'theme' => 'cobalt',
                 'size' => 'compact',
                 'media_mode' => 'none',
             ]
         ); ?>

         <!-- Period Selector -->
         <div class="card shadow mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                 <h3 class="mb-0">Select Period</h3>
                 <div class="d-flex gap-2">
                     <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                 </div>
             </div>
             <div class="card-body">
                 <form action="cash_flow.php" method="GET" class="row">
                     <div class="col-md-4 mb-3">
                         <label for="month" class="form-label">Month</label>
                         <select id="month" name="month" class="form-control">
                             <option value="01" <?php echo $month == '01' ? 'selected' : ''; ?>>January</option>
                             <option value="02" <?php echo $month == '02' ? 'selected' : ''; ?>>February</option>
                             <option value="03" <?php echo $month == '03' ? 'selected' : ''; ?>>March</option>
                             <option value="04" <?php echo $month == '04' ? 'selected' : ''; ?>>April</option>
                             <option value="05" <?php echo $month == '05' ? 'selected' : ''; ?>>May</option>
                             <option value="06" <?php echo $month == '06' ? 'selected' : ''; ?>>June</option>
                             <option value="07" <?php echo $month == '07' ? 'selected' : ''; ?>>July</option>
                             <option value="08" <?php echo $month == '08' ? 'selected' : ''; ?>>August</option>
                             <option value="09" <?php echo $month == '09' ? 'selected' : ''; ?>>September</option>
                             <option value="10" <?php echo $month == '10' ? 'selected' : ''; ?>>October</option>
                             <option value="11" <?php echo $month == '11' ? 'selected' : ''; ?>>November</option>
                             <option value="12" <?php echo $month == '12' ? 'selected' : ''; ?>>December</option>
                         </select>
                     </div>
                     <div class="col-md-4 mb-3">
                         <label for="year" class="form-label">Year</label>
                         <select id="year" name="year" class="form-control">
                             <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                 <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                     <?php echo $y; ?>
                                 </option>
                             <?php endfor; ?>
                         </select>
                     </div>
                     <div class="col-md-4 mb-3 d-flex align-items-end">
                         <button type="submit" class="btn btn-primary">Generate Report</button>
                         <a href="download.php?type=cash_flow&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Cash Flow Statement -->
         <div class="card shadow">
             <div class="card-header">
                 <h3>Cash Flow Statement - <?php echo $display_start; ?> to <?php echo $display_end; ?></h3>
             </div>
             <div class="card-body">
                 <!-- Beginning Balance -->
                 <table class="table table-striped mb-4">
                     <tr class="fw-bold">
                         <td>Beginning Cash Balance</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['beginning_balance'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Operating Activities -->
                 <h4>Cash Flow from Operating Activities</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Cash Received from Operating Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['operating_activities']['income'], 2); ?></td>
                     </tr>
                     <tr>
                         <td>Cash Paid for Operating Activities</td>
                         <td class="text-end">($<?php echo number_format($cash_flow['operating_activities']['expense'], 2); ?>)</td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Net Cash from Operating Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['operating_activities']['net'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Investing Activities -->
                 <h4>Cash Flow from Investing Activities</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Cash Received from Investing Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['investing_activities']['income'], 2); ?></td>
                     </tr>
                     <tr>
                         <td>Cash Paid for Investing Activities</td>
                         <td class="text-end">($<?php echo number_format($cash_flow['investing_activities']['expense'], 2); ?>)</td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Net Cash from Investing Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['investing_activities']['net'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Financing Activities -->
                 <h4>Cash Flow from Financing Activities</h4>
                 <table class="table table-striped mb-4">
                     <tr>
                         <td>Cash Received from Financing Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['financing_activities']['income'], 2); ?></td>
                     </tr>
                     <tr>
                         <td>Cash Paid for Financing Activities</td>
                         <td class="text-end">($<?php echo number_format($cash_flow['financing_activities']['expense'], 2); ?>)</td>
                     </tr>
                     <tr class="fw-bold">
                         <td>Net Cash from Financing Activities</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['financing_activities']['net'], 2); ?></td>
                     </tr>
                 </table>

                 <!-- Ending Balance -->
                 <table class="table table-primary">
                     <tr class="fw-bold">
                         <td>Ending Cash Balance</td>
                         <td class="text-end">$<?php echo number_format($cash_flow['ending_balance'], 2); ?></td>
                     </tr>
                 </table>
             </div>
         </div>
     </div>

     <?php include '../../include/footer.php'; ?>
 </body>

 </html>