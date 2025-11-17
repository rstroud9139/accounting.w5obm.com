<?php

/**
 * W5OBM Main Page Header Component
 * Usage: <?php include __DIR__ . '/../include/page_header.php'; ?>
 * 
 * Variables expected:
 * - $page_title (required) - The main page title
 * - $header_type (optional) - 'main' or 'secondary' (default: 'main')
 * - $show_datetime (optional) - true/false (default: true)
 * - $show_net_badge (optional) - true/false (default: true)
 * - $custom_badge_text (optional) - Custom badge text (default: 'NET Session')
 */

// Set defaults if not provided
$header_type = $header_type ?? 'main';
$show_datetime = $show_datetime ?? true;
$show_net_badge = $show_net_badge ?? true;
$custom_badge_text = $custom_badge_text ?? 'NET Session';

// Determine header size based on type
$header_class = ($header_type === 'secondary') ? 'club-header-secondary' : 'club-header';
$logo_size = ($header_type === 'secondary') ? '100px' : '150px';
$title_size = ($header_type === 'secondary') ? '2rem' : '2.5rem';
$page_title_size = ($header_type === 'secondary') ? '1.2rem' : '1.5rem';
$padding = ($header_type === 'secondary') ? '15px 0' : '20px 0';
$margin_top = ($header_type === 'secondary') ? '10px' : '50px';
$margin_bottom = ($header_type === 'secondary') ? '15px' : '20px';
?>

<style>
    .club-header,
    .club-header-secondary {
        background: linear-gradient(135deg, var(--theme-accent-primary, #1e3c72) 0%, var(--theme-accent-secondary, #2a5298) 100%);
        color: var(--theme-text-primary, #ffffff);
        padding: <?= $padding ?>;
        margin-top: <?= $margin_top ?>;
        margin-bottom: <?= $margin_bottom ?>;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .club-logo {
        width: <?= $logo_size ?>;
        height: <?= $logo_size ?>;
        object-fit: contain;
        box-shadow: 0 0 0 0;
        background: transparent;
    }

    .club-title {
        font-size: <?= $title_size ?>;
        font-weight: bold;
        margin-bottom: 0;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        color: var(--theme-text-primary, #ffffff);
    }

    .page-title {
        font-size: <?= $page_title_size ?>;
        margin-bottom: 0;
        opacity: 0.9;
        color: var(--theme-text-secondary, #e2e8f0);
    }

    .header-info {
        text-align: right;
    }

    .date-time {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 10px;
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {

        .club-header,
        .club-header-secondary {
            padding: 10px 0;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .club-logo {
            width: 80px;
            height: 80px;
        }

        .club-title {
            font-size: 1.5rem;
        }

        .page-title {
            font-size: 1rem;
        }

        .header-info {
            text-align: center;
            margin-top: 10px;
        }

        .date-time {
            font-size: 0.9rem;
        }
    }
</style>

<header class="<?= $header_class ?>">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-3 col-12 text-center text-md-start">
                <img src="<?= BASE_URL ?>images/badges/club_logo.png" alt="Club Logo" class="club-logo" onerror="this.style.display='none'">
            </div>
            <div class="col-md-6 col-12 text-center">
                <h1 class="club-title">W5OBM Amateur Radio Club</h1>
                <h2 class="page-title"><?= htmlspecialchars($page_title) ?></h2>
            </div>
            <div class="col-md-3 col-12 text-end">
                <div class="header-info">
                    <?php if ($show_datetime): ?>
                        <div class="date-time" id="current-datetime"></div>
                    <?php endif; ?>
                    <?php if ($show_net_badge): ?>
                        <div class="net-info">
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($custom_badge_text) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<?php if ($show_datetime): ?>
    <script>
        // Update date/time display
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const dateTimeElement = document.getElementById('current-datetime');
            if (dateTimeElement) {
                dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
            }
        }

        // Update immediately and then every minute
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
        });
    </script>
<?php endif; ?>