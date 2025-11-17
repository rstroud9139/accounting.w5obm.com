<?php

/**
 * Session Manager (Accounting module wrapper)
 * Thin, reliable wrapper around the global SessionManager utility.
 * Ensures consistent session start, auth checks, and gentle redirects.
 */

// Core dependencies
require_once __DIR__ . '/../../include/session_manager.php';
require_once __DIR__ . '/../lib/helpers.php';

/**
 * Ensure a PHP session is active and configured.
 */
function start_session_safe(): void
{
    $sm = SessionManager::getInstance();
    $sm->startSession();
}

/**
 * Return true if the current user is authenticated.
 */
function is_logged_in(): bool
{
    return SessionManager::getInstance()->isAuthenticated();
}

/**
 * Validate session for accounting pages. Redirects to login if not authenticated.
 * Sets a toast message when redirecting.
 */
function validate_session(): void
{
    $sm = SessionManager::getInstance();
    $sm->startSession();

    if (!$sm->isAuthenticated()) {
        if (function_exists('setToastMessage')) {
            setToastMessage('warning', 'Login Required', 'Please log in to continue.', 'club-logo');
        } else {
            // Fallback session-based toast if helper unavailable
            $_SESSION['login_required_toast'] = [
                'type' => 'warning',
                'title' => 'Login Required',
                'message' => 'Please log in to continue.',
                'theme' => 'club-logo'
            ];
        }
        header('Location: /authentication/login.php');
        exit();
    }

    // Expose $user_id for pages that expect it
    $GLOBALS['user_id'] = function_exists('getCurrentUserId')
        ? getCurrentUserId()
        : $sm->getCurrentUserId();
}

/**
 * Optional helper to enforce accounting permissions.
 * Allows Super Admin/Admin or users with accounting_view/manage permissions.
 */
function require_accounting_access(): void
{
    validate_session();

    $uid = $GLOBALS['user_id'] ?? (function_exists('getCurrentUserId') ? getCurrentUserId() : null);
    $is_allowed = false;

    if (function_exists('isSuperAdmin') && isSuperAdmin($uid)) {
        $is_allowed = true;
    } elseif (function_exists('isAdmin') && isAdmin($uid)) {
        $is_allowed = true;
    } elseif (function_exists('hasPermission')) {
        $is_allowed = hasPermission($uid, 'accounting_view') || hasPermission($uid, 'accounting_manage');
    }

    if (!$is_allowed) {
        if (function_exists('setToastMessage')) {
            setToastMessage('danger', 'Access Denied', 'You do not have permission to access the accounting module.', 'club-logo');
        }
        header('Location: /authentication/dashboard.php');
        exit();
    }
}

/**
 * Destroy the current session (logout helper).
 */
function end_session(): void
{
    SessionManager::getInstance()->destroySession();
}

/**
 * Update the last activity timestamp to keep the session alive.
 */
function update_session_activity(): void
{
    if (is_logged_in()) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check for inactivity timeout; destroy session and redirect if expired.
 *
 * @param int $timeout Seconds of inactivity (default: 30 minutes)
 */
function check_session_timeout(int $timeout = 1800): void
{
    $sm = SessionManager::getInstance();
    $sm->startSession();

    if (!$sm->isAuthenticated()) {
        return; // Not logged in; nothing to do here.
    }

    $last = $_SESSION['last_activity'] ?? ($_SESSION['login_time'] ?? time());
    if ((time() - (int)$last) > $timeout) {
        // Expired session
        $sm->destroySession();
        if (function_exists('setToastMessage')) {
            setToastMessage('info', 'Session Expired', 'Please log in again.', 'club-logo');
        }
        header('Location: /authentication/login.php?timeout=1');
        exit();
    }

    update_session_activity();
}

// Auto-start and apply default timeout guard on include
start_session_safe();
check_session_timeout();
