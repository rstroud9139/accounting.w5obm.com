-- Migration: 2025_11_28_accounting_import_workflow
-- Purpose: add mapping + commit scaffolding for staged imports

USE `accounting_w5obm`;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS acc_import_account_maps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source_type VARCHAR(40) NOT NULL,
    source_key VARCHAR(160) NOT NULL,
    source_label VARCHAR(255) NULL,
    ledger_account_id INT NOT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_account_map_source (source_type, source_key),
    CONSTRAINT fk_account_map_ledger FOREIGN KEY (ledger_account_id) REFERENCES acc_ledger_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_import_batch_commits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    batch_id INT UNSIGNED NOT NULL,
    committed_by INT NULL,
    journal_count INT UNSIGNED NOT NULL DEFAULT 0,
    line_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes VARCHAR(255) NULL,
    CONSTRAINT fk_batch_commit_batch FOREIGN KEY (batch_id) REFERENCES acc_import_batches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
