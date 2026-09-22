<?php
declare(strict_types=1);

namespace Example;

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    public static function requireUser(): void
    {
        if (!self::check()) \redirect('login.php');
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = (string)$user['name'];
        $_SESSION['user_email'] = (string)$user['email'];
        $_SESSION['user_picture'] = (string)($user['picture_url'] ?? '');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
