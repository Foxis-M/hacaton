<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/translations.php';

// Set language to Russian
$_SESSION['language'] = 'ru';

// Perform logout
logoutUser();

// Redirect to login page with logout message
header('Location: /login.php?loggedout=1');
exit;
