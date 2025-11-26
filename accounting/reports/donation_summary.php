<?php
// /accounting/reports/donation_summary.php
     require_once __DIR__ . '/../utils/session_manager.php';
     require_once __DIR__ . '/../../include/dbconn.php';
     require_once __DIR__ . '/../controllers/donation_controller.php';

// Validate session
validate_session();

// Get parameters
$year = $_GET['year'] ?? date('Y');

// Get donation statistics for the year
$start_date = "$year-01-01";
$end_date = "$year-12-31";

// Monthly donation totals
$monthly_query = "SELECT 
                    MONTH(donation_date) AS month,
                    SUM(amount) AS total,
                    COUNT(*) AS count
                  FROM acc_donations
                  WHERE donation_date BETWEEN ? AND ?
                  GROUP BY MONTH(donation_date)
                  ORDER BY month";
$stmt = $conn->prepare($monthly_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$monthly_result = $stmt->get_result();

$monthly_data = array_fill(1, 12, ['total' => 0, 'count' => 0]);
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[$row['month']] = $row;
}

// Top donors
$top_donors_query = "SELECT 
                      c.id,
                      c.name,
                      COUNT(d.id) AS donation_count,
                      SUM(d.amount) AS total_amount
                    FROM acc_donations d
                    JOIN acc_contacts c ON d.contact_id = c.id
                    WHERE d.donation_date BETWEEN ? AND ?
                    GROUP BY c.id, c.name
                    ORDER BY total_amount DESC
                    LIMIT 10";
$stmt = $conn->prepare($top_donors_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$top_donors_result = $stmt->get_result();
$top_donors = [];
while ($row = $top_donors_result->fetch_assoc()) {
    $top_donors[] = $row;
}

// Donation size breakdown
$size_breakdown_query = "SELECT 
                          CASE 
                            WHEN amount < 100 THEN 'Under $100'
                            WHEN amount >= 100 AND amount < 500 THEN '$100 - $499'
                            WHEN amount >= 500 AND amount < 1000 THEN '$500 - $999'
                            WHEN amount >= 1000 AND amount < 5000 THEN '$1,000 - $4,999'
                            ELSE '$5,000 and above'
                          END AS range_label,
                          COUNT(*) AS donation_count,
                          SUM(amount) AS total_amount
                        FROM acc_donations
                        WHERE donation_date BETWEEN ? AND ?
                        GROUP BY range_label
                        ORDER BY 
                          CASE range_label
                            WHEN 'Under $100' THEN 1
                            WHEN '$100 - $499' THEN 2
                            WHEN '$500 - $999' THEN 3
                            WHEN '$1,000 - $4,999' THEN 4
                            ELSE 5
                          END";
$stmt = $conn->prepare($size_breakdown_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$size_breakdown_result = $stmt->get_result();
$size_breakdown = [];
while ($row = $size_breakdown_result->fetch_assoc()) {
    $size_breakdown[] = $row;
}

// Overall totals
$totals_query = "SELECT 
                  SUM(amount) AS total_amount,
                  COUNT(*) AS donation_count,
                  AVG(amount) AS average_donation,
                  MIN(amount) AS smallest_donation,
                  MAX(amount) AS largest_donation
                FROM acc_donations
                WHERE donation_date BETWEEN ? AND ?";
$stmt = $conn->prepare($totals_query);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Donation Summary</title>
    <?php include '../../include/header.php'; ?>
</head>

<body>
    <?php include '../../include/menu.php'; ?>
    <?php include '../../include/report_header.php'; ?>

    <div class="container mt-5">
        <!-- Standard Report Header -->
        <?php renderReportHeader('Donation Summary Report', 'For the Year ' . $year); ?>

        <!-- Year Selector -->
        <div class="card shadow mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="mb-0">Select Year</h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()" title="Print"><i class="fas fa-print me-1"></i>Print</button>
                </div>
            </div>
            <div class="card-body">
                <form action="donation_summary.php" method="GET" class="row">
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
                        <a href="download.php?type=donation_summary&year=<?php echo $year; ?>" class="btn btn-secondary ms-2 no-print">Download PDF</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h3>Overall Statistics</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Donations</h5>
                                <p class="h3">$<?php echo number_format($totals['total_amount'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Number of Donations</h5>
                                <p class="h3"><?php echo $totals['donation_count']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Average Donation</h5>
                                <p class="h3">$<?php echo number_format($totals['average_donation'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Smallest Donation</h5>
                                <p class="h3">$<?php echo number_format($totals['smallest_donation'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Largest Donation</h5>
                                <p class="h3">$<?php echo number_format($totals['largest_donation'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Breakdown -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h3>Monthly Breakdown</h3>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Number of Donations</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $year_total = 0;
                        $year_count = 0;
                        for ($i = 1; $i <= 12; $i++):
                            $month_name = date('F', mktime(0, 0, 0, $i, 1));
                            $count = $monthly_data[$i]['count'] ?? 0;
                            $total = $monthly_data[$i]['total'] ?? 0;
                            $year_count += $count;
                            $year_total += $total;
                        ?>
                            <tr>
                                <td><?php echo $month_name; ?></td>
                                <td><?php echo $count; ?></td>
                                <td>$<?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-primary">
                            <th>Total</th>
                            <th><?php echo $year_count; ?></th>
                            <th>$<?php echo number_format($year_total, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Top Donors -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h3>Top Donors</h3>
            </div>
            <div class="card-body">
                <?php if (empty($top_donors)): ?>
                    <div class="alert alert-info">No donations recorded for this period.</div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Donor Name</th>
                                <th>Number of Donations</th>
                                <th>Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_donors as $donor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                    <td><?php echo $donor['donation_count']; ?></td>
                                    <td>$<?php echo number_format($donor['total_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Donation Size Breakdown -->
        <div class="card shadow">
            <div class="card-header">
                <h3>Donation Size Breakdown</h3>
            </div>
            <div class="card-body">
                <?php if (empty($size_breakdown)): ?>
                    <div class="alert alert-info">No donations recorded for this period.</div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Donation Range</th>
                                <th>Number of Donations</th>
                                <th>Total Amount</th>
                                <th>Percentage of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($size_breakdown as $range): ?>
                                <tr>
                                    <td><?php echo $range['range_label']; ?></td>
                                    <td><?php echo $range['donation_count']; ?></td>
                                    <td>$<?php echo number_format($range['total_amount'], 2); ?></td>
                                    <td><?php echo number_format(($range['total_amount'] / $totals['total_amount']) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../include/footer.php'; ?>
</body>

</html>