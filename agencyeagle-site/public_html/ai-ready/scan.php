<?php
// Starts the live site scan while the visitor answers the questions. finish.php waits for it.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$in = ae_read_json_post();
$token = (string)($in['token'] ?? '');
if (!ae_valid_token($token) || ae_lead_load($token) === null) {
    ae_json(['error' => 'Unknown quiz'], 404);
}
ignore_user_abort(true);
set_time_limit(120);
ae_ensure_scan($token);
ae_json(['ok' => true]);
