<?php

/**
 * Category Controller - W5OBM Accounting System
 * File: /accounting/controllers/categoryController.php
 * Purpose: Complete transaction category management controller
 * SECURITY: All operations require authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

// Ensure we have required includes
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Add a new transaction category
 * @param array $data Category data
 * @return bool|int Category ID on success, false on failure
 */
function addCategory($data)
{
    global $conn;

    try {
        // Validate required fields
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }

        // Validate category type
        $valid_types = ['Income', 'Expense', 'Asset', 'Liability', 'Equity'];
        if (!in_array($data['type'], $valid_types)) {
            throw new Exception("Invalid category type");
        }

        // Check if category name already exists for this type
        $stmt = $conn->prepare("SELECT id FROM acc_transaction_categories WHERE name = ? AND type = ?");
        $stmt->bind_param('ss', $data['name'], $data['type']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("A category with this name already exists for this type");
        }
        $stmt->close();

        $stmt = $conn->prepare("
            INSERT INTO acc_transaction_categories 
            (name, description, type, parent_category_id, active, created_by, created_at) 
            VALUES (?, ?, ?, ?, 1, ?, NOW())
        ");

        $created_by = getCurrentUserId();
        $parent_category_id = !empty($data['parent_category_id']) ? intval($data['parent_category_id']) : null;
        $description = sanitizeInput($data['description'] ?? '', 'string');

        $stmt->bind_param(
            'sssii',
            $data['name'],
            $description,
            $data['type'],
            $parent_category_id,
            $created_by
        );

        if ($stmt->execute()) {
            $category_id = $conn->insert_id;
            $stmt->close();

            // Log activity
            logActivity(
                $created_by,
                'category_created',
                'acc_transaction_categories',
                $category_id,
                "Created category: {$data['name']} ({$data['type']})"
            );

            return $category_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error adding category: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing category
 * @param int $id Category ID
 * @param array $data Updated category data
 * @return bool Success status
 */
function updateCategory($id, $data)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid category ID");
        }

        // Check if category exists
        $existing = getCategoryById($id);
        if (!$existing) {
            throw new Exception("Category not found");
        }

        // Check if name already exists (excluding current category)
        if (!empty($data['name'])) {
            $stmt = $conn->prepare("SELECT id FROM acc_transaction_categories WHERE name = ? AND type = ? AND id != ?");
            $stmt->bind_param('ssi', $data['name'], $data['type'], $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception("A category with this name already exists for this type");
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("
            UPDATE acc_transaction_categories 
            SET name = ?, description = ?, type = ?, parent_category_id = ?, 
                updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $updated_by = getCurrentUserId();
        $parent_category_id = !empty($data['parent_category_id']) ? intval($data['parent_category_id']) : null;
        $description = sanitizeInput($data['description'] ?? '', 'string');

        $stmt->bind_param(
            'sssiii',
            $data['name'],
            $description,
            $data['type'],
            $parent_category_id,
            $updated_by,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                $updated_by,
                'category_updated',
                'acc_transaction_categories',
                $id,
                "Updated category: {$data['name']} ({$data['type']})"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error updating category: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Delete a category by its ID
 * @param int $id Category ID
 * @param int $reassign_to_id Optional ID to reassign transactions to
 * @return bool Success status
 */
function deleteCategory($id, $reassign_to_id = null)
{
    global $conn;

    try {
        // Validate ID
        if (!$id || !is_numeric($id)) {
            throw new Exception("Invalid category ID");
        }

        // Get category details for logging
        $category = getCategoryById($id);
        if (!$category) {
            throw new Exception("Category not found");
        }

        // Check if category has transactions
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE category_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $transaction_count = intval($result['count']);
        $stmt->close();

        // If has transactions and no reassignment specified, prevent deletion
        if ($transaction_count > 0 && !$reassign_to_id) {
            throw new Exception("Cannot delete category with transactions. Please reassign transactions first.");
        }

        // If has transactions, reassign them
        if ($transaction_count > 0 && $reassign_to_id) {
            if (!is_numeric($reassign_to_id)) {
                throw new Exception("Invalid reassignment category ID");
            }

            // Verify reassignment category exists
            $reassign_category = getCategoryById($reassign_to_id);
            if (!$reassign_category) {
                throw new Exception("Reassignment category not found");
            }

            // Reassign transactions
            $stmt = $conn->prepare("UPDATE acc_transactions SET category_id = ? WHERE category_id = ?");
            $stmt->bind_param('ii', $reassign_to_id, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Failed to reassign transactions");
            }
            $stmt->close();

            // Log reassignment
            logActivity(
                getCurrentUserId(),
                'transactions_reassigned',
                'acc_transactions',
                null,
                "Reassigned $transaction_count transactions from category {$category['name']} to {$reassign_category['name']}"
            );
        }

        // Check for child categories
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transaction_categories WHERE parent_category_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $child_count = intval($result['count']);
        $stmt->close();

        if ($child_count > 0) {
            throw new Exception("Cannot delete category with sub-categories. Please delete or reassign sub-categories first.");
        }

        // Delete the category
        $stmt = $conn->prepare("DELETE FROM acc_transaction_categories WHERE id = ?");
        $stmt->bind_param('i', $id);

        if ($stmt->execute()) {
            $stmt->close();

            // Log activity
            logActivity(
                getCurrentUserId(),
                'category_deleted',
                'acc_transaction_categories',
                $id,
                "Deleted category: {$category['name']} ({$category['type']})"
            );

            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        logError("Error deleting category: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get a single category by its ID
 * @param int $id Category ID
 * @return array|false Category data or false if not found
 */
function getCategoryById($id)
{
    global $conn;

    try {
        if (!$id || !is_numeric($id)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT c.*, 
                   p.name AS parent_category_name,
                   cu.username AS created_by_username,
                   uu.username AS updated_by_username,
                   (SELECT COUNT(*) FROM acc_transactions WHERE category_id = c.id) AS transaction_count
            FROM acc_transaction_categories c 
            LEFT JOIN acc_transaction_categories p ON c.parent_category_id = p.id
            LEFT JOIN auth_users cu ON c.created_by = cu.id
            LEFT JOIN auth_users uu ON c.updated_by = uu.id
            WHERE c.id = ?
        ");

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $result;
    } catch (Exception $e) {
        logError("Error getting category by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all categories with optional filtering
 * @param array $filters Optional filters
 * @param array $options Optional limit, offset, order
 * @return array Categories array
 */
function getAllCategories($filters = [], $options = [])
{
    global $conn;

    try {
        $where_conditions = [];
        $params = [];
        $types = '';

        // Build WHERE clause
        if (!empty($filters['type'])) {
            $where_conditions[] = "c.type = ?";
            $params[] = $filters['type'];
            $types .= 's';
        }

        if (!empty($filters['parent_category_id'])) {
            $where_conditions[] = "c.parent_category_id = ?";
            $params[] = $filters['parent_category_id'];
            $types .= 'i';
        }

        if (isset($filters['active'])) {
            $where_conditions[] = "c.active = ?";
            $params[] = $filters['active'] ? 1 : 0;
            $types .= 'i';
        } else {
            // Default to active categories only
            $where_conditions[] = "c.active = 1";
        }

        if (!empty($filters['search'])) {
            $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= 'ss';
        }

        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

        // Order clause
        $order_by = $options['order_by'] ?? 'c.type ASC, c.name ASC';

        $query = "
            SELECT c.*, 
                   p.name AS parent_category_name,
                   (SELECT COUNT(*) FROM acc_transactions WHERE category_id = c.id) AS transaction_count
            FROM acc_transaction_categories c 
            LEFT JOIN acc_transaction_categories p ON c.parent_category_id = p.id
            $where_clause
            ORDER BY $order_by
        ";

        // Add limit and offset if specified
        if (!empty($options['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $options['limit'];
            $types .= 'i';

            if (!empty($options['offset'])) {
                $query .= " OFFSET ?";
                $params[] = $options['offset'];
                $types .= 'i';
            }
        }

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }

        $stmt->close();
        return $categories;
    } catch (Exception $e) {
        logError("Error getting all categories: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Get categories by type
 * @param string $type Category type
 * @return array Categories of the specified type
 */
function getCategoriesByType($type)
{
    return getAllCategories(['type' => $type, 'active' => true]);
}

/**
 * Validate category data
 * @param array $data Category data
 * @param int $exclude_id Optional ID to exclude from validation (for updates)
 * @return array Validation result with 'valid' boolean and 'errors' array
 */
function validateCategoryData($data, $exclude_id = null)
{
    global $conn;
    $errors = [];

    // Required fields
    $required_fields = ['name', 'type'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Validate category type
    if (!empty($data['type'])) {
        $valid_types = ['Income', 'Expense', 'Asset', 'Liability', 'Equity'];
        if (!in_array($data['type'], $valid_types)) {
            $errors[] = "Invalid category type";
        }
    }

    // Validate name uniqueness for type
    if (!empty($data['name']) && !empty($data['type'])) {
        try {
            $query = "SELECT id FROM acc_transaction_categories WHERE name = ? AND type = ?";
            $params = [$data['name'], $data['type']];
            $types = 'ss';

            if ($exclude_id) {
                $query .= " AND id != ?";
                $params[] = $exclude_id;
                $types .= 'i';
            }

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "A category with this name already exists for this type";
            }
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Error validating category name";
        }
    }

    // Validate name length
    if (!empty($data['name']) && strlen($data['name']) > 255) {
        $errors[] = "Category name cannot exceed 255 characters";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
