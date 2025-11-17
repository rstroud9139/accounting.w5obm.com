# Accounting Application Redesign (QuickBooks + gnuCash Inspired)

This document outlines the next-generation Accounting app for W5OBM, modeled after QuickBooks workflows with gnuCash feature parity where practical. The goal is a robust, modular, and auditable system with clean UX, strong reporting, and flexibility.

## Core Principles
- Double-entry accounting with a first-class Chart of Accounts
- Clear separation of concerns (MVC-like structure; controllers/services/repositories)
- Strong data integrity: transactions are immutable once posted; adjustments via journals
- Role-based access with audit logs on sensitive actions
- Consistent UI patterns, keyboard-friendly forms, and print-ready outputs

## Initial Architecture
- `accounting/app/index.php`: Front controller and router via `route` query parameter
- `controllers/`: Thin controllers orchestrating services/repos, rendering views
- `views/`: PHP templates with shared layout; Chart.js for charts
- `models/`: Entities (Accounts, Transaction, Contact, Vendor, Item, Budget)
- `repositories/`: DB access (mysqli prepared statements), single responsibility methods
- `services/`: Business logic (posting, reconciliation, budgeting, recurring rules)

## Roadmap: Feature Parity

### 1) Foundation
- Chart of Accounts: types (Asset, Liability, Equity, Income, Expense), codes, parent/child
- Transactions & Journal: splits, references, attachments, void/adjust entries
- Payees: Contacts/Donors, Vendors, Members
- Categories: transaction categories mapped to CoA when simplified views are needed
- Reconciliation: statement start/end, cleared flags, diffs, history
- Budgets: annual and monthly, category/account-level, variance analysis

### 2) Workflows (QuickBooks-like)
- Receipts (Donations/Dues): cash receipt forms → post to income and cash/bank
- Bills/Expenses: vendor bills, reimbursements, approvals → post to expense and cash/AP
- Transfers: between cash accounts; enforce no orphan postings
- Recurring: memorizable templates (frequency, next date)

### 3) Reports (QuickBooks + gnuCash)
- Profit & Loss (Income Statement), Balance Sheet, Cash Flow
- Budget vs Actual, Category/Account summaries, YTD/Monthly breakdowns
- Donor/Member statements, Annual donation summaries, 1099 helpers (if needed)
- Reconciliation reports, Audit log report

### 4) Usability
- Search everywhere, quick filters, date presets, sticky parameters
- Keyboard shortcuts for save/submit/line add/remove
- Print-friendly with standardized report header; export CSV/PDF
- Tooltips and inline guidance (new users)

## Proposed DB Additions (minimal)
- `acc_chart_of_accounts(id, code, name, type, parent_id, is_active)`
- `acc_journal(id, entry_date, memo, created_by, created_at)`
- `acc_journal_lines(id, journal_id, account_id, debit, credit, description)`
- `acc_reconciliations(id, account_id, period_start, period_end, ending_balance, created_by, created_at)`
- `acc_budgets(id, year, name)` / `acc_budget_lines(id, budget_id, account_id, month, amount)`
- `acc_audit_log(id, user_id, action, entity, entity_id, meta, created_at)`

Note: Existing `acc_transactions`/`acc_transaction_categories` remain supported while migrating to journals.

## Milestones
- M1: Dashboard (KPIs + charts), Accounts list, Transactions list (read-only)
- M2: Chart of Accounts CRUD, Transaction entry form with splits
- M3: Basic reconciliation module, Budget setup and Budget vs Actual report
- M4: Migration helpers from legacy tables to journals
- M5: Audit log + permissions matrix; attachment storage for receipts

## Tech Choices
- PHP 8 + mysqli prepared statements (existing)
- Chart.js for visualizations
- Bootstrap 5 components with standardized print CSS and shared report header
- No external framework; keep footprint small and cohesive with existing site

## Next Steps
- Wire sidebar/links to `accounting/app/index.php` routes
- Implement `repositories` and `services` for Accounts and Transactions
- Design Transaction Entry (split lines, debits/credits) with validation and posting rules
- Add CSV importers (bank statements), and CSV/QIF export
