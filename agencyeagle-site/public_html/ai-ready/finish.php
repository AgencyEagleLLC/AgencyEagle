<?php
// Scores the answers on the server, combines them with the scan, emails the report once.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$in = ae_read_json_post();
$token = (string)($in['token'] ?? '');
$lead = ae_valid_token($token) ? ae_lead_load($token) : null;
if ($lead === null) {
    ae_json(['error' => 'This quiz has expired. Please start again.'], 404);
}
ignore_user_abort(true);
set_time_limit(120);

$valid = [];
foreach (ae_quiz_questions() as $q) {
    $valid[$q['id']] = array_column($q['options'], 0);
}
$answers = [];
foreach ((array)($in['answers'] ?? []) as $id => $value) {
    if (isset($valid[$id]) && in_array((string)$value, $valid[$id], true)) {
        $answers[$id] = (string)$value;
    }
}

ae_ensure_scan($token);
$result = ae_with_lead_lock($token, static function () use ($token, $answers) {
    $lead = ae_lead_load($token);
    if (($lead['status'] ?? '') === 'finished') {
        return [$lead, false]; // a second finish shows the first result and sends nothing
    }
    $lead['answers'] = $answers;
    $report = ae_build_report($lead);
    $lead['score'] = $report['score'];
    $lead['grade'] = $report['grade']['letter'];
    $lead['finished'] = gmdate('c');
    $lead['status'] = 'finished';
    ae_lead_save($lead);
    return [$lead, true];
});
[$lead, $isNew] = $result;
$report = ae_build_report($lead);

if ($isNew) {
    ae_funnel_hit('finish');
    $sent = ae_mail($lead['email'], 'Your AI-Ready Site Score: ' . $report['score'] . '/100', ae_report_email_text($report, $token));
    $answersText = '';
    foreach (ae_quiz_questions() as $q) {
        foreach ($q['options'] as [$v, $label]) {
            if (($lead['answers'][$q['id']] ?? '') === $v) {
                $answersText .= '- ' . $q['q'] . ' ' . $label . "\n";
            }
        }
    }
    ae_mail(AE_SALES_EMAIL, 'AI-Ready lead: ' . $lead['name'] . ' (' . $report['score'] . '/100) ' . parse_url($lead['site'], PHP_URL_HOST),
        "Name: {$lead['name']}\nEmail: {$lead['email']}\nPhone: {$lead['phone']}\nBusiness: {$lead['business']}\nSite: {$lead['site']}\n"
        . "Score: {$report['score']}/100 (grade {$report['grade']['letter']}), scan {$report['scan_points']}/70, quiz {$report['quiz_points']}/30\n"
        . 'Recommended: ' . $report['offer']['plan'] . "\n\nAnswers:\n$answersText\nReport: " . ae_report_url($token) . "\n",
        $lead['email']);
    ae_with_lead_lock($token, static function () use ($token, $sent) {
        $l = ae_lead_load($token);
        $l['emails']['result'] = $sent ? gmdate('c') : 'failed';
        ae_lead_save($l);
    });
}
ae_json(['report' => $report, 'report_url' => '/ai-ready/report/?t=' . $token]);
