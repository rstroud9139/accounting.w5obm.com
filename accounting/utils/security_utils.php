<?php
/**
 * Enhanced Security & Validation Utilities - W5OBM Accounting System
 * File: /accounting/utils/security_utils.php
 * Purpose: Advanced security functions and input validation
 * SECURITY: Core security functions - handle with extreme care
 * UPDATED: Follows Website Guidelines and integrates with existing auth system
 */

// Security check - ensure this file is included properly
if (!defined('SECURE_ACCESS')) {
    if (!isset($_SESSION) || !function_exists('isAuthenticated')) {
        die('Direct access not permitted');
    }
}

/**
 * Enhanced CSRF token generation and validation
 */
class CSRFProtection {
    
    /**
     * Generate a CSRF token for the current session
     * @return string CSRF token
     */
    public static function generateToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Clean up old tokens (older than 1 hour)
        $current_time = time();
        foreach ($_SESSION['csrf_tokens'] as $stored_token => $timestamp) {
            if ($current_time - $timestamp > 3600) {
                unset($_SESSION['csrf_tokens'][$stored_token]);
            }
        }
        
        return $token;
    }
    
    /**
     * Validate a CSRF token
     * @param string $token Token to validate
     * @return bool Valid token
     */
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        // Check if token is not expired (1 hour)
        $token_age = time() - $_SESSION['csrf_tokens'][$token];
        if ($token_age > 3600) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Remove token after use (one-time use)
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    /**
     * Add CSRF token to form HTML
     * @return string HTML input field
     */
    public static function getTokenField() {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}

/**
 * Enhanced input validation and sanitization
 */
class AccountingValidator {
    
    /**
     * Validate and sanitize monetary amount
     * @param mixed $amount Amount to validate
     * @param float $min_value Minimum allowed value
     * @param float $max_value Maximum allowed value
     * @return array [valid, sanitized_value, error_message]
     */
    public static function validateAmount($amount, $min_value = 0, $max_value = 999999999.99) {
        // Remove any non-numeric characters except decimal point and minus
        $clean_amount = preg_replace('/[^\d.-]/', '', (string)$amount);
        
        // Check if it's a valid number
        if (!is_numeric($clean_amount)) {
            return [false, 0, 'Amount must be a valid number'];
        }
        
        $numeric_amount = floatval($clean_amount);
        
        // Check range
        if ($numeric_amount < $min_value) {
            return [false, 0, "Amount must be at least $" . number_format($min_value, 2)];
        }
        
        if ($numeric_amount > $max_value) {
            return [false, 0, "Amount cannot exceed $" . number_format($max_value, 2)];
        }
        
        // Round to 2 decimal places
        $sanitized_amount = round($numeric_amount, 2);
        
        return [true, $sanitized_amount, ''];
    }
    
    /**
     * Validate date input
     * @param string $date Date string
     * @param string $format Expected format (default Y-m-d)
     * @param string $min_date Minimum allowed date
     * @param string $max_date Maximum allowed date
     * @return array [valid, formatted_date, error_message]
     */
    public static function validateDate($date, $format = 'Y-m-d', $min_date = '2000-01-01', $max_date = null) {
        if (empty($date)) {
            return [false, '', 'Date is required'];
        }
        
        // Set max_date to 1 year in future if not specified
        if (!$max_date) {
            $max_date = date('Y-m-d', strtotime('+1 year'));
        }
        
        $date_obj = DateTime::createFromFormat($format, $date);
        
        if (!$date_obj || $date_obj->format($format) !== $date) {
            return [false, '', "Date must be in format: $format"];
        }
        
        $formatted_date = $date_obj->format('Y-m-d');
        
        // Check date range
        if ($formatted_date < $min_date) {
            return [false, '', "Date cannot be earlier than $min_date"];
        }
        
        if ($formatted_date > $max_date) {
            return [false, '', "Date cannot be later than $max_date"];
        }
        
        return [true, $formatted_date, ''];
    }
    
    /**
     * Validate and sanitize description/notes
     * @param string $description Description text
     * @param int $max_length Maximum length allowed
     * @param bool $required Whether description is required
     * @return array [valid, sanitized_description, error_message]
     */
    public static function validateDescription($description, $max_length = 500, $required = false) {
        $description = trim((string)$description);
        
        if (empty($description)) {
            if ($required) {
                return [false, '', 'Description is required'];
            }
            return [true, '', ''];
        }
        
        if (strlen($description) > $max_length) {
            return [false, '', "Description cannot exceed $max_length characters"];
        }
        
        // Remove potentially dangerous HTML/JavaScript
        $clean_description = strip_tags($description);
        $clean_description = htmlspecialchars($clean_description, ENT_QUOTES, 'UTF-8');
        
        return [true, $clean_description, ''];
    }
    
    /**
     * Validate transaction type
     * @param string $type Transaction type
     * @return array [valid, type, error_message]
     */
    public static function validateTransactionType($type) {
        $valid_types = ['Income', 'Expense', 'Asset', 'Transfer'];
        
        if (!in_array($type, $valid_types)) {
            return [false, '', 'Invalid transaction type'];
        }
        
        return [true, $type, ''];
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
