<?php

/**
 * Category Controller (underscore wrapper) - Delegates to canonical camelCase categoryController.php
 * Maintains backwards-compatible function names while centralizing business logic.
 */
require_once __DIR__ . '/categoryController.php';

// Wrapper functions mapping underscore API to camelCase implementations
function add_category($name, $description, $type, $parent_category_id = null)
{
    return addCategory([
        'name' => $name,
        'description' => $description,
        'type' => $type,
        'parent_category_id' => $parent_category_id,
    ]);
}

function update_category($id, $name, $description, $type, $parent_category_id = null)
{
    return updateCategory($id, [
        'name' => $name,
        'description' => $description,
        'type' => $type,
        'parent_category_id' => $parent_category_id,
    ]);
}

function delete_category($id, $reassign_to_id = null)
{
    return deleteCategory($id, $reassign_to_id);
}

function fetch_category_by_id($id)
{
    return getCategoryById($id);
}

function fetch_all_categories($type = null)
{
    $filters = [];
    if ($type) {
        $filters['type'] = $type;
    }
    return getAllCategories($filters);
}

function fetch_categories_by_type($type)
{
    return getCategoriesByType($type);
}

function validate_category_data($data, $exclude_id = null)
{
    return validateCategoryData($data, $exclude_id);
}

// Keep a helper for legacy API used by routes
function is_category_in_use($id)
{
    // Prefer a camelCase implementation if added in the future
    if (function_exists('isCategoryInUse')) {
        return isCategoryInUse($id);
    }
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM acc_transactions WHERE category_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($res['count'] ?? 0) > 0;
}
