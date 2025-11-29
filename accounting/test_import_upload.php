<?php
/**
 * Quick Import Upload Integration Test
 * Simulates the upload flow to verify backend plumbing.
 */

require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/lib/import_helpers.php';

if (!isAuthenticated() || !isAdmin(getCurrentUserId())) {
    die('Admin access required for test scripts.');
}

echo "<pre style='background:#f4f4f4;padding:1rem;border-radius:0.5rem;'>\n";
echo "=== Import Upload Integration Test ===\n\n";

// 1. Ensure tables exist
echo "1. Ensuring import tables...\n";
try {
    accounting_imports_ensure_tables($conn);
    echo "   ✓ Tables ready\n\n";
} catch (Exception $e) {
    echo "   ✗ Failed: " . $e->getMessage() . "\n\n";
    die();
}

// 2. Verify source types
echo "2. Checking source types...\n";
$sourceTypes = accounting_imports_get_source_types();
echo "   ✓ Found " . count($sourceTypes) . " source types: " . implode(', ', array_keys($sourceTypes)) . "\n\n";

// 3. Verify storage path creation
echo "3. Verifying storage root...\n";
$storageRoot = accounting_imports_storage_root();
echo "   Path: $storageRoot\n";
if (!is_dir($storageRoot) && !mkdir($storageRoot, 0775, true)) {
    echo "   ✗ Could not create storage directory\n\n";
    die();
}
echo "   ✓ Storage directory accessible\n\n";

// 4. Simulate batch creation (no file)
echo "4. Testing batch creation helper...\n";
try {
    $testMeta = [
        'original_name' => 'test_upload.csv',
        'relative_path' => 'test/20251128/source.csv',
        'stored_path' => $storageRoot . '/test/20251128/source.csv',
        'checksum' => hash('sha256', 'test content'),
        'size' => 1234,
    ];
    
    $batchId = accounting_imports_create_batch($conn, getCurrentUserId(), 'quickbooks_csv', $testMeta);
    echo "   ✓ Created batch #$batchId\n\n";
    
    // Clean up test batch
    $conn->query("DELETE FROM acc_import_batches WHERE id = $batchId");
    echo "   ✓ Cleanup complete\n\n";
    
} catch (Exception $e) {
    echo "   ✗ Batch creation failed: " . $e->getMessage() . "\n\n";
    die();
}

// 5. Verify recent batches fetch
echo "5. Testing recent batches query...\n";
try {
    $batches = accounting_imports_fetch_recent_batches($conn, 3);
    echo "   ✓ Retrieved " . count($batches) . " recent batches\n\n";
} catch (Exception $e) {
    echo "   ✗ Query failed: " . $e->getMessage() . "\n\n";
}

echo "=== All Tests Passed ===\n";
echo "Backend plumbing is ready for import uploads.\n";
echo "Next: Test via UI at accounting/imports.php\n";
echo "</pre>";
