# Accounting UI Mockup Plan

## Goals
- Give stakeholders a tangible preview of the redesigned Ledger Register, Reconciliation workspace, and Reports dashboard.
- Use medium-fidelity HTML mockups that run locally (no backend dependencies) so interaction flows can be clicked through.
- Annotate critical interactions (required audit notes, modal behaviors, reconciliation steps, filter drawers) directly inside each mockup for clarity.

## Deliverables
| Area | File | Description |
| --- | --- | --- |
| Ledger Register | `docs/mockups/ledger-register.html` | Interactive table showing debit/credit/balance columns, account & category tags, audit sidebar, and modal overlays for **New Entry**, **Reverse Entry**, and **Adjust Entry**.
| Reconciliation Workspace | `docs/mockups/reconciliation.html` | Three-step workflow layout with hero, statement input card, dual-pane clearing list (Uncleared vs Cleared), progress badge, and recent reconciliation history.
| Reports Dashboard | `docs/mockups/reports-dashboard.html` | Grouped report cards (Operations, Compliance, Giving, Custom) plus a collapsible filter drawer/pill showing active filter count.

Each HTML file will:
1. Reference a shared lightweight stylesheet (`docs/mockups/mockups.css`) for typography, cards, and helper badges.
2. Include inline notes (small muted text) that describe how components behave in the production build.
3. Remain static—no AJAX or PHP—so they can be opened directly in a browser for review.

## Workflow
1. **Ledger First**
   - Build `ledger-register.html` using Bootstrap 5 grid classes and sample data.
   - Embed modal markup to demonstrate form fields (date, account, debit/credit split, required audit note) and show how reverse/adjust actions would appear.
   - Iterate with stakeholder feedback; once approved, move to implementation tickets.

2. **Reconciliation Next**
   - Reuse shared styles; emphasize the step-by-step process using numbered cards and status indicators.
   - Mock the transaction clearing table with checkbox rows and running totals.

3. **Reports Dashboard**
   - Highlight missing report categories; provide CTA buttons and secondary text describing each report bundle.
   - Implement collapsible filter drawer (pure CSS/JS) to demonstrate how the live page can hide filters until needed.

4. **Review & Sign-off**
   - Host screenshots or short screen recordings if needed.
   - Convert approved mockups into actual PHP templates and wire them to existing controllers/routes.

## Notes & Assumptions
- Mockups assume existing Bootstrap assets; no new build tooling required.
- Interaction text (e.g., audit note prompts) will follow the wording you provided earlier; we can refine copy after visual approval.
- Implementation tickets will be opened only after mockups receive a green light.
