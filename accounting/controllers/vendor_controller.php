<-- /accounting/controllers/vendor_controller.php
    <?php
    /**
     * Vendor Controller
     * Handles all vendor-related operations
     */

    /**
     * Add a new vendor to the database.
     */
    function add_vendor($name, $contact_name, $email, $phone, $address, $notes = '')
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
    function update_vendor($id, $name, $contact_name, $email, $phone, $address, $notes = '')
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
     * Delete a vendor by its ID.
     */
    function delete_vendor($id)
    {
        global $conn;

        $query = "DELETE FROM acc_vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Fetch a single vendor by its ID.
     */
    function fetch_vendor_by_id($id)
    {
        global $conn;

        $query = "SELECT * FROM acc_vendors WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Fetch all vendors.
     */
    function fetch_all_vendors()
    {
        global $conn;

        $query = "SELECT * FROM acc_vendors ORDER BY name ASC";
        $result = $conn->query($query);

        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }

        return $vendors;
    }

    /**
     * Search for vendors by name or contact information.
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
     * Fetch transactions for a vendor.
     */
    function fetch_vendor_transactions($vendor_id)
    {
        global $conn;

        $query = "SELECT t.*, c.name AS category_name 
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
