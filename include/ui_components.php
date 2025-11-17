<?php
// UI Components helpers

if (!function_exists('render_help_alert')) {
    /**
     * Render a standard help/info alert per Modern Website Guidelines
     * @param string $html Body HTML (already escaped as needed)
     */
    function render_help_alert($html)
    {
        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . $html . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}
