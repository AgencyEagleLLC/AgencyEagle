<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
ae_send_security_headers();

$conf = ae_oauth_conf();
$home = ($_SESSION['oauth_purpose'] ?? 'admin') === 'portal' ? '/portal/' : '/admin/';
$result = $conf === null ? 'google' : ae_google_callback($conf, $_GET);
if ($result === 'portal') {
    header('Location: /portal/');
} elseif ($result === 'ok') {
    header('Location: /admin/dashboard.php');
} else {
    header('Location: ' . $home . '?error=' . urlencode($result));
}
