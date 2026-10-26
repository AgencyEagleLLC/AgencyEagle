<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
ae_send_security_headers();

$conf = ae_oauth_conf();
$result = $conf === null ? 'google' : ae_google_callback($conf, $_GET);
header('Location: ' . ($result === 'ok' ? '/admin/dashboard.php' : '/admin/?error=' . urlencode($result)));
