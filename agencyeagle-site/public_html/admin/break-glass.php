<?php
// Emergency sign-in. The link is created on the server over SSH:
//   php ~/public_html/_private/tools/break-glass.php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
ae_send_security_headers();

$ok = ae_use_breakglass((string)($_GET['t'] ?? ''));
header('Location: ' . ($ok ? '/admin/dashboard.php' : '/admin/?error=breakglass'));
