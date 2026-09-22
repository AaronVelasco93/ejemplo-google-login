<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

// La entrada principal elige el perfil o el login según el estado de la sesión.
redirect(Example\Auth::check() ? 'dashboard.php' : 'login.php');
