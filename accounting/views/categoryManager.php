<?php

if (!function_exists('renderCategoryWorkspace')) {
    function renderCategoryWorkspace(
        array $categories,
        array $filters,
        array $summary,
        array $parentOptions,
        bool $canAdd,
        bool $canManage
    ): void {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $csrfToken = $_SESSION['csrf_token'];
        $typeOptions = ['Income', 'Expense', 'Asset', 'Liability', 'Equity'];
        $statusOptions = [
            'active' => 'Active Only',
            'inactive' => 'Archived Only',
            'all' => 'Active + Archived',
        ];
?>
        <div class="card shadow mb-4 border-0">
            <div class="card-header bg-primary text-white border-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Categories</h5>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-light btn-sm" id="categoryClearFilters">
                            <i class="fas fa-times me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0 flex-column flex-lg-row">
                    <div class="col-12 col-lg-3 border-bottom border-lg-bottom-0 border-lg-end bg-light-subtle p-3">
                        <h6 class="text-uppercase small text-muted fw-bold mb-2">Preset Filters</h6>
                        <p class="text-muted small mb-3">Apply common category groupings with a single click.</p>
                        <div class="d-grid gap-2">
                            <?php foreach ($typeOptions as $option): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm category-chip text-start"
                                    data-chip-type="<?= $option ?>">
                                    <?= $option ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm category-chip text-start"
                                data-chip-status="inactive">
                                Needs Review
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm category-chip text-start"
                                data-chip-clear="true">
                                Reset Filters
                            </button>
                        </div>
                    </div>
                    <div class="col-12 col-lg-9 p-3 p-lg-4">
                        <form method="GET" id="categoryFilterForm">
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <div class="col">
                                    <label for="type" class="form-label text-muted text-uppercase small mb-1">Category Type</label>
                                    <select class="form-select form-select-sm" id="type" name="type">
                                        <option value="">All Types</option>
                                        <?php foreach ($typeOptions as $option): ?>
                                            <option value="<?= $option ?>" <?= ($filters['type'] ?? '') === $option ? 'selected' : '' ?>>
                                                <?= $option ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label for="status" class="form-label text-muted text-uppercase small mb-1">Status</label>
                                    <select class="form-select form-select-sm" id="status" name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($filters['status'] ?? 'active') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 col-lg-8">
                                    <label for="search" class="form-label text-muted text-uppercase small mb-1">Keyword</label>
                                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                                        value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                                        placeholder="Name or description">
                                </div>
                            </div>
                            <div class="mt-3 d-flex flex-column flex-sm-row align-items-stretch justify-content-between gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="categoryExportBtn">
                                    <i class="fas fa-file-export me-1"></i>Export
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search me-1"></i>Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="fas fa-tags me-2 text-warning"></i>Categories
                        <small class="text-muted">(<?= number_format($summary['total'] ?? 0) ?>)</small>
                    </h4>
                    <small class="text-muted">Filtered results shown below</small>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($canAdd): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus-circle me-1"></i>New Category
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-2">No categories match the selected filters.</p>
                        <button class="btn btn-outline-primary" id="categoryEmptyStateReset">Reset Filters</button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Parent</th>
                                    <th class="text-center">Transactions</th>
                                    <th class="text-center">Status</th>
                                    <?php if ($canManage): ?>
                                        <th class="text-center">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category):
                                    $payload = htmlspecialchars(json_encode([
                                        'id' => (int)$category['id'],
                                        'name' => $category['name'] ?? '',
                                        'type' => $category['type'] ?? '',
                                        'description' => $category['description'] ?? '',
                                        'parent_category_id' => $category['parent_category_id'] ?? '',
                                        'transaction_count' => (int)($category['transaction_count'] ?? 0),
                                        'active' => (int)($category['active'] ?? 1),
                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars($category['name'] ?? '') ?><br>
                                            <small class="text-muted">ID: <?= (int)$category['id'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($category['type'] ?? '—') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($category['description'] ?? '--') ?></td>
                                        <td><?= htmlspecialchars($category['parent_category_name'] ?? '--') ?></td>
                                        <td class="text-center">
                                            <?php if (($category['transaction_count'] ?? 0) > 0): ?>
                                                <span class="badge bg-info"><?= (int)$category['transaction_count'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= !empty($category['active']) ? 'success' : 'secondary' ?>">
                                                <?= !empty($category['active']) ? 'Active' : 'Archived' ?>
                                            </span>
                                        </td>
                                        <?php if ($canManage): ?>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                                        data-bs-target="#editCategoryModal" data-category='<?= $payload ?>'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal"
                                                        data-bs-target="#deleteCategoryModal" data-category='<?= $payload ?>'>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-lg-none">
                        <?php foreach ($categories as $category):
                            $payload = htmlspecialchars(json_encode([
                                'id' => (int)$category['id'],
                                'name' => $category['name'] ?? '',
                                'type' => $category['type'] ?? '',
                                'description' => $category['description'] ?? '',
                                'parent_category_id' => $category['parent_category_id'] ?? '',
                                'transaction_count' => (int)($category['transaction_count'] ?? 0),
                                'active' => (int)($category['active'] ?? 1),
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="card border-0 border-bottom rounded-0">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="badge bg-secondary"><?= htmlspecialchars($category['type'] ?? '--') ?></span>
                                                <span class="badge bg-<?= !empty($category['active']) ? 'success' : 'secondary' ?>"><?= !empty($category['active']) ? 'Active' : 'Archived' ?></span>
                                            </div>
                                            <h6 class="mb-1"><?= htmlspecialchars($category['name'] ?? '') ?></h6>
                                            <?php if (!empty($category['description'])): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($category['description']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($category['parent_category_name'])): ?>
                                                <small class="text-muted">Parent: <?= htmlspecialchars($category['parent_category_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal" data-category='<?= $payload ?>'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal"
                                                    data-bs-target="#deleteCategoryModal" data-category='<?= $payload ?>'>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 text-muted small">Transactions: <?= (int)($category['transaction_count'] ?? 0) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canAdd): ?>
            <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST"
                        action="/accounting/categories/category_actions.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="addCategoryModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Category</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name" maxlength="255" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Type *</label>
                                    <select class="form-select" name="type" required>
                                        <option value="">Select type</option>
                                        <?php foreach ($typeOptions as $option): ?>
                                            <option value="<?= $option ?>"><?= $option ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Parent Category</label>
                                    <select class="form-select" name="parent_category_id">
                                        <option value="">None (top level)</option>
                                        <?php foreach ($parentOptions as $option): ?>
                                            <option value="<?= (int)$option['id'] ?>">
                                                <?= htmlspecialchars($option['name'] ?? '') ?> (<?= htmlspecialchars($option['type'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" maxlength="1000" placeholder="Optional details for this category"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Add Category</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <form class="modal-content border-0 shadow-lg needs-validation" method="POST"
                        action="/accounting/categories/category_actions.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="editCategoryId">
                        <div class="modal-header bg-primary text-white border-0">
                            <h5 class="modal-title" id="editCategoryModalLabel"><i class="fas fa-edit me-2"></i>Edit Category</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" name="name" id="editCategoryName" maxlength="255" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Type *</label>
                                    <select class="form-select" name="type" id="editCategoryType" required>
                                        <?php foreach ($typeOptions as $option): ?>
                                            <option value="<?= $option ?>"><?= $option ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Parent Category</label>
                                    <select class="form-select" name="parent_category_id" id="editCategoryParent">
                                        <option value="">None (top level)</option>
                                        <?php foreach ($parentOptions as $option): ?>
                                            <option value="<?= (int)$option['id'] ?>">
                                                <?= htmlspecialchars($option['name'] ?? '') ?> (<?= htmlspecialchars($option['type'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editCategoryDescription" rows="3" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content border-0 shadow" method="POST" action="/accounting/categories/category_actions.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteCategoryId">
                        <div class="modal-header bg-dark text-white border-0">
                            <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Deleting <strong id="deleteCategoryName">this category</strong>.</p>
                            <div class="alert alert-warning" id="deleteCategoryReassignAlert" style="display:none;">
                                This category has transactions. Reassignment is required before deletion.
                            </div>
                            <div class="mb-3" id="deleteCategoryReassignGroup" style="display:none;">
                                <label class="form-label">Reassign transactions to *</label>
                                <select class="form-select" name="reassign_category_id" id="deleteCategoryReassignSelect">
                                    <option value="">Select replacement</option>
                                    <?php foreach ($parentOptions as $option): ?>
                                        <option value="<?= (int)$option['id'] ?>">
                                            <?= htmlspecialchars($option['name'] ?? '') ?> (<?= htmlspecialchars($option['type'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="alert alert-danger mb-0">
                                This action cannot be undone.
                            </div>
                        </div>
                        <div class="modal-footer border-0 bg-light">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('categoryFilterForm');
                const clearBtn = document.getElementById('categoryClearFilters');
                const emptyReset = document.getElementById('categoryEmptyStateReset');
                const chips = document.querySelectorAll('.category-chip');
                const exportBtn = document.getElementById('categoryExportBtn');

                clearBtn?.addEventListener('click', () => {
                    form.reset();
                    window.location = window.location.pathname;
                });

                emptyReset?.addEventListener('click', () => {
                    form.reset();
                    form.submit();
                });

                chips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        if (chip.dataset.chipType) {
                            document.getElementById('type').value = chip.dataset.chipType;
                        }
                        if (chip.dataset.chipStatus) {
                            document.getElementById('status').value = chip.dataset.chipStatus;
                        }
                        if (chip.dataset.chipClear) {
                            document.getElementById('type').value = '';
                            document.getElementById('status').value = 'active';
                            document.getElementById('search').value = '';
                        }
                        form.submit();
                    });
                });

                exportBtn?.addEventListener('click', () => {
                    const params = new URLSearchParams(window.location.search);
                    params.set('export', 'csv');
                    window.location.href = '?' + params.toString();
                });

                document.querySelectorAll('.needs-validation').forEach(f => {
                    f.addEventListener('submit', event => {
                        if (!f.checkValidity()) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        f.classList.add('was-validated');
                    });
                });

                <?php if ($canManage): ?>
                    const editModal = document.getElementById('editCategoryModal');
                    editModal?.addEventListener('show.bs.modal', event => {
                        const data = extractCategoryPayload(event.relatedTarget);
                        if (!data) return;
                        document.getElementById('editCategoryId').value = data.id;
                        document.getElementById('editCategoryName').value = data.name || '';
                        document.getElementById('editCategoryType').value = data.type || '';
                        document.getElementById('editCategoryDescription').value = data.description || '';
                        const parentSelect = document.getElementById('editCategoryParent');
                        parentSelect.value = data.parent_category_id || '';
                        Array.from(parentSelect.options).forEach(option => {
                            option.disabled = option.value && parseInt(option.value, 10) === data.id;
                        });
                    });

                    const deleteModal = document.getElementById('deleteCategoryModal');
                    deleteModal?.addEventListener('show.bs.modal', event => {
                        const data = extractCategoryPayload(event.relatedTarget);
                        if (!data) return;
                        document.getElementById('deleteCategoryId').value = data.id;
                        document.getElementById('deleteCategoryName').textContent = data.name || 'this category';
                        const requiresReassign = (data.transaction_count || 0) > 0;
                        const reassignGroup = document.getElementById('deleteCategoryReassignGroup');
                        const reassignAlert = document.getElementById('deleteCategoryReassignAlert');
                        const reassignSelect = document.getElementById('deleteCategoryReassignSelect');
                        reassignGroup.style.display = requiresReassign ? 'block' : 'none';
                        reassignAlert.style.display = requiresReassign ? 'block' : 'none';
                        reassignSelect.required = requiresReassign;
                        reassignSelect.value = '';
                        Array.from(reassignSelect.options).forEach(option => {
                            option.disabled = option.value && parseInt(option.value, 10) === data.id;
                        });
                    });
                <?php endif; ?>

                function extractCategoryPayload(trigger) {
                    if (!trigger) return null;
                    const payload = trigger.getAttribute('data-category');
                    if (!payload) return null;
                    try {
                        return JSON.parse(payload);
                    } catch (error) {
                        console.error('Invalid category payload', error);
                        return null;
                    }
                }
            });
        </script>
<?php
    }
}
