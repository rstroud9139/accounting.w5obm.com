<?php

if (defined('W5OBM_REMEMBER_ME_LOADED')) {
    return;
}
define('W5OBM_REMEMBER_ME_LOADED', true);

require_once __DIR__ . '/dbconn.php';
require_once __DIR__ . '/session_manager.php';

const W5OBM_REMEMBER_COOKIE = 'w5obm_remember_me';
const W5OBM_REMEMBER_TTL = 2592000; // 30 days in seconds
const W5OBM_REMEMBER_MAX_DEVICES = 5;

function rememberMeCookieDomain(): string
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if ($host && preg_match('/(^|\.)w5obm\.com$/', $host)) {
        return '.w5obm.com';
    }
    return $host;
}

function rememberMeIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function rememberMeCookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => rememberMeCookieDomain(),
        'secure' => rememberMeIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function rememberMeCleanupExpiredTokens(): void
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return;
    }
    $conn->query("DELETE FROM auth_remember_tokens WHERE expires_at < NOW()");
}

function rememberMeSetCookie(string $value, int $expires): void
{
    setcookie(W5OBM_REMEMBER_COOKIE, $value, rememberMeCookieOptions($expires));
    $_COOKIE[W5OBM_REMEMBER_COOKIE] = $value;
}

function rememberMeClearCookie(): void
{
    setcookie(W5OBM_REMEMBER_COOKIE, '', rememberMeCookieOptions(time() - 3600));
    unset($_COOKIE[W5OBM_REMEMBER_COOKIE]);
}

function rememberMeGetCookieParts(): ?array
{
    $raw = $_COOKIE[W5OBM_REMEMBER_COOKIE] ?? '';
    if (!$raw || strpos($raw, ':') === false) {
        return null;
    }
    [$selector, $token] = explode(':', $raw, 2);
    $selector = trim($selector);
    $token = trim($token);
    if ($selector === '' || $token === '') {
        return null;
    }
    return [$selector, $token];
}

function rememberMeRevokeSelector(string $selector): void
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return;
    }
    $stmt = $conn->prepare('DELETE FROM auth_remember_tokens WHERE selector = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $stmt->close();
    }
}

function rememberMePruneUserTokens(int $userId): void
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return;
    }
    $stmt = $conn->prepare('SELECT id FROM auth_remember_tokens WHERE user_id = ? ORDER BY updated_at DESC');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }
    $stmt->close();
    if (count($ids) <= W5OBM_REMEMBER_MAX_DEVICES) {
        return;
    }
    $idsToDelete = array_slice($ids, W5OBM_REMEMBER_MAX_DEVICES);
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $types = str_repeat('i', count($idsToDelete));
    $stmt = $conn->prepare("DELETE FROM auth_remember_tokens WHERE id IN ($placeholders)");
    if ($stmt) {
        $stmt->bind_param($types, ...$idsToDelete);
        $stmt->execute();
        $stmt->close();
    }
}

function rememberMeIssueToken(int $userId): void
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return;
    }
    rememberMeCleanupExpiredTokens();
    $selector = bin2hex(random_bytes(9));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $expiresAt = time() + W5OBM_REMEMBER_TTL;

    $stmt = $conn->prepare('INSERT INTO auth_remember_tokens (user_id, selector, token_hash, user_agent, ip_address, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW(), NOW())');
    if ($stmt) {
        $stmt->bind_param('issssi', $userId, $selector, $tokenHash, $userAgent, $ip, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    rememberMePruneUserTokens($userId);
    rememberMeSetCookie($selector . ':' . $token, $expiresAt);
}

function rememberMeRotateToken(string $selector, int $userId): void
{
    global $conn;
    if (!$conn instanceof mysqli) {
        return;
    }
    $newToken = bin2hex(random_bytes(32));
    $hash = hash('sha256', $newToken);
    $expiresAt = time() + W5OBM_REMEMBER_TTL;
    $stmt = $conn->prepare('UPDATE auth_remember_tokens SET token_hash = ?, expires_at = FROM_UNIXTIME(?), updated_at = NOW(), user_agent = ?, ip_address = ? WHERE selector = ? AND user_id = ?');
    if ($stmt) {
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt->bind_param('sisssi', $hash, $expiresAt, $ua, $ip, $selector, $userId);
        $stmt->execute();
        $stmt->close();
        rememberMeSetCookie($selector . ':' . $newToken, $expiresAt);
    }
}

function rememberMeHandleLoginSuccess(int $userId, bool $remember): void
{
    if ($remember) {
        rememberMeIssueToken($userId);
    } else {
        rememberMeHandleLogout(null);
    }
}

function rememberMeHandleLogout(?int $userId): void
{
    $parts = rememberMeGetCookieParts();
    if ($parts) {
        rememberMeRevokeSelector($parts[0]);
    } elseif ($userId) {
        // optional: remove any expired tokens lazily
        rememberMeCleanupExpiredTokens();
    }
    rememberMeClearCookie();
}

function rememberMeAutoLogin(): bool
{
    global $conn;
    $parts = rememberMeGetCookieParts();
    if (!$parts || !$conn instanceof mysqli) {
        return false;
    }
    [$selector, $token] = $parts;
    $stmt = $conn->prepare('SELECT user_id, token_hash, expires_at FROM auth_remember_tokens WHERE selector = ? LIMIT 1');
    if (!$stmt) {
        rememberMeClearCookie();
        return false;
    }
    $stmt->bind_param('s', $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        rememberMeClearCookie();
        return false;
    }
    if (strtotime($row['expires_at']) < time()) {
        rememberMeRevokeSelector($selector);
        rememberMeClearCookie();
        return false;
    }
    $incomingHash = hash('sha256', $token);
    if (!hash_equals($row['token_hash'], $incomingHash)) {
        rememberMeRevokeSelector($selector);
        rememberMeClearCookie();
        return false;
    }

    $sessionManager = SessionManager::getInstance();
    if (method_exists($sessionManager, 'setDatabaseConnection')) {
        $sessionManager->setDatabaseConnection($conn);
    }
    if (!$sessionManager->loginUser((int)$row['user_id'], true)) {
        rememberMeRevokeSelector($selector);
        rememberMeClearCookie();
        return false;
    }

    rememberMeRotateToken($selector, (int)$row['user_id']);
    return true;
}
