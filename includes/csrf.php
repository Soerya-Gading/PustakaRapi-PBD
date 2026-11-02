<?php
declare(strict_types=1);

/**
 * PustakaRapi — CSRF Protection Helper
 */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(?string $token = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }
    $provided = $token ?? (string)($_POST['csrf'] ?? '');
    $stored   = (string)($_SESSION['csrf_token'] ?? '');

    if ($provided === '' || $stored === '') {
        return false;
    }
    return hash_equals($stored, $provided);
}
