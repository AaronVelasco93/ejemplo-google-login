<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

/** Regresa al formulario con un mensaje de error codificado para la URL y detiene la petición. */
function fail_login(string $message): never
{
    redirect('login.php?error=' . urlencode($message));
}

// Recupera los datos del intento de acceso y los elimina de la sesión para consumirlos una sola vez.
$expectedState = (string) ($_SESSION['oauth_state'] ?? '');
$startedAt = (int) ($_SESSION['oauth_started_at'] ?? 0);
$verifier = (string) ($_SESSION['oauth_code_verifier'] ?? '');
unset($_SESSION['oauth_state'], $_SESSION['oauth_started_at'], $_SESSION['oauth_code_verifier']);

// Solo acepta una respuesta ligada a esta sesión y a un intento iniciado en los últimos diez minutos.
if (
    $expectedState === '' || $verifier === '' || $startedAt < time() - 600
    || !hash_equals($expectedState, (string) ($_GET['state'] ?? ''))
) {
    fail_login('La solicitud expiró o no es válida. Intenta de nuevo.');
}
// Google puede devolver una cancelación o un error en lugar de un código de autorización.
if (isset($_GET['error']))
    fail_login('Google canceló o rechazó el acceso.');
$code = (string) ($_GET['code'] ?? '');
if ($code === '')
    fail_login('Google no devolvió el código de autorización.');

try {
    $client = Example\GoogleAuth::client();
    // Intercambia el código recibido usando el secreto PKCE guardado al iniciar el acceso.
    $token = $client->fetchAccessTokenWithAuthCode($code, $verifier);
    if (!is_array($token) || !empty($token['error']) || empty($token['id_token'])) {
        throw new RuntimeException('No se obtuvo un token de identidad.');
    }

    // La biblioteca verifica el ID Token; después se exige una identidad con correo verificado.
    $identity = $client->verifyIdToken((string) $token['id_token']);
    if (
        !is_array($identity) || empty($identity['sub']) || empty($identity['email'])
        || ($identity['email_verified'] ?? false) !== true
    ) {
        throw new RuntimeException('La identidad de Google no es válida.');
    }

    // Si se configuró un dominio institucional, se compara con el dato hd del token verificado.
    $allowedDomain = strtolower(env_value('GOOGLE_ALLOWED_DOMAIN'));
    if ($allowedDomain !== '' && strtolower((string) ($identity['hd'] ?? '')) !== $allowedDomain) {
        fail_login('La cuenta no pertenece al dominio institucional autorizado.');
    }

    $pdo = Example\Database::connection();
    $googleSub = (string) $identity['sub'];
    $name = trim((string) ($identity['name'] ?? 'Usuario de Google'));
    $email = strtolower(trim((string) $identity['email']));
    $picture = filter_var((string) ($identity['picture'] ?? ''), FILTER_VALIDATE_URL) ?: null;

    // Busca por el identificador estable de Google, porque el correo del usuario puede cambiar.
    $stmt = $pdo->prepare('SELECT id FROM users WHERE google_sub = :google_sub LIMIT 1');
    $stmt->execute([':google_sub' => $googleSub]);
    $userId = $stmt->fetchColumn();

    // Registra la primera visita o actualiza el perfil existente mediante consultas preparadas.
    if ($userId === false) {
        $insert = $pdo->prepare('INSERT INTO users (google_sub, name, email, picture_url, last_login_at) VALUES (:google_sub, :name, :email, :picture, NOW())');
        $insert->execute([':google_sub' => $googleSub, ':name' => $name, ':email' => $email, ':picture' => $picture]);
        $userId = (int) $pdo->lastInsertId();
    } else {
        $update = $pdo->prepare('UPDATE users SET name = :name, email = :email, picture_url = :picture, last_login_at = NOW() WHERE id = :id');
        $update->execute([':name' => $name, ':email' => $email, ':picture' => $picture, ':id' => $userId]);
    }

    // Crea la sesión autenticada solo después de verificar la identidad y guardar el usuario.
    $user = ['id' => (int) $userId, 'name' => $name, 'email' => $email, 'picture_url' => $picture];
    Example\Auth::login($user);
    redirect('dashboard.php');
} catch (PDOException $e) {
    // Registra el detalle técnico de la base de datos y muestra al usuario un mensaje controlado.
    error_log('OAuth database: ' . $e->getMessage());
    fail_login($e->getCode() === '23000' ? 'El correo ya está asociado a otra identidad.' : 'No se pudo guardar el usuario.');
} catch (Throwable $e) {
    // Maneja los demás fallos de autenticación sin mostrar detalles internos en la página.
    error_log('OAuth callback: ' . $e->getMessage());
    fail_login('No se pudo validar la cuenta de Google.');
}
