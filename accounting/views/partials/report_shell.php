<?php

/**
 * Shared Accounting Report Shell
 *
 * Usage:
 *  $reportShellConfig = [
 *      'title' => 'Financial Statements',
 *      'subtitle' => $periodLabel,
 *      'meta' => [ 'Period' => $periodLabel, 'Generated' => date('M j, Y H:i') ],
 *      'actions' => [
 *          ['id' => 'print', 'label' => 'Print', 'variant' => 'outline-secondary'],
 *          ['id' => 'pdf', 'label' => 'Save PDF', 'variant' => 'primary'],
 *          ['id' => 'csv', 'label' => 'CSV', 'href' => $csvUrl, 'variant' => 'outline-secondary']
 *      ],
 *      'highlights' => [ ['label' => 'Net Income', 'value' => '$12,450', 'trend' => '+4.2% vs LY'] ],
 *      'filters' => function () use ($filterData) {
 *          include __DIR__ . '/../forms/financial_filters.php';
 *      },
 *      'body' => function () use ($reportData) {
 *          include __DIR__ . '/../partials/financial_statement_body.php';
 *      },
 *  ];
 *  include __DIR__ . '/report_shell.php';
 */

if (!defined('REPORT_SHELL_ASSETS_LOADED')) {
    define('REPORT_SHELL_ASSETS_LOADED', true);
    $assetBase = '/accounting/assets';
    echo '<link rel="stylesheet" href="' . $assetBase . '/css/report-shell.css?v=1">';
    echo '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>';
    echo '<script defer src="' . $assetBase . '/js/report-export.js?v=1"></script>';
}

$defaults = [
    'eyebrow' => 'Accounting Report',
    'title' => 'Untitled Report',
    'subtitle' => null,
    'supporting_text' => null,
    'chips' => [],
    'meta' => [],
    'highlights' => [],
    'actions' => [],
    'filters' => null,
    'filter_title' => 'Filters',
    'filter_description' => null,
    'body_id' => 'reportShellBody',
    'body_class' => '',
    'body' => null,
    'body_empty_state' => '<p class="text-muted mb-0">No report data was provided.</p>',
    'body_intro' => null,
    'group_mode' => false,
    'logo_src' => null,
    'logo_alt' => 'Organization logo',
    'context_badge' => null,
];

$config = isset($reportShellConfig) && is_array($reportShellConfig)
    ? array_merge($defaults, $reportShellConfig)
    : $defaults;

$bodyHtml = '';
$bodyCallable = $config['body'] ?? null;
if ($bodyCallable && is_callable($bodyCallable)) {
    ob_start();
    $renderedBody = call_user_func($bodyCallable);
    $bufferedBody = ob_get_clean();
    if (is_string($bufferedBody) && trim($bufferedBody) !== '') {
        $bodyHtml = $bufferedBody;
    } elseif (is_string($renderedBody)) {
        $bodyHtml = $renderedBody;
    }
} elseif (is_string($config['body'])) {
    $bodyHtml = $config['body'];
} elseif (isset($reportShellBody) && is_string($reportShellBody)) {
    $bodyHtml = $reportShellBody;
}

$filtersHtml = '';
$filtersCallable = $config['filters'] ?? null;
if ($filtersCallable && is_callable($filtersCallable)) {
    ob_start();
    $renderedFilters = call_user_func($filtersCallable);
    $bufferedFilters = ob_get_clean();
    if (is_string($bufferedFilters) && trim($bufferedFilters) !== '') {
        $filtersHtml = $bufferedFilters;
    } elseif (is_string($renderedFilters)) {
        $filtersHtml = $renderedFilters;
    }
} elseif (is_string($config['filters'])) {
    $filtersHtml = $config['filters'];
}

$actions = array_filter($config['actions'], static function ($action) {
    return is_array($action) && !empty($action['label']);
});

$metaEntries = [];
if (!empty($config['meta']) && is_array($config['meta'])) {
    foreach ($config['meta'] as $label => $value) {
        if (is_array($value)) {
            $metaEntries[] = [
                'label' => $label,
                'value' => $value['value'] ?? '',
                'hint' => $value['hint'] ?? null,
            ];
        } else {
            $metaEntries[] = ['label' => $label, 'value' => $value, 'hint' => null];
        }
    }
}

$hasFilters = trim($filtersHtml) !== '';
$bodyClasses = trim('report-shell__body printer-friendly ' . $config['body_class']);
$bodyId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$config['body_id']);
$highlights = array_filter($config['highlights'], static fn($item) => is_array($item));
$chips = array_filter($config['chips'], static fn($chip) => is_string($chip) && $chip !== '');
?>

<section class="report-shell" data-report-shell>
    <div class="report-shell__header card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-4">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <?php if ($config['logo_src']): ?>
                    <div class="report-shell__logo-wrapper">
                        <img src="<?= htmlspecialchars($config['logo_src']); ?>" alt="<?= htmlspecialchars($config['logo_alt']); ?>" class="img-fluid">
                    </div>
                <?php endif; ?>
                <div>
                    <?php if ($config['eyebrow']): ?>
                        <p class="report-shell__eyebrow mb-1 text-uppercase text-muted"><?= htmlspecialchars($config['eyebrow']); ?></p>
                    <?php endif; ?>
                    <h1 class="h3 mb-1"><?= htmlspecialchars($config['title']); ?></h1>
                    <?php if ($config['subtitle']): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($config['subtitle']); ?></p>
                    <?php endif; ?>
                    <?php if ($config['supporting_text']): ?>
                        <p class="text-secondary mb-0 mt-2 small"><?= htmlspecialchars($config['supporting_text']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-lg-end w-100 w-lg-auto">
                <?php if (!empty($chips)): ?>
                    <div class="report-shell__chips mb-2">
                        <?php foreach ($chips as $chip): ?>
                            <span class="badge rounded-pill bg-light text-dark border"><?= htmlspecialchars($chip); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($config['context_badge']): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis mb-2"><?= htmlspecialchars($config['context_badge']); ?></span>
                <?php endif; ?>
                <?php if (!$config['group_mode'] && !empty($actions)): ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end" data-report-shell-actions>
                        <?php foreach ($actions as $action):
                            $id = isset($action['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$action['id']) : null;
                            $variant = 'btn-' . ($action['variant'] ?? ($action['id'] === 'pdf' ? 'primary' : 'outline-secondary'));
                            $icon = $action['icon'] ?? null;
                            $attr = 'class="btn ' . htmlspecialchars($variant, ENT_QUOTES) . ' btn-sm"';
                            if ($id) {
                                $attr .= ' data-report-action="' . htmlspecialchars($id) . '"';
                            }
                            if (!empty($action['data']) && is_array($action['data'])) {
                                foreach ($action['data'] as $dataKey => $dataValue) {
                                    $safeKey = preg_replace('/[^a-z0-9_-]/i', '', (string)$dataKey);
                                    if ($safeKey === '') {
                                        continue;
                                    }
                                    $attr .= ' data-' . htmlspecialchars(strtolower($safeKey)) . '="' . htmlspecialchars((string)$dataValue) . '"';
                                }
                            }
                        ?>
                            <?php if (!empty($action['href'])): ?>
                                <a href="<?= htmlspecialchars($action['href']); ?>" <?= $attr; ?><?= isset($action['target']) ? ' target="' . htmlspecialchars($action['target']) . '" rel="noopener"' : ''; ?>>
                                    <?php if ($icon): ?><i class="<?= htmlspecialchars($icon); ?> me-1"></i><?php endif; ?>
                                    <?= htmlspecialchars($action['label']); ?>
                                </a>
                            <?php else: ?>
                                <button type="button" <?= $attr; ?>>
                                    <?php if ($icon): ?><i class="<?= htmlspecialchars($icon); ?> me-1"></i><?php endif; ?>
                                    <?= htmlspecialchars($action['label']); ?>
                                </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($metaEntries)): ?>
            <div class="report-shell__meta border-top">
                <div class="row g-3">
                    <?php foreach ($metaEntries as $entry): ?>
                        <div class="col-md-4 col-lg-3">
                            <p class="text-muted text-uppercase small mb-1"><?= htmlspecialchars($entry['label']); ?></p>
                            <p class="mb-0 fw-medium"><?= htmlspecialchars($entry['value']); ?></p>
                            <?php if (!empty($entry['hint'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($entry['hint']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 align-items-start report-shell__layout">
        <?php if ($hasFilters): ?>
            <div class="col-lg-4 col-xl-3">
                <aside class="report-shell__filters card shadow-sm">
                    <?php if ($config['filter_title']): ?>
                        <div class="card-header bg-white border-bottom-0">
                            <h5 class="mb-0"><?= htmlspecialchars($config['filter_title']); ?></h5>
                            <?php if ($config['filter_description']): ?>
                                <p class="text-muted small mb-0 mt-1"><?= htmlspecialchars($config['filter_description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <?= $filtersHtml; ?>
                    </div>
                </aside>
            </div>
        <?php endif; ?>

        <div class="<?= $hasFilters ? 'col-lg-8 col-xl-9' : 'col-12'; ?>">
            <?php if (!empty($highlights)): ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
                    <?php foreach ($highlights as $tile):
                        $tone = $tile['tone'] ?? 'neutral';
                        $trend = $tile['trend'] ?? null;
                        $trendIcon = $tile['trend_icon'] ?? null;
                    ?>
                        <div class="col">
                            <div class="report-shell__highlight report-shell__highlight--<?= htmlspecialchars($tone); ?>">
                                <small class="text-muted text-uppercase"><?= htmlspecialchars($tile['label'] ?? 'Metric'); ?></small>
                                <h3 class="mb-1"><?= htmlspecialchars($tile['value'] ?? '—'); ?></h3>
                                <?php if (!empty($tile['detail'])): ?>
                                    <p class="mb-0 text-muted small"><?= htmlspecialchars($tile['detail']); ?></p>
                                <?php endif; ?>
                                <?php if ($trend): ?>
                                    <p class="mb-0 small <?= strpos((string)$trend, '-') === false ? 'text-success' : 'text-danger'; ?>">
                                        <?php if ($trendIcon): ?><i class="<?= htmlspecialchars($trendIcon); ?> me-1"></i><?php endif; ?>
                                        <?= htmlspecialchars($trend); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm report-shell__paper">
                <?php if ($config['body_intro']): ?>
                    <div class="card-header bg-white border-bottom-0 pb-0">
                        <p class="text-muted small mb-0"><?= htmlspecialchars($config['body_intro']); ?></p>
                    </div>
                <?php endif; ?>
                <div class="card-body">
                    <div id="<?= htmlspecialchars($bodyId); ?>" class="<?= htmlspecialchars($bodyClasses); ?>" data-report-shell-body>
                        <?= $bodyHtml !== '' ? $bodyHtml : $config['body_empty_state']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php unset($reportShellConfig); ?>