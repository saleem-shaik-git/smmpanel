<?php

declare(strict_types=1);

namespace App;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('smm_session');

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $https,
            'cookie_path' => '/',
        ]);
    }

    public static function login(array $user): void
    {
        self::start();

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) ($user['role'] ?? 'user');
    }

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    public static function id(): ?int
    {
        self::start();

        return isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : null;
    }

    public static function requireLogin(): int
    {
        $id = self::id();

        if ($id === null) {
            header('Location: /login.php', true, 302);
            exit;
        }

        return $id;
    }

    public static function csrfToken(): string
    {
        self::start();

        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): void
    {
        self::start();

        if (
            !$token ||
            !isset($_SESSION['_csrf']) ||
            !hash_equals($_SESSION['_csrf'], $token)
        ) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}