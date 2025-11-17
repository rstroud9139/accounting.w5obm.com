<?php

/** @var array $items */
/** @var string|null $path */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-database me-2"></i>Migrations</span>
        <div class="no-print">
            <a href="<?= route('dashboard') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-home me-1"></i>Dashboard</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check me-2"></i><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <p class="text-muted">Folder: <code><?= htmlspecialchars((string)$path) ?></code></p>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Applied</th>
                        <th>Checksum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="3" class="text-muted">No migrations found.</td>
                        </tr>
                        <?php else: foreach ($items as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['filename']) ?></td>
                                <td>
                                    <?php if ($m['applied']): ?>
                                        <span class="badge bg-success">Applied</span>
                                        <small class="text-muted ms-1"><?= htmlspecialchars($m['applied']) ?></small>
                                        <?php if ($m['matches'] === false): ?>
                                            <span class="badge bg-warning text-dark ms-1" title="File changed since applied">Checksum mismatch</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small"><?= htmlspecialchars(substr($m['checksum'], 0, 12)) ?></code></td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
        <form action="<?= route('migrations_run') ?>" method="post" class="mt-3">
            <?= csrf_input() ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-play me-2"></i>Run Pending Migrations</button>
        </form>
        <p class="text-muted small mt-3">Includes seeds like categories/mapping; run this after pulling new code or adding migrations.</p>
    </div>
</div>