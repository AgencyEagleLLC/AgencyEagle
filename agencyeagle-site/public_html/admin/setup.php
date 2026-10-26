<?php
// One-time creation of the first admin, for servers without a terminal.
// Open only while no admin exists AND _private/data/setup.code is present
// (the deploy writes it from the ADMIN_SETUP_CODE GitHub secret). Deleted after use.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

const AE_SETUP_CODE_FILE = __DIR__ . '/../_private/data/setup.code';

ae_session_start();
ae_send_security_headers();

if (ae_users() !== [] || !is_file(AE_SETUP_CODE_FILE)) {
    http_response_code(404);
    exit('Not found');
}

$error = '';
$done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expected = trim((string)file_get_contents(AE_SETUP_CODE_FILE));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!ae_check_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($expected === '' || !hash_equals($expected, trim((string)($_POST['setup_code'] ?? '')))) {
        $error = 'Setup code is incorrect.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } else {
        $secret = totp_new_secret();
        ae_save_users([$email => [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'totp_secret' => $secret,
            'totp_last_step' => -1,
            'fails' => 0,
            'created' => gmdate('c'),
        ]]);
        unlink(AE_SETUP_CODE_FILE);
        $done = ['email' => $email, 'secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => totp_uri($secret, $email)];
    }
}
$csrf = ae_csrf_token();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Set up admin | Agency Eagle</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body class="admin-auth">
<main class="auth-card">
<?php if ($done): ?>
  <h1>Admin created</h1>
  <p>Open Google Authenticator, tap <strong>+</strong>, choose <strong>Enter a setup key</strong>, and enter:</p>
  <p><strong>Account:</strong> <?= htmlspecialchars($done['email']) ?><br><strong>Key:</strong> <code><?= htmlspecialchars($done['secret']) ?></code></p>
  <p>Save this key somewhere safe. It is shown only once.</p>
  <p><a href="/admin/">Go to sign in</a></p>
<?php else: ?>
  <h1>Set up your admin login</h1>
  <?php if ($error !== ''): ?><p class="alert" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <label for="setup_code">Setup code</label>
    <input id="setup_code" name="setup_code" required autocomplete="off">
    <label for="email">Your email</label>
    <input id="email" name="email" type="email" autocomplete="username" required>
    <label for="password">Choose a password (12+ characters)</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
    <button type="submit">Create admin</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
