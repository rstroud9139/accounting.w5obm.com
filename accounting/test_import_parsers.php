<?php

declare(strict_types=1);

/**
 * Import Parser Test Script
 * Tests GnuCash and Quicken import parsing without requiring web interface
 */

require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/lib/import_helpers.php';

$db = null;
$dbError = null;
$parserOnlyMode = false;

try {
    $db = accounting_db_connection();
} catch (Throwable $e) {
    $parserOnlyMode = true;
    $dbError = $e->getMessage();
}

echo "=== Import Parser Test ===\n\n";

if ($parserOnlyMode) {
    echo "[WARN] Database unavailable ({$dbError}). Running parser-only validation.\n";
} else {
    // Ensure tables exist
    accounting_imports_ensure_tables($db);
    echo "[OK] Import tables ensured\n";
}

// Test 1: GnuCash import
echo "\n--- Testing GnuCash Import ---\n";
$gnucashFile = __DIR__ . '/test_data/sample_test.gnucash';
if (!file_exists($gnucashFile)) {
    echo "[ERROR] Sample GnuCash file not found at: $gnucashFile\n";
} else {
    $fileMeta = [
        'original_name' => 'sample_test.gnucash',
        'stored_path' => $gnucashFile,
        'relative_path' => 'accounting/test_data/sample_test.gnucash',
        'size' => filesize($gnucashFile),
        'checksum' => hash_file('sha256', $gnucashFile),
    ];

    if ($parserOnlyMode) {
        accounting_run_parser_only_check('GnuCash', $fileMeta, 'accounting_imports_parse_gnucash_rows');
    } else {
        try {
            $batchId = accounting_imports_create_batch($db, 1, 'gnucash_file', $fileMeta);
            echo "[OK] Created batch #$batchId\n";

            accounting_imports_populate_gnucash_batch($db, $batchId, $fileMeta);
            echo "[OK] Populated GnuCash batch\n";

            // Check results
            $result = $db->query("SELECT COUNT(*) as cnt FROM acc_import_rows WHERE batch_id = $batchId");
            $row = $result->fetch_assoc();
            echo "[OK] Staged {$row['cnt']} rows from GnuCash file\n";

            // Show sample row
            $result = $db->query("SELECT * FROM acc_import_rows WHERE batch_id = $batchId LIMIT 1");
            if ($sampleRow = $result->fetch_assoc()) {
                echo "[OK] Sample normalized data:\n";
                $normalized = json_decode($sampleRow['normalized'], true);
                foreach ($normalized as $key => $value) {
                    echo "    $key: " . json_encode($value) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "[ERROR] GnuCash import failed: " . $e->getMessage() . "\n";
        }
    }
}

// Test 2: Quicken QFX import
echo "\n--- Testing Quicken QFX Import ---\n";
$qfxFile = __DIR__ . '/test_data/sample_test.qfx';
if (!file_exists($qfxFile)) {
    echo "[ERROR] Sample QFX file not found at: $qfxFile\n";
} else {
    $fileMeta = [
        'original_name' => 'sample_test.qfx',
        'stored_path' => $qfxFile,
        'relative_path' => 'accounting/test_data/sample_test.qfx',
        'size' => filesize($qfxFile),
        'checksum' => hash_file('sha256', $qfxFile),
    ];

    if ($parserOnlyMode) {
        accounting_run_parser_only_check('Quicken', $fileMeta, 'accounting_imports_parse_quicken_rows');
    } else {
        try {
            $batchId = accounting_imports_create_batch($db, 1, 'quicken_qfx', $fileMeta);
            echo "[OK] Created batch #$batchId\n";

            accounting_imports_populate_quicken_batch($db, $batchId, $fileMeta);
            echo "[OK] Populated Quicken batch\n";

            // Check results
            $result = $db->query("SELECT COUNT(*) as cnt FROM acc_import_rows WHERE batch_id = $batchId");
            $row = $result->fetch_assoc();
            echo "[OK] Staged {$row['cnt']} rows from QFX file\n";

            // Show sample row
            $result = $db->query("SELECT * FROM acc_import_rows WHERE batch_id = $batchId LIMIT 1");
            if ($sampleRow = $result->fetch_assoc()) {
                echo "[OK] Sample normalized data:\n";
                $normalized = json_decode($sampleRow['normalized'], true);
                foreach ($normalized as $key => $value) {
                    echo "    $key: " . json_encode($value) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "[ERROR] Quicken import failed: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== All Tests Complete ===\n";

function accounting_run_parser_only_check(string $label, array $fileMeta, callable $parser): void
{
    echo "[INFO] Parser-only mode active for {$label}.\n";
    try {
        $rows = call_user_func($parser, $fileMeta);
        $count = count($rows);
        echo "[OK] Parsed {$count} rows from {$label} file\n";
        if ($count > 0) {
            echo "[OK] Sample normalized data:\n";
            $sample = $rows[0]['normalized'] ?? [];
            foreach ($sample as $key => $value) {
                echo "    $key: " . json_encode($value) . "\n";
            }
        }
    } catch (Throwable $e) {
        echo "[ERROR] {$label} parser failed: " . $e->getMessage() . "\n";
    }
}
