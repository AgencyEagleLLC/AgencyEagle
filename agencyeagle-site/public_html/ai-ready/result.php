<?php
// The saved report for /ai-ready/report/?t=<token>. The token is the only key, so the link is private to whoever has it.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$token = (string)($_GET['t'] ?? '');
$lead = ae_valid_token($token) ? ae_lead_load($token) : null;
if ($lead === null || ($lead['status'] ?? '') !== 'finished') {
    ae_json(['error' => "We couldn't find that report. The link may be incomplete."], 404);
}
ae_funnel_hit('report');
ae_json(['report' => ae_build_report($lead)]);
