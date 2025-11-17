 <!-- /accounting/models/donation_model.php -->
 <?php
    /**
     * Donation Model
     * Database operations for donations
     */

    /**
     * Fetch all donations from the database.
     */
    function get_all_donations($start_date = null, $end_date = null, $contact_id = null)
    {
        global $conn;

        $query = "SELECT d.*, c.name as contact_name, c.email as contact_email 
              FROM acc_donations d 
              LEFT JOIN acc_contacts c ON d.contact_id = c.id 
              WHERE 1=1";
        $params = [];
        $types = '';

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
     * Get a specific donation by ID.
     */
    function get_donation_by_id($id)
    {
        global $conn;

        $query = "SELECT d.*, c.name as contact_name, c.email as contact_email, c.address as contact_address
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
     * Add a new donation.
     */
    function add_new_donation($contact_id, $amount, $donation_date, $description, $tax_deductible = 1, $notes = '')
    {
        global $conn;

        $query = "INSERT INTO acc_donations (contact_id, amount, donation_date, description, tax_deductible, notes, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('idssss', $contact_id, $amount, $donation_date, $description, $tax_deductible, $notes);

        return $stmt->execute();
    }

    /**
     * Update an existing donation.
     */
    function update_existing_donation($id, $contact_id, $amount, $donation_date, $description, $tax_deductible = 1, $notes = '')
    {
        global $conn;

        $query = "UPDATE acc_donations 
              SET contact_id = ?, amount = ?, donation_date = ?, description = ?, tax_deductible = ?, notes = ? 
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        // Types: i (contact_id), d (amount), s (date), s (desc), i (tax_deductible), s (notes), i (id)
        $stmt->bind_param('idssisi', $contact_id, $amount, $donation_date, $description, $tax_deductible, $notes, $id);

        return $stmt->execute();
    }

    /**
     * Remove a donation from the database.
     */
    function delete_donation_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_donations WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Update donation receipt status.
     */
    function update_receipt_status($id, $status = 1, $date = null)
    {
        global $conn;

        if ($date === null) {
            $date = date('Y-m-d');
        }

        $query = "UPDATE acc_donations SET receipt_sent = ?, receipt_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isi', $status, $date, $id);

        return $stmt->execute();
    }

    /**
     * Get donations by donor.
     */
    function get_donations_by_donor($contact_id, $year = null)
    {
        global $conn;

        $query = "SELECT * FROM acc_donations WHERE contact_id = ?";
        $params = [$contact_id];
        $types = 'i';

        if ($year) {
            $query .= " AND YEAR(donation_date) = ?";
            $params[] = $year;
            $types .= 'i';
        }

        $query .= " ORDER BY donation_date DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $donations = [];
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }

        return $donations;
    }

    /**
     * Calculate total donations.
     */
    function calculate_total_donations($start_date = null, $end_date = null)
    {
        global $conn;

        $query = "SELECT SUM(amount) as total FROM acc_donations WHERE 1=1";
        $params = [];
        $types = '';

        if ($start_date && $end_date) {
            $query .= " AND donation_date BETWEEN ? AND ?";
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
     * Get donation statistics.
     */
    function get_donation_statistics($year = null)
    {
        global $conn;

        $year = $year ?? date('Y');
        $stats = [];

        // Total donations for the year
        $query = "SELECT SUM(amount) as total FROM acc_donations WHERE YEAR(donation_date) = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['year_total'] = $result->fetch_assoc()['total'] ?? 0;

        // Monthly breakdown
        $query = "SELECT MONTH(donation_date) as month, SUM(amount) as total 
              FROM acc_donations 
              WHERE YEAR(donation_date) = ? 
              GROUP BY MONTH(donation_date)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        $monthly = array_fill(1, 12, 0); // Initialize array with zeroes for all months
        while ($row = $result->fetch_assoc()) {
            $monthly[$row['month']] = $row['total'];
        }
        $stats['monthly'] = $monthly;

        // Top donors
        $query = "SELECT c.name, SUM(d.amount) as total 
              FROM acc_donations d
              JOIN acc_contacts c ON d.contact_id = c.id
              WHERE YEAR(d.donation_date) = ?
              GROUP BY d.contact_id
              ORDER BY total DESC
              LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();

        $top_donors = [];
        while ($row = $result->fetch_assoc()) {
            $top_donors[] = $row;
        }
        $stats['top_donors'] = $top_donors;

        return $stats;
    }
