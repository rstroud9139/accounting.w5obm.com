-- Migration: 2025_11_28_accounting_schema_refresh
-- Purpose: Enable admin reset utilities, chart-of-accounts templates, and bank linking scaffolding

USE `accounting_w5obm`;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS acc_schema_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_schema_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_bank_connections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    connection_name VARCHAR(120) NOT NULL,
    status ENUM('pending','active','error','disconnected') NOT NULL DEFAULT 'pending',
    last_synced_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    secret_reference VARCHAR(120) NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bank_connections_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_bank_accounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    connection_id INT UNSIGNED NULL,
    ledger_account_id INT UNSIGNED NULL,
    institution_name VARCHAR(120) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    account_type ENUM('checking','savings','credit','loan','other') NOT NULL DEFAULT 'checking',
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    account_mask VARCHAR(8) NULL,
    routing_last4 VARCHAR(4) NULL,
    external_account_id VARCHAR(100) NULL,
    status ENUM('unlinked','linked','paused') NOT NULL DEFAULT 'unlinked',
    last_synced_at DATETIME NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bank_accounts_connection FOREIGN KEY (connection_id) REFERENCES acc_bank_connections(id) ON DELETE SET NULL,
    CONSTRAINT fk_bank_accounts_ledger FOREIGN KEY (ledger_account_id) REFERENCES acc_ledger_accounts(id) ON DELETE SET NULL,
    INDEX idx_bank_accounts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_coa_templates (
    code VARCHAR(30) NOT NULL PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    account_type ENUM('Asset','Liability','Equity','Revenue','Expense','COGS') NOT NULL,
    normal_balance ENUM('Debit','Credit') NOT NULL,
    description VARCHAR(255) NULL,
    parent_code VARCHAR(30) NULL,
    reporting_category VARCHAR(60) NULL,
    UNIQUE KEY uk_coa_template_number (account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_category_templates (
    code VARCHAR(30) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    account_type ENUM('Asset','Liability','Equity','Revenue','Expense','COGS') NOT NULL,
    description VARCHAR(255) NULL,
    default_account_number VARCHAR(20) NULL,
    reporting_group VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acc_admin_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    run_by INT NULL,
    include_chart_of_accounts TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    cleared_tables JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE acc_ledger_accounts
    ADD COLUMN IF NOT EXISTS normal_balance ENUM('Debit','Credit') NULL AFTER account_type,
    ADD COLUMN IF NOT EXISTS template_code VARCHAR(30) NULL AFTER normal_balance,
    ADD COLUMN IF NOT EXISTS external_reference VARCHAR(80) NULL AFTER template_code,
    ADD INDEX IF NOT EXISTS idx_ledger_template_code (template_code);

ALTER TABLE acc_categories
    ADD COLUMN IF NOT EXISTS account_number VARCHAR(20) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS account_type ENUM('Asset','Liability','Equity','Revenue','Expense','COGS') NULL AFTER account_number;

INSERT INTO acc_schema_migrations (name)
SELECT '2025_11_28_bank_links_and_templates'
WHERE NOT EXISTS (
    SELECT 1 FROM acc_schema_migrations WHERE name = '2025_11_28_bank_links_and_templates'
);

COMMIT;
