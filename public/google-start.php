<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (Example\Auth::check()) redirect('dashboard.php');

try {
    $client = Example\GoogleAuth::client();
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_started_at'] = time();
    $_SESSION['oauth_code_verifier'] = $client->getOAuth2Service()->generateCodeVerifier();
    $client->setState($state);
    header('Location: ' . $client->createAuthUrl());
    exit;
} catch (Throwable $e) {
    error_log('OAuth start: ' . $e->getMessage());
    redirect('login.php?error=' . urlencode('No se pudo iniciar la autenticación.'));
}
