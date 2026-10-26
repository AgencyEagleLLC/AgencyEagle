<?php
// Usage (on the server, over SSH or cPanel Terminal):
//   php tools/create-admin.php you@agencyeagle.com
// Prompts for a password, creates or resets the admin, and prints the
// authenticator setup key. Add it in Google Authenticator: + > Enter a setup key.
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    fwrite(STDERR, "Usage: php tools/create-admin.php email\n");
    exit(1);
}
$email = strtolower(trim($argv[1]));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Not a valid email.\n");
    exit(1);
}

fwrite(STDOUT, 'Password (12+ characters): ');
system('stty -echo');
$password = trim((string)fgets(STDIN));
system('stty echo');
fwrite(STDOUT, "\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$secret = totp_new_secret();
$users = ae_users();
$users[$email] = [
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'totp_secret' => $secret,
    'totp_last_step' => -1,
    'fails' => 0,
    'created' => gmdate('c'),
];
ae_save_users($users);

echo "Admin saved: $email\n";
echo "Authenticator setup key: " . trim(chunk_split($secret, 4, ' ')) . "\n";
echo "Or paste this link into a QR generator you trust:\n" . totp_uri($secret, $email) . "\n";
