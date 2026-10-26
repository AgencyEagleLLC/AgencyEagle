<?php
// Funnel counts for the landing page view and the plan buttons on the report. Counts only, no visitor data.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$in = json_decode((string)file_get_contents('php://input', false, null, 0, 500), true);
$event = is_array($in) ? (string)($in['e'] ?? '') : '';
if (in_array($event, ['view', 'cta'], true)) {
    ae_funnel_hit($event);
}
http_response_code(204);
