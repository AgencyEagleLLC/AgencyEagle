<?php
// AI-Ready Site Score leads, the funnel counts, and the follow-up email switch.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/followups.php';
require_once __DIR__ . '/../_private/app/admin_layout.php';

$email = ae_require_admin();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ae_check_csrf()) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'followups') {
        ae_set_followups(($_POST['on'] ?? '') === '1');
    } elseif ($action === 'stop' && ae_valid_token((string)($_POST['t'] ?? ''))) {
        $t = (string)$_POST['t'];
        ae_with_lead_lock($t, static function () use ($t, $email) {
            $l = ae_lead_load($t);
            if ($l) {
                $l['followups_stopped'] = gmdate('c') . ' by ' . $email;
                ae_lead_save($l);
            }
        });
    }
    header('Location: /admin/leads.php');
    exit;
}

$leads = ae_leads();
$answerLabel = static function (array $lead, string $qid): string {
    foreach (ae_quiz_questions() as $q) {
        if ($q['id'] === $qid) {
            foreach ($q['options'] as [$v, $label]) {
                if (($lead['answers'][$qid] ?? '') === $v) {
                    return $label;
                }
            }
        }
    }
    return '';
};

if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ai-ready-leads-' . gmdate('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    $cols = ['created', 'status', 'name', 'email', 'phone', 'business', 'site', 'score', 'grade', 'platform', 'who', 'utm_source', 'utm_campaign', 'unsubscribed', 'report'];
    fputcsv($out, $cols);
    foreach ($leads as $l) {
        $row = [$l['created'] ?? '', $l['status'] ?? '', $l['name'] ?? '', $l['email'] ?? '', $l['phone'] ?? '', $l['business'] ?? '', $l['site'] ?? '',
            $l['score'] ?? '', $l['grade'] ?? '', $answerLabel($l, 'platform'), $answerLabel($l, 'who'), $l['utm']['source'] ?? '', $l['utm']['campaign'] ?? '',
            $l['unsubscribed'] ?? '', ($l['status'] ?? '') === 'finished' ? ae_report_url($l['token']) : ''];
        // Neutralize spreadsheet formulas in visitor-typed fields.
        fputcsv($out, array_map(static fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $row));
    }
    exit;
}

ae_send_security_headers();
$f = ae_funnel_totals(30);
$pct = static fn(int $a, int $b): string => $b > 0 ? round(100 * $a / $b) . '%' : '-';
$on = ae_followups_on();
$csrf = ae_csrf_token();
ae_admin_head('AI-Ready Leads', $email, 'leads');
?>
  <h1>AI-Ready Site Score leads</h1>

  <h2>Funnel, last 30 days</h2>
  <div class="stat-row">
    <div class="stat"><strong><?= $f['view'] ?></strong>Quiz page views</div>
    <div class="stat"><strong><?= $f['start'] ?></strong>Signed up (<?= $pct($f['start'], $f['view']) ?>)</div>
    <div class="stat"><strong><?= $f['finish'] ?></strong>Finished (<?= $pct($f['finish'], $f['start']) ?>)</div>
    <div class="stat"><strong><?= $f['report'] ?></strong>Report views</div>
    <div class="stat"><strong><?= $f['cta'] ?></strong>Plan or call clicks</div>
  </div>

  <h2>Follow-up emails</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <input type="hidden" name="action" value="followups">
    <input type="hidden" name="on" value="<?= $on ? '0' : '1' ?>">
    <p>Follow-ups are <strong><?= $on ? 'ON' : 'OFF' ?></strong>. When on, the daily job sends: a reminder 1 day after an unfinished quiz, then emails 2, 5 and 10 days after the score.
      <button class="button" type="submit"><?= $on ? 'Turn off' : 'Turn on' ?></button></p>
  </form>

  <h2><?= count($leads) ?> leads <a class="button ghost" href="/admin/leads.php?csv=1">Download CSV</a></h2>
  <div class="table-wrap">
  <table class="admin-table">
    <thead><tr><th>Date</th><th>Name</th><th>Contact</th><th>Site</th><th>Score</th><th>Built on / Who runs it</th><th>Emails</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): $finished = ($l['status'] ?? '') === 'finished'; ?>
      <tr>
        <td><?= $h(substr($l['created'] ?? '', 0, 10)) ?></td>
        <td><?= $h($l['name'] ?? '') ?><br><?= $h($l['business'] ?? '') ?></td>
        <td><a href="mailto:<?= $h($l['email'] ?? '') ?>"><?= $h($l['email'] ?? '') ?></a><br><?= $h($l['phone'] ?? '') ?></td>
        <td><?= $h(parse_url($l['site'] ?? '', PHP_URL_HOST)) ?></td>
        <td><?= $finished ? '<a href="' . $h('/ai-ready/report/?t=' . $l['token']) . '">' . $h($l['score']) . ' (' . $h($l['grade']) . ')</a>' : 'Not finished' ?></td>
        <td><?= $h($answerLabel($l, 'platform')) ?><br><?= $h($answerLabel($l, 'who')) ?></td>
        <td><?= $h(implode(', ', array_keys(array_filter($l['emails'] ?? [], static fn($v) => $v !== 'failed')))) ?><?= !empty($l['unsubscribed']) ? '<br>Unsubscribed' : '' ?></td>
        <td><?php if (empty($l['unsubscribed']) && empty($l['followups_stopped'])): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="action" value="stop"><input type="hidden" name="t" value="<?= $h($l['token']) ?>"><button class="quiz-back" type="submit">Stop emails</button></form>
        <?php else: ?>Stopped<?php endif ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  </div>
<?php ae_admin_foot();
