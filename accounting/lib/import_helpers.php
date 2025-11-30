<?php

require_once __DIR__ . '/helpers.php';

if (!function_exists('accounting_imports_ensure_tables')) {
    function accounting_imports_ensure_tables(mysqli $conn): void
    {
        $tables = [
            "CREATE TABLE IF NOT EXISTS acc_import_batches (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                source_type VARCHAR(40) NOT NULL,\n                status VARCHAR(40) NOT NULL DEFAULT 'draft',\n                original_filename VARCHAR(255) NULL,\n                stored_path VARCHAR(255) NULL,\n                checksum CHAR(64) NULL,\n                total_rows INT UNSIGNED NOT NULL DEFAULT 0,\n                ready_rows INT UNSIGNED NOT NULL DEFAULT 0,\n                error_rows INT UNSIGNED NOT NULL DEFAULT 0,\n                created_by INT NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                committed_at DATETIME NULL,\n                INDEX idx_status (status),\n                INDEX idx_created_by (created_by)\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS acc_import_rows (\n                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                batch_id INT UNSIGNED NOT NULL,\n                `row_number` INT UNSIGNED NOT NULL,\n                payload LONGTEXT NULL,\n                normalized LONGTEXT NULL,\n                status VARCHAR(40) NOT NULL DEFAULT 'pending',\n                message TEXT NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                UNIQUE KEY idx_batch_row (batch_id, `row_number`),\n                INDEX idx_status (status),\n                CONSTRAINT fk_import_rows_batch FOREIGN KEY (batch_id) REFERENCES acc_import_batches(id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS acc_import_row_errors (\n                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                batch_id INT UNSIGNED NOT NULL,\n                row_id BIGINT UNSIGNED NULL,\n                `row_number` INT UNSIGNED NULL,\n                error_code VARCHAR(60) NULL,\n                error_message TEXT NOT NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                INDEX idx_batch (batch_id),\n                INDEX idx_row (row_id),\n                CONSTRAINT fk_import_errors_batch FOREIGN KEY (batch_id) REFERENCES acc_import_batches(id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS acc_import_account_maps (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                source_type VARCHAR(40) NOT NULL,\n                source_key VARCHAR(160) NOT NULL,\n                source_label VARCHAR(255) NULL,\n                ledger_account_id INT UNSIGNED NOT NULL,\n                created_by INT NULL,\n                updated_by INT NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n                UNIQUE KEY uk_account_map_source (source_type, source_key),\n                CONSTRAINT fk_account_map_ledger FOREIGN KEY (ledger_account_id) REFERENCES acc_ledger_accounts(id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS acc_import_batch_commits (\n                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n                batch_id INT UNSIGNED NOT NULL,\n                committed_by INT NULL,\n                journal_count INT UNSIGNED NOT NULL DEFAULT 0,\n                line_count INT UNSIGNED NOT NULL DEFAULT 0,\n                notes VARCHAR(255) NULL,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                CONSTRAINT fk_batch_commit_batch FOREIGN KEY (batch_id) REFERENCES acc_import_batches(id) ON DELETE CASCADE\n            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($tables as $sql) {
            if (!$conn->query($sql)) {
                error_log('Failed to ensure accounting import table: ' . $conn->error);
            }
        }
    }
}

if (!function_exists('accounting_imports_get_source_types')) {
    function accounting_imports_get_source_types(): array
    {
        return [
            'quickbooks_iif' => [
                'label' => 'QuickBooks Desktop (IIF)',
                'description' => 'Use the IIF general journal export from QuickBooks Desktop.',
            ],
            'quickbooks_csv' => [
                'label' => 'QuickBooks CSV Snapshot',
                'description' => 'Volunteer-maintained CSV mirror of the register.',
            ],
            'gnucash_csv' => [
                'label' => 'gnuCash CSV',
                'description' => 'Transaction Report → Export → CSV.',
            ],
            'gnucash_file' => [
                'label' => 'GnuCash Saved Book (.gnucash)',
                'description' => 'Upload the raw .gnucash file (compressed XML).',
            ],
            'quicken_qfx' => [
                'label' => 'Quicken (QFX/OFX)',
                'description' => 'Quicken Web Connect or OFX transaction export.',
            ],
            'w5obm_template' => [
                'label' => 'W5OBM Bulk Template',
                'description' => 'Standard CSV template for events and dues drives.',
            ],
        ];
    }
}

if (!function_exists('accounting_imports_storage_root')) {
    function accounting_imports_storage_root(): string
    {
        static $path = null;
        if ($path === null) {
            $path = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)) . '/w5obm_mysql_backups/import_staging', '/');
        }
        return $path;
    }
}

if (!function_exists('accounting_imports_stage_uploaded_file')) {
    function accounting_imports_stage_uploaded_file(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed. Please try again.');
        }
        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Uploaded file could not be verified.');
        }

        $storageRoot = accounting_imports_storage_root();
        if (!is_dir($storageRoot) && !mkdir($storageRoot, 0775, true) && !is_dir($storageRoot)) {
            throw new RuntimeException('Unable to create import staging directory.');
        }

        $folder = $storageRoot . '/' . date('Ymd') . '_' . bin2hex(random_bytes(4));
        if (!mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('Unable to create batch staging folder.');
        }

        $extension = strtolower(pathinfo($file['name'] ?? 'upload', PATHINFO_EXTENSION));
        $safeExt = $extension !== '' ? preg_replace('/[^a-z0-9]/i', '', $extension) : 'dat';
        $targetPath = $folder . '/source.' . $safeExt;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new RuntimeException('Unable to persist uploaded file.');
        }

        $checksum = hash_file('sha256', $targetPath) ?: null;
        return [
            'original_name' => $file['name'] ?? 'upload',
            'stored_path' => $targetPath,
            'relative_path' => accounting_imports_relative_path($targetPath),
            'size' => filesize($targetPath) ?: 0,
            'checksum' => $checksum,
        ];
    }
}

if (!function_exists('accounting_imports_relative_path')) {
    function accounting_imports_relative_path(string $absolutePath): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $normalized = str_replace('\\', '/', $absolutePath);
        if (strpos($normalized, $root) === 0) {
            $normalized = substr($normalized, strlen($root));
        }
        return ltrim($normalized, '/');
    }
}

if (!function_exists('accounting_imports_create_batch')) {
    function accounting_imports_create_batch(mysqli $conn, int $userId, string $sourceType, array $fileMeta): int
    {
        $stmt = $conn->prepare('INSERT INTO acc_import_batches (source_type, status, original_filename, stored_path, checksum, total_rows, ready_rows, error_rows, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, NOW(), NOW())');
        if (!$stmt) {
            throw new RuntimeException('Unable to initialize batch record.');
        }

        $status = 'staging';
        $originalName = $fileMeta['original_name'] ?? null;
        $storedPath = $fileMeta['relative_path'] ?? ($fileMeta['stored_path'] ?? null);
        $checksum = $fileMeta['checksum'] ?? null;
        $stmt->bind_param('sssssi', $sourceType, $status, $originalName, $storedPath, $checksum, $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to save import batch: ' . $conn->error);
        }

        $batchId = (int)$stmt->insert_id;
        $stmt->close();

        if (function_exists('logActivity')) {
            @logActivity($userId, 'accounting_import_batch_created', 'acc_import_batches', $batchId, sprintf('Source: %s (%s)', $sourceType, $originalName));
        }

        return $batchId;
    }
}

if (!function_exists('accounting_imports_fetch_recent_batches')) {
    function accounting_imports_fetch_recent_batches(mysqli $conn, int $limit = 5): array
    {
        $limit = max(1, min($limit, 50));
        $batches = [];
        $result = $conn->query('SELECT * FROM acc_import_batches ORDER BY updated_at DESC LIMIT ' . $limit);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $batches[] = $row;
            }
        }
        return $batches;
    }
}

if (!function_exists('accounting_imports_status_badge_class')) {
    function accounting_imports_status_badge_class(string $status): string
    {
        $map = [
            'draft' => 'bg-secondary',
            'staging' => 'bg-info',
            'ready' => 'bg-primary',
            'committed' => 'bg-success',
            'error' => 'bg-danger',
        ];
        $key = strtolower(trim($status));
        return $map[$key] ?? 'bg-secondary';
    }
}

if (!function_exists('accounting_imports_absolute_path')) {
    function accounting_imports_absolute_path(string $path): string
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $normalized = str_replace(['\\', '..'], ['/', ''], $path);
        if ($normalized === '') {
            return $root;
        }
        if ($normalized[0] === '/') {
            return $root . $normalized;
        }
        return $root . '/' . $normalized;
    }
}

if (!function_exists('accounting_imports_fraction_to_decimal')) {
    function accounting_imports_fraction_to_decimal(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }
        if (strpos($value, '/') !== false) {
            [$numerator, $denominator] = explode('/', $value, 2);
            $denominator = (float)$denominator ?: 1.0;
            return (float)$numerator / $denominator;
        }
        return (float)$value;
    }
}

if (!function_exists('accounting_imports_populate_batch')) {
    function accounting_imports_populate_batch(mysqli $conn, int $batchId, string $sourceType, array $fileMeta): void
    {
        switch ($sourceType) {
            case 'gnucash_file':
                accounting_imports_populate_gnucash_batch($conn, $batchId, $fileMeta);
                break;
            case 'quicken_qfx':
                accounting_imports_populate_quicken_batch($conn, $batchId, $fileMeta);
                break;
            default:
                // CSV/IIF handled later in mapping wizard.
                break;
        }
    }
}

if (!function_exists('accounting_imports_populate_gnucash_batch')) {
    function accounting_imports_populate_gnucash_batch(mysqli $conn, int $batchId, array $fileMeta): void
    {
        $absolutePath = $fileMeta['stored_path'] ?? null;
        if (!$absolutePath && !empty($fileMeta['relative_path'])) {
            $absolutePath = accounting_imports_absolute_path($fileMeta['relative_path']);
        }
        if (!$absolutePath || !is_file($absolutePath)) {
            throw new RuntimeException('Unable to locate staged GnuCash file.');
        }

        $xmlPayload = accounting_imports_read_gnucash_xml($absolutePath);
        $dom = new DOMDocument();
        if (!$dom->loadXML($xmlPayload, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('Uploaded GnuCash file could not be parsed.');
        }

        $xpath = new DOMXPath($dom);
        $namespaces = [
            'gnc' => 'http://www.gnucash.org/XML/gnc',
            'act' => 'http://www.gnucash.org/XML/act',
            'trn' => 'http://www.gnucash.org/XML/trn',
            'spl' => 'http://www.gnucash.org/XML/split',
            'cmdty' => 'http://www.gnucash.org/XML/cmdty',
            'ts' => 'http://www.gnucash.org/XML/ts',
        ];
        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $accountNodes = $xpath->query('//gnc:account');
        $accounts = [];
        foreach ($accountNodes ?: [] as $node) {
            $guid = trim($xpath->evaluate('string(act:id)', $node));
            if ($guid === '') {
                continue;
            }
            $accounts[$guid] = [
                'name' => trim($xpath->evaluate('string(act:name)', $node)),
                'code' => trim($xpath->evaluate('string(act:code)', $node)),
                'type' => trim($xpath->evaluate('string(act:type)', $node)),
                'description' => trim($xpath->evaluate('string(act:description)', $node)),
            ];
        }

        $splitStmt = $conn->prepare('INSERT INTO acc_import_rows (batch_id, `row_number`, payload, normalized, status, message) VALUES (?, ?, ?, ?, ?, NULL)');
        if (!$splitStmt) {
            throw new RuntimeException('Unable to prepare staging insert: ' . $conn->error);
        }

        $rowNumber = 1;
        $totalRows = 0;
        $transactions = $xpath->query('//gnc:transaction');
        foreach ($transactions ?: [] as $txnNode) {
            $txnGuid = trim($xpath->evaluate('string(trn:id)', $txnNode));
            $txnDescription = trim($xpath->evaluate('string(trn:description)', $txnNode));
            $txnNumber = trim($xpath->evaluate('string(trn:number)', $txnNode));
            $posted = trim($xpath->evaluate('string(trn:date-posted/ts:date)', $txnNode));
            $entered = trim($xpath->evaluate('string(trn:date-entered/ts:date)', $txnNode));
            $currency = trim($xpath->evaluate('string(trn:currency/cmdty:id)', $txnNode)) ?: 'USD';

            $splitNodes = $xpath->query('trn:splits/trn:split', $txnNode);
            foreach ($splitNodes ?: [] as $splitNode) {
                $splitGuid = trim($xpath->evaluate('string(spl:id)', $splitNode));
                $accountGuid = trim($xpath->evaluate('string(spl:account)', $splitNode));
                $memo = trim($xpath->evaluate('string(spl:memo)', $splitNode));
                $action = trim($xpath->evaluate('string(spl:action)', $splitNode));
                $valueRaw = trim($xpath->evaluate('string(spl:value)', $splitNode));
                $quantityRaw = trim($xpath->evaluate('string(spl:quantity)', $splitNode));
                $reconciled = trim($xpath->evaluate('string(spl:reconciled-state)', $splitNode));

                $amount = accounting_imports_fraction_to_decimal($valueRaw);
                $normalized = [
                    'amount' => round($amount, 2),
                    'currency' => $currency,
                    'account_guid' => $accountGuid,
                    'account_name' => $accounts[$accountGuid]['name'] ?? null,
                    'account_code' => $accounts[$accountGuid]['code'] ?? null,
                    'account_type' => $accounts[$accountGuid]['type'] ?? null,
                    'debit' => $amount >= 0 ? round($amount, 2) : 0.0,
                    'credit' => $amount < 0 ? round(abs($amount), 2) : 0.0,
                ];

                $payload = [
                    'transaction_guid' => $txnGuid,
                    'transaction_number' => $txnNumber,
                    'transaction_description' => $txnDescription,
                    'date_posted' => $posted,
                    'date_entered' => $entered,
                    'split_guid' => $splitGuid,
                    'account_guid' => $accountGuid,
                    'memo' => $memo,
                    'action' => $action,
                    'value' => $valueRaw,
                    'quantity' => $quantityRaw,
                    'reconciled_state' => $reconciled,
                ];

                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($payloadJson === false || $normalizedJson === false) {
                    $splitStmt->close();
                    throw new RuntimeException('Failed to encode staging payload.');
                }

                $status = 'pending';
                $splitStmt->bind_param('iisss', $batchId, $rowNumber, $payloadJson, $normalizedJson, $status);
                if (!$splitStmt->execute()) {
                    $splitStmt->close();
                    throw new RuntimeException('Failed to stage split row: ' . $conn->error);
                }

                $rowNumber++;
                $totalRows++;
            }
        }

        $splitStmt->close();

        $updateStmt = $conn->prepare('UPDATE acc_import_batches SET total_rows = ?, ready_rows = 0, error_rows = 0 WHERE id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('ii', $totalRows, $batchId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

if (!function_exists('accounting_imports_read_gnucash_xml')) {
    function accounting_imports_read_gnucash_xml(string $absolutePath): string
    {
        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $isGzip = strncmp($contents, "\x1F\x8B", 2) === 0;
        if ($isGzip) {
            $decoded = @gzdecode($contents);
            if ($decoded === false) {
                throw new RuntimeException('Unable to decompress .gnucash archive.');
            }
            return $decoded;
        }

        return $contents;
    }
}

if (!function_exists('accounting_imports_populate_quicken_batch')) {
    function accounting_imports_populate_quicken_batch(mysqli $conn, int $batchId, array $fileMeta): void
    {
        $absolutePath = $fileMeta['stored_path'] ?? null;
        if (!$absolutePath && !empty($fileMeta['relative_path'])) {
            $absolutePath = accounting_imports_absolute_path($fileMeta['relative_path']);
        }
        if (!$absolutePath || !is_file($absolutePath)) {
            throw new RuntimeException('Unable to locate staged Quicken file.');
        }

        $contents = @file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        // Parse OFX/QFX using simple line-by-line parsing
        $transactions = accounting_imports_parse_ofx_transactions($contents);
        
        if (empty($transactions)) {
            throw new RuntimeException('No transactions found in OFX/QFX file.');
        }

        $stmt = $conn->prepare('INSERT INTO acc_import_rows (batch_id, `row_number`, payload, normalized, status, message) VALUES (?, ?, ?, ?, ?, NULL)');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare staging insert: ' . $conn->error);
        }

        $rowNumber = 1;
        $totalRows = 0;
        
        foreach ($transactions as $txn) {
            $type = $txn['TRNTYPE'] ?? '';
            $date = $txn['DTPOSTED'] ?? '';
            $amount = (float)($txn['TRNAMT'] ?? 0);
            $fitid = $txn['FITID'] ?? '';
            $name = $txn['NAME'] ?? '';
            $memo = $txn['MEMO'] ?? '';
            $checkNum = $txn['CHECKNUM'] ?? '';
            
            // Parse date (YYYYMMDDHHMMSS format)
            if (strlen($date) >= 8) {
                $parsedDate = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
            } else {
                $parsedDate = null;
            }

            $payload = [
                'transaction_type' => $type,
                'date_posted' => $date,
                'amount' => $amount,
                'fitid' => $fitid,
                'name' => $name,
                'memo' => $memo,
                'check_number' => $checkNum,
            ];

            $normalized = [
                'date' => $parsedDate,
                'description' => $name ?: $memo ?: 'Transaction',
                'amount' => round($amount, 2),
                'debit' => $amount > 0 ? round($amount, 2) : 0.0,
                'credit' => $amount < 0 ? round(abs($amount), 2) : 0.0,
                'reference' => $checkNum ?: $fitid,
                'type' => $type,
            ];

            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $normalizedJson = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payloadJson === false || $normalizedJson === false) {
                $stmt->close();
                throw new RuntimeException('Failed to encode staging payload.');
            }

            $status = 'pending';
            $stmt->bind_param('iisss', $batchId, $rowNumber, $payloadJson, $normalizedJson, $status);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Failed to stage transaction row: ' . $conn->error);
            }

            $rowNumber++;
            $totalRows++;
        }

        $stmt->close();

        $updateStmt = $conn->prepare('UPDATE acc_import_batches SET total_rows = ?, ready_rows = 0, error_rows = 0 WHERE id = ?');
        if ($updateStmt) {
            $updateStmt->bind_param('ii', $totalRows, $batchId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

if (!function_exists('accounting_imports_ofx_to_xml')) {
    function accounting_imports_ofx_to_xml(string $ofxContent): string
    {
        // Remove OFX headers (everything before <OFX>)
        $lines = explode("\n", $ofxContent);
        $xmlStart = 0;
        foreach ($lines as $idx => $line) {
            if (stripos($line, '<OFX>') !== false) {
                $xmlStart = $idx;
                break;
            }
        }
        
        $xmlContent = implode("\n", array_slice($lines, $xmlStart));
        
        // Convert SGML-style tags to proper XML with closing tags
        // Match opening tags and add closing tags if they don't exist
        $xmlContent = preg_replace_callback('/<([A-Z][A-Z0-9]*?)>([^<]*)/s', function($matches) {
            $tag = $matches[1];
            $content = $matches[2];
            
            // If content contains another opening tag, this is a container - don't close yet
            if (preg_match('/<[A-Z]/', $content)) {
                return $matches[0];
            }
            
            // If already has closing tag, leave it
            if (preg_match('/<\/' . preg_quote($tag, '/') . '>/i', $content)) {
                return $matches[0];
            }
            
            // Add closing tag
            $trimmedContent = trim($content);
            return "<{$tag}>{$trimmedContent}</{$tag}>";
        }, $xmlContent);
        
        // Wrap in XML declaration
        if (stripos($xmlContent, '<?xml') === false) {
            $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmlContent;
        }
        
        return $xmlContent;
    }
}

if (!function_exists('accounting_imports_parse_ofx_transactions')) {
    function accounting_imports_parse_ofx_transactions(string $ofxContent): array
    {
        $transactions = [];
        $lines = explode("\n", $ofxContent);
        
        $currentTxn = null;
        $inTransaction = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Start of transaction
            if (stripos($line, '<STMTTRN>') !== false || stripos($line, '<INVBANKTRAN>') !== false) {
                $inTransaction = true;
                $currentTxn = [];
                continue;
            }
            
            // End of transaction
            if (stripos($line, '</STMTTRN>') !== false || stripos($line, '</INVBANKTRAN>') !== false) {
                if ($currentTxn !== null) {
                    $transactions[] = $currentTxn;
                }
                $inTransaction = false;
                $currentTxn = null;
                continue;
            }
            
            // Parse transaction fields
            if ($inTransaction && $currentTxn !== null) {
                // Match <TAG>value or <TAG>value</TAG>
                if (preg_match('/<([A-Z0-9]+)>([^<]*)/i', $line, $matches)) {
                    $tag = strtoupper($matches[1]);
                    $value = trim($matches[2]);
                    
                    // Remove closing tag if present
                    $value = preg_replace('/<\/' . preg_quote($tag, '/') . '>$/i', '', $value);
                    
                    $currentTxn[$tag] = $value;
                }
            }
        }
        
        return $transactions;
    }
}

if (!function_exists('accounting_imports_fetch_batch')) {
    function accounting_imports_fetch_batch(mysqli $conn, int $batchId): ?array
    {
        $stmt = $conn->prepare('SELECT * FROM acc_import_batches WHERE id = ?');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('accounting_imports_fetch_batch_accounts')) {
    function accounting_imports_fetch_batch_accounts(mysqli $conn, int $batchId): array
    {
        $sql = "SELECT
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(normalized, '$.account_guid')),
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.account_guid')),
                    JSON_UNQUOTE(JSON_EXTRACT(normalized, '$.account_name')),
                    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.account_name')),
                    CONCAT('row-', MIN(row_number))
                ) AS account_key,
                JSON_UNQUOTE(JSON_EXTRACT(normalized, '$.account_name')) AS account_name,
                JSON_UNQUOTE(JSON_EXTRACT(normalized, '$.account_code')) AS account_code,
                JSON_UNQUOTE(JSON_EXTRACT(normalized, '$.account_type')) AS account_type,
                COUNT(*) AS split_count
            FROM acc_import_rows
            WHERE batch_id = ?
            GROUP BY account_key, account_name, account_code, account_type
            ORDER BY account_name IS NULL, account_name";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $result = $stmt->get_result();
        $accounts = [];
        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }
        $stmt->close();
        return $accounts;
    }
}

if (!function_exists('accounting_imports_fetch_account_maps')) {
    function accounting_imports_fetch_account_maps(mysqli $conn, string $sourceType): array
    {
        $stmt = $conn->prepare('SELECT * FROM acc_import_account_maps WHERE source_type = ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $sourceType);
        $stmt->execute();
        $result = $stmt->get_result();
        $maps = [];
        while ($row = $result->fetch_assoc()) {
            $maps[$row['source_key']] = $row;
        }
        $stmt->close();
        return $maps;
    }
}

if (!function_exists('accounting_imports_save_account_map')) {
    function accounting_imports_save_account_map(mysqli $conn, int $userId, string $sourceType, string $sourceKey, string $sourceLabel, int $ledgerAccountId): void
    {
        $sql = 'INSERT INTO acc_import_account_maps (source_type, source_key, source_label, ledger_account_id, created_by, updated_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE source_label = VALUES(source_label), ledger_account_id = VALUES(ledger_account_id), updated_by = VALUES(updated_by), updated_at = NOW()';

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to persist mapping definition.');
        }
        $stmt->bind_param('sssiii', $sourceType, $sourceKey, $sourceLabel, $ledgerAccountId, $userId, $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to save mapping: ' . $conn->error);
        }
        $stmt->close();
    }
}

if (!function_exists('accounting_imports_delete_batch')) {
    function accounting_imports_delete_batch(mysqli $conn, int $batchId, int $userId): void
    {
        $batch = accounting_imports_fetch_batch($conn, $batchId);
        if (!$batch) {
            throw new RuntimeException('Batch not found.');
        }

        $stmt = $conn->prepare('DELETE FROM acc_import_batches WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Unable to delete batch: ' . $conn->error);
        }
        $stmt->bind_param('i', $batchId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Batch removal failed: ' . $conn->error);
        }
        $stmt->close();

        if (function_exists('logActivity')) {
            @logActivity($userId, 'accounting_import_batch_deleted', 'acc_import_batches', $batchId, 'Deleted staged batch ' . $batchId);
        }
    }
}

if (!function_exists('accounting_imports_extract_account_key')) {
    function accounting_imports_extract_account_key(array $normalized, array $payload = []): string
    {
        foreach (['account_guid', 'account_id', 'account_key'] as $key) {
            if (!empty($normalized[$key])) {
                return (string)$normalized[$key];
            }
            if (!empty($payload[$key])) {
                return (string)$payload[$key];
            }
        }
        if (!empty($normalized['account_name'])) {
            return (string)$normalized['account_name'];
        }
        if (!empty($payload['account_name'])) {
            return (string)$payload['account_name'];
        }
        if (!empty($payload['transaction_guid'])) {
            return (string)$payload['transaction_guid'];
        }
        return 'row-' . (string)(microtime(true) * 1000);
    }
}

if (!function_exists('accounting_imports_post_batch')) {
    function accounting_imports_post_batch(mysqli $conn, int $batchId, int $userId): array
    {
        $batch = accounting_imports_fetch_batch($conn, $batchId);
        if (!$batch) {
            throw new RuntimeException('Batch not found.');
        }
        if ($batch['status'] === 'committed') {
            throw new RuntimeException('Batch already committed.');
        }

        $stmt = $conn->prepare('SELECT id, row_number, payload, normalized FROM acc_import_rows WHERE batch_id = ? ORDER BY row_number ASC');
        if (!$stmt) {
            throw new RuntimeException('Unable to read staged rows.');
        }
        $stmt->bind_param('i', $batchId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            $stmt->close();
            throw new RuntimeException('Batch has no staged rows.');
        }

        $transactions = [];
        $uniqueAccounts = [];
        while ($row = $result->fetch_assoc()) {
            $payload = json_decode($row['payload'] ?? '[]', true) ?: [];
            $normalized = json_decode($row['normalized'] ?? '[]', true) ?: [];
            $txnKey = $payload['transaction_guid'] ?? $payload['transaction_number'] ?? ('row-' . $row['row_number']);
            if (!isset($transactions[$txnKey])) {
                $transactions[$txnKey] = [
                    'meta' => [
                        'date' => $payload['date_posted'] ?? $normalized['date'] ?? date('Y-m-d'),
                        'memo' => $payload['transaction_description'] ?? $payload['memo'] ?? ($normalized['description'] ?? ''),
                        'number' => $payload['transaction_number'] ?? $payload['reference'] ?? null,
                    ],
                    'splits' => [],
                ];
            }

            $accountKey = accounting_imports_extract_account_key($normalized, $payload);
            $transactions[$txnKey]['splits'][] = [
                'payload' => $payload,
                'normalized' => $normalized,
                'account_key' => $accountKey,
            ];
            $uniqueAccounts[$accountKey] = $payload['account_name'] ?? ($normalized['account_name'] ?? $accountKey);
        }
        $stmt->close();

        if (empty($transactions)) {
            throw new RuntimeException('No transactions ready for posting.');
        }

        $maps = accounting_imports_fetch_account_maps($conn, $batch['source_type']);
        $missing = [];
        foreach ($uniqueAccounts as $accountKey => $label) {
            if (empty($maps[$accountKey])) {
                $missing[] = $label . ' (' . $accountKey . ')';
            }
        }
        if (!empty($missing)) {
            throw new RuntimeException('Missing account mappings for: ' . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? '...' : ''));
        }

        $journalSql = 'INSERT INTO acc_journals (journal_date, memo, source, ref_no, created_by, posted_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())';
        $journalStmt = $conn->prepare($journalSql);
        if (!$journalStmt) {
            throw new RuntimeException('Unable to prepare journal insert.');
        }
        $journalStmt->bind_param('ssssi', $journalDate, $journalMemo, $journalSource, $journalRef, $journalUser);
        $journalSource = 'import:' . $batch['source_type'];
        $journalUser = $userId;

        $lineSql = 'INSERT INTO acc_journal_lines (journal_id, account_id, category_id, description, debit, credit, line_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
        $lineStmt = $conn->prepare($lineSql);
        if (!$lineStmt) {
            $journalStmt->close();
            throw new RuntimeException('Unable to prepare journal line insert.');
        }
        $lineStmt->bind_param('iiisddi', $lineJournalId, $lineAccountId, $lineCategoryId, $lineDescription, $lineDebit, $lineCredit, $lineOrder);

        $journalCount = 0;
        $lineCount = 0;

        $conn->begin_transaction();
        try {
            foreach ($transactions as $txnKey => $txn) {
                $journalDate = substr((string)$txn['meta']['date'], 0, 10) ?: date('Y-m-d');
                $journalMemo = $txn['meta']['memo'] ?: ('Imported batch #' . $batchId);
                $journalRef = $txn['meta']['number'] ?: $txnKey;

                if (!$journalStmt->execute()) {
                    throw new RuntimeException('Failed to insert journal entry: ' . $conn->error);
                }
                $journalId = (int)$conn->insert_id;
                $journalCount++;

                $lineSequence = 1;
                foreach ($txn['splits'] as $split) {
                    $map = $maps[$split['account_key']] ?? null;
                    if (!$map) {
                        throw new RuntimeException('Mapping vanished for ' . $split['account_key']);
                    }
                    $lineJournalId = $journalId;
                    $lineAccountId = (int)$map['ledger_account_id'];
                    $lineCategoryId = null;
                    $lineDescription = $split['payload']['memo'] ?? $journalMemo;
                    $normalized = $split['normalized'];
                    $lineDebit = isset($normalized['debit']) ? (float)$normalized['debit'] : ((float)($normalized['amount'] ?? 0) >= 0 ? abs((float)($normalized['amount'] ?? 0)) : 0.0);
                    $lineCredit = isset($normalized['credit']) ? (float)$normalized['credit'] : ((float)($normalized['amount'] ?? 0) < 0 ? abs((float)($normalized['amount'] ?? 0)) : 0.0);
                    if (abs($lineDebit) < 0.00001 && abs($lineCredit) < 0.00001) {
                        continue;
                    }
                    $lineOrder = $lineSequence++;
                    if (!$lineStmt->execute()) {
                        throw new RuntimeException('Failed to insert journal line: ' . $conn->error);
                    }
                    $lineCount++;
                }
            }

            $status = 'committed';
            $updateStmt = $conn->prepare('UPDATE acc_import_batches SET status = ?, ready_rows = total_rows, error_rows = 0, committed_at = NOW(), updated_at = NOW() WHERE id = ?');
            if ($updateStmt) {
                $updateStmt->bind_param('si', $status, $batchId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            $conn->query('UPDATE acc_import_rows SET status = "committed", message = NULL WHERE batch_id = ' . (int)$batchId);

            $commitStmt = $conn->prepare('INSERT INTO acc_import_batch_commits (batch_id, committed_by, journal_count, line_count, notes) VALUES (?, ?, ?, ?, ?)');
            if ($commitStmt) {
                $notes = 'Auto post from import wizard';
                $commitStmt->bind_param('iiiis', $batchId, $userId, $journalCount, $lineCount, $notes);
                $commitStmt->execute();
                $commitStmt->close();
            }

            if (function_exists('logActivity')) {
                @logActivity($userId, 'accounting_import_batch_committed', 'acc_import_batches', $batchId, sprintf('Posted %d journals / %d lines', $journalCount, $lineCount));
            }

            $conn->commit();
        } catch (Throwable $ex) {
            $conn->rollback();
            $journalStmt->close();
            $lineStmt->close();
            throw $ex;
        }

        $journalStmt->close();
        $lineStmt->close();

        return [
            'journals' => $journalCount,
            'lines' => $lineCount,
        ];
    }
}

?>
