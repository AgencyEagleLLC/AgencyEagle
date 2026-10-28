<?php
// Every change made to hosting accounts from this admin, newest first.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

$email = ae_require_admin();
ae_send_security_headers();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
$entries = ae_whm_audit_entries();
ae_admin_head('Audit log', $email, 'audit');
?>
  <h1>Audit log</h1>
  <p>The last 200 changes made to hosting accounts from this admin. Passwords are never logged.</p>
<?php if (!$entries): ?>
  <p>Nothing yet.</p>
<?php else: ?>
  <div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>When (UTC)</th><th>Who</th><th>Action</th><th>Account</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
      <tr>
        <td><?= $h(gmdate('M j, H:i', (int)$e['at'])) ?></td>
        <td><?= $h($e['who']) ?></td>
        <td><?= $h(str_replace('_', ' ', (string)$e['action'])) ?></td>
        <td><?= $h($e['target']) ?></td>
        <td><?php if ($e['ok']): ?><span class="tag tag-ok">Done</span><?php else: ?><span class="tag tag-bad">Failed</span> <?= $h($e['detail']) ?><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
<?php ae_admin_foot();
