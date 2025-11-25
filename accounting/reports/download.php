 <!-- /accounting/reports/download.php -->
 <?php
    require_once __DIR__ . '/../utils/session_manager.php';
    require_once '../../include/dbconn.php';
    require_once __DIR__ . '/../utils/pdf_generator.php';
    require_once __DIR__ . '/../controllers/reportController.php';

    // Validate session
    validate_session();

    // Get report parameters
    $type = $_GET['type'] ?? null;
    $id = $_GET['id'] ?? null;

    if ($id) {
        // Fetch an existing report from the database
        $report = fetch_report_by_id($id);

        if ($report && file_exists($report['file_path'])) {
            // Stream the file to the browser
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($report['file_path']) . '"');
            readfile($report['file_path']);
            exit;
        } else {
            echo "Report file not found.";
            exit;
        }
    }

    // Generate a new report based on type
    switch ($type) {
        case 'income_statement':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $file_path = generate_income_statement_pdf($month, $year);
            break;

        case 'ytd_income_statement':
            $year = $_GET['year'] ?? date('Y');
            $file_path = generate_ytd_income_statement_pdf($year);
            break;

        case 'balance_sheet':
            $date = $_GET['date'] ?? date('Y-m-d');
            $file_path = generate_balance_sheet_pdf($date);
            break;

        case 'cash_flow':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $file_path = generate_cash_flow_pdf($month, $year);
            break;

        case 'ytd_cash_flow':
            $year = $_GET['year'] ?? date('Y');
            $file_path = generate_ytd_cash_flow_pdf($year);
            break;

        case 'donation_summary':
            $year = $_GET['year'] ?? date('Y');
            $file_path = generate_donation_summary_pdf($year);
            break;

        case 'donor_list':
            $file_path = generate_donor_list_pdf();
            break;

        case 'asset_listing':
            $file_path = generate_asset_listing_pdf();
            break;

        default:
            echo "Invalid report type.";
            exit;
    }

    if ($file_path && file_exists($file_path)) {
        // Stream the file to the browser
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        readfile($file_path);
        exit;
    } else {
        echo "Failed to generate report.";
        exit;
    }

    /**
     * Generate Balance Sheet PDF
     */
    function generate_balance_sheet_pdf($date)
    {
        // Load required controller functions
        require_once __DIR__ . '/../controllers/reportController.php';

        // Generate report data
        $balance_sheet = generate_balance_sheet($date);

        // Format date for the title
        $display_date = date('F j, Y', strtotime($date));

        $title = "Balance Sheet - As of $display_date";

        // Create the PDF using the utility function
        $filename = "balance_sheet_" . date('Ymd', strtotime($date)) . ".pdf";
        $content = [
            'headers' => ['Item', 'Amount'],
            'rows' => [
                ['Cash and Equivalents', '$' . number_format($balance_sheet['assets']['cash'], 2)],
                ['Physical Assets', '$' . number_format($balance_sheet['assets']['physical_assets'], 2)],
                ['Total Assets', '$' . number_format($balance_sheet['assets']['total'], 2)],
                ['Total Liabilities', '$' . number_format($balance_sheet['liabilities'], 2)],
                ['Total Equity', '$' . number_format($balance_sheet['equity'], 2)],
                ['Total Liabilities and Equity', '$' . number_format($balance_sheet['liabilities'] + $balance_sheet['equity'], 2)]
            ]
        ];

        $file_path = create_pdf($title, $content, $filename);

        // Save report metadata to database
        if ($file_path) {
            save_report('balance_sheet', json_encode(['date' => $date]), $file_path);
        }

        return $file_path;
    }

    /**
     * Generate Cash Flow PDF
     */
    function generate_cash_flow_pdf($month, $year)
    {
        // Similar implementation to balance_sheet_pdf but with cash flow data
        // For brevity, implementation details are omitted here
        return 'sample_path.pdf'; // This would be the actual file path in a real implementation
    }

// Other PDF generation functions would be implemented similarly