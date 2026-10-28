<?php
// Set up (or reset) the backup login, password + authenticator, for the signed-in admin.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

$email = ae_require_admin();
ae_send_security_headers();

$error = '';
$saved = false;
$can_enroll = filter_var($email, FILTER_VALIDATE_EMAIL) !== false; // not for break-glass sessions

if ($can_enroll && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = (string)($_SESSION['enroll_secret'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (!ae_check_csrf() || $secret === '') {
        $error = 'Your session expired. Please try again.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif (($step = totp_verify($secret, (string)($_POST['code'] ?? ''))) === null) {
        $error = 'That code did not match. Check the key in your authenticator app and use the newest code.';
    } else {
        $users = ae_users();
        $users[$email] = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'totp_secret' => $secret,
            'totp_last_step' => $step,
            'fails' => 0,
            'created' => gmdate('c'),
        ];
        ae_save_users($users);
        unset($_SESSION['enroll_secret']);
        $saved = true;
    }
}
if ($can_enroll && !$saved && empty($_SESSION['enroll_secret'])) {
    $_SESSION['enroll_secret'] = totp_new_secret();
}
$has_backup = isset(ae_users()[$email]);
$csrf = ae_csrf_token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Backup login | Agency Eagle Admin</title>
<link rel="stylesheet" href="<?= ae_css_href() ?>">
</head>
<body class="admin">
<header class="admin-bar">
  <strong>Agency Eagle Admin</strong>
  <span><?= htmlspecialchars($email) ?> · <a href="/admin/dashboard.php">Dashboard</a> · <a href="/admin/logout.php">Sign out</a></span>
</header>
<main class="container">
  <h1>Backup login</h1>
<?php if (!$can_enroll): ?>
  <p>Sign in with Google to set up the backup login.</p>
<?php elseif ($saved): ?>
  <p class="notice">Saved. You can now sign in under "Other sign-in options" with your email, this password and an authenticator code.</p>
<?php else: ?>
  <p><?= $has_backup ? 'You already have a backup login. Saving this form replaces its password and authenticator key.' : 'Use this if Google sign-in is ever unavailable.' ?></p>
  <?php if ($error !== ''): ?><p class="alert" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <ol>
    <li>In Google Authenticator tap <strong>+</strong>, choose <strong>Enter a setup key</strong>, and enter:<br>
      Account: <?= htmlspecialchars($email) ?><br>
      Key: <code><?= htmlspecialchars(trim(chunk_split($_SESSION['enroll_secret'], 4, ' '))) ?></code></li>
    <li>Choose a password and enter the 6-digit code the app shows.</li>
  </ol>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <label for="password">Password (12+ characters)</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
    <label for="code">6-digit code</label>
    <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" autocomplete="one-time-code" required>
    <button type="submit">Save backup login</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
