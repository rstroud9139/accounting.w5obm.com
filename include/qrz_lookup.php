<?php

/**
 * QRZ LOOKUP HANDLER / LIBRARY
 * File: /include/qrz_lookup.php
 * Handles QRZ.com database lookups for callsign information
 */

if (!defined('QRZ_LOOKUP_LIBRARY_MODE')) {
    define('QRZ_LOOKUP_LIBRARY_MODE', false);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/dbconn.php';
require_once __DIR__ . '/helper_functions.php';

if (!QRZ_LOOKUP_LIBRARY_MODE) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }

    $callsign = trim(strtoupper($_POST['callsign'] ?? ''));
    if (empty($callsign)) {
        echo json_encode(['success' => false, 'error' => 'Callsign required']);
        exit();
    }

    if (!preg_match('/^[A-Z0-9\/]{3,12}$/', $callsign)) {
        echo json_encode(['success' => false, 'error' => 'Invalid callsign format']);
        exit();
    }

    try {
        $qrz_data = lookupCallsignRecord($callsign, $conn);
    } catch (Exception $e) {
        error_log("QRZ Lookup Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Lookup service temporarily unavailable'
        ]);
        exit();
    }

    if ($qrz_data) {
        $class_map = [
            'T' => 'Technician',
            'G' => 'General',
            'A' => 'Advanced',
            'E' => 'Extra'
        ];
        $class_code = strtoupper($qrz_data['class'] ?? '');
        $license_class = $class_map[$class_code] ?? $class_code;

        echo json_encode([
            'success' => true,
            'fname' => $qrz_data['first_name'] ?? '',
            'lname' => $qrz_data['last_name'] ?? '',
            'nickname' => $qrz_data['nickname'] ?? '',
            'email' => $qrz_data['email'] ?? '',
            'address' => $qrz_data['addr1'] ?? '',
            'city' => $qrz_data['addr2'] ?? '',
            'state' => $qrz_data['state'] ?? '',
            'zip' => $qrz_data['zip'] ?? '',
            'country' => $qrz_data['country'] ?? '',
            'grid' => $qrz_data['grid'] ?? '',
            'class' => $class_code,
            'license_class' => $license_class,
            'callsign' => $qrz_data['callsign'] ?? $callsign,
            'moddate' => $qrz_data['moddate'] ?? '',
            'source' => $qrz_data['source'] ?? 'cache'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No data found for this callsign'
        ]);
    }

    exit();
}

// ============================================================================
// QRZ LOOKUP FUNCTIONS
// ============================================================================

/**
 * Primary helper used by both the API endpoint and internal PHP callers.
 * Returns associative array with callsign data or null if not found.
 */
function lookupCallsignRecord($callsign, $conn)
{
    if (!$conn) {
        throw new Exception('Database connection unavailable');
    }

    $normalized = strtoupper(trim($callsign));
    if ($normalized === '') {
        return null;
    }
    $base_callsign = explode('/', $normalized)[0];

    // Try cache first
    $qrz_data = getQRZDataFromCache($base_callsign, $conn);
    if ($qrz_data) {
        $qrz_data['source'] = 'cache';
        return $qrz_data;
    }

    // Live lookup (QRZ or fallback)
    $qrz_data = performLiveQRZLookup($base_callsign);
    if ($qrz_data) {
        $qrz_data['source'] = 'live';
        cacheQRZData($base_callsign, $qrz_data, $conn);
        return $qrz_data;
    }

    return null;
}

/**
 * Get QRZ data from local cache
 * @param string $callsign
 * @param mysqli $conn
 * @return array|null
 */
function getQRZDataFromCache($callsign, $conn)
{
    $stmt = $conn->prepare("
        SELECT callsign, first_name, last_name, addr1, addr2, state, zip, 
               country, grid, class, email, moddate
        FROM qrz_data 
        WHERE callsign = ? 
        AND moddate > DATE_SUB(NOW(), INTERVAL 30 DAY)
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $callsign);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row;
    }

    $stmt->close();
    return null;
}

/**
 * Perform live QRZ lookup via QRZ.com API
 * @param string $callsign
 * @return array|null
 */
function performLiveQRZLookup($callsign)
{
    // QRZ.com API credentials (should be in config)
    $qrz_username = defined('QRZ_USERNAME') ? QRZ_USERNAME : null;
    $qrz_password = defined('QRZ_PASSWORD') ? QRZ_PASSWORD : null;

    if (!$qrz_username || !$qrz_password) {
        // Fall back to alternative lookup methods
        return performAlternativeLookup($callsign);
    }

    try {
        // Get QRZ session key
        $session_key = getQRZSessionKey($qrz_username, $qrz_password);

        if (!$session_key) {
            return performAlternativeLookup($callsign);
        }

        // Lookup callsign data
        $lookup_url = "https://xmldata.qrz.com/xml/current/?s={$session_key}&callsign={$callsign}";

        $xml_data = file_get_contents($lookup_url);

        if (!$xml_data) {
            return performAlternativeLookup($callsign);
        }

        // Parse XML response
        $xml = simplexml_load_string($xml_data);

        if (!$xml || !isset($xml->Callsign)) {
            return performAlternativeLookup($callsign);
        }

        $callsign_data = $xml->Callsign;

        // Convert to standardized format
        return [
            'callsign' => (string)$callsign_data->call,
            'first_name' => (string)$callsign_data->fname,
            'last_name' => (string)$callsign_data->name,
            'addr1' => (string)$callsign_data->addr1,
            'addr2' => (string)$callsign_data->addr2,
            'state' => (string)$callsign_data->state,
            'zip' => (string)$callsign_data->zip,
            'country' => (string)$callsign_data->country,
            'grid' => (string)$callsign_data->grid,
            'class' => (string)$callsign_data->class,
            'email' => (string)$callsign_data->email,
            'moddate' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("QRZ API Error: " . $e->getMessage());
        return performAlternativeLookup($callsign);
    }
}

/**
 * Get QRZ session key for API access
 * @param string $username
 * @param string $password
 * @return string|null
 */
function getQRZSessionKey($username, $password)
{
    $auth_url = "https://xmldata.qrz.com/xml/current/?username={$username}&password={$password}";

    $xml_data = file_get_contents($auth_url);

    if (!$xml_data) {
        return null;
    }

    $xml = simplexml_load_string($xml_data);

    if (!$xml || !isset($xml->Session->Key)) {
        return null;
    }

    return (string)$xml->Session->Key;
}

/**
 * Alternative lookup methods when QRZ API unavailable
 * @param string $callsign
 * @return array|null
 */
function performAlternativeLookup($callsign)
{
    // Try radio-electronics.com lookup
    $lookup_url = "https://www.radio-electronics.com/info/amateur-radio/callsigns/callsign-lookup.php?callsign=" . urlencode($callsign);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'W5OBM-NET-Logger/1.0'
        ]
    ]);

    $html = file_get_contents($lookup_url, false, $context);

    if ($html) {
        // Parse HTML response (simplified parsing)
        $data = parseAlternativeLookupHTML($html, $callsign);
        if ($data) {
            return $data;
        }
    }

    // Try ARRL lookup as final fallback
    return performARRLLookup($callsign);
}

/**
 * Parse alternative lookup HTML
 * @param string $html
 * @param string $callsign
 * @return array|null
 */
function parseAlternativeLookupHTML($html, $callsign)
{
    // Simple regex parsing for common patterns
    $patterns = [
        'name' => '/Name:\s*([^<\n]+)/i',
        'address' => '/Address:\s*([^<\n]+)/i',
        'state' => '/State:\s*([A-Z]{2})/i',
        'class' => '/Class:\s*([A-Z]+)/i'
    ];

    $data = ['callsign' => $callsign, 'moddate' => date('Y-m-d H:i:s')];

    foreach ($patterns as $field => $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            if ($field === 'name') {
                $name_parts = explode(' ', trim($matches[1]), 2);
                $data['first_name'] = $name_parts[0] ?? '';
                $data['last_name'] = $name_parts[1] ?? '';
            } else {
                $data[$field] = trim($matches[1]);
            }
        }
    }

    // Return data only if we found something useful
    return !empty($data['first_name']) || !empty($data['last_name']) ? $data : null;
}

/**
 * ARRL lookup fallback
 * @param string $callsign
 * @return array|null
 */
function performARRLLookup($callsign)
{
    // This would require ARRL access - placeholder for now
    // Could implement FCC database lookup instead
    return null;
}

/**
 * Cache QRZ data in local database
 * @param string $callsign
 * @param array $data
 * @param mysqli $conn
 */
function cacheQRZData($callsign, $data, $conn)
{
    $stmt = $conn->prepare("
        INSERT INTO qrz_data (
            callsign, first_name, last_name, addr1, addr2, state, zip,
            country, grid, class, email, moddate
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            addr1 = VALUES(addr1),
            addr2 = VALUES(addr2),
            state = VALUES(state),
            zip = VALUES(zip),
            country = VALUES(country),
            grid = VALUES(grid),
            class = VALUES(class),
            email = VALUES(email),
            moddate = VALUES(moddate)
    ");

    if ($stmt) {
        $first_name = $data['first_name'] ?? null;
        $last_name = $data['last_name'] ?? null;
        $addr1 = $data['addr1'] ?? null;
        $addr2 = $data['addr2'] ?? null;
        $state = $data['state'] ?? null;
        $zip = $data['zip'] ?? null;
        $country = $data['country'] ?? null;
        $grid = $data['grid'] ?? null;
        $class = $data['class'] ?? null;
        $email = $data['email'] ?? null;
        $moddate = $data['moddate'];

        $stmt->bind_param(
            "ssssssssssss",
            $callsign,
            $first_name,
            $last_name,
            $addr1,
            $addr2,
            $state,
            $zip,
            $country,
            $grid,
            $class,
            $email,
            $moddate
        );

        $stmt->execute();
        $stmt->close();
    }
}
