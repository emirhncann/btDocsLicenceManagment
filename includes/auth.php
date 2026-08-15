<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function requireLogin(): void
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function doLogin(string $username, string $password): bool
{
    $stmt = getDb()->prepare('SELECT * FROM AdminUsers WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        return true;
    }

    return false;
}

function doLogout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function currentAdminUsername(): string
{
    return (string) ($_SESSION['admin_username'] ?? '');
}
