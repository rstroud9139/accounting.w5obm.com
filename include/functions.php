<?php
// functions.php

/**
 * Outputs JavaScript code to display a toast notification.
 *
 * @param string $type    The type of toast ('success', 'info', 'warning', 'error').
 * @param string $title   The title of the toast.
 * @param string $message The message content of the toast.
 * @param string $theme   The theme of the toast ('standard', 'club-logo', etc.).
 * @param array  $actions Optional actions to include in the toast.
 */
function showToast($type, $title, $message, $theme = 'standard', $actions = [])
{
    // Escape parameters to prevent XSS
    $type = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $theme = htmlspecialchars($theme, ENT_QUOTES, 'UTF-8');
    $actions_json = json_encode($actions);

    // Output JavaScript code to call showToast() after the page has loaded
    echo "<script>
        window.addEventListener('load', function() {
            if (typeof showToast === 'function') {
                showToast('$type', '$title', '$message', '$theme', $actions_json);
            } else {
                console.error('showToast function is not defined.');
            }
        });
    </script>";
}

/**
 * Retrieve and clear toast messages from session.
 * Supports both single and multiple toasts.
 * @return array Array of toasts with keys type,title,message,theme,actions
 */
function getToastMessage()
{
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    $toasts = [];
    if (!empty($_SESSION['toast'])) {
        $t = $_SESSION['toast'];
        unset($_SESSION['toast']);
        // Normalize single toast to array
        if (isset($t['type'])) { $toasts[] = $t; }
        elseif (is_array($t)) { $toasts = $t; }
    }
    return $toasts;
}
