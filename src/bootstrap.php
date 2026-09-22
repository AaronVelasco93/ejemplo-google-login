<?php
declare(strict_types=1);

use Dotenv\Dotenv;

// Inicialización compartida por las páginas: dependencias, entorno, sesión y funciones auxiliares.
$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Faltan dependencias. Ejecuta composer install.');
}

require_once $autoload;
// Carga .env si existe, sin sobrescribir variables de entorno ya definidas.
Dotenv::createImmutable($root)->safeLoad();

/** Lee una variable de entorno, recorta sus espacios y usa el valor predeterminado si falta o está vacía. */
function env_value(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? trim($value) : $default;
}

/** Detecta HTTPS según el servidor o la cabecera X-Forwarded-Proto que recibe la aplicación. */
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

// Configura la cookie antes de iniciar la sesión; SameSite=Lax permite el regreso desde Google.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(env_value('SESSION_NAME', 'google_login_example'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Evita almacenar páginas en caché y agrega restricciones de seguridad para el navegador.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

/** Redirige a una ruta de la aplicación y termina la petición; deduce la URL base si falta APP_URL. */
function redirect(string $path): never
{
    $base = rtrim(env_value('APP_URL'), '/');
    if ($base === '') {
        $scheme = is_https() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $publicPath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $base = $scheme . '://' . $host . $publicPath;
    }
    header('Location: ' . $base . '/' . ltrim($path, '/'));
    exit;
}

/** Escapa texto para mostrarlo en HTML o en atributos entre comillas sin interpretarlo como código. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
