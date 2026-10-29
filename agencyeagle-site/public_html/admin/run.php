<?php
// Runs a batch started from the Sites page one site per request, so no request outlives the
// proxy's one-minute limit: each step checks a site, then the page reloads itself for the next.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';
require_once __DIR__ . '/../_private/app/watch.php';
require_once __DIR__ . '/../_private/app/brain.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

$run = $_SESSION['batch'] ?? null;
if (!is_array($run)) {
    header('Location: /admin/sites.php');
    exit;
}
if ($run['i'] < count($run['steps'])) {
    [$kind, $user] = $run['steps'][$run['i']];
    session_write_close(); // keep the admin's other tabs usable while this step runs
    @set_time_limit(90);
    $site = ae_whm_site($user);
    $line = '';
    $new = [];
    if ($site === null) {
        $line = $user . ': not found on the server any more';
    } elseif ($kind === 'watch') {
        try {
            $w = ae_watch_check($site, AE_WATCH_WEB_SECONDS);
            $line = $site['domain'] . ': ' . ae_watch_label($w);
            $new = $w['new'];
        } catch (Throwable $e) {
            $line = $site['domain'] . ': check failed (' . $e->getMessage() . ')';
        }
    } else {
        try {
            $b = ae_brain_score($site['domain']);
            $line = $site['domain'] . ': ' . ($b['reached'] ? $b['score'] . '/100' : 'can\'t reach');
        } catch (Throwable $e) {
            $line = $site['domain'] . ': scoring failed (' . $e->getMessage() . ')';
        }
    }
    ae_session_start();
    $run = $_SESSION['batch'] ?? $run;
    $run['lines'][] = $line;
    if ($new) {
        $run['new'][$site['domain']] = $new;
    }
    $run['i']++;
    $_SESSION['batch'] = $run;
}
$done = $run['i'] >= count($run['steps']);
if ($done) {
    unset($_SESSION['batch']);
    if (!empty($run['new']) && empty($run['emailed'])) {
        ae_watch_email($run['new']);
    }
}
$total = count($run['steps']);
// A Refresh header moves on to the next site (CSP allows no inline script).
if (!$done) {
    header('Refresh: 0; url=/admin/run.php');
}
ae_admin_head($run['title'], $email, 'sites');
?>
  <p><a href="/admin/sites.php">← Back to sites</a></p>
  <h1><?= $h($run['title']) ?></h1>
<?php if ($done): ?>
  <p class="notice" role="status">Done: <?= $total ?> site<?= $total === 1 ? '' : 's' ?>.<?= !empty($run['new']) ? ' New alerts on ' . count($run['new']) . ' site' . (count($run['new']) === 1 ? '' : 's') . (ae_admin_emails() ? ', emailed to you.' : '.') : '' ?></p>
<?php else: ?>
  <p role="status">Checked <?= (int)$run['i'] ?> of <?= $total ?>. Keep this tab open; it moves on by itself.</p>
  <progress max="<?= $total ?>" value="<?= (int)$run['i'] ?>"></progress>
<?php endif; ?>
<?php if ($run['lines']): ?>
  <ul>
  <?php foreach ($run['lines'] as $l): ?>
    <li><?= $h($l) ?></li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if ($done): ?>
  <p><a class="button" href="/admin/sites.php">See all sites</a></p>
<?php endif; ?>
<?php ae_admin_foot();
