# W5OBM Theme System

All pages inherit the active site or user theme via `include/header.php`. The header loads Bootstrap (or a Bootswatch variant) and emits CSS custom properties you can use anywhere.

## Global CSS variables

Available in every page that includes `include/header.php`:

- --theme-accent-primary: primary gradient/color accent
- --theme-accent-secondary: secondary gradient/color accent
- --theme-text-primary: primary text color for headers on gradients
- --theme-text-secondary: secondary/subtitle text color
- --w5obm-primary, --w5obm-secondary: mapped to the theme accents
- --primary-blue, --secondary-blue, --accent-gold: site settings fallbacks
- --hero-overlay-from, --hero-overlay-to: site hero overlay colors (dev)

Example usage:

```css
.card-header {
  background: linear-gradient(135deg, var(--theme-accent-primary) 0%, var(--theme-accent-secondary) 100%);
  color: var(--theme-text-primary);
}
```

## PHP include pattern

Make sure your page includes the site header before any output:

```php
<?php include __DIR__ . '/../include/header.php'; ?>
<?php include __DIR__ . '/../include/menu.php'; ?>
<!-- page content here -->
<?php include __DIR__ . '/../include/footer.php'; ?>
```

Pages using custom hero or header components should use theme variables (no hard-coded colors). Updated components include:

- include/page_header.php
- include/club_header.php
- include/admin_hero.php

## Advanced: Detecting missing header include

Use the provided script to report PHP files that do not include the header and therefore may miss theme variables:

- dev.w5obm.com/tools/report_theme_coverage.php

Run it from the web server (authenticated/admin only) to generate a report.
