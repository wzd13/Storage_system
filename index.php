<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . appUrl('/dashboard.php'));
    exit;
}

header('Location: ' . appUrl('/login.php'));
exit;
