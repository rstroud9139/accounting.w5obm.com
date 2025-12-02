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

require_once __DIR__ . '/../app/repositories/TransactionRepository.php';
// Controllers (use legacy controllers to match current DB schema)
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
        $transactionsRepo = api_transactions_repo();
        if ($method === 'GET') {
            if (!$can_view) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if ($id) {
                $tx = $transactionsRepo->findById($id);
                if (!$tx) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => $tx]);
            }
            $filters = api_transaction_filters_from_request();
            $data = $transactionsRepo->findAll($filters);
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            try {
                $payload = api_normalize_transaction_payload($d);
            } catch (InvalidArgumentException $e) {
                api_response(400, ['success' => false, 'error' => $e->getMessage()]);
            }
            $newId = $transactionsRepo->createWithPosting($payload, []);
            if ($newId) {
                api_response(201, ['success' => true, 'id' => $newId]);
            }
            api_response(500, ['success' => false, 'error' => 'Failed to create transaction']);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $d = $json ?? [];
            try {
                $payload = api_normalize_transaction_payload($d);
            } catch (InvalidArgumentException $e) {
                api_response(400, ['success' => false, 'error' => $e->getMessage()]);
            }
            $ok = $transactionsRepo->updateWithPosting($id, $payload, []);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $ok = $transactionsRepo->delete($id);
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
                $acct = getLedgerAccountById($id);
                if (!$acct) api_response(404, ['success' => false, 'error' => 'Not found']);
                api_response(200, ['success' => true, 'data' => ledger_format_response($acct)]);
            }

            $filters = [];
            $status = strtolower($_GET['status'] ?? 'active');
            if ($status === 'inactive') {
                $filters['active'] = false;
            } elseif ($status === 'all') {
                // leave filter empty to pull both active and inactive
            } else {
                $filters['active'] = true;
            }
            if (!empty($_GET['type'])) {
                $filters['account_type'] = $_GET['type'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            $rows = getAllLedgerAccounts($filters);
            $data = array_map('ledger_format_response', $rows);
            api_response(200, ['success' => true, 'data' => $data]);
        } elseif ($method === 'POST') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            $d = $json ?? $_POST;
            try {
                $payload = ledger_normalize_request($d, [], true);
            } catch (InvalidArgumentException $e) {
                api_response(400, ['success' => false, 'error' => $e->getMessage()]);
            }
            $newId = addLedgerAccount($payload);
            api_response($newId ? 201 : 500, ['success' => (bool)$newId, 'id' => $newId]);
        } elseif ($method === 'PUT') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $existing = getLedgerAccountById($id);
            if (!$existing) api_response(404, ['success' => false, 'error' => 'Not found']);
            $d = $json ?? [];
            try {
                $payload = ledger_normalize_request($d, $existing, false);
            } catch (InvalidArgumentException $e) {
                api_response(400, ['success' => false, 'error' => $e->getMessage()]);
            }
            $ok = updateLedgerAccount($id, $payload);
            api_response($ok ? 200 : 500, ['success' => (bool)$ok]);
        } elseif ($method === 'DELETE') {
            if (!$can_write) api_response(403, ['success' => false, 'error' => 'Forbidden']);
            if (!$id) api_response(400, ['success' => false, 'error' => 'Missing id']);
            $body = $json ?? [];
            $reassign = null;
            if (isset($body['reassign_account_id'])) {
                $reassign = (int)$body['reassign_account_id'];
            } elseif (isset($_GET['reassign_account_id'])) {
                $reassign = (int)$_GET['reassign_account_id'];
            }
            $ok = deleteLedgerAccount($id, $reassign ?: null);
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

function api_transactions_repo(): TransactionRepository
{
    static $repo = null;
    if ($repo instanceof TransactionRepository) {
        return $repo;
    }
    $repo = new TransactionRepository(accounting_db_connection());
    return $repo;
}

function api_transaction_filters_from_request(): array
{
    $filters = [];
    $start = $_GET['start_date'] ?? null;
    $end = $_GET['end_date'] ?? null;
    if ($start && $end) {
        $filters['start_date'] = $start;
        $filters['end_date'] = $end;
    }
    if (!empty($_GET['category_id'])) {
        $filters['category_id'] = (int)$_GET['category_id'];
    }
    if (!empty($_GET['type'])) {
        $filters['type'] = $_GET['type'];
    }
    if (!empty($_GET['account_id'])) {
        $filters['account_id'] = (int)$_GET['account_id'];
    }
    if (!empty($_GET['vendor_id'])) {
        $filters['vendor_id'] = (int)$_GET['vendor_id'];
    }
    if (!empty($_GET['q'])) {
        $filters['q'] = trim((string)$_GET['q']);
    }
    return $filters;
}

function api_normalize_transaction_payload(array $source): array
{
    $categoryId = isset($source['category_id']) ? (int)$source['category_id'] : 0;
    if ($categoryId <= 0) {
        throw new InvalidArgumentException('category_id is required.');
    }

    $amount = isset($source['amount']) ? (float)$source['amount'] : 0.0;
    if ($amount <= 0) {
        throw new InvalidArgumentException('amount must be greater than zero.');
    }

    $date = trim((string)($source['transaction_date'] ?? ''));
    if ($date === '') {
        throw new InvalidArgumentException('transaction_date is required.');
    }
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('transaction_date must be formatted as YYYY-MM-DD.');
    }

    $type = trim((string)($source['type'] ?? ''));
    if ($type === '') {
        throw new InvalidArgumentException('type is required.');
    }
    $allowedTypes = ['Income', 'Expense', 'Transfer', 'Asset'];
    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('type must be one of: ' . implode(', ', $allowedTypes));
    }

    $cashAccountId = null;
    if (isset($source['cash_account_id'])) {
        $cashAccountId = (int)$source['cash_account_id'];
    } elseif (isset($source['account_id'])) {
        $cashAccountId = (int)$source['account_id'];
    }
    if ($cashAccountId !== null && $cashAccountId <= 0) {
        $cashAccountId = null;
    }

    $vendorId = isset($source['vendor_id']) ? (int)$source['vendor_id'] : null;
    if ($vendorId !== null && $vendorId <= 0) {
        $vendorId = null;
    }

    $payload = [
        'category_id' => $categoryId,
        'amount' => abs($amount),
        'transaction_date' => $date,
        'description' => trim((string)($source['description'] ?? '')),
        'notes' => trim((string)($source['notes'] ?? '')),
        'reference_number' => trim((string)($source['reference_number'] ?? '')),
        'type' => $type,
    ];

    if ($cashAccountId !== null) {
        $payload['cash_account_id'] = $cashAccountId;
    }
    if ($vendorId !== null) {
        $payload['vendor_id'] = $vendorId;
    }

    return $payload;
}

function ledger_format_response(array $account): array
{
    $response = [
        'id' => (int)($account['id'] ?? 0),
        'name' => $account['name'] ?? '',
        'account_number' => $account['account_number'] ?? '',
        'account_type' => $account['account_type'] ?? '',
        'description' => $account['description'] ?? '',
        'parent_account_id' => isset($account['parent_account_id']) ? (int)$account['parent_account_id'] : null,
        'parent_account_name' => $account['parent_account_name'] ?? null,
        'active' => (int)($account['active'] ?? 1) === 1,
    ];

    if (isset($account['account_number'])) {
        $response['code'] = $account['account_number'];
    }
    if (isset($account['transaction_count'])) {
        $response['transaction_count'] = (int)$account['transaction_count'];
    }
    if (isset($account['child_count'])) {
        $response['child_count'] = (int)$account['child_count'];
    }

    return $response;
}

function ledger_normalize_request(array $source, array $defaults = [], bool $allowAutoCode = false): array
{
    $name = trim((string)($source['name'] ?? ($defaults['name'] ?? '')));
    if ($name === '') {
        throw new InvalidArgumentException('Account name is required.');
    }

    $code = trim((string)(
        $source['account_number']
        ?? $source['code']
        ?? $source['number']
        ?? ($defaults['account_number'] ?? $defaults['code'] ?? '')
    ));

    $typeRaw = $source['account_type'] ?? $source['type'] ?? ($defaults['account_type'] ?? $defaults['type'] ?? '');
    $accountType = ledger_normalize_account_type($typeRaw);

    if ($code === '') {
        if ($allowAutoCode) {
            $code = ledger_autocode($accountType, $name);
        } else {
            throw new InvalidArgumentException('account_number (code) is required.');
        }
    }

    $description = trim((string)($source['description'] ?? ($defaults['description'] ?? '')));
    $parentRaw = $source['parent_account_id'] ?? $source['parent_id'] ?? ($defaults['parent_account_id'] ?? null);
    $parentId = is_numeric($parentRaw) ? (int)$parentRaw : null;

    return [
        'name' => $name,
        'account_number' => $code,
        'account_type' => $accountType,
        'description' => $description,
        'parent_account_id' => $parentId,
    ];
}

function ledger_normalize_account_type(?string $type): string
{
    $map = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expense',
    ];

    $key = strtolower(trim((string)$type));
    return $map[$key] ?? 'Asset';
}

function ledger_autocode(string $type, string $name): string
{
    $prefix = strtoupper(substr($type ?: 'ACC', 0, 3));
    $hash = strtoupper(substr(md5($name . microtime(true)), 0, 6));
    return $prefix . '-' . $hash;
}
