<?php

/**
 * Report PDF Generator - W5OBM Accounting System
 * File: /accounting/utils/report_pdf_generator.php
 * Purpose: Generate PDF reports using TCPDF
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Security check
if (!defined('SECURE_ACCESS')) {
    if (!isset($_SESSION) || !function_exists('isAuthenticated')) {
        die('Direct access not permitted');
    }
}

// Include TCPDF library from known locations; define a safe stub if missing
require_once __DIR__ . '/../lib/helpers.php';
$__tcpdf_included = false;
$__tcpdf_candidates = [
    __DIR__ . '/../../include/tcpdf/tcpdf.php',
    __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
];
foreach ($__tcpdf_candidates as $__c) {
    if (is_file($__c)) {
        require_once $__c;
        $__tcpdf_included = true;
        break;
    }
}
if (!class_exists('TCPDF')) {
    // Minimal stub to satisfy static analysis; will throw on use
    class TCPDF
    {
        public function __construct(...$args)
        { /* no-op */
        }
        public function SetCreator($v) {}
        public function SetAuthor($v) {}
        public function SetTitle($v) {}
        public function SetHeaderData($a = '', $b = 0, $c = '', $d = '') {}
        public function setHeaderFont($arr) {}
        public function setFooterFont($arr) {}
        public function SetDefaultMonospacedFont($v) {}
        public function SetMargins($l, $t, $r) {}
        public function SetHeaderMargin($m) {}
        public function SetFooterMargin($m) {}
        public function SetAutoPageBreak($auto, $margin = 0) {}
        public function setImageScale($s) {}
        public function Image(...$args) {}
        public function SetFont($fam, $style = '', $size = 10) {}
        public function Cell(...$args) {}
        public function MultiCell(...$args) {}
        public function Ln($h = null) {}
        public function SetY($y) {}
        public function GetY()
        {
            return 0;
        }
        public function getAliasNumPage()
        {
            return '1';
        }
        public function getAliasNbPages()
        {
            return '1';
        }
        public function AddPage() {}
        public function Line($x1, $y1, $x2, $y2) {}
        public function Output($name = '', $dest = 'I')
        {
            throw new RuntimeException('TCPDF library is not available. Please install or place tcpdf.php in include/tcpdf or vendor/tecnickcom/tcpdf.');
        }
    }
}

class AccountingPDFGenerator extends TCPDF
{

    private $report_title;
    private $report_date;
    private $club_info;

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4')
    {
        parent::__construct($orientation, $unit, $format, true, 'UTF-8', false);

        // Set default club information
        $this->club_info = [
            'name' => 'W5OBM Amateur Radio Club',
            'address' => 'P.O. Box 1234',
            'city_state_zip' => 'Your City, State 12345',
            'website' => 'www.w5obm.com',
            'email' => 'info@w5obm.com'
        ];

        // Set document information
        $this->SetCreator('W5OBM Accounting System');
        $this->SetAuthor('W5OBM Amateur Radio Club');
        $this->SetTitle('Financial Report');

        // Set default header data
        $this->SetHeaderData('', 0, $this->club_info['name'], 'Financial Management System');

        // Set header and footer fonts
        $this->setHeaderFont(['helvetica', '', 10]);
        $this->setFooterFont(['helvetica', '', 8]);

        // Set default monospaced font
        $this->SetDefaultMonospacedFont('courier');

        // Set margins
        $this->SetMargins(15, 25, 15);
        $this->SetHeaderMargin(10);
        $this->SetFooterMargin(10);

        // Set auto page breaks
        $this->SetAutoPageBreak(TRUE, 25);

        // Set image scale factor
        $this->setImageScale(1.25);
    }

    public function setReportTitle($title)
    {
        $this->report_title = $title;
    }

    public function setReportDate($date)
    {
        $this->report_date = $date;
    }

    // Page header
    public function Header()
    {
        // Logo (prefer accounting-local, fallback to site images)
        $logo_path = accounting_get_logo_path();
        if ($logo_path && file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // Club name
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, $this->club_info['name'], 0, 1, 'C');

        // Report title
        if ($this->report_title) {
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 8, $this->report_title, 0, 1, 'C');
        }

        // Report date
        if ($this->report_date) {
            $this->SetFont('helvetica', '', 12);
            $this->Cell(0, 6, $this->report_date, 0, 1, 'C');
        }

        $this->Ln(5);
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

/**
 * Generate Income Statement PDF
 * @param array $data Income statement data
 * @param array $options PDF options
 * @return string PDF file path
 */
function generateIncomeStatementPDF($data, $options = [])
{

    // Create new PDF document
    $pdf = new AccountingPDFGenerator();
    $pdf->setReportTitle('Income Statement');
    $pdf->setReportDate('For the Month Ended ' . $data['period']['display']);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Revenue Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'REVENUE', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);

    foreach ($data['income']['categories'] as $category) {
        $pdf->Cell(140, 6, '   ' . $category['name'], 0, 0, 'L');
        $pdf->Cell(40, 6, '$' . number_format($category['total'], 2), 0, 1, 'R');
    }

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total Revenue', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['income']['total'], 2), 'T', 1, 'R');

    $pdf->Ln(5);

    // Expenses Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'EXPENSES', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);

    foreach ($data['expenses']['categories'] as $category) {
        $pdf->Cell(140, 6, '   ' . $category['name'], 0, 0, 'L');
        $pdf->Cell(40, 6, '$' . number_format($category['total'], 2), 0, 1, 'R');
    }

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total Expenses', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['expenses']['total'], 2), 'T', 1, 'R');

    $pdf->Ln(8);

    // Net Income
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(140, 8, 'NET INCOME', 0, 0, 'L');
    $pdf->Cell(40, 8, '$' . number_format($data['net_income'], 2), 'TB', 1, 'R');

    // Save PDF
    $filename = 'income_statement_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = __DIR__ . '/../reports/generated/' . $filename;

    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdf->Output($filepath, 'F');

    return $filepath;
}

/**
 * Generate Balance Sheet PDF
 * @param array $data Balance sheet data
 * @param array $options PDF options
 * @return string PDF file path
 */
function generateBalanceSheetPDF($data, $options = [])
{

    // Create new PDF document
    $pdf = new AccountingPDFGenerator();
    $pdf->setReportTitle('Balance Sheet');
    $pdf->setReportDate('As of ' . $data['display_date']);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Assets Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'ASSETS', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, '   Cash and Cash Equivalents', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['assets']['cash'], 2), 0, 1, 'R');

    $pdf->Cell(140, 6, '   Physical Assets', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['assets']['physical_assets'], 2), 0, 1, 'R');

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total Assets', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['assets']['total'], 2), 'T', 1, 'R');

    $pdf->Ln(8);

    // Liabilities Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'LIABILITIES', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, '   Current Liabilities', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['liabilities']['current'], 2), 0, 1, 'R');

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total Liabilities', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['liabilities']['total'], 2), 'T', 1, 'R');

    $pdf->Ln(8);

    // Equity Section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'EQUITY', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 6, '   Net Assets', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['equity']['net_assets'], 2), 0, 1, 'R');

    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 6, 'Total Equity', 0, 0, 'L');
    $pdf->Cell(40, 6, '$' . number_format($data['equity']['total'], 2), 'T', 1, 'R');

    $pdf->Ln(8);

    // Total Liabilities and Equity
    $pdf->SetFont('helvetica', 'B', 12);
    $total_liab_equity = $data['liabilities']['total'] + $data['equity']['total'];
    $pdf->Cell(140, 8, 'TOTAL LIABILITIES AND EQUITY', 0, 0, 'L');
    $pdf->Cell(40, 8, '$' . number_format($total_liab_equity, 2), 'TB', 1, 'R');

    // Save PDF
    $filename = 'balance_sheet_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = __DIR__ . '/../reports/generated/' . $filename;

    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdf->Output($filepath, 'F');

    return $filepath;
}

/**
 * Generate Asset Listing PDF
 * @param array $data Asset listing data
 * @param array $options PDF options
 * @return string PDF file path
 */
function generateAssetListingPDF($data, $options = [])
{

    // Create new PDF document in landscape
    $pdf = new AccountingPDFGenerator('L', 'mm', 'A4');
    $pdf->setReportTitle('Asset Listing');
    $pdf->setReportDate('As of ' . date('F j, Y', strtotime($data['as_of_date'])));

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', 'B', 10);

    // Table headers
    $pdf->Cell(60, 8, 'Asset Name', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Acq. Date', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Original Value', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Depr. Rate', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Current Value', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Depreciation', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Years', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);

    foreach ($data['assets'] as $asset) {
        $pdf->Cell(60, 6, substr($asset['name'], 0, 35), 1, 0, 'L');
        $pdf->Cell(25, 6, date('m/d/Y', strtotime($asset['acquisition_date'])), 1, 0, 'C');
        $pdf->Cell(25, 6, '$' . number_format($asset['value'], 2), 1, 0, 'R');
        $pdf->Cell(20, 6, $asset['depreciation_rate'] . '%', 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . number_format($asset['current_value'], 2), 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . number_format($asset['total_depreciation'], 2), 1, 0, 'R');
        $pdf->Cell(20, 6, $asset['years_owned'], 1, 1, 'R');
    }

    // Totals row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(85, 8, 'TOTALS', 1, 0, 'C');
    $pdf->Cell(25, 8, '$' . number_format($data['totals']['original_value'], 2), 1, 0, 'R');
    $pdf->Cell(20, 8, '', 1, 0, 'C');
    $pdf->Cell(25, 8, '$' . number_format($data['totals']['current_value'], 2), 1, 0, 'R');
    $pdf->Cell(25, 8, '$' . number_format($data['totals']['total_depreciation'], 2), 1, 0, 'R');
    $pdf->Cell(20, 8, '', 1, 1, 'C');

    // Save PDF
    $filename = 'asset_listing_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = __DIR__ . '/../reports/generated/' . $filename;

    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdf->Output($filepath, 'F');

    return $filepath;
}

/**
 * Generate Donor Statement PDF
 * @param array $data Donor statement data
 * @param array $options PDF options
 * @return string PDF file path
 */
function generateDonorStatementPDF($data, $options = [])
{

    // Create new PDF document
    $pdf = new AccountingPDFGenerator();
    $pdf->setReportTitle('Annual Donation Statement');
    $pdf->setReportDate('Year ' . $data['year']);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Donor information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Donation Statement', 0, 1, 'L');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 6, 'Donor Name:', 0, 0, 'L');
    $pdf->Cell(0, 6, $data['donor']['name'], 0, 1, 'L');

    if (!empty($data['donor']['address'])) {
        $pdf->Cell(40, 6, 'Address:', 0, 0, 'L');
        $pdf->Cell(0, 6, $data['donor']['address'], 0, 1, 'L');

        if (!empty($data['donor']['city'])) {
            $pdf->Cell(40, 6, '', 0, 0, 'L');
            $address_line2 = $data['donor']['city'];
            if (!empty($data['donor']['state'])) {
                $address_line2 .= ', ' . $data['donor']['state'];
            }
            if (!empty($data['donor']['zip'])) {
                $address_line2 .= ' ' . $data['donor']['zip'];
            }
            $pdf->Cell(0, 6, $address_line2, 0, 1, 'L');
        }
    }

    $pdf->Ln(5);

    // Statement text
    $pdf->SetFont('helvetica', '', 10);
    $statement_text = "Thank you for your generous support of the W5OBM Amateur Radio Club during {$data['year']}. ";
    $statement_text .= "This statement summarizes your donations for tax purposes. ";
    $statement_text .= "Please retain this statement for your records.";

    $pdf->MultiCell(0, 6, $statement_text, 0, 'L');
    $pdf->Ln(5);

    // Donations table
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 8, 'Date', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Amount', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Method', 1, 0, 'C');
    $pdf->Cell(60, 8, 'Purpose', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);

    foreach ($data['donations'] as $donation) {
        $pdf->Cell(30, 6, date('m/d/Y', strtotime($donation['donation_date'])), 1, 0, 'C');
        $pdf->Cell(40, 6, '$' . number_format($donation['amount'], 2), 1, 0, 'R');
        $pdf->Cell(40, 6, $donation['payment_method'] ?? 'Cash', 1, 0, 'C');
        $pdf->Cell(60, 6, substr($donation['purpose'] ?? 'General Fund', 0, 30), 1, 1, 'L');
    }

    // Total row
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 8, 'TOTAL', 1, 0, 'C');
    $pdf->Cell(40, 8, '$' . number_format($data['total_donations'], 2), 1, 0, 'R');
    $pdf->Cell(100, 8, '', 1, 1, 'C');

    $pdf->Ln(10);

    // Footer text
    $pdf->SetFont('helvetica', 'I', 9);
    $footer_text = "This statement is provided for your tax records. W5OBM Amateur Radio Club is a non-profit organization. ";
    $footer_text .= "Please consult your tax advisor regarding the deductibility of these donations.";
    $pdf->MultiCell(0, 5, $footer_text, 0, 'L');

    // Save PDF
    $filename = 'donor_statement_' . $data['year'] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data['donor']['name']) . '.pdf';
    $filepath = __DIR__ . '/../reports/generated/' . $filename;

    // Ensure directory exists
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdf->Output($filepath, 'F');

    return $filepath;
}
