<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
unset($_SESSION['admin_email'], $_SESSION['csrf']);
if (empty($_SESSION['portal_email'])) {
    $_SESSION = [];
    session_destroy();
}
header('Location: /admin/');
