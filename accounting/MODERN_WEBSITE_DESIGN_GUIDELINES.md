# W5OBM Modern Website Design & Maintenance Guidelines

This file is a copy of `dev.w5obm.com/documentation/technical_guides/MODERN_WEBSITE_DESIGN_GUIDELINES.md` at the time of copying.

For the authoritative, up-to-date version, refer to the technical guides under `dev.w5obm.com/documentation/technical_guides`.

---

## 1. Purpose & Objectives
These guidelines formalize the modernization work completed across W5OBM systems and establish forward standards for UI/UX consistency, accessibility, maintainability, and security. They apply to all folders under `authentication/`, `administration/`, and any maintenance sub‑applications (CRM, Events, Raffle, Accounting, Surveys, Email System, etc.).

Key Goals:
- Consistent theming and layout behavior across all applications.
- Predictable component patterns (hero sections, cards, tables, modals, forms).
- Reduction of user guesswork via controlled inputs (selectors, validation, hints).
- Strong baseline security (authorization, CSRF, validation, logging, auditing).
- Improved preview & configuration workflow for administrators (theme, navbar tone, hero fade profiles).
- Production readiness and maintainability.

---
## 2. Core Modernization Standards (Implemented & Required)
The following items have been implemented recently and are now required everywhere:
1. Theme System: Unified Bootstrap theme key with `preview_theme` support and standardized color/tone utilities.
2. Navbar Tone Preview: `preview_nav` parameter enabling live light/dark tone switching in Theme Gallery.
3. Hero Logo Component: Centralized hero logo include with per‑page size/visibility overrides (small/standard/hidden).
4. Adaptive Hero Overlay Fade: Global CSS custom properties with tone-aware effective values (`--hero-fade-start-effective`, `--hero-fade-alpha-effective`).
5. Fade Profiles: Support for `preview_fade` (default | strong | minimal) for rapid experimentation without code changes.
6. Layered Hero Overlay: Two-layer gradient (brand tint + bottom fade) fixing prior malformed CSS syntax and improving legibility.
7. Global CSS Variables: Canonical variables for hero fade start/alpha enabling consistent override + adaptive transformation.
8. Embedded Theme Live Preview: Small in-page iframe that responds instantly to theme, nav tone, and fade selection.
9. Documentation Integration: In-dashboard Markdown viewer; each subsystem should link to its technical or user guide from the hero.
10. Per-Page Overrides: Pages may define local hero fade or logo size without diverging from the adaptive pipeline.
11. Consistent Card Styling: Shadow, rounded corners, accessible contrast, clearly visible headers.
12. Structured Toast Notifications: Positioned centrally (top) with theme icons for success/error context.
13. Activity Logging: Administrative mutations logged with user, entity, target record, and action descriptor.
14. Secure Session Init: Standard `session_init.php` include + role verification functions `isAuthenticated()` / `isAdmin()`.
15. CSRF Tokens: Mandatory for all POST form submissions that mutate data (generated once per session, validated strictly).
16. Prepared Statements: All DB write/read operations use prepared statements (no concatenated SQL). Mandatory.
17. Preview Parameters Isolation: Theme preview parameters affect only display styling, not persisted user state until explicitly applied.
18. Modal-Based CRUD: Consistent use of Bootstrap modals for create/edit (exceptions: Events as noted by business rules).

All new code must adopt these patterns; legacy code should be refactored opportunistically.

---
## 3. Security Enhancements (Required Baseline)
Security posture requirements to be audited quarterly:
- Authentication Enforcement: Every admin or maintenance route must check `isAuthenticated()` + role/permission (e.g., `isAdmin()` or granular privilege).
- CSRF Protection: Hidden token on all mutating forms; immediate rejection with toast + log on mismatch.
- Input Validation: Server-side validation with explicit allowlists per field (length, format, enum values). Client-side validation augments UX but never replaces server validation.
- Output Escaping: Use `htmlspecialchars()` for dynamic content (text, attributes, option labels). Never trust stored data.
- Database Queries: Parameterized prepared statements only; zero tolerance for dynamic SQL injection risk.
- Password & Secret Handling: Never echo sensitive tokens; configuration secrets stored outside web root or in environment variables.
- Session Hardening: Regenerate session ID on privilege elevation; enforce secure cookie flags where HTTPS is supported.
- Rate Limiting (Future): Introduce throttle for high-risk mutation endpoints (optional enhancement phase).
- Logging & Auditing: All create/update/delete operations recorded with timestamp, actor ID, table, record key, and summary.
- Error Handling: User-facing errors generic; detailed stack traces to `error_log` only.
- File Uploads (If Any): Validate MIME, size, extension; store outside document root; randomize filenames.
- Principle of Least Privilege: Non-admin users cannot access administrative maintenance endpoints even via crafted direct URL.

---
## 4. Table Maintenance Screen Standards
Applies to all maintenance applications, excluding explicit Events folder exceptions.
1. Selector Inputs: Any field referencing a related table must use a a drop-down (or searchable select) populated from live query—no manual key guessing.
2. Icon Actions: Edit (`fa-pen` or similar) and Delete (`fa-trash`) icons must be functional and accessible (ARIA labels, tooltips).
3. Edit Modal: Clicking edit opens a Bootstrap modal with a form representing the full record; all fields preloaded with real data (except Events special case).
4. Add Modal: Add/Create actions open a modal with empty form aligned to table schema (except Events special case).
5. Validation: Client-side (HTML5 + JS) plus server-side; required fields indicated with an asterisk and `aria-required="true"`.
6. Refresh After Save: On successful modal save (add or edit) page reloads to reflect changes and recalculated statistics.
7. Table Width Discipline: Tables constrained to needed columns; avoid full-width unless data density warrants. Use responsive wrapping classes.
8. Column Ordering: Primary identifier, key descriptive fields, status indicators, then action icons right-aligned.
9. Accessible Headers: Ensure sufficient contrast, avoid theme combinations that obscure text/icons; use semantic `<th scope="col">`.
10. Paging & Searching: Provide search input and pagination where row count may exceed manageable scroll.
11. Batch Operations (Future): Bulk selection features must also follow logging standards (optional phase).

---
## 5. Dashboard & Hero Requirements
Every dashboard page must include a hero section at the top containing:
- Title (clear functional name of subsystem)
- Slogan or Info Area (brief helpful descriptor or current operational status)
- Club Logo (standard hero logo component; size adjustable per-page)
- Documentation Link (User Guide or System Technical Document)
- Theming Compliance (uses adaptive hero overlay fade + tone-effective variables)
- Smooth transitions for theme changes (CSS transitions on color, background, opacity)

Immediately below hero: Statistics section (cards) summarizing key metrics (counts, derived calculations). Cards:
- Pull real query data—no hard-coded values
- Uniform height behavior where possible
- Iconography aligned to semantic meaning (users, events, finances, etc.)

Dashboard Link Ordering:
1. User Applications (end-user tools)
2. Maintenance Items (data curation, reference tables)
3. Administrative / System Tasks (high privilege)

---
## 6. Logging & Auditing Standards
For each mutation (add/edit/delete/apply theme/change status):
- Record: User ID, Action Type, Target Table, Target Record ID, Summary Text
- Timestamp stored in UTC
- Provide optional filtering UI for activity review (future enhancement)
- Theme changes: Include previous theme and new theme
- Failed validations or security denials also logged (type: `security_violation`, `validation_failure`)

---
## 7. Reporting Standards
- Page & PDF Consistency: Reports styled to approximate 8.5" x 11" sheet (print-friendly formatting).
- Margins: Reasonable printable margins (0.5"–0.75").
- Typography: Use base font-size for readability; avoid theme color conflicts for headings.
- Export Options: PDF/Print buttons grouped, consistent icon usage.
- Section Headings: Clear hierarchy (H1 title, H2 subsections). Avoid deep nesting beyond H3.
- Table Overflow: Use wrapped cells or controlled horizontal scroll with visual cue.

---
## 8. Theming & UX Consistency
- Use CSS variables (hero fade, brand colors) rather than inline hard-coded values.
- All pages respect theme palette contrast—no overriding with incompatible color sets.
- Animations subtle, purpose-driven (hover elevation, fade transitions) ≤ 300ms.
- Iframes (live preview) sandboxed only for styling display; do not expose admin state.
- Maintain consistent spacing scale (Bootstrap spacing utilities) to avoid layout drift.

---
## 9. Form & Modal Patterns
- Modal size: `modal-lg` or `modal-xl` only when field count justifies.
- Primary Action Button: Rightmost; descriptive text ("Save Changes", "Add Record").
- Secondary Actions: Cancel/Close left of primary.
- Disable Submit During Processing; show spinner if async.
- Error Display: Inline near fields + summary alert if multiple errors.

---
## 10. Future / Pending Enhancements
The following are planned but not fully implemented; design now should anticipate them:
- Granular Permission Matrix (beyond simple admin/user separation).
- Rate limiting for high-frequency mutation endpoints.
- Bulk edit operations for maintenance tables.
- Centralized search/index across subsystems.
- Versioned record history (audit diffing).

---
## 11. Compliance & Review Process
- New or refactored pages must self-audit against this checklist prior to merge.
- Quarterly compliance sweep generates a summarized report (per folder) logged in documentation.
- Non-compliant items prioritized for next sprint—security items highest priority.

---
## 12. Implementation Checklist (Quick Reference)
Use this condensed list when building/editing a maintenance page:
[ ] Hero section present (title, slogan, logo, doc link, adaptive fade)
[ ] Statistics cards directly below hero with live data
[ ] Table uses constrained width, searchable, proper headers
[ ] All relational fields are selectors (no raw ID entry)
[ ] Edit/Delete icons functional with tooltips
[ ] Edit modal preloads correct data; Add modal mirrors schema
[ ] Validation (client + server) enforced; CSRF token included
[ ] Page reloads after successful save
[ ] Logging captures mutation details
[ ] Theme variables & adaptive fades used (no hard-coded gradients)
[ ] Documentation link functional
[ ] Prepared statements only; escaped output

---
## 13. Governance
This document is the authoritative source for W5OBM UI/UX & maintenance standards. Changes require admin approval and version bump. Any deviation must be documented with justification.

---
## 14. Changelog
- 1.0 (2025-11-14): Initial consolidated guidelines from modernization initiative (Theme Gallery, adaptive hero fades, security baseline, modal CRUD patterns).

---
End of Document.
