<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
Example\Auth::requireUser();

$picture = filter_var((string)($_SESSION['user_picture'] ?? ''), FILTER_VALIDATE_URL) ?: '';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Perfil autenticado</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="center"><section class="card profile">
<?php if ($picture): ?><img src="<?= e($picture) ?>" alt="Foto de perfil" referrerpolicy="no-referrer"><?php else: ?><div class="avatar">U</div><?php endif; ?>
<span class="badge success">Sesión activa</span><h1><?= e((string)$_SESSION['user_name']) ?></h1><p><?= e((string)$_SESSION['user_email']) ?></p>
<p class="explanation">Estos datos fueron verificados por Google, guardados con PDO y copiados a la sesión.</p>
<form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e((string)$_SESSION['csrf_token']) ?>"><button class="button danger" type="submit">Cerrar sesión</button></form>
</section></main></body></html>
