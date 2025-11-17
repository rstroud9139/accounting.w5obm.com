<?php

/** @var array $reports */
/** @var array $batch */
/** @var int $year */
/** @var int $month */
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="fas fa-play-circle me-2"></i><?= htmlspecialchars($batch['name']) ?> — Results</span>
        <div class="no-print">
            <a href="<?= route('batch_reports') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <button class="btn btn-sm btn-outline-primary" id="openAll"><i class="fas fa-external-link-square-alt me-1"></i>Open All Tabs</button>
            <button class="btn btn-sm btn-outline-success" onclick="window.print()"><i class="fas fa-print me-1"></i>Print This Page</button>
        </div>
    </div>
    <div class="card-body">
        <ol class="mb-3">
            <?php foreach ($reports as $r): ?>
                <li class="mb-2">
                    <a target="_blank" href="<?= htmlspecialchars($r['url']) ?>"><?= htmlspecialchars($r['label']) ?></a>
                </li>
            <?php endforeach; ?>
        </ol>

        <hr>
        <div class="row g-3">
            <?php foreach ($reports as $r): ?>
                <div class="col-12">
                    <div class="border rounded">
                        <div class="p-2 bg-light border-bottom"><strong><?= htmlspecialchars($r['label']) ?></strong></div>
                        <iframe src="<?= htmlspecialchars($r['url']) ?>" style="width:100%; height:800px; border:0;"></iframe>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
    document.getElementById('openAll').addEventListener('click', function(e) {
        e.preventDefault();
        var links = document.querySelectorAll('ol a[target="_blank"]');
        links.forEach(function(a) {
            window.open(a.href, '_blank');
        });
    });
</script>