 <!-- /accounting/controllers/asset_controller.php -->
 <?php
    /**
     * Asset Controller
     * Handles all asset-related operations
     */

    /**
     * Add a new asset to the database.
     */
    function add_asset($name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        global $conn;

        $query = "INSERT INTO acc_assets (name, value, acquisition_date, depreciation_rate, description, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sdsds', $name, $value, $acquisition_date, $depreciation_rate, $description);

        return $stmt->execute();
    }

    /**
     * Update an existing asset.
     */
    function update_asset($id, $name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        global $conn;

        $query = "UPDATE acc_assets SET name = ?, value = ?, acquisition_date = ?, depreciation_rate = ?, description = ? 
              WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sdsdsi', $name, $value, $acquisition_date, $depreciation_rate, $description, $id);

        return $stmt->execute();
    }

    /**
     * Delete an asset by its ID.
     */
    function delete_asset($id)
    {
        global $conn;

        $query = "DELETE FROM acc_assets WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);

        return $stmt->execute();
    }

    /**
     * Fetch a single asset by its ID.
     */
    function fetch_asset_by_id($id)
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
     * Fetch all assets.
     */
    function fetch_all_assets()
    {
        global $conn;

        $query = "SELECT * FROM acc_assets ORDER BY name ASC";
        $result = $conn->query($query);

        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        return $assets;
    }

    /**
     * Calculate the current value of an asset after depreciation.
     */
    function calculate_current_value($asset)
    {
        $value = $asset['value'];
        $rate = $asset['depreciation_rate'] / 100;
        $years = calculate_years_since_acquisition($asset['acquisition_date']);

        // Calculate depreciation
        $depreciated_value = $value * pow((1 - $rate), $years);

        return max(0, $depreciated_value);
    }

    /**
     * Calculate the number of years since acquisition.
     */
    function calculate_years_since_acquisition($acquisition_date)
    {
        $acquisition = new DateTime($acquisition_date);
        $now = new DateTime();
        $interval = $acquisition->diff($now);

        return $interval->y + ($interval->m / 12) + ($interval->d / 365);
    }

    /**
     * Calculate the total value of all assets.
     */
    function calculate_total_assets($conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        $query = "SELECT SUM(value) AS total FROM acc_assets";
        $result = $conn->query($query);
        $row = $result->fetch_assoc();

        return $row['total'] ?? 0;
    }
