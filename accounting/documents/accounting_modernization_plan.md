# Accounting Modernization & Readiness Plan

_Date: 2025-11-28_

This plan translates the latest requirements into actionable workstreams so we can bring the accounting portal to a production-ready state and document the broader W5OBM upgrades.

## Phase 1 – Imports & Data Foundations
1. **GnuCash Snapshot Support**
   - Detect `.gnucash` uploads, decompress into XML, and store metadata alongside CSV/IIF batches.
   - Build parser to extract accounts + transactions into staging tables (ties into reset tooling below).
   - Tasks: storage helper updates, XML parser service, staging job, validation hooks, tests.
2. **Chart of Accounts Baseline**
   - Draft nonprofit template derived from current production + industry guidance.
   - Merge logic: imported accounts win; template fills gaps; custom zero-balance accounts optionally retained during resets.
3. **Environment Reset Console**
   - Admin-only UI + API that truncates accounting tables, replays schema migrations, and reseeds the chart based on options.
   - Logging + double-confirmation to prevent accidental wipes.

## Phase 2 – Ledger Experience & Access Hardening
1. **Ledger Workspace (CRUD-lite)**
   - Hierarchical chart display with account number, description, debit/credit columns, memo detail, running balance, and variance indicator.
   - Transactions immutable; add reversing-entry workflow + documentation attachment hook.
2. **Authentication Shield**
   - Route anonymous visits to `accounting/index.php` through reCAPTCHA before the authentication portal.
   - Ensure toast-based messaging + logging on captcha failures.

## Phase 3 – Backup, Restore, and Downloads
1. **Database Backup/Restore Tooling**
   - On-demand dumps with short-lived temp storage and immediate browser download.
   - Retention rules: up to 5 intra-month copies, 24 monthly snapshots.
   - Restore flow with checksum validation and activity log entries.
2. **Folder Snapshot Utility**
   - Admin/Super Admin UI to zip any top-level site folder (accounting/dev/production) and stream the archive to the requester.
   - Optionally enforce standard naming: `<folder>-YYYYMMDD-HHMMSS.zip`.

## Phase 4 – Communications & Member-Facing Material
1. **"New OBARC Website Upgrades & Features" Page (dev.w5obm.com)**
   - Sections: New Systems, Existing App Upgrades, Integrations, Security Enhancements.
   - Each system links to its reference doc.
2. **Reference Briefs**
   - Short-form Markdown documents per system/feature in `accounting/documents/` (mirrored for other apps as needed).
   - Accessible via new "Documentation" links throughout the UI.
3. **Presentation Kit**
   - PowerPoint deck summarizing systems, improvements, integrations, and security posture for member briefings.

## Dependencies & Notes
- **Testing**: Every destructive tool (reset, restore) must run in staging first; add CLI smoke scripts.
- **Security**: All new endpoints follow CSRF + permission checks and emit structured audit logs.
- **Storage**: Backups streamed to the browser so no long-term server storage is required (temp files cleaned immediately).
- **Next Actions**:
  1. Implement GnuCash upload + staging validation (in progress).
  2. Draft chart-of-accounts template and reset workflow UX.
  3. Build backup streaming utilities once staging flow is verified.
