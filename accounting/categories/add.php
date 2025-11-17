<?php

/**
 * Add Category Page - W5OBM Accounting System
 * File: /accounting/categories/add.php
 * Purpose: Add new transaction categories
 * SECURITY: Requires authentication and accounting permissions
 * UPDATED: Follows Website Guidelines and uses consolidated helper functions
 */

session_start();
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../controllers/categoryController.php';

// Authentication check
if (!isAuthenticated()) {
    header('Location: /authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();

// Check accounting permissions
if (!hasPermission($user_id, 'accounting_manage') && !hasPermission($user_id, 'accounting_add')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to add categories.', 'club-logo');
    header('Location: /accounting/categories/');
    exit();
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Add Category - W5OBM Accounting";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid security token. Please try again.");
        }

        // Prepare category data
        $category_data = [
            'name' => sanitizeInput($_POST['name'], 'string'),
            'type' => sanitizeInput($_POST['type'], 'string'),
            'description' => sanitizeInput($_POST['description'] ?? '', 'string'),
            'parent_category_id' => !empty($_POST['parent_category_id']) ? sanitizeInput($_POST['parent_category_id'], 'int') : null
        ];

        // Validate category data
        $validation = validateCategoryData($category_data);
        if (!$validation['valid']) {
            throw new Exception(implode(', ', $validation['errors']));
        }

        // Add category
        $category_id = addCategory($category_data);

        if ($category_id) {
            setToastMessage(
                'success',
                'Category Added',
                "Category '" . htmlspecialchars($category_data['name']) . "' has been successfully added.",
                'club-logo'
            );
            header('Location: /accounting/categories/');
            exit();
        } else {
            throw new Exception("Failed to add category. Please try again.");
        }
    } catch (Exception $e) {
        setToastMessage('danger', 'Error Adding Category', $e->getMessage(), 'club-logo');
        logError("Error adding category: " . $e->getMessage(), 'accounting');
    }
}

// Get all categories for parent selection
// Get all categories for parent selection
try {
    $all_categories = getAllCategories(['active' => true]);
} catch (Exception $e) {
    $all_categories = [];
    logError("Error fetching categories for parent selection: " . $e->getMessage(), 'accounting');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <?php include __DIR__ . '/../../include/header.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../../include/menu.php'; ?>

    <!-- Toast Message Display -->
    <?php
    if (function_exists('displayToastMessage')) {
        displayToastMessage();
    }
    ?>

    <!-- Page Container -->
    <div class="page-container">
        <!-- Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="fas fa-plus fa-2x"></i>
                    </div>
                    <div class="col">
                        <h3 class="mb-0">Add Transaction Category</h3>
                        <small>Create a new category to organize transactions</small>
                    </div>
                    <div class="col-auto">
                        <a href="/accounting/categories/" class="btn btn-dark btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Back to Categories
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Form -->
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-tag me-2 text-warning"></i>Category Details
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="categoryForm" class="needs-validation" novalidate>
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="row">
                        <!-- Category Name -->
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-tag me-1 text-primary"></i>Category Name *
                            </label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name"
                                maxlength="255" required
                                placeholder="Enter category name (e.g., Office Supplies, Membership Dues)">
                            <div class="invalid-feedback">Please enter a category name.</div>
                        </div>

                        <!-- Category Type -->
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">
                                <i class="fas fa-layer-group me-1 text-success"></i>Category Type *
                            </label>
                            <select class="form-select form-control-lg" id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="Income">Income</option>
                                <option value="Expense">Expense</option>
                                <option value="Asset">Asset</option>
                                <option value="Liability">Liability</option>
                                <option value="Equity">Equity</option>
                            </select>
                            <div class="invalid-feedback">Please select a category type.</div>
                        </div>
                    </div>

                    <!-- Parent Category (Optional) -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="parent_category_id" class="form-label">
                                <i class="fas fa-sitemap me-1 text-secondary"></i>Parent Category (Optional)
                            </label>
                            <select class="form-select form-control-lg" id="parent_category_id" name="parent_category_id">
                                <option value="">None (Top Level Category)</option>
                                <?php foreach ($all_categories as $category): ?>
                                    <option value="<?= $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Optional: Create sub-category under existing category</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left me-1 text-dark"></i>Description
                        </label>
                        <textarea class="form-control form-control-lg" id="description" name="description"
                            rows="3" maxlength="1000"
                            placeholder="Optional description of what this category is used for"></textarea>
                        <div class="form-text">Optional description (maximum 1000 characters)</div>
                    </div>

                    <!-- Category Type Help -->
                    <div class="alert alert-info border-0">
                        <div class="d-flex">
                            <i class="fas fa-info-circle me-3 mt-1 text-info"></i>
                            <div>
                                <h6 class="alert-heading mb-2">Category Type Guide</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Income:</strong> Money coming into the club (dues, donations, event income)</p>
                                        <p class="mb-1"><strong>Expense:</strong> Money going out of the club (utilities, supplies, meeting costs)</p>
                                        <p class="mb-0"><strong>Asset:</strong> Things the club owns or purchases (equipment, inventory)</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Liability:</strong> Money the club owes (loans, accounts payable)</p>
                                        <p class="mb-0"><strong>Equity:</strong> Club's net worth and capital accounts</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="action-area bg-light border-top mx-n3 mt-4 p-3">
                        <div class="row">
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-save me-2"></i>Add Category
                                </button>
                            </div>
                            <div class="col-md-6 mt-2 mt-md-0">
                                <a href="/accounting/categories/" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../include/footer.php'; ?>

    <script>
        // Form validation and enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('categoryForm');
            const typeField = document.getElementById('type');
            const parentCategoryField = document.getElementById('parent_category_id');

            // Bootstrap validation
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });

            // Filter parent categories by type when type changes
            typeField.addEventListener('change', function() {
                const selectedType = this.value;
                const parentOptions = parentCategoryField.options;

                // Show/hide parent category options based on selected type
                for (let i = 1; i < parentOptions.length; i++) { // Skip first "None" option
                    const option = parentOptions[i];
                    const optionText = option.textContent;

                    // Show only categories of the same type as potential parents
                    if (selectedType && optionText.includes('(' + selectedType + ')')) {
                        option.style.display = 'block';
                    } else if (selectedType) {
                        option.style.display = 'none';
                    } else {
                        option.style.display = 'block'; // Show all if no type selected
                    }
                }

                // Reset parent selection if current selection is now hidden
                if (parentCategoryField.selectedIndex > 0) {
                    const currentOption = parentCategoryField.options[parentCategoryField.selectedIndex];
                    if (currentOption.style.display === 'none') {
                        parentCategoryField.selectedIndex = 0;
                    }
                }
            });
        });
    </script>
</body>

</html>