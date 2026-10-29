<?php
// One hosting account: details and every action we can take on it through WHM.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';
require_once __DIR__ . '/../_private/app/brain.php';
require_once __DIR__ . '/../_private/app/backups.php';
require_once __DIR__ . '/../_private/app/clients.php';
require_once __DIR__ . '/../_private/app/golive.php';

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
                $args = ['user' => $user, 'service' => 'cpaneld'];
                if (($_POST['app'] ?? '') === 'backups') {
                    $args['app'] = 'Backup';
                }
                $data = ae_whm_act($email, 'cpanel_login', $user, 'create_user_session', $args);
                ae_flash(['ok' => 'Your cPanel login link is ready. It works once, for a few minutes.', 'link' => (string)($data['url'] ?? '')]);
                break;
            case 'brain':
                @set_time_limit(120);
                $r = ae_brain_score($site['domain']);
                ae_flash(['ok' => 'SEO brain scored ' . $site['domain'] . ': ' . $r['score'] . '/100.']);
                break;
            case 'watch':
                @set_time_limit(300);
                $w = ae_watch_check($site);
                ae_flash(['ok' => 'Site watch checked ' . $site['domain'] . ': ' . strtolower(ae_watch_label($w)) . '.']);
                break;
            case 'watch_review':
                ae_watch_review($email, $site);
                ae_flash(['ok' => 'Marked reviewed. Today\'s pages and files are the new normal for ' . $site['domain'] . '.']);
                break;
            case 'announce':
                if (!ae_golive_check($site)['dns']) {
                    throw new RuntimeException($site['domain'] . ' doesn\'t point at this server yet (A record ' . AE_SERVER_IP . '), so nothing was announced.');
                }
                @set_time_limit(300);
                ae_flash(['ok' => implode(' ', ae_golive_announce($email, $site))]);
                break;
            case 'welcome':
                $to = ae_golive_welcome($email, $site);
                ae_flash(['ok' => 'Welcome email sent to ' . implode(', ', $to) . '.']);
                break;
            case 'backup':
                ae_backup_start($email, $user, $site['email']);
                ae_flash(['ok' => 'Full backup started. It usually takes a few minutes; refresh the list below to see it. cPanel also emails ' . ($site['email'] ?: 'the account contact') . ' when it finishes.']);
                break;
            case 'client_access':
                $list = ae_client_set_emails($site['domain'], preg_split('/[\s,;]+/', (string)($_POST['emails'] ?? '')) ?: []);
                ae_whm_audit($email, 'client_access', $site['domain'], true, implode(', ', $list));
                ae_flash(['ok' => $list ? 'Portal access saved for ' . implode(', ', $list) . '.' : 'Portal access removed. Nobody can sign in to this site\'s portal now.']);
                break;
            case 'install_admin':
                $existing = ae_uapi($user, 'Fileman', 'list_files', ['dir' => '/home/' . $user . '/public_html', 'types' => 'dir|file']);
                foreach ((array)($existing['data'] ?? []) as $f) {
                    if (($f['file'] ?? '') === 'admin') {
                        throw new RuntimeException('This site already has an /admin folder (maybe WordPress or another app), so nothing was changed. Clients can use https://agencyeagle.com/portal/ directly.');
                    }
                }
                ae_whm_save_file($email, $user, 'public_html/admin', 'index.php', ae_client_admin_stub($site['domain']));
                ae_flash(['ok' => $site['domain'] . '/admin now opens the client portal.']);
                break;
            case 'backups_refresh':
                ae_backup_list($user);
                ae_flash(['ok' => 'Backup list refreshed.']);
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
$brain = ae_brain_get($site['domain']);
$backups = ae_backup_cached($user);
$watch = ae_watch_get($site['domain']);
$golive = ae_golive_get($site['domain']);
$dnsIps = @gethostbynamel($site['domain']) ?: [];
$pointsHere = in_array(AE_SERVER_IP, $dnsIps, true);
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

  <div class="site-actions site-actions-wide">
    <section class="site-card">
      <h3>SEO brain</h3>
<?php if ($brain): $tone = ae_brain_tone($brain); ?>
<?php if (!$brain['reached']): ?>
      <p class="brain-score"><span class="brain-num brain-bad">Can't reach</span></p>
<?php else: ?>
      <p class="brain-score"><span class="brain-num brain-<?= $tone ?>"><?= (int)$brain['score'] ?></span>/100 · <?= $h($brain['grade']['label']) ?></p>
<?php endif; ?>
      <p>Checked <?= $h(gmdate('M j, H:i', (int)$brain['at'])) ?> UTC · <?= (int)$brain['passed'] ?> of <?= (int)$brain['checked'] ?> checks pass<?= $brain['reached'] ? '' : ' · couldn\'t reach the site: ' . $h($brain['error']) ?></p>
<?php if ($brain['fixes']): ?>
      <p class="card-label">Top fixes</p>
      <ol class="fix-list">
      <?php foreach (array_slice($brain['fixes'], 0, 5) as $f): ?>
        <li><strong><?= $h($f['label']) ?></strong><br><span class="muted"><?= $h($f['fix'] ?: $f['detail']) ?></span></li>
      <?php endforeach; ?>
      </ol>
<?php endif; ?>
<?php else: ?>
      <p>Not scored yet. The brain checks crawl access for Google, Bing and AI assistants, indexing, schema, llms.txt, speed and more.</p>
<?php endif; ?>
      <?= $form('brain', $brain ? 'Rescore now' : 'Score this site', 'button ghost') ?>
    </section>
    <section class="site-card">
      <h3>Site watch</h3>
<?php if ($watch): ?>
      <p><span class="pill pill-<?= ae_watch_tone($watch) ?>"><?= $h(ae_watch_label($watch)) ?></span> · checked <?= $h(gmdate('M j, H:i', (int)$watch['at'])) ?> UTC</p>
      <p class="muted">Home page <?= $watch['page']['ok'] ? 'loaded in ' . (int)$watch['page']['ms'] . ' ms' : 'did not load' ?><?= $watch['file_count'] !== null ? ' · ' . (int)$watch['file_count'] . ' code files watched' . ($watch['truncated'] ? ' (large site, first ' . AE_WATCH_MAX_DIRS . ' folders)' : '') : ' · files not readable: ' . $h($watch['file_error']) ?> · normal since <?= $h(gmdate('M j', (int)$watch['baseline']['at'])) ?></p>
<?php if ($watch['alerts']): ?>
      <ul class="watch-list">
      <?php foreach ($watch['alerts'] as $a): ?>
        <li class="watch-<?= $h($a['kind']) ?>"><strong><?= $h($a['text']) ?></strong><?php if (!empty($a['items'])): ?><br><span class="muted watch-files"><?= $h(implode(', ', array_slice($a['items'], 0, 8))) ?><?= count($a['items']) > 8 ? ' …' : '' ?></span><?php endif; ?></li>
      <?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php else: ?>
      <p>Not checked yet. Each night the watch loads the home page and lists the site's code files, and emails you when spam, hidden scripts, or new or changed files show up.</p>
<?php endif; ?>
      <div class="card-buttons">
        <?= $form('watch', 'Check now', 'button ghost') ?>
<?php if ($watch && $watch['alerts']): ?>
        <?= $form('watch_review', 'Mark reviewed', 'button ghost') ?>
<?php endif; ?>
      </div>
    </section>
    <section class="site-card">
      <h3>Backups</h3>
      <p>Full backups (files, databases and email) saved in the account's home folder.</p>
<?php if ($backups && $backups['files']): ?>
      <ul class="backup-list">
      <?php foreach (array_slice($backups['files'], 0, 5) as $b): ?>
        <li><span><?= $h(gmdate('M j, Y H:i', $b['at'])) ?> UTC</span><span class="muted"><?= $h(ae_human_size($b['size'])) ?></span></li>
      <?php endforeach; ?>
      </ul>
<?php elseif ($backups): ?>
      <p class="muted">No backups in the home folder yet.</p>
<?php else: ?>
      <p class="muted">Backup list not loaded yet.</p>
<?php endif; ?>
      <div class="card-buttons">
        <?= $form('backup', 'Back up now') ?>
        <?= $form('backups_refresh', 'Refresh list', 'button ghost') ?>
        <?= $form('login', 'Download in cPanel', 'button ghost', '<input type="hidden" name="app" value="backups">') ?>
      </div>
    </section>
    <section class="site-card">
      <h3>Go live</h3>
      <ul class="golive-list">
        <li class="<?= $pointsHere ? 'is-done' : 'is-todo' ?>"><?= $pointsHere ? 'Domain points here' : 'Domain doesn\'t point here yet' . ($dnsIps ? ' (' . $h(implode(', ', $dnsIps)) . ')' : ' (no A record found)') ?></li>
        <li class="<?= !empty($golive['announced_at']) ? 'is-done' : 'is-todo' ?>"><?= !empty($golive['announced_at']) ? 'Announced to search engines ' . $h(gmdate('M j, Y', (int)$golive['announced_at'])) . ' (' . (int)$golive['announced_urls'] . ' pages)' : 'Not announced to search engines yet' ?></li>
        <li class="<?= !empty($golive['welcomed_at']) ? 'is-done' : 'is-todo' ?>"><?= !empty($golive['welcomed_at']) ? 'Welcome email sent ' . $h(gmdate('M j, Y', (int)$golive['welcomed_at'])) . ' to ' . $h(implode(', ', $golive['welcomed_to'])) : 'Welcome email not sent yet' ?></li>
      </ul>
<?php if (!$pointsHere): ?>
      <p class="muted">Set the A record to <?= $h(AE_SERVER_IP) ?> and www as a CNAME at the client's DNS host. This card updates on its own once it resolves.</p>
<?php endif; ?>
      <div class="card-buttons">
<?php if ($pointsHere): ?>
        <?= $form('announce', !empty($golive['announced_at']) ? 'Announce again' : 'Announce to search engines', !empty($golive['announced_at']) ? 'button ghost' : 'button') ?>
<?php endif; ?>
        <?= $form('welcome', !empty($golive['welcomed_at']) ? 'Resend welcome email' : 'Send welcome email', 'button ghost') ?>
      </div>
    </section>
    <section class="site-card">
      <h3>Client portal access</h3>
      <p>Google accounts that can sign in at <a href="https://<?= $h($site['domain']) ?>/admin" target="_blank" rel="noopener"><?= $h($site['domain']) ?>/admin</a> (the Agency Eagle portal) to see their SEO score, backups and Site Brief.</p>
      <?= $form('client_access', 'Save access', 'button ghost', '<label for="emails">Google emails, one per line</label><textarea id="emails" name="emails" rows="3">' . $h(implode("\n", ae_client_emails($site['domain']))) . '</textarea>') ?>
      <?= $form('install_admin', 'Install /admin link on their site', 'button ghost') ?>
    </section>
    <section class="site-card">
      <h3>Connect your AI</h3>
      <p>A Site Brief for Claude, ChatGPT or a developer: the business, the account, the SEO brain's fix list and the Agency Eagle HTML and SEO standards every page must pass.</p>
      <a class="button ghost" href="/admin/brief.php?user=<?= $h(rawurlencode($user)) ?>">Download Site Brief (.md)</a>
    </section>
  </div>

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
