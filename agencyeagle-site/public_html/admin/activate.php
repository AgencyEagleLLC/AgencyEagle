<?php
// Activate a new site: cPanel account, splash page and AutoSSL in one go.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';
require_once __DIR__ . '/../_private/app/clients.php';

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
                ae_client_set_emails($in['domain'], [$in['contact']]);
                try {
                    ae_whm_save_file($email, $user, 'public_html/admin', 'index.php', ae_client_admin_stub($in['domain']));
                    $steps[] = ['ok', 'Installed ' . $in['domain'] . '/admin: ' . $in['contact'] . ' signs in there with Google to reach their portal.'];
                } catch (RuntimeException $e) {
                    $steps[] = ['warn', 'Client /admin link not installed: ' . $e->getMessage()];
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
// The six steps from the mockup, with what happened on this run.
$did = [];
foreach ($steps as [$tone, $text]) {
    $did[] = $tone;
}
$plan = [
    ['Create the cPanel account', 'WHM API: a new account on the chosen package, with a random password shown to you once.'],
    ['Publish the splash page', 'One clean page with the business name, email and phone: "new website on the way".'],
    ['Install the client /admin', 'Their /admin opens the Agency Eagle portal: Google sign-in for their email, SEO brain, backups and Site Brief.'],
    ['Start SSL', 'Runs AutoSSL now. The certificate is issued as soon as the domain points here.'],
    ['Point the domain', 'Set the A record to ' . AE_SERVER_IP . ' and www as a CNAME at the client\'s DNS host.'],
    ['Go live and welcome the client', 'Once DNS points here, the site\'s Go live card announces it to search engines, runs the first SEO score, site watch and backup, and emails the client their /admin link.'],
];
$state = static function (int $i) use ($did, $steps): array {
    // Steps 0-3 run here; 4 waits on the client's DNS; 5 happens on the site page once DNS is set.
    if ($i <= 3 && isset($did[$i])) {
        return $did[$i] === 'ok' ? ['done', '✓'] : ['warn', '!'];
    }
    if ($i === 4 && $steps) {
        return ['next', (string)($i + 1)];
    }
    return ['todo', (string)($i + 1)];
};
ae_admin_head('Activate a new site', $email, 'sites');
?>
  <p><a href="/admin/sites.php">← Back to sites</a></p>
  <div class="console-head">
    <div>
      <h1>Activate a new site</h1>
      <p>Fill in a few fields. The panel builds the hosting account, a splash page and SSL, then tells you the DNS records to set.</p>
    </div>
  </div>
  <div class="activate-grid">
<?php if ($steps): ?>
    <section class="console-card console-pad">
      <h2>1. <?= $h($in['domain']) ?> is set up</h2>
      <ul class="steps">
      <?php foreach ($steps as [$tone, $text]): ?>
        <li><span class="pill pill-<?= $tone === 'ok' ? 'ok' : 'warn' ?>"><?= $tone === 'ok' ? 'Done' : 'Check' ?></span> <?= $h($text) ?></li>
      <?php endforeach; ?>
      </ul>
      <p><strong>cPanel password</strong> (copy it now; it isn't shown again or stored):</p>
      <p><code class="secret"><?= $h($pw) ?></code></p>
      <p><a class="button" href="/admin/site.php?user=<?= $h(rawurlencode($user)) ?>">Manage <?= $h($in['domain']) ?></a> <a class="button ghost" href="/admin/activate.php">Activate another</a></p>
    </section>
<?php else: ?>
    <form method="post" class="console-card console-pad console-form">
      <h2>1. Client and site</h2>
<?php if ($error): ?>
      <p class="alert" role="alert"><?= $h($error) ?></p>
<?php endif; ?>
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <label for="domain">Domain</label>
      <input id="domain" name="domain" required placeholder="newclient.com" value="<?= $h($in['domain']) ?>">
      <label for="business">Business name</label>
      <input id="business" name="business" required value="<?= $h($in['business']) ?>">
      <label for="contact">Client's email</label>
      <input id="contact" name="contact" type="email" required value="<?= $h($in['contact']) ?>">
      <p class="hint">Used as the cPanel contact and shown on the splash page. It'll also be their Google sign-in for the client /admin.</p>
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
      <details class="more"<?= $in['username'] !== '' ? ' open' : '' ?>>
        <summary>cPanel username</summary>
        <label for="username">Username (optional)</label>
        <input id="username" name="username" maxlength="16" pattern="[a-z][a-z0-9]{0,15}" placeholder="made from the domain if blank" value="<?= $h($in['username']) ?>">
      </details>
      <button type="submit" class="button big block">Activate site</button>
    </form>
<?php endif; ?>
    <section class="console-card console-pad" aria-label="Setup progress">
      <h2>2. What the panel does</h2>
      <ol class="flow">
      <?php foreach ($plan as $i => [$title, $text]): [$st, $mark] = $state($i); ?>
        <li class="flow-<?= $st ?>">
          <span class="flow-dot"><?= $h($mark) ?></span>
          <span><strong><?= $h($title) ?></strong><?= $st === 'soon' ? ' <span class="pill pill-soon">Coming soon</span>' : ($st === 'next' ? ' <span class="pill pill-info">Waiting on DNS</span>' : '') ?><br><span class="muted"><?= $h($text) ?></span></span>
        </li>
      <?php endforeach; ?>
      </ol>
      <div class="dns-box">
        <strong>DNS records<?= $steps ? ' for ' . $h($in['domain']) : '' ?></strong>
        <pre>A      @     <?= $h(AE_SERVER_IP) ?>

CNAME  www   <?= $h($steps ? $in['domain'] : 'newclient.com') ?></pre>
      </div>
    </section>
  </div>
<?php ae_admin_foot();
