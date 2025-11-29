<?php

/**
 * Two-Factor Authentication Utilities - W5OBM Amateur Radio Club
 * File: /authentication/totp_utils.php
 * Purpose: Complete 2FA implementation with TOTP, backup codes, and trusted devices
 * FIXED: Complete file with all necessary functions
 */

// Prevent direct access
if (!defined('TOTP_UTILS_LOADED')) {
    define('TOTP_UTILS_LOADED', true);
}

// Required includes
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';

/**
 * ============================================================================
 * TOTP SECRET GENERATION AND MANAGEMENT
 * ============================================================================
 */

/**
 * Generate a cryptographically secure TOTP secret
 * @param int $length Secret length (default 32 characters)
 * @return string Base32 encoded secret
 */
function generateTOTPSecret($length = 32)
{
    try {
        $random_bytes = random_bytes($length);
        return base32_encode($random_bytes);
    } catch (Exception $e) {
        error_log("TOTP Secret generation error: " . $e->getMessage());
        // Fallback to less secure method
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $secret;
    }
}

/**
 * Base32 encode function
 * @param string $input Binary data to encode
 * @return string Base32 encoded string
 */
function base32_encode($input)
{
    if (empty($input)) {
        return '';
    }
    
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $v = ($v << 8) | ord($input[$i]);
        $vbits += 8;
        
        while ($vbits >= 5) {
            $output .= $alphabet[($v >> ($vbits - 5)) & 31];
            $vbits -= 5;
        }
    }
    
    if ($vbits > 0) {
        $output .= $alphabet[($v << (5 - $vbits)) & 31];
    }
    
    return $output;
}

/**
 * Base32 decode function
 * @param string $input Base32 encoded string
 * @return string|false Decoded binary data or false on error
 */
function base32_decode($input)
{
    if (empty($input)) {
        return false;
    }
    
    $input = strtoupper($input);
    $input = preg_replace('/[^A-Z2-7]/', '', $input);
    
    $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32_map = array_flip(str_split($base32_chars));
    
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($input); $i++) {
        $char = $input[$i];
        if (!isset($base32_map[$char])) {
            return false;
        }
        
        $v = ($v << 5) | $base32_map[$char];
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr(($v >> ($vbits - 8)) & 0xff);
            $vbits -= 8;
        }
    }
    
    return $output;
}

/**
 * Generate TOTP URL for QR code
 * @param string $username Username or email
 * @param string $secret Base32 encoded secret
 * @param string $issuer Issuer name (default W5OBM)
 * @return string TOTP URL
 */
function generateTOTPURL($username, $secret, $issuer = 'W5OBM Amateur Radio Club')
{
    $url = 'otpauth://totp/' . 
           urlencode($issuer) . ':' . urlencode($username) . 
           '?secret=' . $secret . 
           '&issuer=' . urlencode($issuer) . 
           '&algorithm=SHA1' . 
           '&digits=6' . 
           '&period=30';
    
    return $url;
}

/**
 * Generate Google Charts QR Code image URL
 * @param string $totp_url TOTP URL
 * @param int $size QR code size (default 200x200)
 * @return string QR code image URL
 */
function generateQRCodeImageURL($totp_url, $size = 200)
{
    return 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . 
           '&chld=M|0&cht=qr&chl=' . urlencode($totp_url);
}

/**
 * ============================================================================
 * TOTP VERIFICATION FUNCTIONS
 * ============================================================================
 */

/**
 * Generate TOTP code for given secret and timestamp
 * @param string $secret Base32 encoded secret
 * @param int|null $timestamp Unix timestamp (null for current time)
 * @return string 6-digit TOTP code
 */
function generateTOTPCode($secret, $timestamp = null)
{
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    // Convert timestamp to 30-second periods
    $time_slice = floor($timestamp / 30);
    
    // Base32 decode the secret
    $decoded_secret = base32_decode($secret);
    if ($decoded_secret === false) {
        throw new InvalidArgumentException('Invalid secret format');
    }
    
    // Pack time slice into binary
    $time_hash = pack('N*', 0) . pack('N*', $time_slice);
    
    // Generate HMAC
    $hash = hash_hmac('sha1', $time_hash, $decoded_secret, true);
    
    // Extract dynamic binary code
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset+0]) & 0x7f) << 24) |
        ((ord($hash[$offset+1]) & 0xff) << 16) |
        ((ord($hash[$offset+2]) & 0xff) << 8) |
        (ord($hash[$offset+3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify TOTP code against secret with time window tolerance
 * @param string $secret Base32 encoded secret
 * @param string $code 6-digit code to verify
 * @param int $window Time window tolerance (default 1 = ±30 seconds)
 * @return bool True if code is valid
 */
function verifyTOTPCodeWithSecret($secret, $code, $window = 1)
{
    if (!$secret || !$code) {
        return false;
    }
    
    $code = str_pad(preg_replace('/[^0-9]/', '', $code), 6, '0', STR_PAD_LEFT);
    if (strlen($code) !== 6) {
        return false;
    }
    
    $current_time = time();
    
    // Check current time and surrounding windows
    for ($i = -$window; $i <= $window; $i++) {
        $test_time = $current_time + ($i * 30);
        try {
            $expected_code = generateTOTPCode($secret, $test_time);
            if (hash_equals($expected_code, $code)) {
                return true;
            }
        } catch (Exception $e) {
            error_log("TOTP verification error: " . $e->getMessage());
            continue;
        }
    }
    
    return false;
}

/**
 * Verify TOTP code for a specific user
 * @param int $user_id User ID
 * @param string $code 6-digit code to verify
 * @return array Verification result
 */
function verifyUserTOTPCode($user_id, $code)
{
    global $conn;
    
    $result = [
        'success' => false,
        'message' => '',
        'user_exists' => false,
        '2fa_enabled' => false
    ];
    
    try {
        // Get user's 2FA secret
        $stmt = $conn->prepare("
            SELECT two_factor_secret, two_factor_enabled 
            FROM auth_users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            $result['message'] = 'User not found.';
            return $result;
        }
        
        $result['user_exists'] = true;
        
        if (!$user['two_factor_enabled'] || !$user['two_factor_secret']) {
            $result['message'] = '2FA is not enabled for this user.';
            return $result;
        }
        
        $result['2fa_enabled'] = true;
        
        // Verify the code
        if (verifyTOTPCodeWithSecret($user['two_factor_secret'], $code)) {
            $result['success'] = true;
            $result['message'] = 'Code verified successfully.';
            
            // Log successful verification
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_totp_verified', 'TOTP code verified successfully', null, true);
            }
        } else {
            $result['message'] = 'Invalid verification code.';
            
            // Log failed verification
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_totp_failed', 'Invalid TOTP code provided', null, false);
            }
        }
        
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred during verification.';
        error_log("TOTP verification error: " . $e->getMessage());
        return $result;
    }
}

/**
 * ============================================================================
 * BACKUP CODE GENERATION AND MANAGEMENT
 * ============================================================================
 */

/**
 * Generate backup recovery codes
 * @param int $count Number of codes to generate (default 8)
 * @return array Array of backup codes
 */
function generateBackupCodes($count = 8)
{
    $codes = [];
    $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    
    for ($i = 0; $i < $count; $i++) {
        $code = '';
        for ($j = 0; $j < 4; $j++) {
            $code .= $charset[random_int(0, 35)];
        }
        $code .= '-';
        for ($j = 0; $j < 4; $j++) {
            $code .= $charset[random_int(0, 35)];
        }
        $codes[] = $code;
    }
    
    return $codes;
}

/**
 * Save backup codes for user
 * @param int $user_id User ID
 * @param array $codes Array of backup codes
 * @return bool True if saved successfully
 */
function saveUserBackupCodes($user_id, $codes)
{
    global $conn;
    
    try {
        $codes_json = json_encode($codes);
        $stmt = $conn->prepare("UPDATE auth_users SET two_factor_backup_codes = ? WHERE id = ?");
        $stmt->bind_param('si', $codes_json, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result && function_exists('logAuthActivity')) {
            logAuthActivity($user_id, '2fa_backup_codes_generated', 'New backup codes generated', null, true);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error saving backup codes: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify backup code for user
 * @param int $user_id User ID
 * @param string $code Backup code to verify
 * @return array Verification result
 */
function verifyBackupCode($user_id, $code)
{
    global $conn;
    
    $result = [
        'success' => false,
        'message' => '',
        'codes_remaining' => 0
    ];
    
    try {
        // Get user's backup codes
        $stmt = $conn->prepare("
            SELECT two_factor_backup_codes 
            FROM auth_users 
            WHERE id = ? AND is_active = 1 AND two_factor_enabled = 1
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$user || !$user['two_factor_backup_codes']) {
            $result['message'] = 'No backup codes available.';
            return $result;
        }
        
        $backup_codes = json_decode($user['two_factor_backup_codes'], true);
        if (!is_array($backup_codes)) {
            $result['message'] = 'Invalid backup codes format.';
            return $result;
        }
        
        $code_upper = strtoupper(trim($code));
        $key = array_search($code_upper, $backup_codes);
        
        if ($key !== false) {
            // Remove used backup code
            unset($backup_codes[$key]);
            $backup_codes = array_values($backup_codes);
            
            // Update database
            $stmt = $conn->prepare("UPDATE auth_users SET two_factor_backup_codes = ? WHERE id = ?");
            $codes_json = json_encode($backup_codes);
            $stmt->bind_param('si', $codes_json, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $result['success'] = true;
            $result['codes_remaining'] = count($backup_codes);
            $result['message'] = 'Backup code verified successfully.';
            
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_backup_verified', 
                    'Backup code verified, ' . count($backup_codes) . ' codes remaining', null, true);
            }
        } else {
            $result['message'] = 'Invalid backup code.';
            
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_backup_failed', 'Invalid backup code provided', null, false);
            }
        }
        
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred during verification.';
        error_log("Backup code verification error: " . $e->getMessage());
        return $result;
    }
}

/**
 * ============================================================================
 * 2FA SETUP AND MANAGEMENT
 * ============================================================================
 */

/**
 * Enable 2FA for user
 * @param int $user_id User ID
 * @param string $secret TOTP secret
 * @param string $verification_code Code to verify setup
 * @return array Setup result
 */
function enable2FAForUser($user_id, $secret, $verification_code)
{
    global $conn;
    
    $result = [
        'success' => false,
        'message' => '',
        'backup_codes' => []
    ];
    
    try {
        // Verify the code first
        if (!verifyTOTPCodeWithSecret($secret, $verification_code)) {
            $result['message'] = 'Invalid verification code. Please try again.';
            return $result;
        }
        
        // Generate backup codes
        $backup_codes = generateBackupCodes();
        $backup_codes_json = json_encode($backup_codes);
        
        // Enable 2FA in database
        $stmt = $conn->prepare("
            UPDATE auth_users 
            SET two_factor_enabled = 1, 
                two_factor_secret = ?, 
                two_factor_backup_codes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('ssi', $secret, $backup_codes_json, $user_id);
        
        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = '2FA enabled successfully.';
            $result['backup_codes'] = $backup_codes;
            
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_enabled', '2FA enabled for user account', null, true);
            }
        } else {
            $result['message'] = 'Failed to enable 2FA. Please try again.';
        }
        
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred while enabling 2FA.';
        error_log("2FA enable error: " . $e->getMessage());
        return $result;
    }
}

/**
 * Disable 2FA for user
 * @param int $user_id User ID
 * @param string $verification_code Current TOTP code or backup code
 * @return array Disable result
 */
function disable2FAForUser($user_id, $verification_code)
{
    global $conn;
    
    $result = [
        'success' => false,
        'message' => ''
    ];
    
    try {
        // First verify the current code
        $verify_totp = verifyUserTOTPCode($user_id, $verification_code);
        $verify_backup = verifyBackupCode($user_id, $verification_code);
        
        if (!$verify_totp['success'] && !$verify_backup['success']) {
            $result['message'] = 'Invalid verification code. Please provide a valid TOTP code or backup code.';
            return $result;
        }
        
        // Disable 2FA
        $stmt = $conn->prepare("
            UPDATE auth_users 
            SET two_factor_enabled = 0, 
                two_factor_secret = NULL, 
                two_factor_backup_codes = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param('i', $user_id);
        
        if ($stmt->execute()) {
            $result['success'] = true;
            $result['message'] = '2FA disabled successfully.';
            
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, '2fa_disabled', '2FA disabled for user account', null, true);
            }
        } else {
            $result['message'] = 'Failed to disable 2FA. Please try again.';
        }
        
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        $result['message'] = 'An error occurred while disabling 2FA.';
        error_log("2FA disable error: " . $e->getMessage());
        return $result;
    }
}

/**
 * Get 2FA status for user
 * @param int $user_id User ID
 * @return array 2FA status information
 */
function get2FAStatus($user_id)
{
    global $conn;
    
    $status = [
        'enabled' => false,
        'secret_exists' => false,
        'backup_codes_count' => 0,
        'user_found' => false
    ];
    
    try {
        $stmt = $conn->prepare("
            SELECT two_factor_enabled, two_factor_secret, two_factor_backup_codes
            FROM auth_users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $status['user_found'] = true;
            $status['enabled'] = (bool)$user['two_factor_enabled'];
            $status['secret_exists'] = !empty($user['two_factor_secret']);
            
            if ($user['two_factor_backup_codes']) {
                $backup_codes = json_decode($user['two_factor_backup_codes'], true);
                $status['backup_codes_count'] = is_array($backup_codes) ? count($backup_codes) : 0;
            }
        }
        
        return $status;
    } catch (Exception $e) {
        error_log("Error getting 2FA status: " . $e->getMessage());
        return $status;
    }
}

/**
 * ============================================================================
 * TRUSTED DEVICE MANAGEMENT
 * ============================================================================
 */

/**
 * Check if device is trusted
 * @param int $user_id User ID
 * @param string $device_token Device token from cookie
 * @return bool True if device is trusted
 */
function isTrustedDevice($user_id, $device_token)
{
    global $conn;
    
    if (!function_exists('tableExists') || !tableExists('auth_trusted_devices')) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT id FROM auth_trusted_devices 
            WHERE user_id = ? AND device_token = ? AND expires_at > NOW()
        ");
        $stmt->bind_param('is', $user_id, $device_token);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result) {
            // Update last used
            $stmt = $conn->prepare("UPDATE auth_trusted_devices SET last_used_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $result['id']);
            $stmt->execute();
            $stmt->close();
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Trusted device check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create trusted device
 * @param int $user_id User ID
 * @param string $ip_address IP address
 * @param string $user_agent User agent string
 * @return string|false Device token or false on failure
 */
function createTrustedDevice($user_id, $ip_address, $user_agent)
{
    global $conn;
    
    if (!function_exists('tableExists') || !tableExists('auth_trusted_devices')) {
        return false;
    }
    
    try {
        $device_token = bin2hex(random_bytes(32));
        $device_name = getBrowserInfo($user_agent);
        $trusted_days = 30;
        $expires_at = date('Y-m-d H:i:s', time() + ($trusted_days * 24 * 60 * 60));
        
        $stmt = $conn->prepare("
            INSERT INTO auth_trusted_devices 
            (user_id, device_token, device_name, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssss', $user_id, $device_token, $device_name, $ip_address, $user_agent, $expires_at);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Set cookie
            setcookie('trusted_device', $device_token, time() + ($trusted_days * 24 * 60 * 60), '/', '', true, true);
            
            if (function_exists('logAuthActivity')) {
                logAuthActivity($user_id, 'trusted_device_created', 'Trusted device created: ' . $device_name, null, true);
            }
            
            return $device_token;
        }
        
        $stmt->close();
        return false;
    } catch (Exception $e) {
        error_log("Create trusted device error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get browser info from user agent
 * @param string $user_agent User agent string
 * @return string Browser and OS information
 */
function getBrowserInfo($user_agent)
{
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';
    
    // Detect browser
    if (strpos($user_agent, 'Chrome') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false) {
        $browser = 'Safari';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        $browser = 'Edge';
    }
    
    // Detect OS
    if (strpos($user_agent, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($user_agent, 'Mac') !== false) {
        $os = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $os = 'Android';
    } elseif (strpos($user_agent, 'iOS') !== false) {
        $os = 'iOS';
    }
    
    return $browser . ' on ' . $os;
}

/**
 * ============================================================================
 * UTILITY FUNCTIONS
 * ============================================================================
 */

/**
 * Clean up expired 2FA data
 * @return array Cleanup statistics
 */
function cleanup2FAData()
{
    global $conn;
    
    $stats = [
        'trusted_devices_cleaned' => 0,
        'expired_sessions_cleaned' => 0
    ];
    
    try {
        // Clean expired trusted devices if table exists
        if (function_exists('tableExists') && tableExists('auth_trusted_devices')) {
            $result = $conn->query("DELETE FROM auth_trusted_devices WHERE expires_at < NOW()");
            if ($result) {
                $stats['trusted_devices_cleaned'] = $conn->affected_rows;
            }
        }
        
        // Clean expired 2FA sessions if table exists
        if (function_exists('tableExists') && tableExists('auth_sessions')) {
            $result = $conn->query("DELETE FROM auth_sessions WHERE expires_at < NOW()");
            if ($result) {
                $stats['expired_sessions_cleaned'] = $conn->affected_rows;
            }
        }
    } catch (Exception $e) {
        error_log("2FA cleanup error: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Test TOTP implementation
 * @return array Test results
 */
function testTOTPImplementation()
{
    $tests = [
        'secret_generation' => false,
        'code_generation' => false,
        'code_verification' => false,
        'backup_codes' => false
    ];
    
    try {
        // Test secret generation
        $secret = generateTOTPSecret();
        $tests['secret_generation'] = !empty($secret) && strlen($secret) === 32;
        
        // Test code generation
        $code = generateTOTPCode($secret);
        $tests['code_generation'] = !empty($code) && strlen($code) === 6 && ctype_digit($code);
        
        // Test code verification (should verify against itself)
        $tests['code_verification'] = verifyTOTPCodeWithSecret($secret, $code);
        
        // Test backup codes
        $backup_codes = generateBackupCodes(5);
        $tests['backup_codes'] = count($backup_codes) === 5 && 
                                 preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $backup_codes[0]);
    } catch (Exception $e) {
        error_log("TOTP test error: " . $e->getMessage());
    }
    
    return $tests;
}

/**
 * ============================================================================
 * INITIALIZATION
 * ============================================================================
 */

// Log that TOTP utils has been loaded (debug mode only)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_log("TOTP Utils loaded successfully");
}

// Test TOTP implementation on first load (development only)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && function_exists('logError')) {
    $test_results = testTOTPImplementation();
    $failed_tests = array_filter($test_results, function($result) { return !$result; });
    
    if (!empty($failed_tests)) {
        logError("TOTP implementation tests failed: " . implode(', ', array_keys($failed_tests)), 'auth');
    }
}

// Automatically clean up old data occasionally (1% chance)
if (rand(1, 100) === 1) {
    cleanup2FAData();
}
