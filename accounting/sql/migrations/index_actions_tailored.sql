-- Tailored index actions based on accounting/INDEX_SNAPSHOT.md (Captured: 2025-11-10 19:46:03)
-- Review carefully before applying to production. Run in a maintenance window.
-- Notes:
-- - We add two composite indexes for acc_transactions commonly used filters.
-- - We drop exact duplicate/redundant single-column indexes observed in the snapshot.
-- - We DO NOT drop broadly useful single-column indexes without clear redundancy.

START TRANSACTION;

-- acc_transactions: add composite indexes (only if missing)
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transactions'
			  AND INDEX_NAME = 'idx_acc_transactions_account_date'
		),
		'SELECT ''skip idx_acc_transactions_account_date''',
		'CREATE INDEX idx_acc_transactions_account_date ON acc_transactions (account_id, transaction_date)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transactions'
			  AND INDEX_NAME = 'idx_acc_transactions_type_date'
		),
		'SELECT ''skip idx_acc_transactions_type_date''',
		'CREATE INDEX idx_acc_transactions_type_date ON acc_transactions (type, transaction_date)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_transactions: drop duplicate single-column indexes only if they exist
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transactions'
			  AND INDEX_NAME = 'idx_acc_transactions_date'
		),
		'DROP INDEX idx_acc_transactions_date ON acc_transactions',
		'SELECT ''skip idx_acc_transactions_date'''
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transactions'
			  AND INDEX_NAME = 'idx_acc_transactions_category'
		),
		'DROP INDEX idx_acc_transactions_category ON acc_transactions',
		'SELECT ''skip idx_acc_transactions_category'''
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_transaction_categories: drop duplicate type index only when present
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transaction_categories'
			  AND INDEX_NAME = 'idx_acc_categories_type'
		),
		'DROP INDEX idx_acc_categories_type ON acc_transaction_categories',
		'SELECT ''skip idx_acc_categories_type'''
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
