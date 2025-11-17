<?php
/**
 * Budget Management System - W5OBM Accounting System
 * File: /accounting/controllers/budget_controller.php
 * Purpose: Complete budget management with variance analysis
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
 * Create a new budget entry
 * @param array $budget_data Budget information
 * @return int|bool Budget ID on success, false on failure
 */
function createBudget($budget_data) {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['category_id', 'year', 'amount'];
        foreach ($required_fields as $field) {
            if (empty($budget_data[$field])) {
                throw new Exception("Required field missing: $field");
            }
        }
        
        // Validate amount is numeric and positive
        if (!is_numeric($budget_data['amount']) || $budget_data['amount'] < 0) {
            throw new Exception("Budget amount must be a positive number");
        }
        
        // Validate year
        $year = intval($budget_data['year']);
        if ($year < 2000 || $year > date('Y') + 5) {
            throw new Exception("Invalid budget year");
        }
        
        // Check if budget already exists for this category/year
        $existing = getBudgetByCategoryYear($budget_data['category_id'], $year);
        if ($existing) {
            throw new Exception("Budget already exists for this category and year");
        }
        
        // Ensure budget table exists
        ensureBudgetTableExists();
        
        $stmt = $conn->prepare("
            INSERT INTO acc_budgets (
                category_id, year, amount, notes, created_at, created_by
            ) VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        
        $notes = $budget_data['notes'] ?? '';
        $created_by = getCurrentUserId();
        
        $stmt->bind_param('iidsi',
            $budget_data['category_id'],
            $year,
            $budget_data['amount'],
            $notes,
            $created_by
        );
        
        if ($stmt->execute()) {
            $budget_id = $conn->insert_id;
            $stmt->close();
            
            // Log activity
            logActivity($created_by, 'budget_created', 'acc_budgets', $budget_id,
                "Created budget for category {$budget_data['category_id']}, year $year");
            
            return $budget_id;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        logError("Error creating budget: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Update an existing budget
 * @param int $budget_id Budget ID
 * @param array $budget_data Updated budget information
 * @return bool Success status
 */
function updateBudget($budget_id, $budget_data) {
    global $conn;
    
    try {
        if (!$budget_id || !is_numeric($budget_id)) {
            throw new Exception("Invalid budget ID");
        }
        
        // Check if budget exists
        $existing_budget = getBudgetById($budget_id);
        if (!$existing_budget) {
            throw new Exception("Budget not found");
        }
        
        // Validate permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_edit') && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to update budget");
        }
        
        // Validate amount if provided
        if (isset($budget_data['amount'])) {
            if (!is_numeric($budget_data['amount']) || $budget_data['amount'] < 0) {
                throw new Exception("Budget amount must be a positive number");
            }
        }
        
        // Build update query dynamically
        $update_fields = [];
        $params = [];
        $types = '';
        
        $allowed_fields = ['amount', 'notes'];
        
        foreach ($allowed_fields as $field) {
            if (isset($budget_data[$field])) {
                $update_fields[] = "$field = ?";
                $params[] = $budget_data[$field];
                
                if ($field === 'amount') {
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
        
        // Add budget_id for WHERE clause
        $params[] = $budget_id;
        $types .= 'i';
        
        $query = "UPDATE acc_budgets SET " . implode(', ', $update_fields) . " WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            logActivity($user_id, 'budget_updated', 'acc_budgets', $budget_id,
                "Updated budget for category {$existing_budget['category_id']}");
            
            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        logError("Error updating budget: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get budget by ID
 * @param int $budget_id Budget ID
 * @return array|false Budget data or false if not found
 */
function getBudgetById($budget_id) {
    global $conn;
    
    try {
        if (!$budget_id || !is_numeric($budget_id)) {
            return false;
        }
        
        $stmt = $conn->prepare("
            SELECT b.*, 
                   c.name as category_name,
                   c.type as category_type,
                   u1.username as created_by_username,
                   u2.username as updated_by_username
            FROM acc_budgets b
            JOIN acc_transaction_categories c ON b.category_id = c.id
            LEFT JOIN auth_users u1 ON b.created_by = u1.id
            LEFT JOIN auth_users u2 ON b.updated_by = u2.id
            WHERE b.id = ?
        ");
        
        $stmt->bind_param('i', $budget_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        logError("Error getting budget by ID: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get budget by category and year
 * @param int $category_id Category ID
 * @param int $year Year
 * @return array|false Budget data or false if not found
 */
function getBudgetByCategoryYear($category_id, $year) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   c.name as category_name,
                   c.type as category_type
            FROM acc_budgets b
            JOIN acc_transaction_categories c ON b.category_id = c.id
            WHERE b.category_id = ? AND b.year = ?
        ");
        
        $stmt->bind_param('ii', $category_id, $year);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result;
        
    } catch (Exception $e) {
        logError("Error getting budget by category/year: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get all budgets for a year
 * @param int $year Year
 * @return array Budgets array
 */
function getBudgetsByYear($year) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT b.*, 
                   c.name as category_name,
                   c.type as category_type,
                   u1.username as created_by_username
            FROM acc_budgets b
            JOIN acc_transaction_categories c ON b.category_id = c.id
            LEFT JOIN auth_users u1 ON b.created_by = u1.id
            WHERE b.year = ?
            ORDER BY c.type, c.name
        ");
        
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $budgets = [];
        while ($row = $result->fetch_assoc()) {
            $budgets[] = $row;
        }
        
        $stmt->close();
        return $budgets;
        
    } catch (Exception $e) {
        logError("Error getting budgets by year: " . $e->getMessage(), 'accounting');
        return [];
    }
}

/**
 * Generate budget vs actual report
 * @param int $year Year for comparison
 * @return array Budget vs actual data
 */
function generateBudgetVsActualReport($year) {
    global $conn;
    
    try {
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        
        // Get all categories with budget and actual data
        $stmt = $conn->prepare("
            SELECT 
                c.id,
                c.name,
                c.type,
                COALESCE(b.amount, 0) as budget_amount,
                COALESCE(SUM(t.amount), 0) as actual_amount,
                b.notes as budget_notes
            FROM acc_transaction_categories c
            LEFT JOIN acc_budgets b ON c.id = b.category_id AND b.year = ?
            LEFT JOIN acc_transactions t ON c.id = t.category_id 
                AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.id, c.name, c.type, b.amount, b.notes
            ORDER BY c.type, c.name
        ");
        
        $stmt->bind_param('iss', $year, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categories = [];
        $totals = [
            'budget_income' => 0,
            'budget_expense' => 0,
            'actual_income' => 0,
            'actual_expense' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $budget_amount = floatval($row['budget_amount']);
            $actual_amount = floatval($row['actual_amount']);
            $variance = $actual_amount - $budget_amount;
            $percentage = $budget_amount > 0 ? ($actual_amount / $budget_amount) * 100 : 0;
            
            $category_data = [
                'id' => $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'budget_amount' => $budget_amount,
                'actual_amount' => $actual_amount,
                'variance' => $variance,
                'percentage' => $percentage,
                'notes' => $row['budget_notes'],
                'status' => getBudgetStatus($variance, $percentage, $row['type'])
            ];
            
            $categories[] = $category_data;
            
            // Add to totals
            if ($row['type'] === 'Income') {
                $totals['budget_income'] += $budget_amount;
                $totals['actual_income'] += $actual_amount;
            } else {
                $totals['budget_expense'] += $budget_amount;
                $totals['actual_expense'] += $actual_amount;
            }
        }
        
        $stmt->close();
        
        // Calculate net figures
        $totals['budget_net'] = $totals['budget_income'] - $totals['budget_expense'];
        $totals['actual_net'] = $totals['actual_income'] - $totals['actual_expense'];
        $totals['net_variance'] = $totals['actual_net'] - $totals['budget_net'];
        
        return [
            'year' => $year,
            'categories' => $categories,
            'totals' => $totals,
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        logError("Error generating budget vs actual report: " . $e->getMessage(), 'accounting');
        return [
            'year' => $year,
            'categories' => [],
            'totals' => [
                'budget_income' => 0, 'budget_expense' => 0,
                'actual_income' => 0, 'actual_expense' => 0,
                'budget_net' => 0, 'actual_net' => 0, 'net_variance' => 0
            ]
        ];
    }
}

/**
 * Get budget status based on variance and percentage
 * @param float $variance Variance amount
 * @param float $percentage Percentage of budget used
 * @param string $type Category type (Income/Expense)
 * @return string Status
 */
function getBudgetStatus($variance, $percentage, $type) {
    if ($type === 'Income') {
        if ($percentage >= 100) return 'Over Budget';
        if ($percentage >= 90) return 'On Track';
        if ($percentage >= 50) return 'Under Budget';
        return 'Significantly Under';
    } else { // Expense
        if ($percentage > 110) return 'Over Budget';
        if ($percentage > 100) return 'Slightly Over';
        if ($percentage >= 90) return 'On Track';
        return 'Under Budget';
    }
}

/**
 * Delete a budget
 * @param int $budget_id Budget ID
 * @return bool Success status
 */
function deleteBudget($budget_id) {
    global $conn;
    
    try {
        if (!$budget_id || !is_numeric($budget_id)) {
            throw new Exception("Invalid budget ID");
        }
        
        // Get budget details for logging
        $budget = getBudgetById($budget_id);
        if (!$budget) {
            throw new Exception("Budget not found");
        }
        
        // Check permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_delete') && !hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to delete budget");
        }
        
        $stmt = $conn->prepare("DELETE FROM acc_budgets WHERE id = ?");
        $stmt->bind_param('i', $budget_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Log activity
            logActivity($user_id, 'budget_deleted', 'acc_budgets', $budget_id,
                "Deleted budget for category {$budget['category_name']}, year {$budget['year']}");
            
            return true;
        } else {
            throw new Exception("Database execute failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        logError("Error deleting budget: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Copy budgets from one year to another
 * @param int $from_year Source year
 * @param int $to_year Target year
 * @param float $adjustment_percentage Optional percentage adjustment
 * @return bool Success status
 */
function copyBudgets($from_year, $to_year, $adjustment_percentage = 0) {
    global $conn;
    
    try {
        // Validate years
        if ($from_year < 2000 || $to_year < 2000 || $from_year == $to_year) {
            throw new Exception("Invalid year parameters");
        }
        
        // Check permissions
        $user_id = getCurrentUserId();
        if (!hasPermission($user_id, 'accounting_manage')) {
            throw new Exception("Insufficient permissions to copy budgets");
        }
        
        // Get source budgets
        $source_budgets = getBudgetsByYear($from_year);
        if (empty($source_budgets)) {
            throw new Exception("No budgets found for year $from_year");
        }
        
        // Check if target year already has budgets
        $existing_budgets = getBudgetsByYear($to_year);
        if (!empty($existing_budgets)) {
            throw new Exception("Target year $to_year already has budgets");
        }
        
        $copied_count = 0;
        $adjustment_multiplier = 1 + ($adjustment_percentage / 100);
        
        foreach ($source_budgets as $budget) {
            $new_amount = $budget['amount'] * $adjustment_multiplier;
            
            $budget_data = [
                'category_id' => $budget['category_id'],
                'year' => $to_year,
                'amount' => round($new_amount, 2),
                'notes' => "Copied from $from_year" . 
                    ($adjustment_percentage != 0 ? " with {$adjustment_percentage}% adjustment" : "")
            ];
            
            if (createBudget($budget_data)) {
                $copied_count++;
            }
        }
        
        // Log activity
        logActivity($user_id, 'budgets_copied', 'acc_budgets', null,
            "Copied $copied_count budgets from $from_year to $to_year");
        
        return $copied_count > 0;
        
    } catch (Exception $e) {
        logError("Error copying budgets: " . $e->getMessage(), 'accounting');
        return false;
    }
}

/**
 * Get budget summary for dashboard
 * @param int $year Year
 * @return array Summary data
 */
function getBudgetSummary($year = null) {
    global $conn;
    
    if (!$year) {
        $year = date('Y');
    }
    
    try {
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        
        // Get summary statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT b.id) as total_budgets,
                SUM(CASE WHEN c.type = 'Income' THEN b.amount ELSE 0 END) as total_income_budget,
                SUM(CASE WHEN c.type = 'Expense' THEN b.amount ELSE 0 END) as total_expense_budget,
                SUM(CASE WHEN c.type = 'Income' THEN COALESCE(actual.amount, 0) ELSE 0 END) as total_income_actual,
                SUM(CASE WHEN c.type = 'Expense' THEN COALESCE(actual.amount, 0) ELSE 0 END) as total_expense_actual
            FROM acc_budgets b
            JOIN acc_transaction_categories c ON b.category_id = c.id
            LEFT JOIN (
                SELECT category_id, SUM(amount) as amount
                FROM acc_transactions 
                WHERE transaction_date BETWEEN ? AND ?
                GROUP BY category_id
            ) actual ON b.category_id = actual.category_id
            WHERE b.year = ?
        ");
        
        $stmt->bind_param('ssi', $start_date, $end_date, $year);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $income_budget = floatval($result['total_income_budget'] ?? 0);
        $expense_budget = floatval($result['total_expense_budget'] ?? 0);
        $income_actual = floatval($result['total_income_actual'] ?? 0);
        $expense_actual = floatval($result['total_expense_actual'] ?? 0);
        
        return [
            'year' => $year,
            'total_budgets' => intval($result['total_budgets'] ?? 0),
            'income_budget' => $income_budget,
            'expense_budget' => $expense_budget,
            'income_actual' => $income_actual,
            'expense_actual' => $expense_actual,
            'net_budget' => $income_budget - $expense_budget,
            'net_actual' => $income_actual - $expense_actual,
            'income_variance' => $income_actual - $income_budget,
            'expense_variance' => $expense_actual - $expense_budget,
            'income_percentage' => $income_budget > 0 ? ($income_actual / $income_budget) * 100 : 0,
            'expense_percentage' => $expense_budget > 0 ? ($expense_actual / $expense_budget) * 100 : 0
        ];
        
    } catch (Exception $e) {
        logError("Error getting budget summary: " . $e->getMessage(), 'accounting');
        return [
            'year' => $year,
            'total_budgets' => 0,
            'income_budget' => 0, 'expense_budget' => 0,
            'income_actual' => 0, 'expense_actual' => 0,
            'net_budget' => 0, 'net_actual' => 0,
            'income_variance' => 0, 'expense_variance' => 0,
            'income_percentage' => 0, 'expense_percentage' => 0
        ];
    }
}

/**
 * Ensure budget table exists
 */
function ensureBudgetTableExists() {
    global $conn;
    
    if (!tableExists('acc_budgets')) {
        $sql = "
            CREATE TABLE acc_budgets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id INT NOT NULL,
                year INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                notes TEXT,
                created_at DATETIME NOT NULL,
                created_by INT,
                updated_at DATETIME,
                updated_by INT,
                UNIQUE KEY unique_category_year (category_id, year),
                INDEX idx_year (year),
                FOREIGN KEY (category_id) REFERENCES acc_transaction_categories(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES auth_users(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES auth_users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create budgets table: " . $conn->error);
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
