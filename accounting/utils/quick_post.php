<?php

/**
 * Quick-post helpers for accounting cash donation routine.
 * Provides shared logic for resolving contact, category, and account defaults.
 */

declare(strict_types=1);

if (!function_exists('accounting_quick_cash_contact_name')) {
    function accounting_quick_cash_contact_name(): string
    {
        $raw = $_ENV['ACCOUNTING_QUICK_CASH_CONTACT'] ?? 'Event Cash (Quick Post)';
        $name = trim((string)$raw);
        return $name !== '' ? $name : 'Event Cash (Quick Post)';
    }
}

if (!function_exists('accounting_quick_cash_category_keywords')) {
    function accounting_quick_cash_category_keywords(): array
    {
        $raw = $_ENV['ACCOUNTING_QUICK_CASH_CATEGORY_KEYWORDS'] ?? 'donation,fund,event';
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}

if (!function_exists('accounting_quick_cash_account_keywords')) {
    function accounting_quick_cash_account_keywords(): array
    {
        $raw = $_ENV['ACCOUNTING_QUICK_CASH_ACCOUNT_KEYWORDS'] ?? 'event,cash,checking,deposit';
        return array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
    }
}

if (!function_exists('accounting_quick_cash_contact_id')) {
    function accounting_quick_cash_contact_id(mysqli $conn): ?int
    {
        $name = accounting_quick_cash_contact_name();
        try {
            $stmt = $conn->prepare('SELECT id FROM acc_contacts WHERE name = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $stmt->close();
                    return (int)$row['id'];
                }
                $stmt->close();
            }

            $insert = $conn->prepare('INSERT INTO acc_contacts (name, email, address, tax_id, created_at) VALUES (?, ?, ?, ?, NOW())');
            if ($insert) {
                $email = '';
                $address = 'Quick-post auto contact';
                $taxId = '';
                $insert->bind_param('ssss', $name, $email, $address, $taxId);
                if ($insert->execute()) {
                    $id = (int)$conn->insert_id;
                    $insert->close();
                    return $id;
                }
                $insert->close();
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Quick cash contact failure: ' . $e->getMessage(), 'accounting');
            } else {
                error_log('Quick cash contact failure: ' . $e->getMessage());
            }
        }
        return null;
    }
}

if (!function_exists('accounting_quick_cash_fetch_categories')) {
    function accounting_quick_cash_fetch_categories(mysqli $conn): array
    {
        $categories = [];
        try {
            $sql = "SELECT id, name, type FROM acc_transaction_categories ORDER BY name";
            $result = $conn->query($sql);
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $categories[] = [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'type' => (string)($row['type'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Quick cash categories failure: ' . $e->getMessage(), 'accounting');
            }
        }
        return $categories;
    }
}

if (!function_exists('accounting_quick_cash_fetch_accounts')) {
    function accounting_quick_cash_fetch_accounts(mysqli $conn): array
    {
        $accounts = [];
        try {
            $sql = "SELECT id, name, account_type FROM acc_ledger_accounts WHERE active = 1 ORDER BY account_type, name";
            $result = $conn->query($sql);
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $accounts[] = [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'type' => (string)($row['account_type'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Quick cash accounts failure: ' . $e->getMessage(), 'accounting');
            }
        }
        return $accounts;
    }
}

if (!function_exists('accounting_quick_cash_pick_default')) {
    function accounting_quick_cash_pick_default(array $options, array $keywords, string $typeKey): ?int
    {
        if (empty($options)) {
            return null;
        }

        $targetType = strtolower(trim($typeKey));
        $matchesType = static function (array $option) use ($targetType): bool {
            if ($targetType === '') {
                return true;
            }
            $value = strtolower((string)($option['type'] ?? ''));
            if ($value === $targetType) {
                return true;
            }
            // Treat legacy blank types as acceptable when targeting income or asset defaults.
            if ($value === '' && in_array($targetType, ['income', 'asset'], true)) {
                return true;
            }
            return false;
        };

        $normalized = array_values(array_filter(array_map('strtolower', $keywords)));
        if (!empty($normalized)) {
            foreach ($normalized as $keyword) {
                foreach ($options as $option) {
                    if (!$matchesType($option)) {
                        continue;
                    }
                    $name = strtolower($option['name'] ?? '');
                    if ($keyword !== '' && strpos($name, $keyword) !== false) {
                        return (int)$option['id'];
                    }
                }
            }
        }

        foreach ($options as $option) {
            if ($matchesType($option)) {
                return (int)$option['id'];
            }
        }

        // As an absolute fallback, return the first option even if the type does not match.
        return (int)($options[0]['id'] ?? null);
    }
}

if (!function_exists('accounting_quick_cash_resolve_context')) {
    function accounting_quick_cash_resolve_context(mysqli $conn): array
    {
        $categories = accounting_quick_cash_fetch_categories($conn);
        $accounts = accounting_quick_cash_fetch_accounts($conn);

        return [
            'contact_id' => accounting_quick_cash_contact_id($conn),
            'contact_name' => accounting_quick_cash_contact_name(),
            'categories' => $categories,
            'accounts' => $accounts,
            'default_category_id' => accounting_quick_cash_pick_default($categories, accounting_quick_cash_category_keywords(), 'Income'),
            'default_account_id' => accounting_quick_cash_pick_default($accounts, accounting_quick_cash_account_keywords(), 'Asset'),
        ];
    }
}

if (!function_exists('accounting_quick_cash_description')) {
    function accounting_quick_cash_description(string $eventLabel = ''): string
    {
        $prefix = $_ENV['ACCOUNTING_QUICK_CASH_DESCRIPTION_PREFIX'] ?? 'Cash Donation';
        $prefix = trim($prefix) !== '' ? trim($prefix) : 'Cash Donation';
        $eventLabel = trim($eventLabel);
        return $eventLabel !== '' ? $prefix . ' - ' . $eventLabel : $prefix;
    }
}

if (!function_exists('accounting_quick_cash_account_exists')) {
    function accounting_quick_cash_account_exists(mysqli $conn, int $accountId): bool
    {
        if ($accountId <= 0) {
            return false;
        }
        try {
            $stmt = $conn->prepare('SELECT id FROM acc_ledger_accounts WHERE id = ? AND active = 1');
            if ($stmt) {
                $stmt->bind_param('i', $accountId);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = ($result && $result->num_rows > 0);
                $stmt->close();
                return $exists;
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Quick cash account lookup failure: ' . $e->getMessage(), 'accounting');
            }
        }
        return false;
    }
}

if (!function_exists('accounting_quick_cash_category_exists')) {
    function accounting_quick_cash_category_exists(mysqli $conn, int $categoryId): bool
    {
        if ($categoryId <= 0) {
            return false;
        }
        try {
            $stmt = $conn->prepare('SELECT id FROM acc_transaction_categories WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = ($result && $result->num_rows > 0);
                $stmt->close();
                return $exists;
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Quick cash category lookup failure: ' . $e->getMessage(), 'accounting');
            }
        }
        return false;
    }
}
