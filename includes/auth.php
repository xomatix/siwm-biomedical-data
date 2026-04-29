<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function getClientIP(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim(reset($parts));
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function loginUser(string $email, string $password): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    $success = false;

    if ($user && verifyPassword($password, $user['password_hash'])) {
        $success = true;
        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);
    }

    $logStmt = $pdo->prepare('INSERT INTO login_logs (email, ip_address, success) VALUES (:email, :ip, :success)');
    $logStmt->execute([
        'email' => $email,
        'ip' => getClientIP(),
        'success' => $success ? 1 : 0,
    ]);

    return $success;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
