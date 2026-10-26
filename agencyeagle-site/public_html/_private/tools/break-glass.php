<?php
// Emergency admin sign-in for when Google sign-in is broken.
// Run on the server over SSH (key login):
//   php ~/public_html/_private/tools/break-glass.php
// Prints a link that signs you in once, within 10 minutes.
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$token = ae_b64url(random_bytes(32));
file_put_contents(AE_BREAKGLASS_FILE, json_encode([
    'hash' => hash('sha256', $token),
    'expires' => time() + AE_BREAKGLASS_SECONDS,
]), LOCK_EX);
chmod(AE_BREAKGLASS_FILE, 0600);
echo "Open within 10 minutes (works once):\nhttps://agencyeagle.com/admin/break-glass.php?t={$token}\n";
