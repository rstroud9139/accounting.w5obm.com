<?php
// /accounting/models/asset_model.php
    /**
     * Asset Model
     * Database operations for assets
     */

    /**
     * Fetch all assets from the database.
     */
    function get_all_assets()
    {
        global $conn;

        $query = "SELECT * FROM acc_assets ORDER BY name ASC";
        $result = $conn->query($query);

        if (!$result) {
            return [];
        }

        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        return $assets;
    }

    /**
     * Get a specific asset by ID.
     */
    function get_asset_by_id($id)
    {
        global $conn;

        $query = "SELECT * FROM acc_assets WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Add a new asset.
     */
    function add_new_asset($name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        global $conn;

        $query = "INSERT INTO acc_assets (name, value, acquisition_date, depreciation_rate, description) 
              VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sdsds', $name, $value, $acquisition_date, $depreciation_rate, $description);

        return $stmt->execute();
    }

    /**
     * Update an existing asset.
     */
    function update_existing_asset($id, $name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        global $conn;

        $query = "UPDATE acc_assets 
              SET name = ?, value = ?, acquisition_date = ?, depreciation_rate = ?, description = ? 
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sdsdsi', $name, $value, $acquisition_date, $depreciation_rate, $description, $id);

        return $stmt->execute();
    }

    /**
     * Remove an asset from the database.
     */
    function delete_asset_by_id($id)
    {
        global $conn;

        $query = "DELETE FROM acc_assets WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Calculate the total value of all assets.
     */
    function calculate_total_asset_value()
    {
        global $conn;

        $query = "SELECT SUM(value) AS total FROM acc_assets";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }

    /**
     * Calculate the deprecated value of an asset.
     */
    function calculate_depreciated_value($asset, $date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $acquisition_date = new DateTime($asset['acquisition_date']);
        $target_date = new DateTime($date);

        $years = $acquisition_date->diff($target_date)->y + ($acquisition_date->diff($target_date)->m / 12);
        $depreciation_rate = $asset['depreciation_rate'] / 100;

        // Calculate using straight-line depreciation
        $depreciated_value = $asset['value'] * (1 - $depreciation_rate * $years);

        return max(0, $depreciated_value);
    }
