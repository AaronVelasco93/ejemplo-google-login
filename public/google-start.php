<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// Una sesión ya autenticada puede entrar directamente al perfil.
if (Example\Auth::check())
    redirect('dashboard.php');

try {
    $client = Example\GoogleAuth::client();
    // state vincula la respuesta de Google con esta sesión y ayuda a impedir solicitudes falsificadas.
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_started_at'] = time();
    // PKCE guarda un secreto temporal; el cliente envía su desafío a Google al crear la URL.
    $_SESSION['oauth_code_verifier'] = $client->getOAuth2Service()->generateCodeVerifier();
    $client->setState($state);
    // Envía al usuario a Google para elegir su cuenta y autorizar el acceso.
    header('Location: ' . $client->createAuthUrl());
    exit;
} catch (Throwable $e) {
    error_log('OAuth start: ' . $e->getMessage());
    redirect('login.php?error=' . urlencode('No se pudo iniciar la autenticación.'));
}
