<?php

/**
 * Premium Hero Component
 * Inspired by insurance marketing layout with split content + media carousel.
 * Usage: require this file and call renderPremiumHero([...]).
 *  - Provide `slides` for a carousel or `media[src]` for a static image.
 *  - `media_mode`: auto | carousel | image | none.
 *  - `size`: standard | compact for smaller accounting modules.
 */

if (!function_exists('renderPremiumHero')) {
    function renderPremiumHero(array $config = []): void
    {
        static $instance = 0;
        $instance++;
        $heroId = 'premiumHero_' . $instance;
        $defaults = [
            'eyebrow' => 'Finance',
            'title' => 'Financial Confidence, Every Day.',
            'subtitle' => 'Monitor trends, prepare reports, and keep leadership informed with a single control center.',
            'description' => '',
            'actions' => [],
            'highlights' => [],
            'chips' => [],
            'media' => [
                'type' => 'image',
                'src' => 'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=900&q=80',
                'alt' => 'Team collaborating over financial reports'
            ],
            'slides' => [],
            'theme' => 'midnight', // midnight, cobalt, emerald
            'media_mode' => 'auto', // auto, carousel, image, none
            'size' => 'standard', // standard, compact
        ];

        $settings = array_merge($defaults, $config);
        $eyebrow = htmlspecialchars($settings['eyebrow'], ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($settings['title'], ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($settings['subtitle'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($settings['description'], ENT_QUOTES, 'UTF-8');
        $theme = preg_replace('/[^a-z0-9_-]/i', '', $settings['theme']);
        $size = strtolower((string)$settings['size']);
        $size = in_array($size, ['standard', 'compact'], true) ? $size : 'standard';
        $actions = $settings['actions'];
        $highlights = $settings['highlights'];
        $media = $settings['media'];
        $slides = $settings['slides'];
        $mediaMode = strtolower((string)$settings['media_mode']);
        $mediaMode = in_array($mediaMode, ['auto', 'carousel', 'image', 'none'], true) ? $mediaMode : 'auto';
        $hasSlides = !empty($slides);
        $hasImage = !empty($media['src']);
        $renderMedia = $mediaMode !== 'none' && ($hasSlides || $hasImage);
        $useCarousel = false;
        if ($renderMedia) {
            if ($mediaMode === 'carousel') {
                $useCarousel = $hasSlides;
            } elseif ($mediaMode === 'image') {
                $useCarousel = false;
            } else { // auto
                $useCarousel = $hasSlides;
            }
        }

        if (!defined('W5OBM_PREMIUM_HERO_STYLES')) {
            define('W5OBM_PREMIUM_HERO_STYLES', true);
?>
            <style id="premium-hero-styles">
                .premium-hero {
                    position: relative;
                    border-radius: 28px;
                    overflow: hidden;
                    padding: 2.75rem;
                    margin-bottom: 2rem;
                    color: #fff;
                }

                .premium-hero::after {
                    content: "";
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(120deg, rgba(0, 0, 0, .35), rgba(0, 0, 0, .15));
                    pointer-events: none;
                }

                .premium-hero__inner {
                    position: relative;
                    display: flex;
                    gap: 2.5rem;
                    flex-wrap: wrap;
                    z-index: 2;
                    align-items: stretch;
                }

                .premium-hero__copy {
                    flex: 1 1 360px;
                    max-width: 580px;
                }

                .premium-hero__eyebrow {
                    text-transform: uppercase;
                    letter-spacing: .16em;
                    font-size: .78rem;
                    color: rgba(255, 255, 255, .75);
                }

                .premium-hero__title {
                    font-size: clamp(1.9rem, 3vw, 2.75rem);
                    font-weight: 700;
                    margin-bottom: .75rem;
                }

                .premium-hero__subtitle {
                    font-size: 1.05rem;
                    color: rgba(255, 255, 255, .85);
                    margin-bottom: 1rem;
                }

                .premium-hero__description {
                    color: rgba(255, 255, 255, .72);
                    margin-bottom: 1.5rem;
                }

                .premium-hero__actions .btn {
                    min-width: 160px;
                }

                .premium-hero__media {
                    flex: 1 1 320px;
                    min-height: 320px;
                    border-radius: 22px;
                    overflow: hidden;
                    position: relative;
                    box-shadow: inset 0 0 40px rgba(0, 0, 0, .35), 0 20px 60px rgba(3, 10, 37, .45);
                    background-color: rgba(3, 17, 48, .95);
                }

                .premium-hero__media::before,
                .premium-hero__media::after {
                    content: "";
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                }

                .premium-hero__media::before {
                    background: linear-gradient(135deg, rgba(4, 18, 59, .55), rgba(13, 51, 107, .15));
                }

                .premium-hero__media--carousel::after {
                    background: linear-gradient(90deg, rgba(4, 18, 59, .65) 0%, rgba(4, 18, 59, 0) 26%, rgba(4, 18, 59, 0) 74%, rgba(4, 18, 59, .65) 100%);
                    mix-blend-mode: multiply;
                }

                .premium-hero__highlights {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 1rem;
                    margin-top: 2rem;
                }

                .premium-hero__highlight {
                    flex: 1 1 150px;
                    background: rgba(255, 255, 255, .12);
                    border-radius: 16px;
                    padding: 1rem 1.25rem;
                    backdrop-filter: blur(4px);
                    min-width: 140px;
                }

                .premium-hero__highlight-value {
                    font-size: 1.35rem;
                    font-weight: 600;
                    line-height: 1.1;
                }

                .premium-hero__highlight-label {
                    font-size: .85rem;
                    text-transform: uppercase;
                    letter-spacing: .08em;
                    color: rgba(255, 255, 255, .65);
                }

                .premium-hero__highlight-meta {
                    font-size: .78rem;
                    color: rgba(255, 255, 255, .8);
                }

                .premium-hero--midnight {
                    background: linear-gradient(135deg, #04123b, #0c447a);
                }

                .premium-hero--cobalt {
                    background: linear-gradient(135deg, #0c326f, #2563eb);
                }

                .premium-hero--emerald {
                    background: linear-gradient(135deg, #053f2b, #1c8f5c);
                }

                .premium-hero--compact {
                    padding: 1.75rem 1.5rem;
                    border-radius: 22px;
                }

                .premium-hero--compact .premium-hero__title {
                    font-size: clamp(1.5rem, 2.4vw, 2.1rem);
                }

                .premium-hero--compact .premium-hero__subtitle {
                    font-size: .95rem;
                    margin-bottom: .75rem;
                }

                .premium-hero--compact .premium-hero__description {
                    font-size: .92rem;
                    margin-bottom: 1rem;
                }

                .premium-hero--compact .premium-hero__media {
                    min-height: 240px;
                }

                .premium-hero--compact .premium-hero__highlights {
                    margin-top: 1.2rem;
                    gap: .75rem;
                }

                .premium-hero--compact .premium-hero__highlight {
                    padding: .85rem 1rem;
                }

                .premium-hero__carousel .carousel-item {
                    min-height: 320px;
                }

                .premium-hero__carousel img {
                    object-fit: cover;
                    height: 100%;
                    width: 100%;
                }

                .premium-hero__carousel .carousel-indicators [data-bs-target] {
                    width: 12px;
                    height: 12px;
                    border-radius: 50%;
                }

                .premium-hero__media-overlay {
                    position: absolute;
                    inset: 0;
                    border-radius: inherit;
                    background: linear-gradient(90deg,
                            var(--hero-overlay-from, rgba(3, 17, 48, 0.7)) 0%,
                            rgba(3, 17, 48, 0.5) 6%,
                            rgba(3, 17, 48, 0.26) 10%,
                            rgba(3, 17, 48, 0.12) 13%,
                            rgba(3, 17, 48, 0) 15%,
                            rgba(3, 17, 48, 0) 85%,
                            rgba(3, 17, 48, 0.12) 87%,
                            rgba(3, 17, 48, 0.26) 90%,
                            rgba(3, 17, 48, 0.5) 94%,
                            var(--hero-overlay-to, rgba(3, 17, 48, 0.7)) 100%);
                    pointer-events: none;
                    z-index: 2;
                }

                .premium-hero__media .carousel,
                .premium-hero__media .carousel-inner,
                .premium-hero__media .carousel-item,
                .premium-hero__media .carousel-item img,
                .premium-hero__media .h-100.w-100 {
                    position: relative;
                    z-index: 1;
                }

                .premium-hero__media .carousel-indicators,
                .premium-hero__media .carousel-control-prev,
                .premium-hero__media .carousel-control-next {
                    z-index: 3;
                }

                .premium-hero__chips {
                    display: flex;
                    flex-wrap: wrap;
                    gap: .5rem;
                    margin-bottom: 1.25rem;
                }

                .premium-hero__chip {
                    border: 1px solid rgba(255, 255, 255, .35);
                    border-radius: 999px;
                    padding: .35rem 1rem;
                    font-size: .82rem;
                    color: rgba(255, 255, 255, .85);
                }

                @media (max-width: 991.98px) {
                    .premium-hero {
                        padding: 2rem;
                    }

                    .premium-hero__inner {
                        flex-direction: column;
                    }

                    .premium-hero__media {
                        min-height: 240px;
                    }
                }
            </style>
<?php
        }

        $themeClass = $theme ? ' premium-hero--' . $theme : '';
        $sizeClass = $size === 'compact' ? ' premium-hero--compact' : '';
        $sectionClass = 'premium-hero' . $themeClass . $sizeClass;
        echo '<section class="' . $sectionClass . '"><div class="premium-hero__inner">';
        echo '<div class="premium-hero__copy">';
        if ($eyebrow !== '') {
            echo '<div class="premium-hero__eyebrow">' . $eyebrow . '</div>';
        }
        echo '<h1 class="premium-hero__title">' . $title . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="premium-hero__subtitle">' . $subtitle . '</p>';
        }
        if ($description !== '') {
            echo '<p class="premium-hero__description">' . $description . '</p>';
        }

        if (!empty($settings['chips']) && is_array($settings['chips'])) {
            echo '<div class="premium-hero__chips">';
            foreach ($settings['chips'] as $chip) {
                echo '<span class="premium-hero__chip">' . htmlspecialchars($chip, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            echo '</div>';
        }

        if (!empty($actions)) {
            echo '<div class="premium-hero__actions d-flex flex-wrap gap-3 mb-3">';
            foreach ($actions as $action) {
                $label = htmlspecialchars($action['label'] ?? 'Learn more', ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars($action['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                $variant = $action['variant'] ?? 'primary';
                $icon = $action['icon'] ?? '';
                $target = !empty($action['external']) ? ' target="_blank" rel="noopener"' : '';
                $btnClass = $variant === 'outline' ? 'btn btn-outline-light' : 'btn btn-light text-primary fw-semibold';
                echo '<a class="' . $btnClass . '" href="' . $url . '"' . $target . '>';
                if ($icon) {
                    echo '<i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-2"></i>';
                }
                echo $label . '</a>';
            }
            echo '</div>';
        }

        if (!empty($highlights)) {
            echo '<div class="premium-hero__highlights">';
            foreach ($highlights as $highlight) {
                $value = htmlspecialchars($highlight['value'] ?? '', ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($highlight['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $meta = htmlspecialchars($highlight['meta'] ?? '', ENT_QUOTES, 'UTF-8');
                echo '<div class="premium-hero__highlight">';
                if ($value !== '') {
                    echo '<div class="premium-hero__highlight-value">' . $value . '</div>';
                }
                if ($label !== '') {
                    echo '<div class="premium-hero__highlight-label">' . $label . '</div>';
                }
                if ($meta !== '') {
                    echo '<div class="premium-hero__highlight-meta">' . $meta . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        if ($renderMedia) {
            $mediaClasses = 'premium-hero__media' . ($useCarousel ? ' premium-hero__media--carousel' : '');
            echo '<div class="' . $mediaClasses . '">';
            if ($useCarousel) {
                $carouselId = $heroId . '_carousel';
                echo '<div id="' . $carouselId . '" class="carousel slide carousel-fade premium-hero__carousel" data-bs-ride="carousel" data-bs-interval="6500">';
                echo '<div class="carousel-inner">';
                foreach ($slides as $index => $slide) {
                    $src = htmlspecialchars($slide['src'] ?? '', ENT_QUOTES, 'UTF-8');
                    $alt = htmlspecialchars($slide['alt'] ?? ('Slide ' . ($index + 1)), ENT_QUOTES, 'UTF-8');
                    $activeClass = $index === 0 ? ' active' : '';
                    echo '<div class="carousel-item' . $activeClass . '">';
                    echo '<img src="' . $src . '" class="d-block w-100" alt="' . $alt . '">';
                    if (!empty($slide['caption'])) {
                        echo '<div class="carousel-caption d-none d-md-block">' . htmlspecialchars($slide['caption'], ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                if (count($slides) > 1) {
                    echo '<div class="carousel-indicators">';
                    foreach ($slides as $index => $slide) {
                        $activeClass = $index === 0 ? ' class="active"' : '';
                        echo '<button type="button" data-bs-target="#' . $carouselId . '" data-bs-slide-to="' . $index . '"' . $activeClass . ' aria-label="Slide ' . ($index + 1) . '"></button>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            } else {
                $src = htmlspecialchars($media['src'] ?? '', ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars($media['alt'] ?? 'Hero image', ENT_QUOTES, 'UTF-8');
                $style = $src !== '' ? 'style="background-image:url(' . $src . ');background-size:cover;background-position:center;"' : '';
                echo '<div class="h-100 w-100" ' . $style . ' aria-label="' . $alt . '"></div>';
            }
            echo '<div class="premium-hero__media-overlay" aria-hidden="true"></div>';
            echo '</div>';
        }
        echo '</div></section>';
    }
}
