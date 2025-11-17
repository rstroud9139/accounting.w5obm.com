<?php
// Index maintenance utilities for admin dashboard
// Provides: inspection of acc_transactions indexes, safe redundant drop, analyze, and schema snapshot helpers

require_once __DIR__ . '/../../include/dbconn.php';

function am_get_acc_txn_indexes(mysqli $conn): array
{
    $res = $conn->query("SHOW INDEX FROM acc_transactions");
    $byKey = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $k = $row['Key_name'];
            if (!isset($byKey[$k])) $byKey[$k] = [];
            $byKey[$k][] = $row['Column_name'];
        }
    }
    return $byKey;
}

function am_compute_redundant_plan(array $indexes): array
{
    // Redundant singles if composites exist
    $plan = [
        'idx_acc_txn_date' => ['requires' => ['idx_acc_transactions_date_type']],
        'idx_acc_txn_type' => ['requires' => ['idx_acc_transactions_type_date']],
        'idx_acc_transactions_account' => ['requires' => ['idx_acc_transactions_account_date']],
    ];
    $out = [];
    foreach ($plan as $idx => $meta) {
        $present = isset($indexes[$idx]);
        $deps_ok = true;
        foreach ($meta['requires'] as $r) {
            if (!isset($indexes[$r])) {
                $deps_ok = false;
                break;
            }
        }
        $out[] = [
            'index' => $idx,
            'present' => $present,
            'deps_ok' => $deps_ok,
            'columns' => $indexes[$idx] ?? [],
        ];
    }
    return $out;
}

function am_drop_redundant(mysqli $conn, array $plan): array
{
    $dropped = [];
    $errors = [];
    foreach ($plan as $item) {
        if ($item['present'] && $item['deps_ok']) {
            $idx = $conn->real_escape_string($item['index']);
            $sql = "ALTER TABLE acc_transactions DROP INDEX `{$idx}`";
            if ($conn->query($sql)) {
                $dropped[] = $item['index'];
            } else {
                $errors[] = $conn->error;
            }
        }
    }
    return ['dropped' => $dropped, 'errors' => $errors];
}

function am_log_audit(mysqli $conn, string $action, string $performed_by = '', string $details = ''): bool
{
    $stmt = $conn->prepare("INSERT INTO acc_audit_logs (action, performed_by, details) VALUES (?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param('sss', $action, $performed_by, $details);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool)$ok;
}

function am_analyze_acc_transactions(mysqli $conn): bool
{
    return (bool)$conn->query('ANALYZE TABLE acc_transactions');
}

function am_schema_snapshot(mysqli $conn): string
{
    $out = [];
    $tables = [];
    if ($res = $conn->query("SHOW TABLES LIKE 'acc_%'")) {
        while ($row = $res->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    if (!$tables) return "No acc_* tables found.";
    foreach ($tables as $t) {
        $out[] = str_repeat('=', 80) . " TABLE: {$t} " . str_repeat('-', 80);
        if ($cr = $conn->query("SHOW CREATE TABLE `{$t}`")) {
            if ($row = $cr->fetch_assoc()) {
                $out[] = $row['Create Table'] ?? '';
            }
        }
        $out[] = '';
        $out[] = 'INDEXES:';
        $byKey = [];
        if ($ir = $conn->query("SHOW INDEX FROM `{$t}`")) {
            while ($idx = $ir->fetch_assoc()) {
                $key = $idx['Key_name'];
                if (!isset($byKey[$key])) $byKey[$key] = [];
                $byKey[$key][] = $idx['Column_name'];
            }
        }
        foreach ($byKey as $k => $cols) {
            $out[] = "- {$k}: (" . implode(',', $cols) . ")";
        }
        $out[] = '';
    }
    $out[] = str_repeat('=', 80);
    $out[] = 'Done.';
    return implode("\n", $out);
}
