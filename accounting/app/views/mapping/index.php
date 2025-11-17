<?php

/** @var array $categories */
/** @var array $accounts */
/** @var array $map */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-link me-2"></i>Category → Default Offset Account</span>
        <div class="no-print">
            <a href="<?= route('dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check me-2"></i><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= route('category_map_save') ?>" class="table-responsive">
            <?= csrf_input() ?>
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th style="width:50%">Category</th>
                        <th style="width:50%">Default Offset Account</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): $cid = (int)$c['id'];
                        $sel = isset($map[$cid]) ? (int)$map[$cid] : 0; ?>
                        <tr>
                            <td><?= htmlspecialchars($c['name']) ?></td>
                            <td>
                                <select class="form-select form-select-sm" name="map[<?= $cid ?>]">
                                    <option value="0">-- None --</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= (int)$a['id'] ?>" <?= $sel === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?><?= isset($a['type']) && $a['type'] ? ' (' . htmlspecialchars($a['type']) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-3">
                <button class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Mappings</button>
            </div>
        </form>

        <hr>
        <p class="text-muted small">These mappings will auto-suggest the offset account when you pick a category in import splits.</p>
    </div>
</div>