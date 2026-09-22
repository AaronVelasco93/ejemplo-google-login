<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

function fail_login(string $message): never
{
    redirect('login.php?error=' . urlencode($message));
}

$expectedState = (string)($_SESSION['oauth_state'] ?? '');
$startedAt = (int)($_SESSION['oauth_started_at'] ?? 0);
$verifier = (string)($_SESSION['oauth_code_verifier'] ?? '');
unset($_SESSION['oauth_state'], $_SESSION['oauth_started_at'], $_SESSION['oauth_code_verifier']);

if ($expectedState === '' || $verifier === '' || $startedAt < time() - 600
    || !hash_equals($expectedState, (string)($_GET['state'] ?? ''))) {
    fail_login('La solicitud expiró o no es válida. Intenta de nuevo.');
}
if (isset($_GET['error'])) fail_login('Google canceló o rechazó el acceso.');
$code = (string)($_GET['code'] ?? '');
if ($code === '') fail_login('Google no devolvió el código de autorización.');

try {
    $client = Example\GoogleAuth::client();
    $token = $client->fetchAccessTokenWithAuthCode($code, $verifier);
    if (!is_array($token) || !empty($token['error']) || empty($token['id_token'])) {
        throw new RuntimeException('No se obtuvo un token de identidad.');
    }

    $identity = $client->verifyIdToken((string)$token['id_token']);
    if (!is_array($identity) || empty($identity['sub']) || empty($identity['email'])
        || ($identity['email_verified'] ?? false) !== true) {
        throw new RuntimeException('La identidad de Google no es válida.');
    }

    $allowedDomain = strtolower(env_value('GOOGLE_ALLOWED_DOMAIN'));
    if ($allowedDomain !== '' && strtolower((string)($identity['hd'] ?? '')) !== $allowedDomain) {
        fail_login('La cuenta no pertenece al dominio institucional autorizado.');
    }

    $pdo = Example\Database::connection();
    $googleSub = (string)$identity['sub'];
    $name = trim((string)($identity['name'] ?? 'Usuario de Google'));
    $email = strtolower(trim((string)$identity['email']));
    $picture = filter_var((string)($identity['picture'] ?? ''), FILTER_VALIDATE_URL) ?: null;

    $stmt = $pdo->prepare('SELECT id FROM users WHERE google_sub = :google_sub LIMIT 1');
    $stmt->execute([':google_sub' => $googleSub]);
    $userId = $stmt->fetchColumn();

    if ($userId === false) {
        $insert = $pdo->prepare('INSERT INTO users (google_sub, name, email, picture_url, last_login_at) VALUES (:google_sub, :name, :email, :picture, NOW())');
        $insert->execute([':google_sub' => $googleSub, ':name' => $name, ':email' => $email, ':picture' => $picture]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $update = $pdo->prepare('UPDATE users SET name = :name, email = :email, picture_url = :picture, last_login_at = NOW() WHERE id = :id');
        $update->execute([':name' => $name, ':email' => $email, ':picture' => $picture, ':id' => $userId]);
    }

    $user = ['id' => (int)$userId, 'name' => $name, 'email' => $email, 'picture_url' => $picture];
    Example\Auth::login($user);
    redirect('dashboard.php');
} catch (PDOException $e) {
    error_log('OAuth database: ' . $e->getMessage());
    fail_login($e->getCode() === '23000' ? 'El correo ya está asociado a otra identidad.' : 'No se pudo guardar el usuario.');
} catch (Throwable $e) {
    error_log('OAuth callback: ' . $e->getMessage());
    fail_login('No se pudo validar la cuenta de Google.');
}
