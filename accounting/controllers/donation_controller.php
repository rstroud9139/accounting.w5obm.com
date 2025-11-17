 <!-- /accounting/controllers/donation_controller.php -->
 <?php
    /**
     * Donation Controller
     * Handles all donation-related operations
     */

    /**
     * Add a new donation to the database.
     */
    function add_donation($contact_id, $amount, $donation_date, $description, $tax_deductible = true, $notes = '')
    {
        global $conn;

        $query = "INSERT INTO acc_donations (contact_id, amount, donation_date, description, tax_deductible, notes, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $tax_deductible_int = $tax_deductible ? 1 : 0;
        $stmt->bind_param('idssss', $contact_id, $amount, $donation_date, $description, $tax_deductible_int, $notes);

        return $stmt->execute();
    }

    /**
     * Update an existing donation.
     */
    function update_donation($id, $contact_id, $amount, $donation_date, $description, $tax_deductible = true, $notes = '')
    {
        global $conn;

        $query = "UPDATE acc_donations SET contact_id = ?, amount = ?, donation_date = ?, description = ?, tax_deductible = ?, notes = ? 
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        $tax_deductible_int = $tax_deductible ? 1 : 0;
        $stmt->bind_param('idssssi', $contact_id, $amount, $donation_date, $description, $tax_deductible_int, $notes, $id);

        return $stmt->execute();
    }

    /**
     * Delete a donation by its ID.
     */
    function delete_donation($id)
    {
        global $conn;

        $query = "DELETE FROM acc_donations WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Fetch a single donation by its ID.
     */
    function fetch_donation_by_id($id)
    {
        global $conn;

        $query = "SELECT d.*, c.name as contact_name, c.email as contact_email, c.tax_id as contact_tax_id 
              FROM acc_donations d
              LEFT JOIN acc_contacts c ON d.contact_id = c.id
              WHERE d.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Fetch all donations with optional filtering by date range or donor.
     */
    function fetch_all_donations($start_date = null, $end_date = null, $contact_id = null)
    {
        global $conn;

        $query = "SELECT d.*, c.name as contact_name, c.email as contact_email 
              FROM acc_donations d
              LEFT JOIN acc_contacts c ON d.contact_id = c.id
              WHERE 1 = 1";
        $types = '';
        $params = [];

        if ($start_date && $end_date) {
            $query .= " AND d.donation_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }

        if ($contact_id) {
            $query .= " AND d.contact_id = ?";
            $params[] = $contact_id;
            $types .= 'i';
        }

        $query .= " ORDER BY d.donation_date DESC";

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $donations = [];
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }

        return $donations;
    }

    /**
     * Mark a donation as having a receipt sent.
     */
    function mark_receipt_sent($id, $receipt_date = null)
    {
        global $conn;

        if ($receipt_date === null) {
            $receipt_date = date('Y-m-d');
        }

        $query = "UPDATE acc_donations SET receipt_sent = 1, receipt_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $receipt_date, $id);

        return $stmt->execute();
    }

    /**
     * Calculate the total donations for a given time period.
     */
    function calculate_total_donations($conn = null, $start_date = null, $end_date = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(amount) AS total FROM acc_donations";
        $types = '';
        $params = [];

        if ($start_date && $end_date) {
            $query .= " WHERE donation_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Generate a donation receipt PDF.
     */
    function generate_donation_receipt($donation_id)
    {
        global $conn;

        // Fetch donation details
        $query = "SELECT d.*, c.name, c.email, c.address FROM acc_donations d
              JOIN acc_contacts c ON d.contact_id = c.id
              WHERE d.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();
        $donation = $stmt->get_result()->fetch_assoc();

        if (!$donation) {
            return false;
        }

        // Generate PDF receipt
        require_once __DIR__ . '/../utils/pdf_generator.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, "Donation Receipt", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Date: " . date("F j, Y"), 0, 1);
        $pdf->Cell(0, 10, "Receipt #: " . $donation_id, 0, 1);
        $pdf->Cell(0, 10, "Donor: " . $donation['name'], 0, 1);
        $pdf->Cell(0, 10, "Amount: $" . number_format($donation['amount'], 2), 0, 1);
        $pdf->Cell(0, 10, "Date of Donation: " . $donation['donation_date'], 0, 1);
        $pdf->Cell(0, 10, "Description: " . $donation['description'], 0, 1);
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->MultiCell(0, 5, "This letter acknowledges that no goods or services were provided in exchange for this donation. Our organization is a 501(c)(3) non-profit organization. Your donation is tax-deductible to the extent allowed by law.");

        $file_path = __DIR__ . "/../../reports/generated/receipt_{$donation_id}.pdf";
        $pdf->Output('F', $file_path);

        // Mark receipt as sent
        mark_receipt_sent($donation_id);

        return $file_path;
    }

    /**
     * Generate an annual donor statement PDF.
     */
    function generate_yearly_donor_statement($contact_id, $year)
    {
        global $conn;

        // Fetch all donations for the year
        $query = "SELECT * FROM acc_donations 
              WHERE contact_id = ? AND YEAR(donation_date) = ?
              ORDER BY donation_date";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $contact_id, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        $donations = [];
        $total_amount = 0;
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
            $total_amount += $row['amount'];
        }

        if (count($donations) === 0) {
            return false;
        }

        // Fetch contact details
        $query = "SELECT * FROM acc_contacts WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $contact_id);
        $stmt->execute();
        $contact = $stmt->get_result()->fetch_assoc();

        // Generate PDF statement
        require_once __DIR__ . '/../utils/pdf_generator.php';

        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, "{$year} Donation Statement", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Date: " . date("F j, Y"), 0, 1);
        $pdf->Cell(0, 10, "Donor: " . $contact['name'], 0, 1);
        $pdf->Cell(0, 10, "Address: " . $contact['address'], 0, 1);
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 10, "Date", 1);
        $pdf->Cell(100, 10, "Description", 1);
        $pdf->Cell(40, 10, "Amount", 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 12);
        foreach ($donations as $donation) {
            $pdf->Cell(40, 10, $donation['donation_date'], 1);
            $pdf->Cell(100, 10, $donation['description'], 1);
            $pdf->Cell(40, 10, "$" . number_format($donation['amount'], 2), 1, 0, 'R');
            $pdf->Ln();
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(140, 10, "Total", 1);
        $pdf->Cell(40, 10, "$" . number_format($total_amount, 2), 1, 0, 'R');
        $pdf->Ln(20);

        $pdf->SetFont('Arial', 'I', 10);
        $pdf->MultiCell(0, 5, "This letter acknowledges that no goods or services were provided in exchange for these donations. Our organization is a 501(c)(3) non-profit organization. Your donations are tax-deductible to the extent allowed by law.");

        $file_path = __DIR__ . "/../../reports/generated/{$contact['name']}_{$year}_statement.pdf";
        $pdf->Output('F', $file_path);

        return $file_path;
    }
