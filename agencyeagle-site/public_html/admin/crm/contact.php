<?php
// One contact: details, stage and plan, notes, follow-up tasks, and their AI-Ready quiz score.
declare(strict_types=1);
require_once __DIR__ . '/../../_private/app/auth.php';
require_once __DIR__ . '/../../_private/app/crm.php';
require_once __DIR__ . '/../../_private/app/crm_layout.php';

$email = ae_require_admin();
$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
if (!ae_crm_valid_id($id)) {
    header('Location: /admin/crm/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ae_check_csrf()) {
    $action = (string)($_POST['action'] ?? '');
    $gone = ae_crm_update(static function (array &$data) use ($id, $action, $email): bool {
        $i = ae_crm_find($data, $id);
        if ($i === null) {
            return true;
        }
        $c = &$data['contacts'][$i];
        $now = gmdate('c');
        switch ($action) {
            case 'save':
                foreach (['name' => 120, 'business' => 160, 'email' => 200, 'phone' => 40, 'website' => 300, 'next_step' => 200] as $k => $max) {
                    $c[$k] = ae_crm_line($_POST[$k] ?? '', $max);
                }
                $plan = (string)($_POST['plan'] ?? '');
                $c['plan'] = isset(ae_crm_plans()[$plan]) ? $plan : '';
                $c['build'] = ($_POST['build'] ?? '') === '1';
                $source = (string)($_POST['source'] ?? '');
                if (isset(ae_crm_sources()[$source])) {
                    $c['source'] = $source;
                }
                // fall through: the stage is on the same form
            case 'stage':
                $stage = (string)($_POST['stage'] ?? $c['stage']);
                if (isset(ae_crm_stages()[$stage]) && $stage !== $c['stage']) {
                    ae_crm_add_note($c, 'Stage: ' . ae_crm_stages()[$c['stage']] . ' → ' . ae_crm_stages()[$stage], $email);
                    $c['stage'] = $stage;
                    $c['stage_changed'] = $now;
                }
                break;
            case 'note':
                $text = ae_crm_text($_POST['text'] ?? '', 5000);
                if ($text !== '') {
                    ae_crm_add_note($c, $text, $email);
                }
                break;
            case 'delete_note':
                $c['notes'] = array_values(array_filter($c['notes'], static fn($n) => $n['id'] !== ($_POST['note'] ?? '')));
                break;
            case 'task':
                $text = ae_crm_line($_POST['text'] ?? '', 300);
                $due = (string)($_POST['due'] ?? '');
                if ($text !== '') {
                    $c['tasks'][] = ['id' => ae_crm_id(), 'text' => $text, 'due' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : '', 'done' => '', 'created' => $now, 'by' => $email];
                }
                break;
            case 'task_done':
            case 'task_undo':
            case 'task_delete':
                foreach ($c['tasks'] as $k => $task) {
                    if ($task['id'] === ($_POST['task'] ?? '')) {
                        if ($action === 'task_delete') {
                            unset($c['tasks'][$k]);
                        } else {
                            $c['tasks'][$k]['done'] = $action === 'task_done' ? $now : '';
                        }
                    }
                }
                $c['tasks'] = array_values($c['tasks']);
                break;
            case 'delete':
                if (($_POST['confirm'] ?? '') === 'DELETE') {
                    unset($data['contacts'][$i]);
                    $data['contacts'] = array_values($data['contacts']);
                    return true;
                }
                break;
        }
        $c['updated'] = $now;
        return false;
    });
    $back = (string)($_POST['back'] ?? '');
    if ($gone) {
        header('Location: /admin/crm/');
    } elseif ($back === 'tasks') {
        header('Location: /admin/crm/tasks.php');
    } else {
        header('Location: /admin/crm/contact.php?id=' . $id);
    }
    exit;
}

$data = ae_crm_load();
$i = ae_crm_find($data, $id);
if ($i === null) {
    header('Location: /admin/crm/');
    exit;
}
$c = $data['contacts'][$i];
$csrf = ae_csrf_token();
$today = date('Y-m-d');
$hidden = '<input type="hidden" name="csrf" value="' . ae_h($csrf) . '"><input type="hidden" name="id" value="' . ae_h($id) . '">';
[$monthly, $build] = ae_crm_value($c);

ae_crm_head($c['name'] ?: $c['email'] ?: 'Contact', $email, 'list');
?>
  <p><a href="/admin/crm/">← Pipeline</a></p>
  <h1><?= ae_h($c['name'] ?: $c['email'] ?: '(no name)') ?></h1>
  <?php
  $bits = [];
  if ($c['business'] !== '') $bits[] = ae_h($c['business']);
  if ($c['email'] !== '') $bits[] = '<a href="mailto:' . ae_h($c['email']) . '">' . ae_h($c['email']) . '</a>';
  if ($c['phone'] !== '') $bits[] = '<a href="tel:' . ae_h(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . ae_h($c['phone']) . '</a>';
  if ($c['website'] !== '') $bits[] = '<a href="' . ae_h(preg_match('#^https?://#i', $c['website']) ? $c['website'] : 'https://' . $c['website']) . '" rel="noopener noreferrer" target="_blank">' . ae_h(preg_replace('#^https?://#i', '', $c['website'])) . '</a>';
  ?>
  <p class="crm-sub"><?= implode(' · ', $bits) ?></p>

  <form method="post" class="crm-stages" aria-label="Move to stage">
    <?= $hidden ?><input type="hidden" name="action" value="stage">
    <?php foreach (ae_crm_stages() as $k => $label): ?>
      <button type="submit" name="stage" value="<?= ae_h($k) ?>" class="crm-stage<?= $c['stage'] === $k ? ' current' : '' ?>"<?= $c['stage'] === $k ? ' aria-current="step"' : '' ?>><?= ae_h($label) ?></button>
    <?php endforeach ?>
  </form>

  <div class="crm-detail">
    <div>
      <h2>Tasks</h2>
      <?php $open = array_filter($c['tasks'], static fn($t) => empty($t['done'])); $done = array_filter($c['tasks'], static fn($t) => !empty($t['done'])); ?>
      <?php if ($open === []): ?><p class="crm-meta">No open tasks.</p><?php endif ?>
      <ul class="crm-tasks">
        <?php foreach ($open as $t): ?>
          <li class="<?= $t['due'] !== '' && $t['due'] < $today ? 'late' : ($t['due'] === $today ? 'today' : '') ?>">
            <form method="post"><?= $hidden ?><input type="hidden" name="task" value="<?= ae_h($t['id']) ?>"><button class="crm-check" name="action" value="task_done" title="Mark done">✓</button></form>
            <span><?= ae_h($t['text']) ?><?php if ($t['due'] !== ''): ?> <em>due <?= ae_h(ae_crm_date($t['due'])) ?></em><?php endif ?></span>
            <form method="post"><?= $hidden ?><input type="hidden" name="task" value="<?= ae_h($t['id']) ?>"><button class="quiz-back" name="action" value="task_delete">Delete</button></form>
          </li>
        <?php endforeach ?>
      </ul>
      <form method="post" class="crm-inline">
        <?= $hidden ?><input type="hidden" name="action" value="task">
        <label class="sr-only" for="task-text">New task</label>
        <input id="task-text" name="text" maxlength="300" placeholder="Follow-up task, like “Call about the proposal”" required>
        <label class="sr-only" for="task-due">Due</label>
        <input id="task-due" name="due" type="date" value="<?= ae_h(date('Y-m-d', strtotime('+2 days'))) ?>">
        <button class="button" type="submit">Add task</button>
      </form>
      <?php if ($done !== []): ?>
        <details><summary><?= count($done) ?> done</summary>
          <ul class="crm-tasks done">
            <?php foreach ($done as $t): ?>
              <li><span><?= ae_h($t['text']) ?> <em>done <?= ae_h(ae_crm_date($t['done'])) ?></em></span>
                <form method="post"><?= $hidden ?><input type="hidden" name="task" value="<?= ae_h($t['id']) ?>"><button class="quiz-back" name="action" value="task_undo">Undo</button></form></li>
            <?php endforeach ?>
          </ul>
        </details>
      <?php endif ?>

      <h2>Notes</h2>
      <form method="post">
        <?= $hidden ?><input type="hidden" name="action" value="note">
        <label class="sr-only" for="note">New note</label>
        <textarea id="note" name="text" rows="3" maxlength="5000" placeholder="Call notes, what they need, what was promised" required></textarea>
        <button class="button" type="submit">Add note</button>
      </form>
      <ul class="crm-notes">
        <?php foreach (array_reverse($c['notes']) as $n): ?>
          <li>
            <div class="crm-meta"><?= ae_h(date('M j, Y g:ia', strtotime($n['at']))) ?> · <?= ae_h($n['by']) ?>
              <form method="post" class="crm-del"><?= $hidden ?><input type="hidden" name="note" value="<?= ae_h($n['id']) ?>"><button class="quiz-back" name="action" value="delete_note">Delete</button></form></div>
            <p><?= nl2br(ae_h($n['text'])) ?></p>
          </li>
        <?php endforeach ?>
      </ul>
    </div>

    <aside>
      <h2>Deal</h2>
      <p class="crm-value-big"><?= $monthly ? ae_crm_money($monthly) . '/mo' : 'No plan yet' ?><?= $build ? ' + ' . ae_crm_money($build) . ' build' : '' ?></p>
      <?php if ($c['score']): ?>
        <p>AI-Ready score: <a href="/ai-ready/report/?t=<?= ae_h($c['score']['token']) ?>" target="_blank" rel="noopener"><?= ae_h($c['score']['score']) ?> (<?= ae_h($c['score']['grade']) ?>)</a></p>
      <?php elseif ($c['lead_tokens']): ?>
        <p class="crm-meta">Started the AI-Ready quiz but hasn't finished.</p>
      <?php endif ?>
      <form method="post" class="crm-form">
        <?= $hidden ?><input type="hidden" name="action" value="save">
        <label>Stage <select name="stage"><?php foreach (ae_crm_stages() as $k => $label): ?><option value="<?= ae_h($k) ?>"<?= $c['stage'] === $k ? ' selected' : '' ?>><?= ae_h($label) ?></option><?php endforeach ?></select></label>
        <label>Plan <select name="plan"><?php foreach (ae_crm_plans() as $k => [$label]): ?><option value="<?= ae_h($k) ?>"<?= $c['plan'] === $k ? ' selected' : '' ?>><?= ae_h($label) ?></option><?php endforeach ?></select></label>
        <label class="crm-checkbox"><input type="checkbox" name="build" value="1"<?= !empty($c['build']) ? ' checked' : '' ?>> Done-for-you build (<?= ae_crm_money(AE_CRM_BUILD_PRICE) ?>)</label>
        <label>Next step <input name="next_step" maxlength="200" value="<?= ae_h($c['next_step']) ?>"></label>
        <label>Name <input name="name" maxlength="120" value="<?= ae_h($c['name']) ?>"></label>
        <label>Business <input name="business" maxlength="160" value="<?= ae_h($c['business']) ?>"></label>
        <label>Email <input name="email" type="email" maxlength="200" value="<?= ae_h($c['email']) ?>"></label>
        <label>Phone <input name="phone" maxlength="40" value="<?= ae_h($c['phone']) ?>"></label>
        <label>Website <input name="website" maxlength="300" value="<?= ae_h($c['website']) ?>"></label>
        <label>Source <select name="source"><?php foreach (ae_crm_sources() as $k => $label): ?><option value="<?= ae_h($k) ?>"<?= $c['source'] === $k ? ' selected' : '' ?>><?= ae_h($label) ?></option><?php endforeach ?></select></label>
        <button class="button" type="submit">Save</button>
      </form>
      <p class="crm-meta">Added <?= ae_h(ae_crm_date($c['created'])) ?> · in this stage since <?= ae_h(ae_crm_date($c['stage_changed'])) ?></p>
      <details class="crm-danger">
        <summary>Delete contact</summary>
        <form method="post"><?= $hidden ?><input type="hidden" name="action" value="delete">
          <label>Type DELETE to remove this contact and its notes and tasks <input name="confirm" autocomplete="off"></label>
          <button class="button" type="submit">Delete for good</button></form>
      </details>
    </aside>
  </div>
<?php ae_crm_foot();
