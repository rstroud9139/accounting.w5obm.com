# Accounting Report Print & PDF Framework

This document defines the shared structure, assets, and workflow that every modernized accounting report should use so on-screen views, browser printouts, saved PDFs, and batched report groups all look identical.

## Goals

1. **Consistency** – every report uses the same typography, spacing, and export controls.
2. **Print fidelity** – the browser print view and generated PDF share the same DOM.
3. **Reusability** – controllers drop in a single partial instead of rewriting layout/JS.
4. **Extensibility** – future report-group batching, scheduling, or emailing reuse the same hooks.

## Components

| Component | Location | Purpose |
| --- | --- | --- |
| `views/partials/report_shell.php` | *(new)* | Wraps any report body with heading, metadata, export buttons, and standardized `.printer-friendly` classes. Accepts slots for summary tiles, filter controls, and charts. |
| `assets/css/report-shell.css` | *(new)* | Provides layout rules for the shell, print overrides, and compact hero strip. Loaded once via `header.php` after Bootstrap. |
| `assets/js/report-export.js` | *(new)* | Tiny helper that wires the Print button to `window.print()` and the PDF button to `html2pdf`. Accepts options (filename, selector, orientation). |
| `include/report_header.php` (existing) | Enhanced to act as the default summary/header partial when a page does not need the full shell (e.g., legacy admin exports). |

## Usage Flow

1. **Controller builds data** – focus on SQL/arrays only.
2. **View sets `$reportShellConfig`**:
   ```php
   $reportShellConfig = [
       'title' => 'Financial Statement',
       'subtitle' => $periodLabel,
       'meta' => [
           'Period' => $periodLabel,
           'Generated' => date('M d, Y H:i')
       ],
       'actions' => [
           ['id' => 'print', 'label' => 'Print'],
           ['id' => 'pdf', 'label' => 'Save PDF'],
           ['id' => 'csv', 'label' => 'CSV', 'href' => $csvUrl]
       ]
   ];
   include __DIR__ . '/../views/partials/report_shell.php';
   ```
3. **Insert report body** inside the provided `<div class="report-shell__body" id="reportShellBody">…</div>` slot.
4. **Hook charts** by loading `report-charts.js` (see chart pack section below) after Chart.js.

## Export Handling

- **Browser Print** – automatically handled by `.report-shell` CSS and `window.print()` binding.
- **PDF Button** – `report-export.js` will find `data-report-pdf` attributes, clone the preview container, and call `html2pdf()` with consistent margins/filenames.
- **CSV/Excel** – still handled per-report by linking to existing `export=csv` endpoints. The shell just renders buttons.
- **Stored PDFs** – server-side processes should render the same PHP template and feed the resulting HTML to wkhtmltopdf (future `reports/group_preview.php`).

## Batch/Group Compatibility

Because every report mounts inside the same `.report-shell`, the report-group engine simply needs to:

1. Load each report view with `?group_mode=1` (suppresses CTA buttons via config).
2. Concatenate the resulting HTML into one long page.
3. Run it through the same `report-export.js` helper or server-side PDF tool.

## Next Steps

1. Build the actual partial (`views/partials/report_shell.php`) + CSS/JS assets.
2. Update `financial_statements.php` to use the shell as the pilot.
3. Migrate the rest of the reports in waves (Balance Sheet, Cash Flow, Donation Summary, etc.).
4. Introduce a `group_preview.php` endpoint once at least three reports use the shell reliably.

## Chart Pack Reference

- Script: `accounting/assets/js/report-charts.js`
- Load order: Chart.js → `report-charts.js` → page-specific inline config.
- Available helpers:
    - `ReportChartPack.renderLine(canvasId, labels, series, options)` – multi-series line/area.
    - `ReportChartPack.renderBar(canvasId, labels, series, options)` – stacked/clustered bars.
    - `ReportChartPack.renderDoughnut(canvasId, labels, data, options)` – expense mix / allocation.
    - `ReportChartPack.renderHealthGauge(canvasId, value)` – 0–100% indicator.
    - `ReportChartPack.palette` + `formatCurrencySeries()` for consistent colors/data prep.
