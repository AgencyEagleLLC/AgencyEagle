<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_session_start();
ae_send_security_headers();

if (!empty($_SESSION['admin_email'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = '';
$stage = empty($_SESSION['pending_email']) ? 'password' : 'code';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ae_check_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($stage === 'password') {
        $result = ae_check_password((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($result === 'ok') {
            $stage = 'code';
        } else {
            $error = $result === 'locked' ? 'Too many attempts. Try again in 15 minutes.' : 'Email or password is incorrect.';
        }
    } else {
        $result = ae_check_totp((string)($_POST['code'] ?? ''));
        if ($result === 'ok') {
            header('Location: /admin/dashboard.php');
            exit;
        }
        if ($result === 'expired') {
            $stage = 'password';
            $error = 'That took too long. Sign in again.';
        } else {
            $error = $result === 'locked' ? 'Too many attempts. Try again in 15 minutes.' : 'That code did not match. Use the newest code in your authenticator app.';
        }
    }
}
$csrf = ae_csrf_token();
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
  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <?php if ($stage === 'password'): ?>
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required autofocus>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Continue</button>
    <?php else: ?>
      <label for="code">6-digit code from Google Authenticator</label>
      <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" autocomplete="one-time-code" required autofocus>
      <button type="submit">Sign in</button>
    <?php endif; ?>
  </form>
</main>
</body>
</html>
