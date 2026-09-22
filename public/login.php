<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (Example\Auth::check()) redirect('dashboard.php');
$error = (string)($_GET['error'] ?? '');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso con Google</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="center"><section class="card"><span class="badge">OAuth 2.0</span><h1>Bienvenido</h1><p>Este ejemplo inicia sesión exclusivamente mediante una cuenta de Google.</p>
<?php if ($error !== ''): ?><div class="error" role="alert"><?= e($error) ?></div><?php endif; ?>
<a class="button" href="google-start.php"><span>G</span> Continuar con Google</a><small>Solo se solicitan nombre, correo y foto de perfil.</small></section></main></body></html>
