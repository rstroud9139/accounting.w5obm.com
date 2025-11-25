 <!-- /accounting/utils/pdf_generator.php -->
 <?php
    /**
     * PDF Generator
     * Functions for generating PDF documents
     */

    /**
     * Create a PDF file.
     */
    function create_pdf($title, $content, $filename = null)
    {
        // Check if FPDF or TCPDF is available
        if (!class_exists('FPDF') && !class_exists('TCPDF')) {
            // If not, try to load FPDF
            $fpdf_path = __DIR__ . '/../../lib/fpdf/fpdf.php';
            if (file_exists($fpdf_path)) {
                require_once $fpdf_path;
            } else {
                // Error: PDF library not found
                log_error('PDF library not found');
                return false;
            }
        }

        // Generate a filename if not provided
        if ($filename === null) {
            $filename = 'report_' . date('YmdHis') . '.pdf';
        }

        // Create PDF using FPDF
        if (class_exists('FPDF')) {
            $pdf = new FPDF();
            $pdf->AddPage();

            // Title
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, $title, 0, 1, 'C');

            // Add some spacing
            $pdf->Ln(5);

            // Content
            $pdf->SetFont('Arial', '', 12);

            // If content is an array, create a table
            if (is_array($content)) {
                create_pdf_table($pdf, $content);
            } else {
                // Split content into paragraphs
                $paragraphs = explode("\n", $content);
                foreach ($paragraphs as $paragraph) {
                    $pdf->MultiCell(0, 10, $paragraph);
                    $pdf->Ln(5);
                }
            }

            // Save to file
            $file_path = __DIR__ . '/../../reports/generated/' . $filename;

            // Ensure the directory exists
            $dir = dirname($file_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $pdf->Output('F', $file_path);

            return $file_path;
        }

        return false;
    }

    /**
     * Create a table in the PDF.
     */
    function create_pdf_table($pdf, $data)
    {
        // Check if data is appropriate for a table
        if (!isset($data['headers']) || !isset($data['rows'])) {
            $pdf->MultiCell(0, 10, 'Invalid table data');
            return;
        }

        // Define width and spacing
        $page_width = $pdf->GetPageWidth() - 20; // Adjust for margins
        $column_count = count($data['headers']);
        $column_width = $page_width / $column_count;

        // Headers
        $pdf->SetFont('Arial', 'B', 12);
        foreach ($data['headers'] as $header) {
            $pdf->Cell($column_width, 10, $header, 1, 0, 'C');
        }
        $pdf->Ln();

        // Rows
        $pdf->SetFont('Arial', '', 12);
        foreach ($data['rows'] as $row) {
            foreach ($row as $cell) {
                $pdf->Cell($column_width, 10, $cell, 1);
            }
            $pdf->Ln();
        }
    }

    /**
     * Generate an income statement PDF.
     */
    function generate_income_statement_pdf($month, $year)
    {
        // Load required controller
        require_once __DIR__ . '/../controllers/reportController.php';

        // Generate report data
        $report_data = generate_income_statement($month, $year);

        // Format dates for the title
        $start_date = date('F j, Y', strtotime($report_data['start_date']));
        $end_date = date('F j, Y', strtotime($report_data['end_date']));

        $title = "Income Statement - $start_date to $end_date";

        // Get organization name from settings
        $org_name = get_setting('organization_name', 'Amateur Radio Club');

        // Create the PDF
        $pdf = new FPDF();
        $pdf->AddPage();

        // Header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $org_name, 0, 1, 'C');
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Generated on " . date('F j, Y'), 0, 1, 'C');

        $pdf->Ln(10);

        // Income section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, "Income", 0, 1);

        $pdf->SetFont('Arial', '', 12);
        foreach ($report_data['income']['categories'] as $category) {
            $pdf->Cell(100, 10, $category['name'], 0);
            $pdf->Cell(0, 10, '$' . number_format($category['total'], 2), 0, 1, 'R');
        }

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(100, 10, "Total Income", 0);
        $pdf->Cell(0, 10, '$' . number_format($report_data['income']['total'], 2), 0, 1, 'R');

        $pdf->Ln(10);

        // Expense section
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, "Expenses", 0, 1);

        $pdf->SetFont('Arial', '', 12);
        foreach ($report_data['expenses']['categories'] as $category) {
            $pdf->Cell(100, 10, $category['name'], 0);
            $pdf->Cell(0, 10, '$' . number_format($category['total'], 2), 0, 1, 'R');
        }

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(100, 10, "Total Expenses", 0);
        $pdf->Cell(0, 10, '$' . number_format($report_data['expenses']['total'], 2), 0, 1, 'R');

        $pdf->Ln(10);

        // Net Income
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(100, 10, "Net Income", 0);
        $pdf->Cell(0, 10, '$' . number_format($report_data['net_income'], 2), 0, 1, 'R');

        // Save the PDF
        $filename = "income_statement_{$year}_{$month}.pdf";
        $file_path = __DIR__ . '/../../reports/generated/' . $filename;

        // Ensure the directory exists
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->Output('F', $file_path);

        // Save report metadata to database
        save_report('income_statement', json_encode(['month' => $month, 'year' => $year]), $file_path);

        return $file_path;
    }

    /**
     * Generate a donation receipt PDF.
     */
    function generate_donation_receipt_pdf($donation_id)
    {
        global $conn;

        // Load required utilities
        require_once __DIR__ . '/email_utils.php';

        // Get donation details
        $query = "SELECT d.*, c.name as donor_name, c.email as donor_email, c.address as donor_address, c.tax_id as donor_tax_id
              FROM acc_donations d
              JOIN acc_contacts c ON d.contact_id = c.id
              WHERE d.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $donation = $result->fetch_assoc();

        if (!$donation) {
            return false;
        }

        // Get organization info from settings
        $org_name = get_setting('organization_name', 'Amateur Radio Club');
        $org_address = get_setting('organization_address', '123 Main St, Anytown, USA');
        $org_tax_id = get_setting('organization_tax_id', '12-3456789');

        // Create PDF
        $pdf = new FPDF();
        $pdf->AddPage();

        // Header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $org_name, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $org_address, 0, 1, 'C');
        $pdf->Cell(0, 10, "Tax ID: " . $org_tax_id, 0, 1, 'C');

        $pdf->Ln(10);

        // Receipt title
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, "Charitable Donation Receipt", 0, 1, 'C');

        $pdf->Ln(10);

        // Receipt details
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, "Receipt Number:");
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $donation_id, 0, 1);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, "Donor Name:");
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $donation['donor_name'], 0, 1);

        if (!empty($donation['donor_address'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(80, 10, "Donor Address:");
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, $donation['donor_address'], 0, 1);
        }

        if (!empty($donation['donor_tax_id'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(80, 10, "Donor Tax ID:");
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, $donation['donor_tax_id'], 0, 1);
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, "Donation Date:");
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, date('F j, Y', strtotime($donation['donation_date'])), 0, 1);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(80, 10, "Donation Amount:");
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, '$' . number_format($donation['amount'], 2), 0, 1);

        if (!empty($donation['description'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(80, 10, "Description:");
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, $donation['description'], 0, 1);
        }

        $pdf->Ln(10);

        // Tax deductible notice
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->MultiCell(0, 5, "This letter acknowledges that no goods or services were provided in exchange for this donation. " .
            "$org_name is a 501(c)(3) tax-exempt organization. " .
            "Your donation is tax-deductible to the extent allowed by law.");

        $pdf->Ln(10);

        // Signature
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Signature: ________________________", 0, 1);
        $pdf->Cell(0, 10, "Date: " . date('F j, Y'), 0, 1);

        // Save the PDF
        $filename = "donation_receipt_{$donation_id}.pdf";
        $file_path = __DIR__ . '/../../reports/generated/' . $filename;

        // Ensure the directory exists
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->Output('F', $file_path);

        // Mark receipt as sent
        $query = "UPDATE acc_donations SET receipt_sent = 1, receipt_date = CURDATE() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();

        return $file_path;
    }
