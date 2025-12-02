-- Migration 001: journaling, reconciliation, external payments
-- Apply this to the accounting_w5obm database after initial schema setup

USE `accounting_w5obm`;

-- Track schema migrations
CREATE TABLE IF NOT EXISTS acc_schema_migrations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Enrich journals (source + external payment info + status)
-- Guard each change so the migration is idempotent if partially applied earlier
SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journals'
              AND COLUMN_NAME = 'source_system'
        ),
        'SELECT "skip acc_journals.source_system"',
        'ALTER TABLE acc_journals ADD COLUMN source_system VARCHAR(50) NULL AFTER source'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journals'
              AND COLUMN_NAME = 'external_txn_id'
        ),
        'SELECT "skip acc_journals.external_txn_id"',
        'ALTER TABLE acc_journals ADD COLUMN external_txn_id VARCHAR(100) NULL AFTER ref_no'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journals'
              AND COLUMN_NAME = 'status'
        ),
        'SELECT "skip acc_journals.status"',
        'ALTER TABLE acc_journals ADD COLUMN status ENUM(''Draft'',''Posted'',''Voided'') NOT NULL DEFAULT ''Posted'' AFTER external_txn_id'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journals'
              AND INDEX_NAME = 'idx_acc_journals_source_txn'
        ),
        'SELECT "skip idx_acc_journals_source_txn"',
        'ALTER TABLE acc_journals ADD KEY idx_acc_journals_source_txn (source_system, external_txn_id)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journals'
              AND INDEX_NAME = 'idx_acc_journals_status_date'
        ),
        'SELECT "skip idx_acc_journals_status_date"',
        'ALTER TABLE acc_journals ADD KEY idx_acc_journals_status_date (status, journal_date)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Enrich journal lines for reconciliation
SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journal_lines'
              AND COLUMN_NAME = 'reconciled'
        ),
        'SELECT "skip acc_journal_lines.reconciled"',
        'ALTER TABLE acc_journal_lines ADD COLUMN reconciled TINYINT(1) NOT NULL DEFAULT 0 AFTER credit'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journal_lines'
              AND COLUMN_NAME = 'reconciled_at'
        ),
        'SELECT "skip acc_journal_lines.reconciled_at"',
        'ALTER TABLE acc_journal_lines ADD COLUMN reconciled_at TIMESTAMP NULL DEFAULT NULL AFTER reconciled'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journal_lines'
              AND COLUMN_NAME = 'external_ref'
        ),
        'SELECT "skip acc_journal_lines.external_ref"',
        'ALTER TABLE acc_journal_lines ADD COLUMN external_ref VARCHAR(100) NULL AFTER reconciled_at'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_journal_lines'
              AND INDEX_NAME = 'idx_acc_journal_lines_reconciled'
        ),
        'SELECT "skip idx_acc_journal_lines_reconciled"',
        'ALTER TABLE acc_journal_lines ADD KEY idx_acc_journal_lines_reconciled (reconciled, reconciled_at)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Link single-row transactions (legacy) to journals, for gradual migration
SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_transactions'
              AND COLUMN_NAME = 'journal_id'
        ),
        'SELECT "skip acc_transactions.journal_id"',
        'ALTER TABLE acc_transactions ADD COLUMN journal_id INT NULL AFTER id'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ddl := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'acc_transactions'
              AND INDEX_NAME = 'idx_acc_transactions_journal'
        ),
        'SELECT "skip idx_acc_transactions_journal"',
        'ALTER TABLE acc_transactions ADD KEY idx_acc_transactions_journal (journal_id)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mark this migration as applied
INSERT INTO acc_schema_migrations (name) VALUES ('001_journals_reconciliation_and_external_payments');
