<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(405);
    exit('Solicitud no válida.');
}

Example\Auth::logout();
redirect('login.php');
