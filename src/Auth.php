<?php
declare(strict_types=1);

namespace Example;

final class Auth
{
    /** Comprueba si la sesión contiene un identificador de usuario de tipo entero. */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
    }

    /** Protege una página: envía al login y detiene la ejecución si no hay sesión autenticada. */
    public static function requireUser(): void
    {
        if (!self::check())
            \redirect('login.php');
    }

    /** Guarda en la sesión los datos del usuario después de validar su identidad. */
    public static function login(array $user): void
    {
        // Cambia el identificador para evitar reutilizar la sesión anterior al acceso.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = (string) $user['name'];
        $_SESSION['user_email'] = (string) $user['email'];
        $_SESSION['user_picture'] = (string) ($user['picture_url'] ?? '');
        // El formulario de salida usa este token para comprobar que pertenece a la sesión.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /** Vacía los datos, caduca la cookie y destruye la sesión del servidor. */
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
