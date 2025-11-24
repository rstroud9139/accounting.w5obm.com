<?php

require_once __DIR__ . '/../utils/session_manager.php';
require_once __DIR__ . '/../../include/dbconn.php';
require_once __DIR__ . '/../lib/helpers.php';

validate_session();

$userId = getCurrentUserId();
if (!hasPermission($userId, 'accounting_manage')) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to post adjusting entries.');
    header('Location: /accounting/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/ledger/register.php');
    exit();
}

$sessionToken = $_SESSION['ledger_adjust_csrf'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';
if (!$sessionToken || !$submittedToken || !hash_equals($sessionToken, $submittedToken)) {
    setToastMessage('danger', 'Security Check Failed', 'Invalid or expired adjustment token.');
    header('Location: /accounting/ledger/register.php');
    exit();
}

function normalizeAmount($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return round((float)$value, 2);
}

function safeReturnUrl($url): string
{
    if (empty($url) || !is_string($url)) {
        return '/accounting/ledger/register.php';
    }
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '/accounting/ledger/register.php';
    }
    $lower = strtolower($trimmed);
    if (strpos($lower, 'http://') === 0 || strpos($lower, 'https://') === 0) {
        return '/accounting/ledger/register.php';
    }
    if ($trimmed[0] !== '/') {
        return '/accounting/ledger/register.php';
    }
    return $trimmed;
}

function ensureAdjustmentAuditTable(mysqli $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS acc_adjustment_audit (
            id INT NOT NULL AUTO_INCREMENT,
            journal_id INT NOT NULL,
            primary_account_id INT NOT NULL,
            offset_account_id INT NOT NULL,
            debit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            credit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            adjust_reason TEXT,
            entry_reference VARCHAR(100) DEFAULT NULL,
            entry_source VARCHAR(50) DEFAULT NULL,
            entry_memo VARCHAR(255) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_adjustment_journal (journal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
    ";

    if (!$conn->query($sql)) {
        logError('Failed creating acc_adjustment_audit table: ' . $conn->error, 'accounting');
    }

    $ensured = true;
}

$returnUrl = safeReturnUrl($_POST['return_url'] ?? '/accounting/ledger/register.php');
$accountId = intval($_POST['account_id'] ?? 0);
$offsetAccountId = intval($_POST['offset_account_id'] ?? 0);
$adjustDate = trim($_POST['adjust_date'] ?? date('Y-m-d'));
$memo = trim($_POST['memo'] ?? '');
$debitAmount = normalizeAmount($_POST['debit_amount'] ?? 0);
$creditAmount = normalizeAmount($_POST['credit_amount'] ?? 0);
$entryReference = trim($_POST['entry_reference'] ?? '');
$entrySource = trim($_POST['entry_source'] ?? '');
$entryMemo = trim($_POST['entry_memo'] ?? '');

if ($accountId <= 0 || $offsetAccountId <= 0) {
    setToastMessage('danger', 'Adjustment Failed', 'Both the source and offset accounts are required.');
    header('Location: ' . $returnUrl);
    exit();
}

if ($accountId === $offsetAccountId) {
    setToastMessage('danger', 'Adjustment Failed', 'The offset account must be different from the adjusted account.');
    header('Location: ' . $returnUrl);
    exit();
}

if (($debitAmount > 0 && $creditAmount > 0) || ($debitAmount <= 0 && $creditAmount <= 0)) {
    setToastMessage('danger', 'Adjustment Failed', 'Enter either a debit or a credit amount (but not both).');
    header('Location: ' . $returnUrl);
    exit();
}

if ($memo === '') {
    setToastMessage('danger', 'Adjustment Failed', 'A memo describing the adjustment is required for audit purposes.');
    header('Location: ' . $returnUrl);
    exit();
}

$amount = max($debitAmount, $creditAmount);

$dateValid = DateTime::createFromFormat('Y-m-d', $adjustDate) !== false;
if (!$dateValid) {
    $adjustDate = date('Y-m-d');
}

$accountsValid = false;
$accountNames = ['primary' => '', 'offset' => ''];

$acctStmt = $conn->prepare('SELECT id, name FROM acc_ledger_accounts WHERE id IN (?, ?)');
if ($acctStmt) {
    $acctStmt->bind_param('ii', $accountId, $offsetAccountId);
    if ($acctStmt->execute()) {
        $result = $acctStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['id'] === $accountId) {
                $accountNames['primary'] = $row['name'];
            }
            if ((int)$row['id'] === $offsetAccountId) {
                $accountNames['offset'] = $row['name'];
            }
        }
        $accountsValid = $accountNames['primary'] !== '' && $accountNames['offset'] !== '';
    }
    $acctStmt->close();
}

if (!$accountsValid) {
    setToastMessage('danger', 'Adjustment Failed', 'Unable to locate one or both ledger accounts.');
    header('Location: ' . $returnUrl);
    exit();
}

try {
    $conn->begin_transaction();

    $refNo = 'ADJ-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    $sourceLabel = 'ledger_adjustment';

    $journalStmt = $conn->prepare('INSERT INTO acc_journals (journal_date, memo, source, ref_no, created_by, posted_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$journalStmt) {
        throw new Exception('Failed preparing journal statement: ' . $conn->error);
    }

    $journalMemo = $memo;
    $journalStmt->bind_param('ssssi', $adjustDate, $journalMemo, $sourceLabel, $refNo, $userId);
    if (!$journalStmt->execute()) {
        throw new Exception('Failed inserting journal: ' . $journalStmt->error);
    }

    $journalId = $conn->insert_id;
    $journalStmt->close();

    $lineStmt = $conn->prepare('INSERT INTO acc_journal_lines (journal_id, account_id, category_id, description, debit, credit, line_order) VALUES (?, ?, NULL, ?, ?, ?, ?)');
    if (!$lineStmt) {
        throw new Exception('Failed preparing journal line statement: ' . $conn->error);
    }

    $lineOneDesc = trim('Adjustment: ' . $memo);
    $lineTwoDesc = trim('Offset: ' . $memo);

    $primaryDebit = $debitAmount > 0 ? $debitAmount : 0.0;
    $primaryCredit = $creditAmount > 0 ? $creditAmount : 0.0;

    $lineOrder = 1;
    $lineStmt->bind_param('iisddi', $journalId, $accountId, $lineOneDesc, $primaryDebit, $primaryCredit, $lineOrder);
    if (!$lineStmt->execute()) {
        throw new Exception('Failed inserting primary line: ' . $lineStmt->error);
    }

    $offsetDebit = $primaryCredit;
    $offsetCredit = $primaryDebit;
    $lineOrder = 2;
    $lineStmt->bind_param('iisddi', $journalId, $offsetAccountId, $lineTwoDesc, $offsetDebit, $offsetCredit, $lineOrder);
    if (!$lineStmt->execute()) {
        throw new Exception('Failed inserting offset line: ' . $lineStmt->error);
    }

    $lineStmt->close();

    ensureAdjustmentAuditTable($conn);
    $auditStmt = $conn->prepare('INSERT INTO acc_adjustment_audit (journal_id, primary_account_id, offset_account_id, debit_amount, credit_amount, adjust_reason, entry_reference, entry_source, entry_memo, created_by, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($auditStmt) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $auditStmt->bind_param(
            'iiiddssssss',
            $journalId,
            $accountId,
            $offsetAccountId,
            $primaryDebit,
            $primaryCredit,
            $memo,
            $entryReference,
            $entrySource,
            $entryMemo,
            $userId,
            $ipAddress
        );
        if (!$auditStmt->execute()) {
            logError('Failed inserting adjustment audit row: ' . $auditStmt->error, 'accounting');
        }
        $auditStmt->close();
    } else {
        logError('Failed preparing adjustment audit insert: ' . $conn->error, 'accounting');
    }

    $conn->commit();

    logActivity(
        $userId,
        'ledger_adjustment_posted',
        'acc_journals',
        $journalId,
        sprintf(
            'Adjustment posted for %s vs %s (amount %.2f, ref %s)',
            $accountNames['primary'],
            $accountNames['offset'],
            $amount,
            $refNo
        )
    );

    setToastMessage('success', 'Adjustment Posted', 'The adjusting journal entry has been recorded.');
} catch (Throwable $e) {
    $conn->rollback();
    logError('Adjustment failed: ' . $e->getMessage(), 'accounting');
    setToastMessage('danger', 'Adjustment Failed', 'We were unable to post that adjustment. Please try again.');
}

header('Location: ' . $returnUrl);
exit();
