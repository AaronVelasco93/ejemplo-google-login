<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

redirect(Example\Auth::check() ? 'dashboard.php' : 'login.php');
