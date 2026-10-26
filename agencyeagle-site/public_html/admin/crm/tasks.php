<?php
// Every open follow-up task across the CRM, late ones first.
declare(strict_types=1);
require_once __DIR__ . '/../../_private/app/auth.php';
require_once __DIR__ . '/../../_private/app/crm.php';
require_once __DIR__ . '/../../_private/app/crm_layout.php';

$email = ae_require_admin();
$tasks = ae_crm_open_tasks(ae_crm_load());
$csrf = ae_csrf_token();
$today = date('Y-m-d');
$groups = ['Late' => [], 'Today' => [], 'Coming up' => [], 'No due date' => []];
foreach ($tasks as $row) {
    $due = $row[1]['due'];
    $groups[$due === '' ? 'No due date' : ($due < $today ? 'Late' : ($due === $today ? 'Today' : 'Coming up'))][] = $row;
}

ae_crm_head('Tasks', $email, 'tasks');
?>
  <h1>Tasks</h1>
  <?php if ($tasks === []): ?><p class="notice">No open tasks. Add follow-ups from a contact's page.</p><?php endif ?>
  <?php foreach ($groups as $label => $rows): if ($rows === []) continue; ?>
    <h2><?= ae_h($label) ?> <span class="crm-meta"><?= count($rows) ?></span></h2>
    <ul class="crm-tasks">
      <?php foreach ($rows as [$c, $t]): ?>
        <li class="<?= $label === 'Late' ? 'late' : ($label === 'Today' ? 'today' : '') ?>">
          <form method="post" action="/admin/crm/contact.php"><input type="hidden" name="csrf" value="<?= ae_h($csrf) ?>"><input type="hidden" name="id" value="<?= ae_h($c['id']) ?>"><input type="hidden" name="task" value="<?= ae_h($t['id']) ?>"><input type="hidden" name="back" value="tasks"><button class="crm-check" name="action" value="task_done" title="Mark done">✓</button></form>
          <span><?= ae_h($t['text']) ?> · <a href="/admin/crm/contact.php?id=<?= ae_h($c['id']) ?>"><?= ae_h($c['name'] ?: $c['email']) ?></a><?php if ($t['due'] !== ''): ?> <em>due <?= ae_h(ae_crm_date($t['due'])) ?></em><?php endif ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endforeach ?>
<?php ae_crm_foot();
