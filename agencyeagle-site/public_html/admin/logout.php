<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
$_SESSION = [];
session_destroy();
header('Location: /admin/');
