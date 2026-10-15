<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (currentUser()) {
    logActivity((int) currentUser()['id'], 'logout', 'User signed out.');
}

session_unset();
session_destroy();

redirect('/login.php');
