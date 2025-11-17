-- SOURCE OF TRUTH: Before applying, refresh accounting/INDEX_SNAPSHOT.md and diff existing indexes.
-- PRINCIPLES:
-- 1. Prefer composite indexes matching common WHERE patterns.
-- 2. Avoid duplicates where left-prefix rule makes single-column index redundant.
-- 3. Introduce UNIQUE only after verifying no conflicting legacy data.

-- acc_transactions
-- Prefer composite (account_id, transaction_date) for balance & range queries
CREATE INDEX idx_acc_transactions_account_date ON acc_transactions (account_id, transaction_date);
-- Monthly category/type summaries often filter by type then date
CREATE INDEX idx_acc_transactions_type_date ON acc_transactions (type, transaction_date);
-- If vendor reporting is common, composite vendor/date reduces sort work
CREATE INDEX idx_acc_transactions_vendor_date ON acc_transactions (vendor_id, transaction_date);
-- category_id alone only if frequent standalone lookups; keep commented unless needed
-- CREATE INDEX idx_acc_transactions_category ON acc_transactions (category_id);
-- Single transaction_date alone may be redundant given above composites; add only if pure date scans dominate
-- CREATE INDEX idx_acc_transactions_date ON acc_transactions (transaction_date);

-- acc_transaction_categories
-- Category lookups typically by name or type; name uniqueness ensures stable references
CREATE INDEX idx_acc_categories_type ON acc_transaction_categories (type);
-- Uncomment after verifying no duplicates
-- CREATE UNIQUE INDEX uq_acc_categories_name ON acc_transaction_categories (name);

-- acc_ledger_accounts
-- Account number should be unique; verify existing null/duplicate issues first
CREATE UNIQUE INDEX uq_acc_ledger_account_number ON acc_ledger_accounts (account_number);
-- Parent linkage for hierarchy queries
CREATE INDEX idx_acc_ledger_parent ON acc_ledger_accounts (parent_account_id);
-- Active flag for filtered lists
CREATE INDEX idx_acc_ledger_active ON acc_ledger_accounts (active);

-- acc_assets
CREATE INDEX idx_acc_assets_acq_date ON acc_assets (acquisition_date);

-- acc_reports
CREATE INDEX idx_acc_reports_type ON acc_reports (report_type);
CREATE INDEX idx_acc_reports_generated ON acc_reports (generated_at);
