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
ALTER TABLE acc_journals
    ADD COLUMN source_system VARCHAR(50) NULL AFTER source,
    ADD COLUMN external_txn_id VARCHAR(100) NULL AFTER ref_no,
    ADD COLUMN status ENUM('Draft','Posted','Voided') NOT NULL DEFAULT 'Posted' AFTER external_txn_id,
    ADD KEY idx_acc_journals_source_txn (source_system, external_txn_id),
    ADD KEY idx_acc_journals_status_date (status, journal_date);

-- Enrich journal lines for reconciliation
ALTER TABLE acc_journal_lines
    ADD COLUMN reconciled TINYINT(1) NOT NULL DEFAULT 0 AFTER credit,
    ADD COLUMN reconciled_at TIMESTAMP NULL DEFAULT NULL AFTER reconciled,
    ADD COLUMN external_ref VARCHAR(100) NULL AFTER reconciled_at,
    ADD KEY idx_acc_journal_lines_reconciled (reconciled, reconciled_at);

-- Link single-row transactions (legacy) to journals, for gradual migration
ALTER TABLE acc_transactions
    ADD COLUMN journal_id INT NULL AFTER id,
    ADD KEY idx_acc_transactions_journal (journal_id);

-- Mark this migration as applied
INSERT INTO acc_schema_migrations (name) VALUES ('001_journals_reconciliation_and_external_payments');
