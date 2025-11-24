<?php

require_once __DIR__ . '/../lib/helpers.php';

/**
 * Ensure the double-entry rule table exists and is seeded.
 */
function ensureDoubleEntryRulesTable(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    global $conn;

    $createSql = "
        CREATE TABLE IF NOT EXISTS acc_double_entry_rules (
            id INT NOT NULL AUTO_INCREMENT,
            rule_name VARCHAR(150) NOT NULL,
            debit_account_type VARCHAR(30) NOT NULL,
            credit_account_type VARCHAR(30) NOT NULL,
            description TEXT,
            example TEXT,
            gaap_reference VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
    ";

    if (!$conn->query($createSql)) {
        logError('Failed creating acc_double_entry_rules: ' . $conn->error, 'accounting');
        return;
    }

    $countRes = $conn->query('SELECT COUNT(*) AS total FROM acc_double_entry_rules');
    $total = 0;
    if ($countRes) {
        $row = $countRes->fetch_assoc();
        $total = intval($row['total'] ?? 0);
        $countRes->close();
    }

    if ($total === 0) {
        seedDefaultDoubleEntryRules();
    }

    $initialized = true;
}

/**
 * Seed default GAAP-aligned rules for common treasurer workflows.
 */
function seedDefaultDoubleEntryRules(): void
{
    global $conn;

    $defaults = [
        [
            'Record Revenue (cash sale)',
            'Asset',
            'Income',
            'Cash or Accounts Receivable increases when revenue is earned.',
            'Debit Cash / Credit Membership Dues Income for collected dues.',
            'FASB ASC 606 revenue recognition'
        ],
        [
            'Record Expense (cash purchase)',
            'Expense',
            'Asset',
            'An operating cost reduces cash or bank balances.',
            'Debit Supplies Expense / Credit Checking for office supplies.',
            'FASB ASC 720 other expenses'
        ],
        [
            'Pay Existing Liability',
            'Liability',
            'Asset',
            'Settling a payable decreases both the liability and the cash account.',
            'Debit Accounts Payable / Credit Checking when paying vendors.',
            'FASB ASC 405 liabilities'
        ],
        [
            'Record Equity Contribution',
            'Asset',
            'Equity',
            'Owner/member contributions increase cash and equity.',
            'Debit Checking / Credit Capital Contributions for donations earmarked as equity.',
            'FASB Concept Statement 6 equity'
        ],
        [
            'Acquire Long-lived Asset',
            'Asset',
            'Asset',
            'One asset increases (equipment) while another decreases (cash).',
            'Debit Equipment / Credit Checking for a new repeater purchase.',
            'FASB ASC 360 property, plant, and equipment'
        ],
        [
            'Record Depreciation',
            'Expense',
            'Asset',
            'Depreciation expense increases while accumulated depreciation grows on the credit side.',
            'Debit Depreciation Expense / Credit Accumulated Depreciation monthly.',
            'FASB ASC 360 depreciation'
        ],
        [
            'Receive Loan or Deferred Revenue',
            'Asset',
            'Liability',
            'Cash increases along with a liability to repay or deliver services.',
            'Debit Checking / Credit Notes Payable when receiving a bank loan.',
            'FASB ASC 470 debt'
        ],
        [
            'Repay Loan Principal',
            'Liability',
            'Asset',
            'Loan balance decreases along with cash.',
            'Debit Notes Payable / Credit Checking for principal-only payments.',
            'FASB ASC 470 debt'
        ],
        [
            'Reclassify Funds Between Accounts',
            'Asset',
            'Asset',
            'Move value between cash, savings, or restricted accounts without impacting equity.',
            'Debit Savings / Credit Checking when transferring reserves to savings.',
            'GAAP cash management'
        ],
        [
            'Accrue Expense (not yet paid)',
            'Expense',
            'Liability',
            'Recognize an expense while setting up a liability to pay later.',
            'Debit Utilities Expense / Credit Accrued Liabilities for unpaid bills.',
            'FASB ASC 450 contingencies'
        ],
    ];

    $stmt = $conn->prepare('INSERT INTO acc_double_entry_rules (rule_name, debit_account_type, credit_account_type, description, example, gaap_reference) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        logError('Failed preparing insert for acc_double_entry_rules: ' . $conn->error, 'accounting');
        return;
    }

    foreach ($defaults as $rule) {
        $stmt->bind_param('ssssss', $rule[0], $rule[1], $rule[2], $rule[3], $rule[4], $rule[5]);
        if (!$stmt->execute()) {
            logError('Failed seeding double entry rule: ' . $stmt->error, 'accounting');
        }
    }

    $stmt->close();
}

/**
 * Fetch all double-entry rules for display.
 */
function getDoubleEntryRules(): array
{
    ensureDoubleEntryRulesTable();

    global $conn;
    $rules = [];

    $result = $conn->query('SELECT id, rule_name, debit_account_type, credit_account_type, description, example, gaap_reference FROM acc_double_entry_rules ORDER BY rule_name ASC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        $result->close();
    }

    return $rules;
}
