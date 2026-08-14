<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
\App\Auth::logout();
header('Location: /login.php');
exit;
