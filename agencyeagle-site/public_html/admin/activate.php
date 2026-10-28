<?php
// Activate a new site: cPanel account, splash page and AutoSSL in one go.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$packages = ae_whm_packages();
$in = [
    'domain' => strtolower(trim((string)($_POST['domain'] ?? ''))),
    'business' => trim((string)($_POST['business'] ?? '')),
    'contact' => strtolower(trim((string)($_POST['contact'] ?? ''))),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'pkg' => (string)($_POST['pkg'] ?? ''),
    'username' => strtolower(trim((string)($_POST['username'] ?? ''))),
];
$in['domain'] = preg_replace('#^(https?://)?(www\.)?#', '', rtrim($in['domain'], '/'));
$error = null;
$steps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ae_check_csrf()) {
        $error = 'Your session expired. Try again.';
    } elseif (!ae_valid_domain($in['domain'])) {
        $error = 'Enter a domain like newclient.com.';
    } elseif ($in['business'] === '') {
        $error = 'Enter the business name.';
    } elseif (!filter_var($in['contact'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the client\'s email address.';
    } elseif ($packages && !in_array($in['pkg'], $packages, true)) {
        $error = 'Pick a package.';
    } else {
        $user = $in['username'] !== '' ? $in['username'] : ae_username_for($in['domain']);
        if (!preg_match('/^[a-z][a-z0-9]{0,15}$/', $user)) {
            $error = 'The cPanel username must start with a letter and use up to 16 lowercase letters and digits.';
        } else {
            $pw = ae_random_password();
            try {
                $args = ['username' => $user, 'domain' => $in['domain'], 'contactemail' => $in['contact'], 'password' => $pw];
                if ($in['pkg'] !== '') {
                    $args['plan'] = $in['pkg'];
                }
                ae_whm_act($email, 'create_account', $user . ' (' . $in['domain'] . ')', 'createacct', $args);
                $steps[] = ['ok', 'Created cPanel account ' . $user . ' for ' . $in['domain'] . '.'];
                try {
                    ae_whm_save_file($email, $user, 'public_html', 'index.html', ae_splash_html($in['business'], $in['domain'], $in['contact'], $in['phone']));
                    $steps[] = ['ok', 'Published the "coming soon" splash page.'];
                } catch (RuntimeException $e) {
                    $steps[] = ['warn', 'Splash page not published: ' . $e->getMessage()];
                }
                try {
                    ae_whm_act($email, 'autossl', $user, 'start_autossl_check_for_one_user', ['username' => $user]);
                    $steps[] = ['ok', 'Started AutoSSL. The certificate is issued once the domain points here.'];
                } catch (RuntimeException $e) {
                    $steps[] = ['warn', 'AutoSSL not started: ' . $e->getMessage() . ' Run it from the site page after DNS is set.'];
                }
            } catch (RuntimeException $e) {
                $error = 'WHM didn\'t create the account: ' . $e->getMessage();
                $pw = null;
            }
        }
    }
}

$csrf = ae_csrf_token();
ae_admin_head('Activate a new site', $email);
?>
  <p><a href="/admin/sites.php">← All sites</a></p>
  <h1>Activate a new site</h1>
<?php if ($steps): ?>
  <div class="notice" role="status">
    <ul class="steps">
    <?php foreach ($steps as [$tone, $text]): ?>
      <li><span class="tag tag-<?= $tone ?>"><?= $tone === 'ok' ? 'Done' : 'Check' ?></span> <?= $h($text) ?></li>
    <?php endforeach; ?>
    </ul>
    <p><strong>cPanel password</strong> (copy it now; it isn't shown again or stored): <code class="secret"><?= $h($pw) ?></code></p>
  </div>
  <h2>Point the domain</h2>
  <p>Set these at the domain's DNS host (GoDaddy, Cloudflare and so on), then run AutoSSL from the site page:</p>
  <pre class="dns">A      @     <?= $h(AE_SERVER_IP) ?>

CNAME  www   <?= $h($in['domain']) ?></pre>
  <p><a class="button" href="/admin/site.php?user=<?= $h(rawurlencode($user)) ?>">Manage <?= $h($in['domain']) ?></a></p>
<?php else: ?>
  <p>Creates the hosting account, publishes a "coming soon" page and starts SSL. The client's /admin, Site Brief and backups come in the next build.</p>
<?php if ($error): ?>
  <p class="alert" role="alert"><?= $h($error) ?></p>
<?php endif; ?>
  <form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <label for="domain">Domain</label>
    <input id="domain" name="domain" required placeholder="newclient.com" value="<?= $h($in['domain']) ?>">
    <label for="business">Business name</label>
    <input id="business" name="business" required value="<?= $h($in['business']) ?>">
    <label for="contact">Client's email</label>
    <input id="contact" name="contact" type="email" required value="<?= $h($in['contact']) ?>">
    <label for="phone">Phone for the splash page (optional)</label>
    <input id="phone" name="phone" type="tel" value="<?= $h($in['phone']) ?>">
<?php if ($packages): ?>
    <label for="pkg">Package</label>
    <select id="pkg" name="pkg">
    <?php foreach ($packages as $p): ?>
      <option<?= $p === $in['pkg'] ? ' selected' : '' ?>><?= $h($p) ?></option>
    <?php endforeach; ?>
    </select>
<?php endif; ?>
    <label for="username">cPanel username (optional)</label>
    <input id="username" name="username" maxlength="16" pattern="[a-z][a-z0-9]{0,15}" placeholder="made from the domain if blank" value="<?= $h($in['username']) ?>">
    <button type="submit">Activate site</button>
  </form>
<?php endif; ?>
<?php ae_admin_foot();
