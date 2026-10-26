<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
ae_send_security_headers();

if (!empty($_SESSION['admin_email'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$conf = ae_oauth_conf();
if ($conf !== null && isset($_GET['go'])) {
    header('Location: ' . ae_google_login_url($conf));
    exit;
}

$errors = [
    'expired' => 'That sign-in took too long or was opened in another tab. Try again.',
    'cancelled' => 'Google sign-in was cancelled.',
    'google' => 'Google sign-in failed. Try again.',
    'not_allowed' => 'That Google account is not allowed to use this admin.',
    'breakglass' => 'That emergency link is invalid, used or expired.',
];
$error = $errors[$_GET['error'] ?? ''] ?? '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in | Agency Eagle Admin</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body class="admin-auth">
<main class="auth-card">
  <h1>Agency Eagle Admin</h1>
  <?php if ($error !== ''): ?><p class="alert" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($conf === null): ?>
    <p>Google sign-in is not set up yet. Add the GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and ADMIN_EMAILS secrets in GitHub and redeploy.</p>
  <?php else: ?>
    <p><a class="button" href="/admin/?go=1">Sign in with Google</a></p>
  <?php endif; ?>
</main>
</body>
</html>
