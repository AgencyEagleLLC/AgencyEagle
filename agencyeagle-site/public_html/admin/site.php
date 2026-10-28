<?php
// One hosting account: details and every action we can take on it through WHM.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$user = (string)($_GET['user'] ?? $_POST['user'] ?? '');
$site = preg_match('/^[a-z][a-z0-9]{0,15}$/', $user) ? ae_whm_site($user) : null;
if ($site === null) {
    http_response_code(404);
    ae_admin_head('Site not found', $email);
    echo '<h1>Site not found</h1><p><a href="/admin/sites.php">Back to all sites</a></p>';
    ae_admin_foot();
    exit;
}
$protected = ae_whm_is_protected($user);
$self = '/admin/site.php?user=' . rawurlencode($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ae_check_csrf()) {
        ae_flash(['error' => 'Your session expired. Try again.']);
        header('Location: ' . $self);
        exit;
    }
    $action = (string)($_POST['action'] ?? '');
    try {
        switch ($action) {
            case 'login':
                $data = ae_whm_act($email, 'cpanel_login', $user, 'create_user_session', ['user' => $user, 'service' => 'cpaneld']);
                ae_flash(['ok' => 'Your cPanel login link is ready. It works once, for a few minutes.', 'link' => (string)($data['url'] ?? '')]);
                break;
            case 'password':
                $pw = ae_random_password();
                ae_whm_act($email, 'reset_password', $user, 'passwd', ['user' => $user, 'password' => $pw]);
                ae_flash(['ok' => 'Password changed. Copy it now; it is not shown again or stored anywhere.', 'secret' => $pw]);
                break;
            case 'suspend':
                if ($protected) {
                    throw new RuntimeException('This is the account the admin runs on, so it can\'t be suspended from here.');
                }
                $reason = trim((string)($_POST['reason'] ?? '')) ?: 'Suspended from Agency Eagle admin';
                ae_whm_act($email, 'suspend', $user, 'suspendacct', ['user' => $user, 'reason' => substr($reason, 0, 200)]);
                ae_flash(['ok' => $site['domain'] . ' is suspended. Visitors see the suspended page.']);
                break;
            case 'unsuspend':
                ae_whm_act($email, 'unsuspend', $user, 'unsuspendacct', ['user' => $user]);
                ae_flash(['ok' => $site['domain'] . ' is active again.']);
                break;
            case 'package':
                $pkg = (string)($_POST['pkg'] ?? '');
                if (!in_array($pkg, ae_whm_packages(), true)) {
                    throw new RuntimeException('Pick a package from the list.');
                }
                ae_whm_act($email, 'change_package', $user . ' → ' . $pkg, 'changepackage', ['user' => $user, 'pkg' => $pkg]);
                ae_flash(['ok' => 'Package changed to ' . $pkg . '.']);
                break;
            case 'autossl':
                ae_whm_act($email, 'autossl', $user, 'start_autossl_check_for_one_user', ['username' => $user]);
                ae_flash(['ok' => 'AutoSSL is checking ' . $site['domain'] . ' now. New certificates usually appear within a few minutes once DNS points here.']);
                break;
            case 'terminate':
                if ($protected) {
                    throw new RuntimeException('This is the account the admin runs on, so it can\'t be deleted from here.');
                }
                if (strtolower(trim((string)($_POST['confirm_domain'] ?? ''))) !== $site['domain']) {
                    throw new RuntimeException('Type the domain exactly to confirm. Nothing was deleted.');
                }
                ae_whm_act($email, 'terminate', $user . ' (' . $site['domain'] . ')', 'removeacct', ['user' => $user, 'keepdns' => 0]);
                ae_flash(['ok' => $site['domain'] . ' and its cPanel account were deleted.']);
                header('Location: /admin/sites.php');
                exit;
            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (RuntimeException $e) {
        ae_flash(['error' => $e->getMessage()]);
    }
    header('Location: ' . $self);
    exit;
}

$flash = ae_flash();
$packages = ae_whm_packages();
$csrf = ae_csrf_token();
$form = static function (string $action, string $label, string $class = 'button', string $extra = '') use ($h, $csrf, $user): string {
    return '<form method="post" class="site-action">'
        . '<input type="hidden" name="csrf" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="user" value="' . $h($user) . '">'
        . '<input type="hidden" name="action" value="' . $h($action) . '">'
        . $extra
        . '<button type="submit" class="' . $h($class) . '">' . $h($label) . '</button></form>';
};
ae_admin_head($site['domain'], $email, 'sites');
?>
  <p><a href="/admin/sites.php">← Back to sites</a></p>
  <div class="console-head">
    <div>
      <h1><?= $h($site['domain']) ?></h1>
      <p><a href="https://<?= $h($site['domain']) ?>/" target="_blank" rel="noopener">Open the site</a></p>
    </div>
  </div>
<?php if ($flash && isset($flash['error'])): ?>
  <p class="alert" role="alert"><?= $h($flash['error']) ?></p>
<?php elseif ($flash): ?>
  <div class="notice" role="status">
    <p><?= $h($flash['ok']) ?></p>
    <?php if (!empty($flash['link'])): ?><p><a class="button" href="<?= $h($flash['link']) ?>" target="_blank" rel="noopener noreferrer">Open cPanel</a></p><?php endif; ?>
    <?php if (!empty($flash['secret'])): ?><p><code class="secret"><?= $h($flash['secret']) ?></code></p><?php endif; ?>
  </div>
<?php endif; ?>
  <dl class="site-facts console-card console-pad">
    <dt>Status</dt><dd><?= $site['suspended'] ? '<span class="pill pill-bad">Suspended</span> ' . $h($site['suspend_reason']) : '<span class="pill pill-ok">Live</span>' ?></dd>
    <dt>cPanel user</dt><dd><?= $h($site['user']) ?></dd>
    <dt>Package</dt><dd><?= $h($site['plan']) ?></dd>
    <dt>Contact email</dt><dd><?= $h($site['email']) ?></dd>
    <dt>Disk</dt><dd><?= $h($site['disk_used']) ?><?= $site['disk_limit'] !== '' && $site['disk_limit'] !== 'unlimited' ? ' of ' . $h($site['disk_limit']) : '' ?></dd>
    <dt>Created</dt><dd><?= $site['created'] ? $h(gmdate('M j, Y', $site['created'])) : '' ?></dd>
    <dt>Owner</dt><dd><?= $h($site['owner']) ?></dd>
  </dl>

  <h2>Manage</h2>
  <div class="site-actions">
    <section class="site-card">
      <h3>Log into cPanel</h3>
      <p>Opens this account's cPanel without its password.</p>
      <?= $form('login', 'Get login link') ?>
    </section>
    <section class="site-card">
      <h3>Reset cPanel password</h3>
      <p>Makes a new strong password and shows it to you once.</p>
      <?= $form('password', 'Reset password', 'button ghost') ?>
    </section>
    <section class="site-card">
      <h3>SSL</h3>
      <p>Run AutoSSL now instead of waiting for tonight's check.</p>
      <?= $form('autossl', 'Run AutoSSL', 'button ghost') ?>
    </section>
    <section class="site-card">
      <h3>Package</h3>
<?php if ($packages): ?>
      <?php
      $opts = '<label for="pkg">Change to</label><select id="pkg" name="pkg">';
      foreach ($packages as $p) {
          $opts .= '<option' . ($p === $site['plan'] ? ' selected' : '') . '>' . $h($p) . '</option>';
      }
      $opts .= '</select>';
      echo $form('package', 'Change package', 'button ghost', $opts);
      ?>
<?php else: ?>
      <p>WHM didn't return any packages.</p>
<?php endif; ?>
    </section>
<?php if (!$protected): ?>
    <section class="site-card">
      <h3><?= $site['suspended'] ? 'Unsuspend' : 'Suspend' ?></h3>
<?php if ($site['suspended']): ?>
      <p>Turns the site, email and cPanel back on.</p>
      <?= $form('unsuspend', 'Unsuspend') ?>
<?php else: ?>
      <p>Takes the site, email and cPanel offline until you unsuspend it. Nothing is deleted.</p>
      <?= $form('suspend', 'Suspend', 'button danger', '<label for="reason">Reason (for your records)</label><input id="reason" name="reason" maxlength="200" placeholder="e.g. unpaid invoice">') ?>
<?php endif; ?>
    </section>
    <section class="site-card site-card-danger">
      <h3>Delete account</h3>
      <p>Permanently deletes the site, its email, databases and files. This can't be undone, so take a backup first.</p>
      <?= $form('terminate', 'Delete permanently', 'button danger', '<label for="confirm_domain">Type <strong>' . $h($site['domain']) . '</strong> to confirm</label><input id="confirm_domain" name="confirm_domain" autocomplete="off" required>') ?>
    </section>
<?php else: ?>
    <section class="site-card">
      <h3>Protected</h3>
      <p>This is the account our admin runs on, so suspend and delete are turned off here.</p>
    </section>
<?php endif; ?>
  </div>
<?php ae_admin_foot();
