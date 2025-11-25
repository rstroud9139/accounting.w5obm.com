<?php

/**
 * Accounting API Routes
 * JSON API for other applications to interact with Accounting.
 * Auth: Requires authenticated user. Write ops require accounting permissions.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';
@require_once __DIR__ . '/../../include/load_env.php';

// Controllers (use legacy controllers to match current DB schema)
require_once __DIR__ . '/../controllers/transactionController.php';
require_once __DIR__ . '/../controllers/categoryController.php';
require_once __DIR__ . '/../controllers/ledgerController.php';
require_once __DIR__ . '/../controllers/vendorController.php';
require_once __DIR__ . '/../controllers/donation_controller.php';

function api_response($code, $payload)
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Auth: session or API token
$expected_token = getenv('W5OBM_API_TOKEN') ?: getenv('API_TOKEN');
$provided_token = null;
if (function_exists('getallheaders')) {
    $hdrs = getallheaders();
    if (!empty($hdrs['Authorization']) && preg_match('/Bearer\s+(.*)/i', $hdrs['Authorization'], $m)) {
        $provided_token = trim($m[1]);
    }
}
if (!$provided_token && !empty($_GET['api_token'])) {
    $provided_token = $_GET['api_token'];
}

$api_token_ok = $expected_token && $provided_token && hash_equals($expected_token, $provided_token);

if (!$api_token_ok && !isAuthenticated()) {
    api_response(401, ['success' => false, 'error' => 'Authentication required']);
}

$user_id = $api_token_ok ? 0 : getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$resource = strtolower($_GET['resource'] ?? '');
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

// Parse JSON body if present
$raw = file_get_contents('php://input');
$json = null;
if (!empty($raw)) {
    $json = json_decode($raw, true);
}

// Basic permission model: API token grants full access; otherwise check session permissions
$can_view = $api_token_ok || isAdmin($user_id) || hasPermission($user_id, 'accounting_view') || hasPermission($user_id, 'accounting_manage');
$can_write = $api_token_ok || isAdmin($user_id) || hasPermission($user_id, 'accounting_manage');

if (!$resource) {
    api_response(400, ['success' => false, 'error' => 'Missing resource parameter']);
}

switch ($resource) {
    case 'transactions':
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $tx = fetch_transaction_by_id($id);
                if (!$tx) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $tx]);
            }
            $start = $_GET['start_date'] ?? null;
            $end = $_GET['end_date'] ?? null;
            $category_id = !empty($_GET['category_id']) ? intval($_GET['category_id']) : null;
            $type = $_GET['type'] ?? null;
            $account_id = !empty($_GET['account_id']) ? intval($_GET['account_id']) : null;
            $data = fetch_all_transactions($start, $end, $category_id, $type, $account_id);
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            $category_id = intval($d['category_id'] ?? 0);
            $amount = floatval($d['amount'] ?? 0);
            $date = $d['transaction_date'] ?? '';
            $desc = $d['description'] ?? '';
            $type = $d['type'] ?? '';
            $account_id = isset($d['account_id']) ? intval($d['account_id']) : null;
            $vendor_id = isset($d['vendor_id']) ? intval($d['vendor_id']) : null;
            if (!$category_id || !$amount || !$date || !$type) {
                api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            }
            $ok = add_transaction($category_id, $amount, $date, $desc, $type, $account_id, $vendor_id);
            api_response($ok ? 201 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            $category_id = intval($d['category_id'] ?? 0);
            $amount = floatval($d['amount'] ?? 0);
            $date = $d['transaction_date'] ?? '';
            $desc = $d['description'] ?? '';
            $type = $d['type'] ?? '';
            $account_id = isset($d['account_id']) ? intval($d['account_id']) : null;
            $vendor_id = isset($d['vendor_id']) ? intval($d['vendor_id']) : null;
            if (!$category_id || !$amount || !$date || !$type) {
                api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            }
            $ok = update_transaction($id, $category_id, $amount, $date, $desc, $type, $account_id, $vendor_id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $ok = delete_transaction($id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        }
        break;

    case 'categories':
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $cat = fetch_category_by_id($id);
                if (!$cat) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $cat]);
            }
            $data = fetch_all_categories();
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            $name = trim($d['name'] ?? '');
            $type = trim($d['type'] ?? '');
            $desc = trim($d['description'] ?? '');
            if (!$name || !$type) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = add_category($name, $desc, $type);
            api_response($ok ? 201 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            $name = trim($d['name'] ?? '');
            $type = trim($d['type'] ?? '');
            $desc = trim($d['description'] ?? '');
            if (!$name || !$type) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = update_category($id, $name, $desc, $type);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            // Prevent deletion if in use
            if (is_category_in_use($id)) {
                api_response(409, ['success' => false, 'error' => 'Category in use']);
            }
            $ok = delete_category($id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        }
        break;

    case 'ledger':
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $acct = fetch_ledger_account_by_id($id);
                if (!$acct) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $acct]);
            }
            $data = fetch_all_ledger_accounts();
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            $name = trim($d['name'] ?? '');
            $desc = trim($d['description'] ?? '');
            $category_id = isset($d['category_id']) ? intval($d['category_id']) : 0;
            if (!$name || !$category_id) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = add_ledger_account($name, $desc, $category_id);
            api_response($ok ? 201 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            $name = trim($d['name'] ?? '');
            $desc = trim($d['description'] ?? '');
            $category_id = isset($d['category_id']) ? intval($d['category_id']) : 0;
            if (!$name || !$category_id) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = update_ledger_account($id, $name, $desc, $category_id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $ok = delete_ledger_account($id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        }
        break;

    case 'vendors':
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $ven = fetch_vendor_by_id($id);
                if (!$ven) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $ven]);
            }
            $data = fetch_all_vendors();
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            $name = trim($d['name'] ?? '');
            $contact = trim($d['contact_name'] ?? '');
            $email = trim($d['email'] ?? '');
            $phone = trim($d['phone'] ?? '');
            $address = trim($d['address'] ?? '');
            $notes = trim($d['notes'] ?? '');
            if (!$name) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = add_vendor($name, $contact, $email, $phone, $address, $notes);
            api_response($ok ? 201 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            $name = trim($d['name'] ?? '');
            $contact = trim($d['contact_name'] ?? '');
            $email = trim($d['email'] ?? '');
            $phone = trim($d['phone'] ?? '');
            $address = trim($d['address'] ?? '');
            $notes = trim($d['notes'] ?? '');
            if (!$name) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = update_vendor($id, $name, $contact, $email, $phone, $address, $notes);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $ok = delete_vendor($id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        }
        break;

    case 'donations':
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $don = fetch_donation_by_id($id);
                if (!$don) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $don]);
            }
            $start = $_GET['start_date'] ?? null;
            $end = $_GET['end_date'] ?? null;
            $contact_id = !empty($_GET['contact_id']) ? intval($_GET['contact_id']) : null;
            $data = fetch_all_donations($start, $end, $contact_id);
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            $contact_id = intval($d['contact_id'] ?? 0);
            $amount = floatval($d['amount'] ?? 0);
            $date = $d['donation_date'] ?? '';
            $desc = $d['description'] ?? '';
            $tax = isset($d['tax_deductible']) ? (bool)$d['tax_deductible'] : true;
            $notes = $d['notes'] ?? '';
            if (!$contact_id || !$amount || !$date) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = add_donation($contact_id, $amount, $date, $desc, $tax, $notes);
            if ($ok) {
                // Attempt to email receipt (non-blocking result in payload)
                $email_sent = null;
                $email_msg = null;
                if (isset($conn) && $conn instanceof mysqli) {
                    $newId = (int)$conn->insert_id;
                    if ($newId > 0) {
                        require_once __DIR__ . '/../utils/email_utils.php';
                        $bridge = __DIR__ . '/../lib/email_bridge.php';
                        if (is_file($bridge)) {
                            require_once $bridge;
                        } else {
                            if (!function_exists('accounting_email_send_simple')) {
                                function accounting_email_send_simple($to, $subject, $body, $isHtml = true)
                                {
                                    return send_email($to, $subject, $body, null, $isHtml);
                                }
                            }
                        }
                        $don = fetch_donation_by_id($newId);
                        if ($don && !empty($don['contact_email'])) {
                            list($subj, $html, $text) = compose_donation_receipt_email($don);
                            $sent = accounting_email_send_simple($don['contact_email'], $subj, $html, true);
                            $okFlag = is_array($sent) ? ($sent['success'] ?? false) : (bool)$sent;
                            if ($okFlag) {
                                mark_receipt_sent($newId);
                                $email_sent = true;
                                $email_msg = 'sent';
                            } else {
                                $email_sent = false;
                                $email_msg = 'failed';
                            }
                        }
                    }
                }
                api_response(201, ['success' => true, 'id' => $newId ?? null, 'email' => $email_msg, 'email_sent' => $email_sent]);
            } else {
                api_response(500, ['success' => false]);
            }
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            $contact_id = intval($d['contact_id'] ?? 0);
            $amount = floatval($d['amount'] ?? 0);
            $date = $d['donation_date'] ?? '';
            $desc = $d['description'] ?? '';
            $tax = isset($d['tax_deductible']) ? (bool)$d['tax_deductible'] : true;
            $notes = $d['notes'] ?? '';
            if (!$contact_id || !$amount || !$date) api_response(400, ['success' => false, 'error' => 'Missing required fields']);
            $ok = update_donation($id, $contact_id, $amount, $date, $desc, $tax, $notes);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $ok = delete_donation($id);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        }
        break;

    default:
        api_response(404, ['success' => false, 'error' => 'Unknown resource']);
}

api_response(405, ['success' => false, 'error' => 'Method not allowed']);
