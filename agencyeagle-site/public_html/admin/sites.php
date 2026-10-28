<?php
// Every hosting account on the server, read live from WHM.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$refresh = $_SERVER['REQUEST_METHOD'] === 'POST' && ae_check_csrf();
$data = ae_whm_sites($refresh);
if ($refresh) {
    header('Location: /admin/sites.php');
    exit;
}
$sites = $data['sites'];
$q = strtolower(trim((string)($_GET['q'] ?? '')));
if ($q !== '') {
    $sites = array_values(array_filter($sites, fn($s) => str_contains($s['domain'] . ' ' . $s['user'] . ' ' . $s['email'], $q)));
}

$sslLabel = static function (?array $ssl): array {
    if ($ssl === null || $ssl['expires'] === 0) {
        return ['None found', 'warn'];
    }
    if ($ssl['self_signed']) {
        return ['Self-signed', 'warn'];
    }
    $days = (int)floor(($ssl['expires'] - time()) / 86400);
    if ($days < 0) {
        return ['Expired', 'bad'];
    }
    return ['Valid · ' . gmdate('M j, Y', $ssl['expires']), $days < 14 ? 'warn' : 'ok'];
};
$attention = 0;
foreach ($data['sites'] as $s) {
    if ($s['suspended'] || $sslLabel($s['ssl'])[1] !== 'ok') {
        $attention++;
    }
}
$csrf = ae_csrf_token();
$flash = ae_flash();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sites | Agency Eagle Admin</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body class="admin">
<header class="admin-bar">
  <strong>Agency Eagle Admin</strong>
  <span><?= $h($email) ?> · <a href="/admin/dashboard.php">Dashboard</a> · <a href="/admin/audit.php">Audit log</a> · <a href="/admin/logout.php">Sign out</a></span>
</header>
<main class="container">
  <div class="sites-head">
    <h1>Client sites</h1>
    <a class="button" href="/admin/activate.php">+ Activate a new site</a>
  </div>
<?php if ($flash): ?>
  <p class="<?= isset($flash['error']) ? 'alert' : 'notice' ?>" role="status"><?= $h($flash['error'] ?? $flash['ok']) ?></p>
<?php endif; ?>
<?php if ($data['error'] === 'not_configured'): ?>
  <p class="alert">WHM isn't connected yet. Add the <code>WHM_USER</code> and <code>WHM_API_TOKEN</code> secrets in GitHub and redeploy.</p>
<?php elseif ($data['error'] !== null): ?>
  <p class="alert" role="alert">Couldn't read WHM: <?= $h($data['error']) ?></p>
<?php else: ?>
  <p><?= count($data['sites']) ?> accounts on the server · <?= $attention ?> need attention · read from WHM <?= $h(gmdate('M j, H:i', (int)$data['fetched'])) ?> UTC</p>
<?php endif; ?>
  <div class="sites-tools">
    <form method="get" class="sites-search">
      <label for="q">Search sites</label>
      <input id="q" name="q" type="search" value="<?= $h($q) ?>" placeholder="Domain, cPanel user or email">
      <button type="submit" class="button ghost">Search</button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
      <button type="submit" class="button ghost">Refresh from WHM</button>
    </form>
  </div>
<?php if ($sites): ?>
  <div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>Site</th><th>cPanel user</th><th>Package</th><th>Status</th><th>SSL</th><th>Disk</th><th>Created</th><th>Contact email</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sites as $s): [$sslText, $sslTone] = $sslLabel($s['ssl']); ?>
      <tr>
        <td><a href="https://<?= $h($s['domain']) ?>/" rel="noopener" target="_blank"><strong><?= $h($s['domain']) ?></strong></a></td>
        <td><?= $h($s['user']) ?></td>
        <td><?= $h($s['plan']) ?></td>
        <td><?php if ($s['suspended']): ?><span class="tag tag-bad" title="<?= $h($s['suspend_reason']) ?>">Suspended</span><?php else: ?><span class="tag tag-ok">Active</span><?php endif; ?></td>
        <td><span class="tag tag-<?= $sslTone ?>"><?= $h($sslText) ?></span></td>
        <td><?= $h($s['disk_used']) ?><?= $s['disk_limit'] !== '' && $s['disk_limit'] !== 'unlimited' ? ' / ' . $h($s['disk_limit']) : '' ?></td>
        <td><?= $s['created'] ? $h(gmdate('M Y', $s['created'])) : '' ?></td>
        <td><?= $h($s['email']) ?></td>
        <td><a class="button ghost small" href="/admin/site.php?user=<?= $h(rawurlencode($s['user'])) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php elseif ($data['error'] === null && $q !== ''): ?>
  <p>No sites match "<?= $h($q) ?>".</p>
<?php endif; ?>
</main>
</body>
</html>
