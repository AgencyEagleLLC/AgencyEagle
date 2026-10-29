<?php
// Nightly cron for the lagunait account:
//   /usr/local/bin/php ~/public_html/_private/tools/nightly.php
// See _private/app/nightly.php. Add --no-backups to only watch and rescore, --no-email to skip alert emails.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
require_once __DIR__ . '/../app/nightly.php';

$run = ae_nightly_run(!in_array('--no-backups', $argv, true), !in_array('--no-email', $argv, true));
echo $run === null ? "Another nightly run is still going.\n" : implode("\n", $run['lines']) . "\n";
