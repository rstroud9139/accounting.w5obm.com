-- SOURCE OF TRUTH: Before applying, refresh accounting/INDEX_SNAPSHOT.md and diff existing indexes.
START TRANSACTION;

-- Guarded helper: acc_transactions composite indexes
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

SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transactions'
			  AND INDEX_NAME = 'idx_acc_transactions_vendor_date'
		),
		'SELECT ''skip idx_acc_transactions_vendor_date''',
		'CREATE INDEX idx_acc_transactions_vendor_date ON acc_transactions (vendor_id, transaction_date)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_transaction_categories index
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_transaction_categories'
			  AND INDEX_NAME = 'idx_acc_categories_type'
		),
		'SELECT ''skip idx_acc_categories_type''',
		'CREATE INDEX idx_acc_categories_type ON acc_transaction_categories (type)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_ledger_accounts indexes
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_ledger_accounts'
			  AND INDEX_NAME = 'uq_acc_ledger_account_number'
		),
		'SELECT ''skip uq_acc_ledger_account_number''',
		'CREATE UNIQUE INDEX uq_acc_ledger_account_number ON acc_ledger_accounts (account_number)'
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
			  AND TABLE_NAME = 'acc_ledger_accounts'
			  AND INDEX_NAME = 'idx_acc_ledger_parent'
		),
		'SELECT ''skip idx_acc_ledger_parent''',
		'CREATE INDEX idx_acc_ledger_parent ON acc_ledger_accounts (parent_account_id)'
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
			  AND TABLE_NAME = 'acc_ledger_accounts'
			  AND INDEX_NAME = 'idx_acc_ledger_active'
		),
		'SELECT ''skip idx_acc_ledger_active''',
		'CREATE INDEX idx_acc_ledger_active ON acc_ledger_accounts (active)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_assets index
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_assets'
			  AND INDEX_NAME = 'idx_acc_assets_acq_date'
		),
		'SELECT ''skip idx_acc_assets_acq_date''',
		'CREATE INDEX idx_acc_assets_acq_date ON acc_assets (acquisition_date)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- acc_reports indexes
SET @ddl := (
	SELECT IF(
		EXISTS (
			SELECT 1 FROM information_schema.statistics
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = 'acc_reports'
			  AND INDEX_NAME = 'idx_acc_reports_type'
		),
		'SELECT ''skip idx_acc_reports_type''',
		'CREATE INDEX idx_acc_reports_type ON acc_reports (report_type)'
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
			  AND TABLE_NAME = 'acc_reports'
			  AND INDEX_NAME = 'idx_acc_reports_generated'
		),
		'SELECT ''skip idx_acc_reports_generated''',
		'CREATE INDEX idx_acc_reports_generated ON acc_reports (generated_at)'
	)
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
