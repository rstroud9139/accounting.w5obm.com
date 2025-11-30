<?php

/**
 * Vendor Controller - W5OBM Accounting System
 * File: /accounting/controllers/vendorController.php
 * Purpose: Complete vendor management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Add a new vendor
 * @param array $data Vendor data
 * @return bool|int Vendor ID on success, false on failure
 */
function addVendor($data)
{
    $db = accounting_db_connection();

    try {
        // Validate required fields
        if (empty($data['name'])) {
            throw new Exception("Vendor name is required");
        }

        $stmt = $db->prepare("
            INSERT INTO acc_vendors 
            (name, contact_name, email, phone, address, notes, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $created_by = getCurrentUserId();
        $contact_name = sanitizeInput($data['contact_name'] ?? '');
        $email = sanitizeInput($data['email'] ?? '');
        $phone = sanitizeInput($data['phone'] ?? '');
        $address = sanitizeInput($data['address'] ?? '');
        $notes = sanitizeInput($data['notes'] ?? '');

        $stmt->bind_param(
            'ssssssi',
            $data['name'],
            $contact_name,
            $email,
            $phone,
            $address,
            $notes,
            $created_by
        );

        if ($stmt->execute()) {
            $vendor_id = $db->insert_id;
            $stmt->close();

            logActivity(
                $created_by,
                'vendor_created',
                'acc_vendors',
                $vendor_id,
                "Created vendor: {$data['name']}"
            );

            return $vendor_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error adding vendor: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing vendor
 * @param int $id Vendor ID
 * @param array $data Updated vendor data
 * @return bool Success status
 */
function updateVendor($id, $data)
{
    $db = accounting_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid vendor ID");
        }

        $existing = getVendorById($id);
        if (!$existing) {
            throw new Exception("Vendor not found");
        }

        if (empty($data['name'])) {
            throw new Exception("Vendor name is required");
        }

        $stmt = $db->prepare("
            UPDATE acc_vendors 
            SET name = ?, contact_name = ?, email = ?, phone = ?, address = ?, 
                notes = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $updated_by = getCurrentUserId();
        $contact_name = sanitizeInput($data['contact_name'] ?? '');
        $email = sanitizeInput($data['email'] ?? '');
        $phone = sanitizeInput($data['phone'] ?? '');
        $address = sanitizeInput($data['address'] ?? '');
        $notes = sanitizeInput($data['notes'] ?? '');

        $stmt->bind_param(
            'ssssssii',
            $data['name'],
            $contact_name,
            $email,
            $phone,
            $address,
            $notes,
            $updated_by,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            logActivity(
                $updated_by,
                'vendor_updated',
                'acc_vendors',
                $id,
                "Updated vendor: {$data['name']}"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error updating vendor: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Delete a vendor by ID
 * @param int $id Vendor ID
 * @return bool Success status
 */
function deleteVendor($id)
{
    $db = accounting_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid vendor ID");
        }

        $vendor = getVendorById($id);
        if (!$vendor) {
            throw new Exception("Vendor not found");
        }

        $user_id = getCurrentUserId();
        if (!isAdmin($user_id) && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to delete vendors");
        }

        // Check if vendor has transactions
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE vendor_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result['count'] > 0) {
            throw new Exception("Cannot delete vendor with existing transactions");
        }

        $stmt = $db->prepare("DELETE FROM acc_vendors WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            logActivity(
                $user_id,
                'vendor_deleted',
                'acc_vendors',
                $id,
                "Deleted vendor: {$vendor['name']}"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting vendor: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a single vendor by ID
 * @param int $id Vendor ID
 * @return array|false Vendor data or false
 */
function getVendorById($id)
{
    $db = accounting_db_connection();

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $db->prepare("
            SELECT v.*, 
                   cu.username AS created_by_username,
                   uu.username AS updated_by_username
            FROM acc_vendors v
            LEFT JOIN w5obm.auth_users cu ON v.created_by = cu.id
            LEFT JOIN w5obm.auth_users uu ON v.updated_by = uu.id
            WHERE v.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        logError("Error getting vendor by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all vendors with optional filtering
 * @param array $filters Optional filters
 * @return array Vendors array
 */
function getAllVendors($filters = [])
{
    $db = accounting_db_connection();

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        if (!empty($filters['search'])) {
            $where_conditions[] = "(v.name LIKE ? OR v.contact_name LIKE ? OR v.email LIKE ? OR v.phone LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ssss';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        $query = "
            SELECT v.*, 
                   cu.username AS created_by_username,
                   (SELECT COUNT(*) FROM acc_transactions t WHERE t.vendor_id = v.id) AS transaction_count
            FROM acc_vendors v
            LEFT JOIN w5obm.auth_users cu ON v.created_by = cu.id
            $where_clause
            ORDER BY v.name ASC
        ";

        $stmt = $db->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $vendors = [];
        while ($row = $result->fetch_assoc()) {
            $vendors[] = $row;
        }

        $stmt->close();
        return $vendors;
    } catch (Exception $e) {
        logError("Error getting all vendors: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Get transactions for a vendor
 * @param int $vendor_id Vendor ID
 * @return array Transactions array
 */
function getVendorTransactions($vendor_id)
{
    $db = accounting_db_connection();

    try {
        if (!$vendor_id || !is_numeric($vendor_id)) {
            return [];
        }

         $stmt = $db->prepare("
            SELECT t.*, 
                   c.name AS category_name,
                   a.name AS account_name
            FROM acc_transactions t
            LEFT JOIN acc_transaction_categories c ON t.category_id = c.id
            LEFT JOIN acc_ledger_accounts a ON t.account_id = a.id
            WHERE t.vendor_id = ?
            ORDER BY t.transaction_date DESC, t.id DESC
        ");

        $stmt->bind_param('i', $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }

        $stmt->close();
        return $transactions;
    } catch (Exception $e) {
        logError("Error getting vendor transactions: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Search vendors
 * @param string $search_term Search term
 * @return array Vendors array
 */
function searchVendors($search_term)
{
    return getAllVendors(['search' => $search_term]);
}

// -----------------------------------------------------------------------------
// Legacy snake_case wrappers (compatibility layer)
// -----------------------------------------------------------------------------

if (!function_exists('add_vendor')) {
    function add_vendor($name, $contact_name, $email, $phone, $address, $notes = '')
    {
        return addVendor([
            'name' => $name,
            'contact_name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'notes' => $notes,
        ]);
    }
}

if (!function_exists('update_vendor')) {
    function update_vendor($id, $name, $contact_name, $email, $phone, $address, $notes = '')
    {
        return updateVendor($id, [
            'name' => $name,
            'contact_name' => $contact_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'notes' => $notes,
        ]);
    }
}

if (!function_exists('delete_vendor')) {
    function delete_vendor($id)
    {
        return deleteVendor($id);
    }
}

if (!function_exists('fetch_vendor_by_id')) {
    function fetch_vendor_by_id($id)
    {
        return getVendorById($id);
    }
}

if (!function_exists('fetch_all_vendors')) {
    function fetch_all_vendors()
    {
        return getAllVendors();
    }
}

if (!function_exists('search_vendors')) {
    function search_vendors($search_term)
    {
        return searchVendors($search_term);
    }
}

if (!function_exists('fetch_vendor_transactions')) {
    function fetch_vendor_transactions($vendor_id)
    {
        return getVendorTransactions($vendor_id);
    }
}
