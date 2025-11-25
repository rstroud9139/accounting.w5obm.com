<?php
// /accounting/controllers/donation_controller.php

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
        $filters = [];

        if (!empty($start_date)) {
            $filters['start_date'] = $start_date;
        }

        if (!empty($end_date)) {
            $filters['end_date'] = $end_date;
        }

        if (!empty($contact_id)) {
            $filters['contact_id'] = $contact_id;
        }

        return get_donations($filters);
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
     * Generic donation retrieval with flexible filtering and ordering.
     */
    function get_donations(array $filters = [], array $options = [])
    {
        global $conn;

        $query = "SELECT d.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone, c.tax_id AS contact_tax_id
              FROM acc_donations d
              LEFT JOIN acc_contacts c ON d.contact_id = c.id
              WHERE 1 = 1";

        $params = [];
        $types = '';

        if (!empty($filters['start_date'])) {
            $query .= " AND d.donation_date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND d.donation_date <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }

        if (!empty($filters['contact_id'])) {
            $query .= " AND d.contact_id = ?";
            $params[] = (int)$filters['contact_id'];
            $types .= 'i';
        }

        if (!empty($filters['search'])) {
            $query .= " AND (d.description LIKE ? OR d.notes LIKE ? OR c.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $types .= 'sss';
        }

        if (isset($filters['tax_deductible']) && $filters['tax_deductible'] !== '' && $filters['tax_deductible'] !== 'all') {
            $value = $filters['tax_deductible'];
            if ($value === 'yes') {
                $value = 1;
            } elseif ($value === 'no') {
                $value = 0;
            } else {
                $value = (int)(bool)$value;
            }
            $query .= " AND d.tax_deductible = ?";
            $params[] = $value;
            $types .= 'i';
        }

        if (!empty($filters['receipt_status'])) {
            if ($filters['receipt_status'] === 'sent') {
                $query .= " AND d.receipt_sent = 1";
            } elseif ($filters['receipt_status'] === 'pending') {
                $query .= " AND (d.receipt_sent IS NULL OR d.receipt_sent = 0)";
            }
        }

        if (!empty($filters['min_amount'])) {
            $query .= " AND d.amount >= ?";
            $params[] = (float)$filters['min_amount'];
            $types .= 'd';
        }

        if (!empty($filters['max_amount'])) {
            $query .= " AND d.amount <= ?";
            $params[] = (float)$filters['max_amount'];
            $types .= 'd';
        }

        $allowedOrder = ['d.donation_date DESC', 'd.donation_date ASC', 'd.amount DESC', 'd.amount ASC', 'd.created_at DESC'];
        $orderBy = $options['order_by'] ?? 'd.donation_date DESC';
        if (!in_array($orderBy, $allowedOrder, true)) {
            $orderBy = 'd.donation_date DESC';
        }
        $query .= " ORDER BY $orderBy, d.id DESC";

        if (!empty($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
        }

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
     * Build summary metrics for a donation collection.
     */
    function get_donation_summary(array $donations)
    {
        $summary = [
            'total_count' => count($donations),
            'total_amount' => 0.0,
            'average_amount' => 0.0,
            'largest_single' => 0.0,
            'receipt_sent' => 0,
            'receipt_pending' => 0,
            'tax_deductible' => 0,
            'non_deductible' => 0,
            'unique_donors' => 0,
            'latest_date' => null,
        ];

        $donorIds = [];

        foreach ($donations as $donation) {
            $amount = (float)($donation['amount'] ?? 0);
            $summary['total_amount'] += $amount;
            if ($amount > $summary['largest_single']) {
                $summary['largest_single'] = $amount;
            }

            if (!empty($donation['receipt_sent'])) {
                $summary['receipt_sent']++;
            } else {
                $summary['receipt_pending']++;
            }

            if (!empty($donation['tax_deductible'])) {
                $summary['tax_deductible']++;
            } else {
                $summary['non_deductible']++;
            }

            if (!empty($donation['contact_id'])) {
                $donorIds[$donation['contact_id']] = true;
            }

            if (!empty($donation['donation_date'])) {
                $date = $donation['donation_date'];
                if ($summary['latest_date'] === null || $date > $summary['latest_date']) {
                    $summary['latest_date'] = $date;
                }
            }
        }

        if ($summary['total_count'] > 0) {
            $summary['average_amount'] = $summary['total_amount'] / $summary['total_count'];
        }

        $summary['unique_donors'] = count($donorIds);

        return $summary;
    }

    /**
     * Retrieve donor options for select inputs.
     */
    function get_donation_contacts()
    {
        global $conn;

        $query = "SELECT id, name, email FROM acc_contacts ORDER BY name ASC";
        $result = $conn->query($query);

        $contacts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $contacts[] = $row;
            }
        }

        return $contacts;
    }

    /**
     * Update receipt sent flag manually.
     */
    function set_donation_receipt_status(int $id, bool $sent, ?string $date = null)
    {
        global $conn;

        $query = "UPDATE acc_donations SET receipt_sent = ?, receipt_date = ? WHERE id = ?";
        if ($date === null) {
            $date = $sent ? date('Y-m-d') : null;
        }

        $stmt = $conn->prepare($query);
        $receiptDate = $date;
        $sentInt = $sent ? 1 : 0;
        $stmt->bind_param('isi', $sentInt, $receiptDate, $id);

        return $stmt->execute();
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
