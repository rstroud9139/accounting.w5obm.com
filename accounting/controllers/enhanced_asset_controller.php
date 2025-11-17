<?php
/**
 * Enhanced Asset Controller - W5OBM Accounting System - COMPLETE VERSION
 * File: /accounting/controllers/enhanced_asset_controller.php
 * Purpose: Complete asset management with depreciation calculations, maintenance tracking, and advanced features
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Security check - ensure this file is included properly
if (!defined('SECURE_ACCESS')) {
    if (!isset($_SESSION) || !function_exists('isAuthenticated')) {
        die('Direct access not permitted');
    }
}

/**
 * Create a new asset
 * @param array $asset_data Asset information
 * @return int|bool Asset ID on success, false on failure
 */
function createAsset($asset_data) {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['name', 'acquisition_date', 'value'];
        foreach ($required_fields as $field) {
            if (empty($asset_data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
        
        // Validate value is numeric and positive
        if (!is_numeric($asset_data['value']) || $asset_data['value'] <= 0) {
            throw new Exception("Asset value must be a positive number");
        }
        
        // Validate acquisition date
        if (!strtotime($asset_data['acquisition_date'])) {
            throw new Exception("Invalid acquisition date");
        }
        
        // Validate depreciation rate (0-100%)
        $depreciation_rate = isset($asset_data['depreciation_rate']) ? 
            floatval($asset_data['depreciation_rate']) : 0;
        
        if ($depreciation_rate < 0 || $depreciation_rate > 100) {
            throw new Exception("Depreciation rate must be between 0 and 100");
        }
        
        // Ensure assets table exists
        ensureAssetTableExists();
        
        $stmt = $conn->prepare("
            INSERT INTO acc_assets (
                name, description, acquisition_date, value, 
                depreciation_rate, status, category, location,
                serial_number, vendor, warranty_expiration,
                created_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $status = $asset_data['status'] ?? 'Active';
        $category = $asset_data['category'] ?? 'Equipment';
        $location = $asset_data['location'] ?? '';
        $serial_number = $asset_data['serial_number'] ?? '';
        $vendor = $asset_data['vendor'] ?? '';
        $warranty_expiration = !empty($asset_data['warranty_expiration']) ? 
            $asset_data['warranty_expiration'] : null;
        $created_by = getCurrentUserId();
        
        $stmt->bind_param('sssddssssssi',
            $asset_data['name'],
            $asset_data['description'] ?? '',
            $asset_data['acquisition_date'],
            $asset_data['value'],
            $depreciation_rate,
            $status,
            $category,
            $location,
            $serial_number,
            $vendor,
            $warranty_expiration,
            $created_by
        );
        
        if ($stmt->execute()) {
            $asset_id = $conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity($created_by, 'asset_created', 'acc_assets', $asset_id,
                "Created asset: {$asset_data['name']}");
            
            return $asset_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        logError("Error creating asset: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing asset
 * @param int $asset_id Asset ID
 * @param array $asset_data Updated asset information
 * @return bool Success status
 */
function updateAsset($asset_id, $asset_data) {
    global $conn;
    
    try {
        if (!$asset_id || !is_numeric($asset_id)) {
            throw new Exception("Invalid asset ID");
        }
        
        // Check if asset exists
        $existing_asset = getAssetById($asset_id);
        if (!$existing_asset) {
            throw new Exception("Asset not found");
        }
        
        // Validate permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_edit') && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to update asset");
        }
        
        // Build update query dynamically
        $update_fields = [];
        $params = [];
        $types = '';
        
        $allowed_fields = [
            'name', 'description', 'acquisition_date', 'value', 
            'depreciation_rate', 'status', 'category', 'location',
            'serial_number', 'vendor', 'warranty_expiration'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($asset_data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $asset_data[$field];
                
                if (in_array($field, ['value', 'depreciation_rate'])) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }
        
        if (empty($update_fields)) {
            throw new Exception("No valid fields to update");
        }
        
        // Add updated_by and updated_at
        $update_fields[] = "updated_by = ?";
        $update_fields[] = "updated_at = NOW()";
        $params[] = $user_id;
        $types .= 'i';
        
        // Add asset_id for WHERE clause
        $params[] = $asset_id;
        $types .= 'i';
        
        $query = "UPDATE acc_assets SET " . implode(', ', $update_fields) . " WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            logActivity($user_id, 'asset_updated', 'acc_assets', $asset_id,
                "Updated asset: {$existing_asset['name']}");
            
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
 * Get asset by ID
 * @param int $asset_id Asset ID
 * @return array|false Asset data or false if not found
 */
function getAssetById($asset_id) {
    global $conn;
    
    try {
        if (!$asset_id || !is_numeric($asset_id)) {
            return false;
        }
        
        $stmt = $conn->prepare("
            SELECT a.*, 
                   u1.username as created_by_username,
                   u2.username as updated_by_username
            FROM acc_assets a
            LEFT JOIN auth_users u1 ON a.created_by = u1.id
            LEFT JOIN auth_users u2 ON a.updated_by = u2.id
            WHERE a.id = ?
        ");
        
        $stmt->bind_param('i', $asset_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            // Calculate current value and depreciation
            $result['current_value'] = calculateCurrentAssetValue($result);
            $result['total_depreciation'] = floatval($result['value']) - $result['current_value'];
            $result['years_owned'] = calculateYearsOwned($result['acquisition_date']);
        }
        
        return $result;
        
    } catch (Exception $e) {
        logError("Error getting asset by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all assets with optional filtering
 * @param array $filters Optional filters
 * @param int $limit Optional limit
 * @return array Assets array
 */
function getAllAssets($filters = [], $limit = null) {
    global $conn;
    
    try {
        $where_conditions = [];
        $params = [];
        $types = '';
        
        // Build WHERE clause
        if (!empty($filters['status'])) {
            $where_conditions[] = "a.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($filters['category'])) {
            $where_conditions[] = "a.category = ?";
            $params[] = $filters['category'];
            $types .= 's';
        }
        
        if (!empty($filters['location'])) {
            $where_conditions[] = "a.location = ?";
            $params[] = $filters['location'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(a.name LIKE ? OR a.description LIKE ? OR a.serial_number LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'sss';
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT a.*, 
                   u1.username as created_by_username,
                   u2.username as updated_by_username
            FROM acc_assets a
            LEFT JOIN auth_users u1 ON a.created_by = u1.id
            LEFT JOIN auth_users u2 ON a.updated_by = u2.id
            $where_clause
            ORDER BY a.acquisition_date DESC, a.name
        ";
        
        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $assets = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate current value and depreciation for each asset
            $row['current_value'] = calculateCurrentAssetValue($row);
            $row['total_depreciation'] = floatval($row['value']) - $row['current_value'];
            $row['years_owned'] = calculateYearsOwned($row['acquisition_date']);
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
 * Delete an asset (soft delete - mark as inactive)
 * @param int $asset_id Asset ID
 * @return bool Success status
 */
function deleteAsset($asset_id) {
    global $conn;
    
    try {
        if (!$asset_id || !is_numeric($asset_id)) {
            throw new Exception("Invalid asset ID");
        }
        
        // Get asset details for logging
        $asset = getAssetById($asset_id);
        if (!$asset) {
            throw new Exception("Asset not found");
        }
        
        // Check permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_delete') && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to delete asset");
        }
        
        // Soft delete - mark as inactive
        $stmt = $conn->prepare("
            UPDATE acc_assets 
            SET status = 'Inactive', updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param('ii', $user_id, $asset_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            logActivity($user_id, 'asset_deleted', 'acc_assets', $asset_id,
                "Deleted asset: {$asset['name']}");
            
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
 * Calculate current value of an asset with depreciation
 * @param array $asset Asset data
 * @return float Current value
 */
function calculateCurrentAssetValue($asset) {
    try {
        $original_value = floatval($asset['value']);
        $depreciation_rate = floatval($asset['depreciation_rate']) / 100; // Convert percentage to decimal
        
        if ($depreciation_rate <= 0) {
            return $original_value; // No depreciation
        }
        
        // Calculate years since acquisition
        $years_owned = calculateYearsOwned($asset['acquisition_date']);
        
        // Calculate depreciation using straight-line method
        $annual_depreciation = $original_value * $depreciation_rate;
        $total_depreciation = $annual_depreciation * $years_owned;
        
        // Current value cannot be negative
        $current_value = max(0, $original_value - $total_depreciation);
        
        return round($current_value, 2);
        
    } catch (Exception $e) {
        logError("Error calculating asset value: " . $e->getMessage(), 'accounting');
        return floatval($asset['value']); // Return original value on error
    }
}

/**
 * Calculate years owned for an asset
 * @param string $acquisition_date Acquisition date (Y-m-d)
 * @return float Years owned
 */
function calculateYearsOwned($acquisition_date) {
    try {
        $acquisition = new DateTime($acquisition_date);
        $current = new DateTime();
        $diff = $acquisition->diff($current);
        
        // Convert to decimal years
        $years = $diff->y + ($diff->m / 12) + ($diff->d / 365.25);
        
        return round($years, 2);
        
    } catch (Exception $e) {
        logError("Error calculating years owned: " . $e->getMessage(), 'accounting');
        return 0;
    }
}

/**
 * Get asset summary statistics
 * @return array Summary statistics
 */
function getAssetSummary() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_assets,
                SUM(value) as total_original_value,
                AVG(value) as average_value,
                COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_assets,
                COUNT(CASE WHEN status = 'Inactive' THEN 1 END) as inactive_assets
            FROM acc_assets
        ");
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate current total value
        $all_assets = getAllAssets(['status' => 'Active']);
        $total_current_value = 0;
        $total_depreciation = 0;
        
        foreach ($all_assets as $asset) {
            $total_current_value += $asset['current_value'];
            $total_depreciation += $asset['total_depreciation'];
        }
        
        $result['total_current_value'] = $total_current_value;
        $result['total_depreciation'] = $total_depreciation;
        
        // Convert numeric strings to appropriate types
        $result['total_assets'] = intval($result['total_assets'] ?? 0);
        $result['active_assets'] = intval($result['active_assets'] ?? 0);
        $result['inactive_assets'] = intval($result['inactive_assets'] ?? 0);
        $result['total_original_value'] = floatval($result['total_original_value'] ?? 0);
        $result['average_value'] = floatval($result['average_value'] ?? 0);
        
        return $result;
        
    } catch (Exception $e) {
        logError("Error getting asset summary: " . $e->getMessage(), 'accounting');
        return [
            'total_assets' => 0,
            'active_assets' => 0,
            'inactive_assets' => 0,
            'total_original_value' => 0,
            'total_current_value' => 0,
            'total_depreciation' => 0,
            'average_value' => 0
        ];
    }
}

/**
 * Get assets requiring maintenance
 * @return array Assets requiring maintenance
 */
function getAssetsRequiringMaintenance() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT *, 
                   DATEDIFF(NOW(), acquisition_date) as days_owned,
                   CASE 
                       WHEN category = 'Equipment' AND DATEDIFF(NOW(), acquisition_date) > 365 THEN 'Annual Calibration'
                       WHEN category = 'Computer' AND DATEDIFF(NOW(), acquisition_date) > 1095 THEN 'Hardware Refresh'
                       WHEN category = 'Vehicle' AND DATEDIFF(NOW(), acquisition_date) > 180 THEN 'Regular Maintenance'
                       WHEN warranty_expiration IS NOT NULL AND warranty_expiration < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Warranty Expiring'
                       ELSE NULL
                   END as maintenance_reason
            FROM acc_assets 
            WHERE status = 'Active'
            HAVING maintenance_reason IS NOT NULL
            ORDER BY acquisition_date
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $assets = [];
        while ($row = $result->fetch_assoc()) {
            $row['current_value'] = calculateCurrentAssetValue($row);
            $row['years_owned'] = calculateYearsOwned($row['acquisition_date']);
            $assets[] = $row;
        }
        
        $stmt->close();
        return $assets;
        
    } catch (Exception $e) {
        logError("Error getting assets requiring maintenance: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Generate asset depreciation report
 * @param int $year Year for report (optional, defaults to current year)
 * @return array Depreciation report data
 */
function generateDepreciationReport($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    try {
        $assets = getAllAssets(['status' => 'Active']);
        
        $categories = [];
        $total_original = 0;
        $total_current = 0;
        $total_depreciation = 0;
        
        foreach ($assets as $asset) {
            $category = $asset['category'];
            
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $category,
                    'asset_count' => 0,
                    'total_original_value' => 0,
                    'total_current_value' => 0,
                    'total_depreciation' => 0
                ];
            }
            
            $categories[$category]['asset_count']++;
            $categories[$category]['total_original_value'] += $asset['value'];
            $categories[$category]['total_current_value'] += $asset['current_value'];
            $categories[$category]['total_depreciation'] += $asset['total_depreciation'];
            
            $total_original += $asset['value'];
            $total_current += $asset['current_value'];
            $total_depreciation += $asset['total_depreciation'];
        }
        
        // Calculate percentages
        foreach ($categories as &$category) {
            $category['depreciation_percentage'] = $category['total_original_value'] > 0 ? 
                ($category['total_depreciation'] / $category['total_original_value']) * 100 : 0;
        }
        
        return [
            'year' => $year,
            'categories' => array_values($categories),
            'totals' => [
                'original_value' => $total_original,
                'current_value' => $total_current,
                'total_depreciation' => $total_depreciation,
                'depreciation_percentage' => $total_original > 0 ? ($total_depreciation / $total_original) * 100 : 0
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        logError("Error generating depreciation report: " . $e->getMessage(), 'accounting');
        return [
            'year' => $year,
            'categories' => [],
            'totals' => [
                'original_value' => 0,
                'current_value' => 0,
                'total_depreciation' => 0,
                'depreciation_percentage' => 0
            ]
        ];
    }
}

/**
 * Export assets to CSV
 * @param array $filters Optional filters
 * @return string|false CSV file path or false on error
 */
function exportAssetsToCSV($filters = []) {
    try {
        $assets = getAllAssets($filters);
        
        $filename = 'assets_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../reports/generated/' . $filename;
        
        // Ensure directory exists
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception("Could not create CSV file");
        }
        
        // Write CSV headers
        $headers = [
            'ID', 'Name', 'Description', 'Category', 'Acquisition Date',
            'Original Value', 'Depreciation Rate', 'Current Value', 'Total Depreciation',
            'Years Owned', 'Status', 'Location', 'Serial Number', 'Vendor',
            'Warranty Expiration', 'Created Date', 'Created By'
        ];
        fputcsv($file, $headers);
        
        // Write asset data
        foreach ($assets as $asset) {
            $row = [
                $asset['id'],
                $asset['name'],
                $asset['description'],
                $asset['category'],
                $asset['acquisition_date'],
                $asset['value'],
                $asset['depreciation_rate'] . '%',
                $asset['current_value'],
                $asset['total_depreciation'],
                $asset['years_owned'],
                $asset['status'],
                $asset['location'],
                $asset['serial_number'],
                $asset['vendor'],
                $asset['warranty_expiration'],
                $asset['created_at'],
                $asset['created_by_username']
            ];
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        // Log activity
        logActivity(getCurrentUserId(), 'assets_exported', 'acc_assets', null,
            "Exported " . count($assets) . " assets to CSV");
        
        return $filepath;
        
    } catch (Exception $e) {
        logError("Error exporting assets to CSV: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get asset categories
 * @return array Categories array
 */
function getAssetCategories() {
    // Return default categories
    return [
        ['id' => 1, 'name' => 'Equipment', 'description' => 'Radio and electronic equipment'],
        ['id' => 2, 'name' => 'Computer', 'description' => 'Computer hardware and software'],
        ['id' => 3, 'name' => 'Furniture', 'description' => 'Office furniture and fixtures'],
        ['id' => 4, 'name' => 'Vehicle', 'description' => 'Vehicles and transportation'],
        ['id' => 5, 'name' => 'Building', 'description' => 'Buildings and real estate'],
        ['id' => 6, 'name' => 'Other', 'description' => 'Other assets']
    ];
}

/**
 * Calculate asset insurance value (replacement cost)
 * @param array $asset Asset data
 * @param float $inflation_rate Annual inflation rate (default 3%)
 * @return float Insurance/replacement value
 */
function calculateInsuranceValue($asset, $inflation_rate = 0.03) {
    try {
        $original_value = floatval($asset['value']);
        $years_owned = calculateYearsOwned($asset['acquisition_date']);
        
        // Calculate inflated replacement cost
        $replacement_value = $original_value * pow((1 + $inflation_rate), $years_owned);
        
        // For certain categories, use current market value instead
        switch ($asset['category']) {
            case 'Computer':
                // Technology depreciates rapidly, use depreciated value
                return calculateCurrentAssetValue($asset);
                
            case 'Equipment':
                // Radio equipment may appreciate or hold value
                return max($replacement_value, calculateCurrentAssetValue($asset));
                
            case 'Building':
                // Real estate typically appreciates
                return $replacement_value;
                
            default:
                // Use replacement cost for other items
                return round($replacement_value, 2);
        }
        
    } catch (Exception $e) {
        logError("Error calculating insurance value: " . $e->getMessage(), 'accounting');
        return floatval($asset['value']);
    }
}

/**
 * Create assets table if it doesn't exist
 */
function ensureAssetTableExists() {
    global $conn;
    
    if (!tableExists('acc_assets')) {
        $sql = "
            CREATE TABLE acc_assets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                acquisition_date DATE NOT NULL,
                value DECIMAL(10,2) NOT NULL,
                depreciation_rate DECIMAL(5,2) DEFAULT 0.00,
                status ENUM('Active', 'Inactive', 'Disposed', 'Sold') DEFAULT 'Active',
                category VARCHAR(100) DEFAULT 'Equipment',
                location VARCHAR(255),
                serial_number VARCHAR(100),
                vendor VARCHAR(255),
                warranty_expiration DATE,
                created_at DATETIME NOT NULL,
                created_by INT,
                updated_at DATETIME,
                updated_by INT,
                INDEX idx_status (status),
                INDEX idx_category (category),
                INDEX idx_acquisition_date (acquisition_date),
                INDEX idx_warranty_expiration (warranty_expiration),
                FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES auth_users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create assets table: " . $conn->error);
        }
    }
}

/**
 * Check if table exists
 * @param string $table_name Table name
 * @return bool
 */
function tableExists($table_name) {
    global $conn;
    
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param('s', $table_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

?>
