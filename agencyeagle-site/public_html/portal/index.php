<?php
// Client portal: Google sign-in, then the site(s) this account may manage.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/clients.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

ae_session_start();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$conf = ae_oauth_conf();
$wanted = strtolower(preg_replace('/[^a-z0-9.-]/i', '', (string)($_GET['site'] ?? '')));
if ($wanted !== '') {
    $_SESSION['portal_site'] = $wanted;
}

$email = $_SESSION['portal_email'] ?? null;
if ($email === null && $conf !== null && isset($_GET['go'])) {
    header('Location: ' . ae_google_login_url($conf, 'portal'));
    exit;
}
if ($email !== null) {
    $sites = ae_portal_sites_for($email);
    $pick = $_SESSION['portal_site'] ?? '';
    if (in_array($pick, $sites, true) || count($sites) === 1) {
        unset($_SESSION['portal_site']);
        header('Location: /portal/site.php?d=' . rawurlencode(in_array($pick, $sites, true) ? $pick : $sites[0]));
        exit;
    }
}
$errors = [
    'expired' => 'That sign-in took too long. Try again.',
    'cancelled' => 'Google sign-in was cancelled.',
    'google' => 'Google sign-in failed. Try again.',
    'not_allowed' => 'That Google account isn\'t set up for a site here. Use the Google account you gave Agency Eagle, or call (865) 300-5397.',
];
$error = $errors[$_GET['error'] ?? ''] ?? '';

ae_portal_head('Your website', $email, $wanted ?: null);
if ($email === null): ?>
  <div class="portal-signin console-card console-pad">
    <h1>Manage your website</h1>
    <p>Sign in with the Google account you gave Agency Eagle to see your site's SEO score, backups and the brief for your AI assistant.</p>
<?php if ($error): ?>
    <p class="alert" role="alert"><?= $h($error) ?></p>
<?php endif; ?>
<?php if ($conf): ?>
    <a class="button big block" href="/portal/?go=1">Sign in with Google</a>
<?php else: ?>
    <p class="alert">Sign-in isn't available right now. Call Agency Eagle at (865) 300-5397.</p>
<?php endif; ?>
    <p class="muted small-print">Hosted by <a href="https://agencyeagle.com/">Agency Eagle</a>. Need help? help@agencyeagle.com · (865) 300-5397</p>
  </div>
<?php else: ?>
  <h1>Your websites</h1>
  <div class="console-pair">
  <?php foreach ($sites as $d): ?>
    <a class="console-card console-pad portal-site" href="/portal/site.php?d=<?= $h(rawurlencode($d)) ?>"><strong><?= $h($d) ?></strong><br><span class="muted">Open the site admin</span></a>
  <?php endforeach; ?>
  </div>
<?php endif;
ae_admin_foot();
