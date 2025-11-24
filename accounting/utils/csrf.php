<?php

/**
 * CSRF Utilities for Accounting Module
 * Provides simple helpers to generate and validate CSRF tokens.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure a CSRF token exists in the session.
 */
function csrf_ensure_token(): void
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Get the current CSRF token (generates one if missing).
 */
function csrf_token(): string
{
    csrf_ensure_token();
    return $_SESSION['csrf_token'];
}

/**
 * Echo a hidden field containing the CSRF token.
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate a CSRF token from POST against the session. Throws on failure.
 *
 * @throws Exception when token is missing or invalid
 */
function csrf_verify_post_or_throw(): void
{
    csrf_ensure_token();
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        throw new Exception('Invalid security token. Please refresh the page and try again.');
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Backwards-compatible CSRF validator used by legacy forms.
     */
    function validateCSRFToken($token): bool
    {
        csrf_ensure_token();
        return !empty($token) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}
