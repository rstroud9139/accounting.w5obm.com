<?php

require_once __DIR__ . '/../include/session_init.php';
require_once __DIR__ . '/../include/dbconn.php';
require_once __DIR__ . '/../include/helper_functions.php';
require_once __DIR__ . '/../include/premium_hero.php';
require_once __DIR__ . '/utils/csrf.php';
require_once __DIR__ . '/lib/import_helpers.php';

csrf_ensure_token();

if (!isAuthenticated()) {
    setToastMessage('info', 'Login Required', 'Please login to access imports.', 'fas fa-file-import');
    header('Location: ../authentication/login.php');
    exit();
}

$user_id = getCurrentUserId();
if (!hasPermission($user_id, 'app.accounting') && !isAdmin($user_id)) {
    setToastMessage('danger', 'Access Denied', 'You do not have permission to access accounting imports.', 'fas fa-file-import');
    header('Location: ../authentication/dashboard.php');
    exit();
}

accounting_imports_ensure_tables($accConn);
$sourceTypes = accounting_imports_get_source_types();
$recentBatches = accounting_imports_fetch_recent_batches($accConn, 8);
$page_title = 'Accounting Imports - W5OBM';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php require __DIR__ . '/../include/header.php'; ?>
    <style>
        .import-shell {
            background-color: #f8fafc;
        }
        .import-dropzone {
            border: 2px dashed #6c63ff;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            background-color: rgba(108, 99, 255, 0.08);
            transition: border-color .2s ease, background-color .2s ease;
        }
        .import-dropzone.drag-over {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.08);
        }
        .import-dropzone.disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        .import-step-card {
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            background-color: #fff;
            height: 100%;
            padding: 1.25rem;
        }
        .import-step-card h6 {
            font-size: .95rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .import-checklist li::marker {
            color: #0d6efd;
        }
    </style>
</head>
<body class="accounting-app import-shell">
    <?php include __DIR__ . '/../include/menu.php'; ?>

    <div class="page-container" style="margin-top:0;padding-top:0;">
        <?php renderPremiumHero([
            'eyebrow' => 'Data Pipeline',
            'title' => 'Accounting Imports',
            'subtitle' => 'Bring in historic QuickBooks or gnuCash activity with confidence.',
            'description' => 'Upload, map, validate, and post ledger entries without leaving the accounting workspace.',
            'theme' => 'cobalt',
            'chips' => [
                'Guided mapping wizard',
                'Audit-friendly staging',
                'Rollback ready',
            ],
            'actions' => [
                [
                    'label' => 'Read Scope Notes',
                    'url' => '/docs/accounting_imports_scope.md',
                    'icon' => 'fa-book-open',
                    'variant' => 'outline',
                ],
                [
                    'label' => 'Back to Dashboard',
                    'url' => '/accounting/dashboard.php',
                    'icon' => 'fa-arrow-left',
                ],
            ],
            'highlights' => [
                [
                    'label' => 'Batches',
                    'value' => number_format(count($recentBatches)),
                    'meta' => 'Recent activity',
                ],
                [
                    'label' => 'Source Types',
                    'value' => number_format(count($sourceTypes)),
                    'meta' => 'Supported feeds',
                ],
                [
                    'label' => 'Status',
                    'value' => 'Wizard Draft',
                    'meta' => 'Posting disabled (WIP)',
                ],
            ],
        ]); ?>

        <div class="row g-4">
            <div class="col-lg-3">
                <?php accounting_render_workspace_nav('imports'); ?>
            </div>
            <div class="col-lg-9">
        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Upload Batch</h5>
                            <small class="text-muted">Nothing is committed until you pass validation and explicitly post.</small>
                        </div>
                        <span class="badge bg-secondary">Phase 1</span>
                    </div>
                    <div class="card-body">
                        <form id="importUploadForm" class="needs-validation" method="post" enctype="multipart/form-data" data-endpoint="/accounting/api/imports_upload.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label for="sourceType" class="form-label text-muted text-uppercase small">Source</label>
                                <select id="sourceType" name="source_type" class="form-select" required>
                                    <option value="">Choose a source</option>
                                    <?php foreach ($sourceTypes as $key => $meta): ?>
                                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    Pick the system that produced the file – adapters tune validation rules automatically.
                                </div>
                            </div>
                            <div class="import-dropzone" id="importDropzone">
                                <i class="fas fa-file-import fa-2x mb-3 text-primary"></i>
                                <p class="mb-1 fw-semibold">Drop CSV, IIF, GnuCash, or Quicken (QFX/OFX) files here</p>
                                <p class="text-muted small mb-0">or click to browse from your device</p>
                                <input type="file" name="import_file" id="importFileInput" class="form-control mt-3" accept=".csv,.CSV,.iif,.IIF,.gnucash,.qfx,.QFX,.ofx,.OFX" required>
                            </div>
                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted text-uppercase small" for="defaultAccount">Default Deposit Account</label>
                                    <input type="text" class="form-control" id="defaultAccount" placeholder="e.g. Bank of America Checking" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted text-uppercase small" for="defaultCategory">Default Income Category</label>
                                    <input type="text" class="form-control" id="defaultCategory" placeholder="e.g. General Donations" disabled>
                                </div>
                            </div>
                            <div id="importUploadStatus" class="visually-hidden" aria-live="polite" aria-atomic="true"></div>
                            <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 mt-2">
                                <button type="submit" class="btn btn-primary" id="importUploadSubmit">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>Stage Batch
                                </button>
                                <small class="text-muted">Large exports may take up to a minute to checksum and store.</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="row g-3">
                    <?php
                        $steps = [
                            [
                                'title' => 'Upload & Classify',
                                'icon' => 'fa-upload',
                                'copy' => 'Store the raw file with checksum + metadata so every decision is auditable.',
                            ],
                            [
                                'title' => 'Map Accounts & Categories',
                                'icon' => 'fa-sitemap',
                                'copy' => 'Use reusable mapping profiles to align QuickBooks/gnuCash accounts with W5OBM ledgers.',
                            ],
                            [
                                'title' => 'Validate & Post',
                                'icon' => 'fa-clipboard-check',
                                'copy' => 'Resolve duplicates, missing splits, and totals before pushing anything to acc_transactions.',
                            ],
                        ];
                    ?>
                    <?php foreach ($steps as $step): ?>
                        <div class="col-12">
                            <div class="import-step-card shadow-sm">
                                <h6 class="text-muted mb-2"><i class="fas <?= htmlspecialchars($step['icon']) ?> me-2 text-primary"></i><?= htmlspecialchars($step['title']) ?></h6>
                                <p class="mb-0 text-muted small"><?= htmlspecialchars($step['copy']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fas fa-clock me-2 text-secondary"></i>Recent Batches</h5>
                            <small class="text-muted">This list will populate once uploads are wired to staging tables.</small>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" type="button" disabled>
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentBatches)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-3"></i>
                                <p class="mb-1">No batches yet.</p>
                                <p class="small mb-0">Once the uploader is wired, staging activity will appear here with status tags.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Batch</th>
                                            <th>Source</th>
                                            <th>Status</th>
                                            <th class="text-end">Rows</th>
                                            <th>Updated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBatches as $batch): ?>
                                            <tr>
                                                <td>#<?= (int)$batch['id'] ?></td>
                                                <td><?= htmlspecialchars($batch['source_type']) ?></td>
                                                <td>
                                                    <?php $badge = accounting_imports_status_badge_class($batch['status']); ?>
                                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($batch['status'])) ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <?= number_format((int)$batch['ready_rows']) ?>/<?= number_format((int)$batch['total_rows']) ?>
                                                </td>
                                                <td><?= htmlspecialchars(date('M j, Y g:ia', strtotime($batch['updated_at']))) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="fas fa-list-check me-2 text-success"></i>Readiness Checklist</h5>
                    </div>
                    <div class="card-body">
                        <ol class="import-checklist text-muted small ps-3">
                            <li class="mb-2">Pull the correct export: QuickBooks IIF (General Journal) or gnuCash CSV (Transaction Report).</li>
                            <li class="mb-2">Snapshot the originating file into the club share in case auditors need the untouched source.</li>
                            <li class="mb-2">Verify Chart of Accounts and Categories are up to date before mapping.</li>
                            <li class="mb-2">Know your equivalency: which W5OBM account receives each source account?</li>
                            <li>Plan a review buddy – no batch should post without someone else spot-checking validation results.</li>
                        </ol>
                        <p class="text-muted small mb-0">Full scope and architecture notes live inside <code>docs/accounting_imports_scope.md</code>. We will hook the wizard into the staging tables next.</p>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../include/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('importUploadForm');
            var dropzone = document.getElementById('importDropzone');
            var fileInput = document.getElementById('importFileInput');
            var statusBox = document.getElementById('importUploadStatus');
            var submitBtn = document.getElementById('importUploadSubmit');
            var endpoint = (form && form.getAttribute('data-endpoint')) || '/accounting/api/imports_upload.php';

            if (dropzone && fileInput) {
                ['dragenter', 'dragover'].forEach(function(evtName) {
                    dropzone.addEventListener(evtName, function(event) {
                        event.preventDefault();
                        dropzone.classList.add('drag-over');
                    });
                });
                ['dragleave', 'drop'].forEach(function(evtName) {
                    dropzone.addEventListener(evtName, function(event) {
                        event.preventDefault();
                        dropzone.classList.remove('drag-over');
                        if (evtName === 'drop' && event.dataTransfer && event.dataTransfer.files.length) {
                            fileInput.files = event.dataTransfer.files;
                        }
                    });
                });
                dropzone.addEventListener('click', function() {
                    if (!fileInput.disabled) {
                        fileInput.click();
                    }
                });
            }

            if (!form) {
                return;
            }

            var defaultButtonLabel = submitBtn ? submitBtn.innerHTML : '';

            function notify(level, title, message) {
                if (statusBox) {
                    statusBox.textContent = (title ? title + ' – ' : '') + message;
                }

                if (typeof window.showToast === 'function') {
                    window.showToast(level, title || 'Notice', message, 'club-logo');
                    return;
                }

                if (statusBox) {
                    statusBox.classList.remove('visually-hidden');
                    statusBox.innerHTML = '<div class="alert alert-' + level + ' mb-0" role="alert">' +
                        (title ? '<strong>' + title + ':</strong> ' : '') + message + '</div>';
                } else if (window.alert) {
                    window.alert((title ? title + ': ' : '') + message);
                }
            }

            function setBusy(isBusy) {
                if (submitBtn) {
                    if (isBusy) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Staging...';
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = defaultButtonLabel;
                    }
                }
                if (fileInput) {
                    fileInput.disabled = isBusy;
                }
                if (dropzone) {
                    dropzone.classList.toggle('disabled', isBusy);
                }
            }

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    notify('danger', 'Missing Information', 'Please choose a source and file before staging.');
                    return;
                }

                var formData = new FormData(form);
                setBusy(true);
                notify('info', 'Upload Started', 'Uploading file and computing checksum...');

                fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function(response) {
                        return response.text().then(function(body) {
                            var payload = {};
                            if (body) {
                                try {
                                    payload = JSON.parse(body);
                                } catch (err) {
                                    payload = { success: false, error: 'Unexpected response from server.' };
                                }
                            }
                            if (!response.ok || payload.success === false) {
                                var message = payload.error || payload.message || 'Unable to stage batch.';
                                throw new Error(message);
                            }
                            return payload;
                        });
                    })
                    .then(function(payload) {
                        if (!payload || !payload.batch) {
                            throw new Error('Server did not return batch details.');
                        }
                        var batch = payload.batch;
                        var fileLabel = batch.original_filename || 'uploaded file';
                        notify('success', 'Batch Staged', 'Batch #' + batch.id + ' staged from ' + fileLabel + '. Refresh the list below to see it.');
                        form.reset();
                        form.classList.remove('was-validated');
                    })
                    .catch(function(err) {
                        notify('danger', 'Upload Failed', err.message || 'Unable to stage batch.');
                    })
                    .finally(function() {
                        setBusy(false);
                    });
            });
        });
    </script>
</body>
</html>
