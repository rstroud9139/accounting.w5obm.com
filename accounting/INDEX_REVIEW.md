# Accounting Module Index & Schema Review

This document guides schema/index auditing for acc_* tables and collects recommendations.

## How to collect current schema/indexes

Run the read-only introspection helper (outputs SHOW CREATE TABLE and current indexes):

- In a browser: `/accounting/utils/db_introspection.php`
- CLI (Windows PowerShell):

```powershell
php .\accounting\utils\db_introspection.php > .\accounting\INDEX_SNAPSHOT.txt
```

Attach or paste `INDEX_SNAPSHOT.txt` into this repo (or the next PR comment) to finalize recommendations.

## Preliminary recommendations (based on query patterns)

These are safe, general-purpose indexes inferred from controllers and reports. Validate against the snapshot before applying.

- acc_transactions
  - INDEX(transaction_date)
  - INDEX(category_id)
  - INDEX(account_id)
  - INDEX(vendor_id)
  - Optional composite: INDEX(transaction_date, type)
- acc_transaction_categories
  - INDEX(type)
  - UNIQUE(name) if not already unique
- acc_ledger_accounts
  - UNIQUE(account_number)
  - INDEX(parent_account_id)
  - INDEX(active)
- acc_assets
  - INDEX(acquisition_date)
- acc_reports
  - INDEX(report_type)
  - INDEX(generated_at)

## Apply indexes (example SQL)

These are examples. Confirm names and existing keys to avoid duplicates:

```sql
-- acc_transactions
CREATE INDEX idx_acc_transactions_date ON acc_transactions (transaction_date);
CREATE INDEX idx_acc_transactions_category ON acc_transactions (category_id);
CREATE INDEX idx_acc_transactions_account ON acc_transactions (account_id);
CREATE INDEX idx_acc_transactions_vendor ON acc_transactions (vendor_id);
CREATE INDEX idx_acc_transactions_date_type ON acc_transactions (transaction_date, type);

-- acc_transaction_categories
CREATE INDEX idx_acc_categories_type ON acc_transaction_categories (type);

-- acc_ledger_accounts
CREATE UNIQUE INDEX uq_acc_ledger_account_number ON acc_ledger_accounts (account_number);
CREATE INDEX idx_acc_ledger_parent ON acc_ledger_accounts (parent_account_id);
CREATE INDEX idx_acc_ledger_active ON acc_ledger_accounts (active);

-- acc_assets
CREATE INDEX idx_acc_assets_acq_date ON acc_assets (acquisition_date);

-- acc_reports
CREATE INDEX idx_acc_reports_type ON acc_reports (report_type);
CREATE INDEX idx_acc_reports_generated ON acc_reports (generated_at);
```

## Notes

- Add indexes only after verifying they don’t duplicate existing ones.
- Prefer naming conventions like `idx_<table>_<columns>` and `uq_<table>_<column>` for clarity.
- Revisit composite indexes if workload changes (e.g., frequent filters by `account_id` + date range).
- Consider foreign key constraints once schema is stabilized (currently nullable FKs are supported by design).
