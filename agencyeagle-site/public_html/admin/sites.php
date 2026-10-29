<?php
// Every hosting account on the server, read live from WHM.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';
require_once __DIR__ . '/../_private/app/brain.php';
require_once __DIR__ . '/../_private/app/backups.php';
require_once __DIR__ . '/../_private/app/nightly.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ae_check_csrf()) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'watch_all' || $action === 'score_all') {
        // One site per request on /admin/run.php, so nothing hits the proxy's one-minute limit.
        $steps = [];
        foreach (ae_whm_sites()['sites'] as $s) {
            if (!$s['suspended'] && ($action === 'watch_all' || ae_brain_is_stale(ae_brain_get($s['domain'])))) {
                $steps[] = [$action === 'watch_all' ? 'watch' : 'brain', $s['user']];
            }
        }
        if (!$steps) {
            ae_flash(['ok' => 'Every site was scored in the last week. Rescore one from its Manage page.']);
        } else {
            $_SESSION['batch'] = ['title' => $action === 'watch_all' ? 'Checking every site' : 'Scoring sites', 'steps' => $steps, 'i' => 0, 'lines' => [], 'new' => []];
            header('Location: /admin/run.php');
            exit;
        }
    } else {
        ae_whm_sites(true);
    }
    header('Location: /admin/sites.php');
    exit;
}
$data = ae_whm_sites();

/** [label, tone, days left or null] for a site's certificate. */
$sslInfo = static function (?array $ssl): array {
    if ($ssl === null || $ssl['expires'] === 0) {
        return ['None found', 'warn', null];
    }
    if ($ssl['self_signed']) {
        return ['Self-signed', 'warn', null];
    }
    $days = (int)floor(($ssl['expires'] - time()) / 86400);
    if ($days < 0) {
        return ['Expired', 'bad', $days];
    }
    return ['Valid · ' . gmdate('M j, Y', $ssl['expires']), $days < 14 ? 'warn' : 'ok', $days];
};

// What needs a look, in plain words.
$issues = [];
$active = $suspended = 0;
foreach ($data['sites'] as $s) {
    $s['suspended'] ? $suspended++ : $active++;
    [$label, $tone, $days] = $sslInfo($s['ssl']);
    $br = ae_brain_get($s['domain']);
    $wt = $s['suspended'] ? null : ae_watch_get($s['domain']);
    foreach ($wt['alerts'] ?? [] as $a) {
        if ($a['kind'] !== 'ssl') {
            $issues[$s['user']][] = lcfirst($a['text']);
        }
    }
    if ($br && !$br['reached'] && !$s['suspended']) {
        $issues[$s['user']][] = 'the site didn\'t load for the SEO brain (' . $br['error'] . ')';
    }
    if ($s['suspended']) {
        $issues[$s['user']][] = 'suspended' . ($s['suspend_reason'] !== '' ? ' (' . $s['suspend_reason'] . ')' : '');
    } elseif ($tone === 'bad') {
        $issues[$s['user']][] = 'SSL certificate expired';
    } elseif ($tone === 'warn' && $days !== null) {
        $issues[$s['user']][] = 'SSL renews in ' . $days . ' day' . ($days === 1 ? '' : 's');
    } elseif ($tone === 'warn') {
        $issues[$s['user']][] = 'no valid SSL certificate (' . strtolower($label) . ')';
    }
}

$filter = (string)($_GET['f'] ?? 'all');
$q = strtolower(trim((string)($_GET['q'] ?? '')));
$sites = array_values(array_filter($data['sites'], function ($s) use ($filter, $q, $issues) {
    if ($filter === 'attention' && !isset($issues[$s['user']])) {
        return false;
    }
    if ($filter === 'suspended' && !$s['suspended']) {
        return false;
    }
    return $q === '' || str_contains($s['domain'] . ' ' . implode(' ', $s['other_domains'] ?? []) . ' ' . $s['user'] . ' ' . $s['email'], $q);
}));
$chip = static function (string $key, string $label) use ($filter, $q, $h): string {
    $href = '/admin/sites.php?f=' . $key . ($q !== '' ? '&q=' . rawurlencode($q) : '');
    return '<a class="chip" href="' . $h($href) . '"' . ($filter === $key ? ' aria-current="true"' : '') . '>' . $h($label) . '</a>';
};
$byUser = array_column($data['sites'], null, 'user');
$brains = [];
foreach ($data['sites'] as $s) {
    $brains[$s['domain']] = ae_brain_get($s['domain']);
}
$scored = array_filter($brains, fn($b) => $b && $b['reached']);
$avgScore = $scored ? (int)round(array_sum(array_column($scored, 'score')) / count($scored)) : null;
$nightly = is_file(AE_NIGHTLY_LOG) ? json_decode((string)file_get_contents(AE_NIGHTLY_LOG), true) : null;
$recent = array_slice(ae_whm_audit_entries(6), 0, 6);
$csrf = ae_csrf_token();
$flash = ae_flash();

ae_admin_head('Client sites', $email, 'sites');
?>
  <div class="console-head">
    <div>
      <h1>Client sites</h1>
      <p>Every site on the server, its health, and one-click actions.</p>
    </div>
    <a class="button" href="/admin/activate.php">+ Activate a new site</a>
  </div>
<?php if ($flash): ?>
  <p class="<?= isset($flash['error']) ? 'alert' : 'notice' ?>" role="status"><?= $h($flash['error'] ?? $flash['ok']) ?></p>
<?php endif; ?>
<?php if ($data['error'] === 'not_configured'): ?>
  <p class="alert">WHM isn't connected yet. Add the <code>WHM_USER</code> and <code>WHM_API_TOKEN</code> secrets in GitHub and redeploy.</p>
<?php elseif ($data['error'] !== null): ?>
  <p class="alert" role="alert">Couldn't read WHM: <?= $h($data['error']) ?></p>
<?php endif; ?>

  <section class="stat-row" aria-label="Totals">
    <div class="stat"><span class="stat-label">Active sites</span><span class="stat-value"><?= $active ?></span></div>
    <div class="stat"><span class="stat-label">Suspended</span><span class="stat-value"><?= $suspended ?></span></div>
    <div class="stat"><span class="stat-label">Need attention</span><span class="stat-value<?= $issues ? ' stat-warn' : '' ?>"><?= count($issues) ?></span></div>
    <div class="stat"><span class="stat-label">Average SEO brain score</span><span class="stat-value<?= $avgScore === null ? ' stat-soon' : '' ?>"><?= $avgScore === null ? 'Not scored yet' : $avgScore . '<small>/100</small>' ?></span></div>
  </section>

  <section class="console-card" aria-label="Sites">
    <div class="console-tools">
      <form method="get" class="sites-search">
        <input type="hidden" name="f" value="<?= $h($filter) ?>">
        <label for="q">Search sites</label>
        <input id="q" name="q" type="search" value="<?= $h($q) ?>" placeholder="Domain, cPanel user or email">
      </form>
      <div class="chips">
        <?= $chip('all', 'All') ?><?= $chip('attention', 'Needs attention') ?><?= $chip('suspended', 'Suspended') ?>
      </div>
      <div class="tool-buttons">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="action" value="watch_all">
          <button type="submit" class="button ghost small">Check all sites now</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <input type="hidden" name="action" value="score_all">
          <button type="submit" class="button ghost small">Score all sites</button>
        </form>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
          <button type="submit" class="button ghost small">Refresh from WHM</button>
        </form>
      </div>
    </div>
<?php if ($sites): ?>
    <div class="table-wrap">
    <table class="console-table">
      <thead><tr><th>Site</th><th>Package</th><th>Status</th><th>SSL</th><th>Watch</th><th>SEO brain</th><th>Last backup</th><th>Disk</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($sites as $s): [$sslText, $sslTone] = $sslInfo($s['ssl']); $manage = '/admin/site.php?user=' . rawurlencode($s['user']); ?>
        <tr>
          <td><a href="<?= $h($manage) ?>"><strong><?= $h($s['domain']) ?></strong></a><br><span class="muted"><?= $h($s['user']) ?><?= $s['email'] !== '' ? ' · ' . $h($s['email']) : '' ?></span><?php if (!empty($s['other_domains'])): ?><br><span class="muted">Also: <?= $h(implode(', ', $s['other_domains'])) ?></span><?php endif; ?></td>
          <td><?= $h($s['plan']) ?></td>
          <td><?php if ($s['suspended']): ?><span class="pill pill-bad" title="<?= $h($s['suspend_reason']) ?>">Suspended</span><?php elseif (isset($issues[$s['user']])): ?><span class="pill pill-warn">Needs attention</span><?php else: ?><span class="pill pill-ok">Live</span><?php endif; ?></td>
          <td class="nowrap"><span class="pill pill-<?= $sslTone ?>"><?= $h($sslText) ?></span></td>
          <?php $b = $brains[$s['domain']]; $bk = ae_backup_cached($s['user']); ?>
          <td class="nowrap"><?php $wt = $s['suspended'] ? null : ae_watch_get($s['domain']); ?><span class="pill pill-<?= ae_watch_tone($wt) ?>"><?= $h(ae_watch_label($wt)) ?></span></td>
          <td class="nowrap"><?php if ($b && !$b['reached']): ?><span class="pill pill-bad" title="<?= $h($b['error']) ?>">Can't reach</span><?php elseif ($b): ?><span class="pill pill-<?= ae_brain_tone($b) ?>"><?= (int)$b['score'] ?>/100</span><?php else: ?><span class="muted">Not scored</span><?php endif; ?></td>
          <td class="nowrap"><?php if ($bk && $bk['files']): ?><?= $h(gmdate('M j, Y', $bk['files'][0]['at'])) ?><?php elseif ($bk): ?><span class="muted">None</span><?php else: ?><span class="muted">Not checked</span><?php endif; ?></td>
          <td class="nowrap"><?= $h($s['disk_used']) ?></td>
          <td class="nowrap"><a href="https://<?= $h($s['domain']) ?>/" target="_blank" rel="noopener">Open</a> · <a href="<?= $h($manage) ?>">Manage</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
<?php elseif ($data['error'] === null): ?>
    <p class="console-empty">No sites match.</p>
<?php endif; ?>
  </section>

  <section class="console-pair">
    <div class="console-card console-pad">
      <h2>Needs attention</h2>
<?php if ($issues): ?>
      <ul>
      <?php foreach ($issues as $user => $list): ?>
        <li><a href="/admin/site.php?user=<?= $h(rawurlencode($user)) ?>"><?= $h($byUser[$user]['domain'] ?? $user) ?></a>: <?= $h(implode(', ', $list)) ?></li>
      <?php endforeach; ?>
      </ul>
<?php else: ?>
      <p class="muted">Nothing right now. Every site is live with a valid certificate.</p>
<?php endif; ?>
    </div>
    <div class="console-card console-pad">
      <h2>Recent activity</h2>
<?php if ($recent): ?>
      <ul>
      <?php foreach ($recent as $e): ?>
        <li><?= $h(gmdate('M j, H:i', (int)$e['at'])) ?> UTC · <?= $h(str_replace('_', ' ', (string)$e['action'])) ?> · <?= $h($e['target']) ?><?= $e['ok'] ? '' : ' (failed)' ?></li>
      <?php endforeach; ?>
      </ul>
      <p><a href="/admin/audit.php">Full audit log</a></p>
<?php else: ?>
      <p class="muted">No changes yet. Actions you take on a site show up here.</p>
<?php endif; ?>
    </div>
  </section>
<?php if ($data['fetched']): ?>
  <p class="muted small-print">Read from WHM <?= $h(gmdate('M j, H:i', (int)$data['fetched'])) ?> UTC · <?= count($data['sites']) ?> accounts · <?= is_array($nightly) ? 'last full check ' . $h(gmdate('M j, H:i', (int)$nightly['at'])) . ' UTC' : 'no full check yet' ?></p>
<?php endif; ?>
<?php ae_admin_foot();
