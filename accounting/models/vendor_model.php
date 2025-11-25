<?php
// /accounting/models/vendor_model.php
    /**
     * Vendor Model
     * Database operations for vendors
     */

    /**
     * Fetch all vendors from the database.
     */
    function get_all_vendors()
    {
        global $conn;

        $query = "SELECT * FROM acc_vendors ORDER BY name ASC";
        $result = $conn->query($query);

        if (!$result) {
            return [];
        }

        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }

        return $vendors;
    }

    /**
     * Get a specific vendor by ID.
     */
    function get_vendor_by_id($id)
    {
        global $conn;

        $query = "SELECT * FROM acc_vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Search vendors by name or contact info.
     */
    function search_vendors($search_term)
    {
        global $conn;

        $search_term = "%$search_term%";
        $query = "SELECT * FROM acc_vendors 
              WHERE name LIKE ? OR contact_name LIKE ? OR email LIKE ? OR phone LIKE ?
              ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssss', $search_term, $search_term, $search_term, $search_term);
        $stmt->execute();
        $result = $stmt->get_result();

        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }

        return $vendors;
    }

    /**
     * Add a new vendor.
     */
    function add_new_vendor($name, $contact_name, $email, $phone, $address, $notes = '')
    {
        global $conn;

        $query = "INSERT INTO acc_vendors (name, contact_name, email, phone, address, notes, created_at)
              VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssss', $name, $contact_name, $email, $phone, $address, $notes);

        return $stmt->execute();
    }

    /**
     * Update an existing vendor.
     */
    function update_existing_vendor($id, $name, $contact_name, $email, $phone, $address, $notes = '')
    {
        global $conn;

        $query = "UPDATE acc_vendors
              SET name = ?, contact_name = ?, email = ?, phone = ?, address = ?, notes = ?
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssssi', $name, $contact_name, $email, $phone, $address, $notes, $id);

        return $stmt->execute();
    }

    /**
     * Remove a vendor from the database.
     */
    function delete_vendor_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Get transactions for a vendor.
     */
    function get_vendor_transactions($vendor_id)
    {
        global $conn;

        $query = "SELECT t.*, c.name as category_name
              FROM acc_transactions t
              LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
              WHERE t.vendor_id = ?
              ORDER BY t.transaction_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }

        return $transactions;
    }

    /**
     * Calculate vendor spending.
     */
    function calculate_vendor_spending($vendor_id, $start_date = null, $end_date = null)
    {
        global $conn;

        $query = "SELECT SUM(amount) as total FROM acc_transactions WHERE vendor_id = ? AND type = 'Expense'";
        $params = [$vendor_id];
        $types = 'i';

        if ($start_date && $end_date) {
            $query .= " AND transaction_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
            $types .= 'ss';
        }

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }
