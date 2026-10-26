<?php
// Daily cron: php ~/public_html/_private/tools/send-followups.php        (add --dry-run to preview)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../app/followups.php';
foreach (ae_send_due_followups(in_array('--dry-run', $argv, true)) as $line) {
    echo $line, "\n";
}
