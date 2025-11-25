<?php
// /accounting/models/category_model.php
    /**
     * Category Model
     * Database operations for transaction categories
     */

    /**
     * Fetch all categories from the database.
     */
    function get_all_categories()
    {
        global $conn;

        $query = "SELECT * FROM acc_transaction_categories ORDER BY name ASC";
        $result = $conn->query($query);

        if (!$result) {
            return [];
        }

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Get a specific category by ID.
     */
    function get_category_by_id($id)
    {
        global $conn;

        $query = "SELECT * FROM acc_transaction_categories WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Get categories by type.
     */
    function get_categories_by_type($type)
    {
        global $conn;

        $query = "SELECT * FROM acc_transaction_categories WHERE type = ? ORDER BY name ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            return [];
        }

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Add a new category.
     */
    function add_new_category($name, $description, $type)
    {
        global $conn;

        $query = "INSERT INTO acc_transaction_categories (name, description, type) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sss', $name, $description, $type);

        return $stmt->execute();
    }

    /**
     * Update an existing category.
     */
    function update_existing_category($id, $name, $description, $type)
    {
        global $conn;

        $query = "UPDATE acc_transaction_categories SET name = ?, description = ?, type = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sssi', $name, $description, $type, $id);

        return $stmt->execute();
    }

    /**
     * Remove a category from the database.
     */
    function delete_category_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_transaction_categories WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Check if a category is being used by any transactions.
     */
    function check_category_in_use($id)
    {
        global $conn;

        $query = "SELECT COUNT(*) as count FROM acc_transactions WHERE category_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['count'] > 0;
    }
