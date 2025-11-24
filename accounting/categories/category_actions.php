<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/categoryController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/categories/');
    exit();
}

if (!isAuthenticated()) {
    setToastMessage('danger', 'Session Expired', 'Please sign in and try again.', 'club-logo');
    header('Location: /authentication/login.php');
    exit();
}

if (!isset($_SESSION['csrf_token']) || ($_POST['csrf_token'] ?? '') !== $_SESSION['csrf_token']) {
    setToastMessage('danger', 'Security Check Failed', 'Please refresh the page and submit again.', 'club-logo');
    header('Location: /accounting/categories/');
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = getCurrentUserId();

$canAdd = hasPermission($user_id, 'accounting_add') || hasPermission($user_id, 'accounting_manage');
$canManage = hasPermission($user_id, 'accounting_manage');

try {
    switch ($action) {
        case 'create':
            if (!$canAdd) {
                throw new Exception('You do not have permission to add categories.');
            }

            $categoryData = [
                'name' => sanitizeInput($_POST['name'] ?? '', 'string'),
                'type' => sanitizeInput($_POST['type'] ?? '', 'string'),
                'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
                'parent_category_id' => !empty($_POST['parent_category_id'])
                    ? sanitizeInput($_POST['parent_category_id'], 'int')
                    : null,
            ];

            $validation = validateCategoryData($categoryData);
            if (!$validation['valid']) {
                throw new Exception(implode(', ', $validation['errors']));
            }

            $categoryId = addCategory($categoryData);
            if (!$categoryId) {
                throw new Exception('Unable to add the category.');
            }

            setToastMessage('success', 'Category Added', 'The category has been created successfully.', 'club-logo');
            break;

        case 'update':
            if (!$canManage) {
                throw new Exception('You do not have permission to update categories.');
            }

            $categoryId = sanitizeInput($_POST['id'] ?? 0, 'int');
            if (!$categoryId) {
                throw new Exception('Invalid category identifier.');
            }

            $categoryData = [
                'name' => sanitizeInput($_POST['name'] ?? '', 'string'),
                'type' => sanitizeInput($_POST['type'] ?? '', 'string'),
                'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
                'parent_category_id' => !empty($_POST['parent_category_id'])
                    ? sanitizeInput($_POST['parent_category_id'], 'int')
                    : null,
            ];

            if ($categoryData['parent_category_id'] === $categoryId) {
                throw new Exception('A category cannot be its own parent.');
            }

            $validation = validateCategoryData($categoryData, $categoryId);
            if (!$validation['valid']) {
                throw new Exception(implode(', ', $validation['errors']));
            }

            if (!updateCategory($categoryId, $categoryData)) {
                throw new Exception('Unable to update the category.');
            }

            setToastMessage('success', 'Category Updated', 'Changes saved successfully.', 'club-logo');
            break;

        case 'delete':
            if (!$canManage) {
                throw new Exception('You do not have permission to delete categories.');
            }

            $categoryId = sanitizeInput($_POST['id'] ?? 0, 'int');
            if (!$categoryId) {
                throw new Exception('Invalid category identifier.');
            }

            $reassignId = null;
            if (!empty($_POST['reassign_category_id'])) {
                $reassignId = sanitizeInput($_POST['reassign_category_id'], 'int');
                if ($reassignId === $categoryId) {
                    throw new Exception('Please choose a different category for reassignment.');
                }
            }

            if (!deleteCategory($categoryId, $reassignId)) {
                throw new Exception('Unable to delete the category.');
            }

            setToastMessage('success', 'Category Deleted', 'The category has been removed.', 'club-logo');
            break;

        default:
            throw new Exception('Unknown action requested.');
    }
} catch (Exception $e) {
    setToastMessage('danger', 'Categories', $e->getMessage(), 'club-logo');
}

header('Location: /accounting/categories/');
exit();
