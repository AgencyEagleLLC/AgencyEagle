<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

$home = '/admin/';
try {
    ae_session_start();
    ae_send_security_headers();
    $conf = ae_oauth_conf();
    $home = ($_SESSION['oauth_purpose'] ?? 'admin') === 'portal' ? '/portal/' : '/admin/';
    $result = $conf === null ? 'google' : ae_google_callback($conf, $_GET);
} catch (Throwable $e) {
    // Keep a record (PHP error logging is off on this account) and send them back to try again
    // instead of showing a bare 500.
    @file_put_contents(
        __DIR__ . '/../_private/data/auth-errors.log',
        gmdate('c') . ' ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . "\n",
        FILE_APPEND | LOCK_EX
    );
    $result = 'google';
}
if ($result === 'portal') {
    header('Location: /portal/');
} elseif ($result === 'ok') {
    header('Location: /admin/dashboard.php');
} else {
    header('Location: ' . $home . '?error=' . urlencode($result));
}
