<?php
// CRM home: headline numbers, the pipeline board (or a searchable list), and "add a contact".
declare(strict_types=1);
require_once __DIR__ . '/../../_private/app/auth.php';
require_once __DIR__ . '/../../_private/app/quiz.php';
require_once __DIR__ . '/../../_private/app/crm.php';
require_once __DIR__ . '/../../_private/app/crm_layout.php';

$email = ae_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ae_check_csrf() && ($_POST['action'] ?? '') === 'add') {
    $name = ae_crm_line($_POST['name'] ?? '', 120);
    $addr = ae_crm_line($_POST['email'] ?? '', 200);
    if ($name !== '' || $addr !== '') {
        $id = ae_crm_update(static function (array &$data) use ($name, $addr, $email) {
            if ($addr !== '' && ($i = ae_crm_find($data, '', $addr)) !== null) {
                return $data['contacts'][$i]['id']; // already here: open that one
            }
            $source = (string)($_POST['source'] ?? 'manual');
            $c = ae_crm_new_contact([
                'name' => $name, 'email' => $addr,
                'business' => ae_crm_line($_POST['business'] ?? '', 160),
                'phone' => ae_crm_line($_POST['phone'] ?? '', 40),
                'website' => ae_crm_line($_POST['website'] ?? '', 300),
            ], isset(ae_crm_sources()[$source]) ? $source : 'manual', $email);
            $data['contacts'][] = $c;
            return $c['id'];
        });
        header('Location: /admin/crm/contact.php?id=' . $id);
        exit;
    }
    header('Location: /admin/crm/');
    exit;
}

ae_crm_sync_quiz_leads(ae_leads());
$data = ae_crm_load();
$stages = ae_crm_stages();
$plans = ae_crm_plans();
$view = ($_GET['view'] ?? '') === 'list' || isset($_GET['q']) ? 'list' : 'board';
$q = mb_strtolower(ae_crm_line($_GET['q'] ?? '', 100));
$stageFilter = (string)($_GET['stage'] ?? '');

$contacts = $data['contacts'];
usort($contacts, static fn($a, $b) => strcmp($b['updated'], $a['updated']));

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="crm-contacts-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'business', 'email', 'phone', 'website', 'stage', 'plan', 'monthly', 'build', 'source', 'next_step', 'quiz_score', 'created', 'updated']);
    foreach ($contacts as $c) {
        [$monthly, $build] = ae_crm_value($c);
        $row = [$c['name'], $c['business'], $c['email'], $c['phone'], $c['website'], $stages[$c['stage']] ?? $c['stage'], $plans[$c['plan']][0] ?? '',
            $monthly, $build, ae_crm_sources()[$c['source']] ?? $c['source'], $c['next_step'], $c['score']['score'] ?? '', $c['created'], $c['updated']];
        fputcsv($out, array_map(static fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $row));
    }
    exit;
}

$t = ae_crm_totals($data);
$csrf = ae_csrf_token();
$matches = static function (array $c) use ($q, $stageFilter): bool {
    if ($stageFilter !== '' && $c['stage'] !== $stageFilter) {
        return false;
    }
    return $q === '' || str_contains(mb_strtolower(implode(' ', [$c['name'], $c['business'], $c['email'], $c['phone'], $c['website']])), $q);
};
$card = static function (array $c) use ($plans): string {
    [$monthly, $build] = ae_crm_value($c);
    $value = array_filter([$monthly ? ae_crm_money($monthly) . '/mo' : '', $build ? ae_crm_money($build) . ' build' : '']);
    $open = count(array_filter($c['tasks'], static fn($x) => empty($x['done'])));
    return '<a class="crm-card" href="/admin/crm/contact.php?id=' . ae_h($c['id']) . '">'
        . '<strong>' . ae_h($c['name'] ?: $c['email']) . '</strong>'
        . ($c['business'] !== '' ? '<span>' . ae_h($c['business']) . '</span>' : '')
        . ($value ? '<span class="crm-value">' . ae_h(implode(' + ', $value)) . '</span>' : '')
        . ($c['next_step'] !== '' ? '<span class="crm-next">Next: ' . ae_h($c['next_step']) . '</span>' : '')
        . '<span class="crm-meta">' . ae_h(ae_crm_sources()[$c['source']] ?? '') . ($c['score'] ? ' · Score ' . ae_h($c['score']['score']) : '') . ($open ? ' · ' . $open . ' task' . ($open > 1 ? 's' : '') : '') . '</span>'
        . '</a>';
};

ae_crm_head($view === 'list' ? 'All contacts' : 'Pipeline', $email, $view === 'list' ? 'list' : 'index');
?>
  <h1><?= $view === 'list' ? 'All contacts' : 'Pipeline' ?></h1>

  <div class="stat-row">
    <div class="stat"><strong><?= ae_crm_money($t['mrr']) ?></strong>Monthly recurring (<?= $t['clients'] ?> client<?= $t['clients'] === 1 ? '' : 's' ?>)</div>
    <div class="stat"><strong><?= ae_crm_money($t['pipeline_mrr']) ?></strong>Monthly in pipeline (<?= $t['open'] ?> open)</div>
    <div class="stat"><strong><?= ae_crm_money($t['builds_won']) ?></strong>Builds won</div>
    <div class="stat"><strong><?= ae_crm_money($t['pipeline_builds']) ?></strong>Builds in pipeline</div>
    <div class="stat"><strong><?= $t['due'] ?></strong><a href="/admin/crm/tasks.php">Tasks due today or late</a></div>
  </div>

  <form class="crm-search" method="get" action="/admin/crm/">
    <label for="q" class="sr-only">Search contacts</label>
    <input id="q" name="q" type="search" placeholder="Search name, business, email, phone, site" value="<?= ae_h($_GET['q'] ?? '') ?>">
    <select name="stage" aria-label="Stage">
      <option value="">Any stage</option>
      <?php foreach ($stages as $k => $label): ?><option value="<?= ae_h($k) ?>"<?= $stageFilter === $k ? ' selected' : '' ?>><?= ae_h($label) ?></option><?php endforeach ?>
    </select>
    <button class="button" type="submit">Search</button>
    <a class="button ghost" href="/admin/crm/?csv=1">Download CSV</a>
  </form>

  <details class="crm-add">
    <summary class="button">+ Add contact</summary>
    <form method="post" class="crm-form">
      <input type="hidden" name="csrf" value="<?= ae_h($csrf) ?>">
      <input type="hidden" name="action" value="add">
      <label>Name <input name="name" maxlength="120"></label>
      <label>Business <input name="business" maxlength="160"></label>
      <label>Email <input name="email" type="email" maxlength="200"></label>
      <label>Phone <input name="phone" maxlength="40"></label>
      <label>Website <input name="website" maxlength="300"></label>
      <label>Source <select name="source"><?php foreach (ae_crm_sources() as $k => $label): ?><option value="<?= ae_h($k) ?>"><?= ae_h($label) ?></option><?php endforeach ?></select></label>
      <button class="button" type="submit">Add</button>
    </form>
  </details>

<?php if ($data['contacts'] === []): ?>
  <p class="notice">No contacts yet. AI-Ready quiz sign-ups and contact-form messages show up here on their own, or add one above.</p>
<?php elseif ($view === 'board'): ?>
  <div class="crm-board">
    <?php foreach ($stages as $k => $label): $col = array_filter($contacts, static fn($c) => $c['stage'] === $k); ?>
      <section class="crm-col crm-col-<?= ae_h($k) ?>">
        <h2><?= ae_h($label) ?> <span><?= count($col) ?></span></h2>
        <?php foreach ($k === 'lost' ? array_slice($col, 0, 10) : $col as $c) echo $card($c); ?>
        <?php if ($k === 'lost' && count($col) > 10): ?><a href="/admin/crm/?view=list&amp;stage=lost">See all <?= count($col) ?></a><?php endif ?>
      </section>
    <?php endforeach ?>
  </div>
<?php else: $rows = array_filter($contacts, $matches); ?>
  <p><?= count($rows) ?> of <?= count($contacts) ?> contacts</p>
  <div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Contact</th><th>Stage</th><th>Plan</th><th>Next step</th><th>Source</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr>
        <td><a href="/admin/crm/contact.php?id=<?= ae_h($c['id']) ?>"><?= ae_h($c['name'] ?: '(no name)') ?></a><br><?= ae_h($c['business']) ?></td>
        <td><?= ae_h($c['email']) ?><br><?= ae_h($c['phone']) ?></td>
        <td><?= ae_h($stages[$c['stage']] ?? $c['stage']) ?></td>
        <td><?= ae_h($plans[$c['plan']][0] ?? '') ?><?= !empty($c['build']) ? '<br>+ build' : '' ?></td>
        <td><?= ae_h($c['next_step']) ?></td>
        <td><?= ae_h(ae_crm_sources()[$c['source']] ?? $c['source']) ?></td>
        <td><?= ae_h(ae_crm_date($c['updated'])) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
<?php endif ?>
<?php ae_crm_foot();
