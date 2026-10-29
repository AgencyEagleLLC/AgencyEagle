<?php
// Client portal: one site's overview, SEO brain, backups and Site Brief (matches the client mockup).
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/clients.php';
require_once __DIR__ . '/../_private/app/brief.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';
require_once __DIR__ . '/../_private/app/watch.php';

$email = ae_require_portal();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$domain = strtolower((string)($_GET['d'] ?? $_POST['d'] ?? ''));
$site = null;
if (in_array($domain, ae_portal_sites_for($email), true)) {
    foreach (ae_whm_sites()['sites'] as $s) {
        if ($s['domain'] === $domain) {
            $site = $s;
        }
    }
}
if ($site === null) {
    http_response_code(404);
    ae_portal_head('Site not found', $email);
    echo '<h1>Site not found</h1><p><a href="/portal/">Back to your sites</a></p>';
    ae_admin_foot();
    exit;
}
$self = '/portal/site.php?d=' . rawurlencode($domain);

/** Once per $seconds per site and action, so a client can't hammer the server. Returns seconds left to wait. */
$limit = static function (string $key, int $seconds): int {
    $file = __DIR__ . '/../_private/data/portal-limits.json';
    $all = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $wait = (int)($all[$key] ?? 0) + $seconds - time();
    if ($wait > 0) {
        return $wait;
    }
    $all[$key] = time();
    file_put_contents($file, json_encode($all), LOCK_EX);
    return 0;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if (!ae_check_csrf()) {
            throw new RuntimeException('Your session expired. Try again.');
        }
        if ($action === 'brain') {
            if ($wait = $limit('brain:' . $domain, 3600)) {
                throw new RuntimeException('The SEO brain checked your site recently. Try again in ' . (int)ceil($wait / 60) . ' minutes.');
            }
            @set_time_limit(120);
            $r = ae_brain_score($domain);
            ae_whm_audit($email, 'client_rescore', $domain, true);
            ae_flash(['ok' => 'Your site scored ' . $r['score'] . '/100.']);
        } elseif ($action === 'backup') {
            if ($wait = $limit('backup:' . $domain, 86400)) {
                throw new RuntimeException('A backup was made in the last day. You can start another in ' . (int)ceil($wait / 3600) . ' hours.');
            }
            ae_backup_start($email, $site['user'], $email);
            ae_flash(['ok' => 'Backup started. It takes a few minutes, and we\'ll email ' . $email . ' when it\'s ready.']);
        } elseif ($action === 'backups_refresh') {
            ae_backup_list($site['user']);
            ae_flash(['ok' => 'Backup list refreshed.']);
        } elseif ($action === 'download') {
            $data = ae_whm_act($email, 'client_backup_download', $site['user'], 'create_user_session', ['user' => $site['user'], 'service' => 'cpaneld', 'app' => 'Backup']);
            ae_flash(['ok' => 'Your download page is ready. The link works once, for a few minutes.', 'link' => (string)($data['url'] ?? '')]);
        }
    } catch (RuntimeException $e) {
        ae_flash(['error' => $e->getMessage()]);
    }
    header('Location: ' . $self);
    exit;
}

$flash = ae_flash();
$brain = ae_brain_get($domain);
$backups = ae_backup_cached($site['user']);
$watch = ae_watch_get($domain);
$csrf = ae_csrf_token();
$form = static function (string $action, string $label, string $class = 'button') use ($h, $csrf, $domain): string {
    return '<form method="post" class="site-action"><input type="hidden" name="csrf" value="' . $h($csrf) . '">'
        . '<input type="hidden" name="d" value="' . $h($domain) . '"><input type="hidden" name="action" value="' . $h($action) . '">'
        . '<button type="submit" class="' . $h($class) . '">' . $h($label) . '</button></form>';
};
$ssl = $site['ssl'];
$sslOk = $ssl && $ssl['expires'] > time() && !$ssl['self_signed'];

ae_portal_head($domain, $email, $domain);
?>
  <div class="console-head">
    <div>
      <h1>Your website</h1>
      <p>Live at <a href="https://<?= $h($domain) ?>/" target="_blank" rel="noopener">https://<?= $h($domain) ?></a> · <?= $site['suspended'] ? 'Suspended: call us' : ($sslOk ? 'SSL valid until ' . $h(gmdate('M j, Y', $ssl['expires'])) : 'SSL needs attention: we\'re on it') ?></p>
    </div>
  </div>
<?php if ($flash && isset($flash['error'])): ?>
  <p class="alert" role="alert"><?= $h($flash['error']) ?></p>
<?php elseif ($flash): ?>
  <div class="notice" role="status"><p><?= $h($flash['ok']) ?></p><?php if (!empty($flash['link'])): ?><p><a class="button" href="<?= $h($flash['link']) ?>" target="_blank" rel="noopener noreferrer">Open download page</a></p><?php endif; ?></div>
<?php endif; ?>
  <div class="site-actions site-actions-wide portal-grid">
    <section class="site-card">
      <h3>SEO brain</h3>
<?php if ($brain && $brain['reached']): ?>
      <p class="brain-score"><span class="brain-num brain-<?= ae_brain_tone($brain) ?>"><?= (int)$brain['score'] ?></span>/100 · <?= $h($brain['grade']['label']) ?></p>
      <p>Checked <?= $h(gmdate('M j', (int)$brain['at'])) ?> · <?= (int)$brain['passed'] ?> of <?= (int)$brain['checked'] ?> checks pass</p>
<?php if ($brain['fixes']): ?>
      <p class="card-label">Top fixes</p>
      <ol class="fix-list">
      <?php foreach (array_slice($brain['fixes'], 0, 3) as $f): ?>
        <li><strong><?= $h($f['label']) ?></strong><br><span class="muted"><?= $h($f['fix'] ?: $f['detail']) ?></span></li>
      <?php endforeach; ?>
      </ol>
<?php endif; ?>
<?php elseif ($brain): ?>
      <p>We couldn't load your site on the last check (<?= $h($brain['error']) ?>). We've been alerted.</p>
<?php else: ?>
      <p>Your first score is on the way. It checks what Google, Bing and AI assistants see.</p>
<?php endif; ?>
      <?= $form('brain', 'Check my site now', 'button ghost') ?>
    </section>
    <section class="site-card">
      <h3>Backups</h3>
      <p>A full copy of your site, email and databases. Keep your own copy on your computer too.</p>
<?php if ($backups && $backups['files']): ?>
      <ul class="backup-list">
      <?php foreach (array_slice($backups['files'], 0, 3) as $b): ?>
        <li><span><?= $h(gmdate('M j, Y', $b['at'])) ?></span><span class="muted"><?= $h(ae_human_size($b['size'])) ?></span></li>
      <?php endforeach; ?>
      </ul>
<?php endif; ?>
      <div class="card-buttons">
        <?= $form('backup', 'Back up now') ?>
        <?= $form('download', 'Download a backup', 'button ghost') ?>
        <?= $form('backups_refresh', 'Refresh list', 'button ghost') ?>
      </div>
    </section>
    <section class="site-card">
      <h3>Security watch</h3>
<?php if ($watch === null): ?>
      <p>Starting tonight, we check your site every night for downtime, injected spam, hidden scripts and changed code files.</p>
<?php elseif (!$watch['alerts']): ?>
      <p><span class="pill pill-ok">All clear</span></p>
      <p>Last checked <?= $h(gmdate('M j', (int)$watch['at'])) ?>. Your home page loaded in <?= number_format($watch['page']['ms'] / 1000, 1) ?> seconds<?= $watch['file_count'] ? ' and ' . (int)$watch['file_count'] . ' code files match what we expect' : '' ?>.</p>
<?php else: ?>
      <p><span class="pill pill-<?= ae_watch_tone($watch) ?>">We're looking into something</span></p>
      <p>Last night's check flagged a change on your site. Our team has been alerted and is reviewing it. If you or your developer changed the site recently, that's usually the reason. Let us know at <a href="mailto:help@agencyeagle.com">help@agencyeagle.com</a>.</p>
<?php endif; ?>
    </section>
    <section class="site-card">
      <h3>Connect your AI</h3>
      <p>Give this file to Claude, ChatGPT or your developer. It explains your site, your fix list and the standards every page has to meet.</p>
      <a class="button ghost" href="/portal/brief.php?d=<?= $h(rawurlencode($domain)) ?>">Download Site Brief (.md)</a>
    </section>
    <section class="site-card">
      <h3>Plan and help</h3>
      <p>Hosting package: <?= $h($site['plan']) ?></p>
      <p>Questions or changes? Email <a href="mailto:help@agencyeagle.com">help@agencyeagle.com</a> or call <a href="tel:+18653005397">(865) 300-5397</a>.</p>
    </section>
  </div>
<?php ae_admin_foot();
