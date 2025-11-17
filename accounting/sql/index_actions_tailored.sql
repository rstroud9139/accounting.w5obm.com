-- Tailored index actions based on accounting/INDEX_SNAPSHOT.md (Captured: 2025-11-10 19:46:03)
-- Review carefully before applying to production. Run in a maintenance window.
-- Notes:
-- - We add two composite indexes for acc_transactions commonly used filters.
-- - We drop exact duplicate/redundant single-column indexes observed in the snapshot.
-- - We DO NOT drop broadly useful single-column indexes without clear redundancy.

START TRANSACTION;

-- acc_transactions: add composite indexes (absent in snapshot)
CREATE INDEX idx_acc_transactions_account_date ON acc_transactions (account_id, transaction_date);
CREATE INDEX idx_acc_transactions_type_date ON acc_transactions (type, transaction_date);

-- acc_transactions: drop duplicate single-column indexes (present twice)
-- Keep idx_acc_txn_date and idx_acc_txn_category; drop duplicated aliases if they exist
DROP INDEX idx_acc_transactions_date ON acc_transactions;       -- duplicate of idx_acc_txn_date
DROP INDEX idx_acc_transactions_category ON acc_transactions;   -- duplicate of idx_acc_txn_category

-- Optional (commented): if pure vendor lookups w/ date ranges are frequent, uncomment
-- CREATE INDEX idx_acc_transactions_vendor_date ON acc_transactions (vendor_id, transaction_date);

-- acc_transaction_categories: drop duplicate type index (two identical indexes present)
-- Keep idx_acc_cat_type, drop idx_acc_categories_type
DROP INDEX idx_acc_categories_type ON acc_transaction_categories;

-- acc_ledger_accounts: already has desired indexes; nothing to add
-- acc_reports: OK
-- acc_assets: OK
-- acc_donations/acc_items/acc_recurring_transactions: OK for now; revisit if reporting grows

COMMIT;
