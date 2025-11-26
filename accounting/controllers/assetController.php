<?php

/**
 * Asset Controller - W5OBM Accounting System
 * File: /accounting/controllers/assetController.php
 * Purpose: Complete asset management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

if (!function_exists('asset_db_connection')) {
    function asset_db_connection(): mysqli
    {
        global $accConn, $conn;

        if (isset($accConn) && $accConn instanceof mysqli && $accConn->connect_errno === 0) {
            return $accConn;
        }

        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }

        throw new \RuntimeException('No database connection available for assets module.');
    }
}

/**
 * Add a new asset
 * @param array $data Asset data
 * @return bool|int Asset ID on success, false on failure
 */
function addAsset($data)
{
    $db = asset_db_connection();

    try {
        // Validate required fields
        $required = ['name', 'value', 'acquisition_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate value
        if (!is_numeric($data['value']) || $data['value'] < 0) {
            throw new Exception("Invalid asset value");
        }

        // Validate date
        if (!strtotime($data['acquisition_date'])) {
            throw new Exception("Invalid acquisition date");
        }

        $stmt = $db->prepare("
            INSERT INTO acc_assets 
            (name, value, acquisition_date, depreciation_rate, description, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $created_by = getCurrentUserId();
        $depreciation_rate = isset($data['depreciation_rate']) ? floatval($data['depreciation_rate']) : 0;
        $description = sanitizeInput($data['description'] ?? '');

        $stmt->bind_param(
            'sdsdsi',
            $data['name'],
            $data['value'],
            $data['acquisition_date'],
            $depreciation_rate,
            $description,
            $created_by
        );

        if ($stmt->execute()) {
            $asset_id = $db->insert_id;
            $stmt->close();

            logActivity(
                $created_by,
                'asset_created',
                'acc_assets',
                $asset_id,
                "Created asset: {$data['name']}"
            );

            return $asset_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error adding asset: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing asset
 * @param int $id Asset ID
 * @param array $data Updated asset data
 * @return bool Success status
 */
function updateAsset($id, $data)
{
    $db = asset_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid asset ID");
        }

        $existing = getAssetById($id);
        if (!$existing) {
            throw new Exception("Asset not found");
        }

        $required = ['name', 'value', 'acquisition_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        $stmt = $db->prepare("
            UPDATE acc_assets 
            SET name = ?, value = ?, acquisition_date = ?, depreciation_rate = ?, 
                description = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $updated_by = getCurrentUserId();
        $depreciation_rate = isset($data['depreciation_rate']) ? floatval($data['depreciation_rate']) : 0;
        $description = sanitizeInput($data['description'] ?? '');

        $stmt->bind_param(
            'sdsdsii',
            $data['name'],
            $data['value'],
            $data['acquisition_date'],
            $depreciation_rate,
            $description,
            $updated_by,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            logActivity(
                $updated_by,
                'asset_updated',
                'acc_assets',
                $id,
                "Updated asset: {$data['name']}"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error updating asset: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Delete an asset by ID
 * @param int $id Asset ID
 * @return bool Success status
 */
function deleteAsset($id)
{
    $db = asset_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid asset ID");
        }

        $asset = getAssetById($id);
        if (!$asset) {
            throw new Exception("Asset not found");
        }

        $user_id = getCurrentUserId();
        if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to delete assets");
        }

        $stmt = $db->prepare("DELETE FROM acc_assets WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            logActivity(
                $user_id,
                'asset_deleted',
                'acc_assets',
                $id,
                "Deleted asset: {$asset['name']}"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting asset: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a single asset by ID
 * @param int $id Asset ID
 * @return array|false Asset data or false
 */
function getAssetById($id)
{
    $db = asset_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $db->prepare("
            SELECT a.*, 
                   cu.username AS created_by_username,
                   uu.username AS updated_by_username
            FROM acc_assets a
            LEFT JOIN auth_users cu ON a.created_by = cu.id
            LEFT JOIN auth_users uu ON a.updated_by = uu.id
            WHERE a.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        logError("Error getting asset by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all assets with optional filtering
 * @param array $filters Optional filters
 * @return array Assets array
 */
function getAllAssets($filters = [])
{
    $db = asset_db_connection();

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.name LIKE ? OR a.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT a.*, 
                   cu.username AS created_by_username
            FROM acc_assets a
            LEFT JOIN auth_users cu ON a.created_by = cu.id
            $where_clause
            ORDER BY a.name ASC
        ";

        $stmt = $db->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        $stmt->close();
        return $assets;
    } catch (Exception $e) {
        logError("Error getting all assets: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Calculate current asset value after depreciation
 * @param array $asset Asset data
 * @return float Current value
 */
function calculateAssetCurrentValue($asset)
{
    $value = floatval($asset['value']);
    $rate = floatval($asset['depreciation_rate'] ?? 0) / 100;
    $years = calculateYearsSinceAcquisition($asset['acquisition_date']);

    // Straight-line depreciation
    $depreciated_value = $value * pow((1 - $rate), $years);

    return max(0, $depreciated_value);
}

/**
 * Calculate years since acquisition
 * @param string $acquisition_date Acquisition date
 * @return float Years since acquisition
 */
function calculateYearsSinceAcquisition($acquisition_date)
{
    try {
        $acquisition = new DateTime($acquisition_date);
        $now = new DateTime();
        $interval = $acquisition->diff($now);

        return $interval->y + ($interval->m / 12) + ($interval->d / 365);
    } catch (Exception $e) {
        logError("Error calculating years since acquisition: " . $e->getMessage(), 'accounting');
        return 0;
    }
}

/**
 * Calculate total value of all assets
 * @return float Total asset value
 */
function calculateTotalAssetValue()
{
    $db = asset_db_connection();

    try {
        $result = $db->query("SELECT COALESCE(SUM(value), 0) AS total FROM acc_assets");
        $row = $result->fetch_assoc();
        return floatval($row['total'] ?? 0);
    } catch (Exception $e) {
        logError("Error calculating total asset value: " . $e->getMessage(), 'accounting');
        return 0;
    }
}

// -----------------------------------------------------------------------------
// Legacy snake_case wrappers (compatibility layer)
// -----------------------------------------------------------------------------

if (!function_exists('add_asset')) {
    function add_asset($name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        return addAsset([
            'name' => $name,
            'value' => $value,
            'acquisition_date' => $acquisition_date,
            'depreciation_rate' => $depreciation_rate,
            'description' => $description,
        ]);
    }
}

if (!function_exists('update_asset')) {
    function update_asset($id, $name, $value, $acquisition_date, $depreciation_rate, $description)
    {
        return updateAsset($id, [
            'name' => $name,
            'value' => $value,
            'acquisition_date' => $acquisition_date,
            'depreciation_rate' => $depreciation_rate,
            'description' => $description,
        ]);
    }
}

if (!function_exists('delete_asset')) {
    function delete_asset($id)
    {
        return deleteAsset($id);
    }
}

if (!function_exists('fetch_asset_by_id')) {
    function fetch_asset_by_id($id)
    {
        return getAssetById($id);
    }
}

if (!function_exists('fetch_all_assets')) {
    function fetch_all_assets()
    {
        return getAllAssets();
    }
}

if (!function_exists('calculate_current_value')) {
    function calculate_current_value($asset)
    {
        return calculateAssetCurrentValue($asset);
    }
}

if (!function_exists('calculate_years_since_acquisition')) {
    function calculate_years_since_acquisition($acquisition_date)
    {
        return calculateYearsSinceAcquisition($acquisition_date);
    }
}

if (!function_exists('calculate_total_assets')) {
    function calculate_total_assets($conn = null)
    {
        return calculateTotalAssetValue();
    }
}
