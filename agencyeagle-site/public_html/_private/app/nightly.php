<?php
// The nightly sweep over every live site: site watch (hack alerts), SEO brain rescore when over a
// week old, and (full runs only) a fresh backup when the newest is over a week old, keeping the newest 2.
// Emails the admins when there are new alerts. Used by _private/tools/nightly.php and the
// "Check all sites now" button on /admin/sites.php (which skips backups).
declare(strict_types=1);
require_once __DIR__ . '/watch.php';
require_once __DIR__ . '/brain.php';

const AE_NIGHTLY_LOG = __DIR__ . '/../data/nightly.json';
const AE_BACKUP_EVERY_DAYS = 7;

/** Returns the run record: ['at', 'took', 'full', 'lines']. Null if another run holds the lock. */
function ae_nightly_run(bool $full, bool $email = true): ?array
{
    $lock = fopen(__DIR__ . '/../data/nightly.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return null;
    }
    $started = time();
    $lines = [];
    $newAlerts = [];
    $sites = ae_whm_sites(true);
    if ($sites['error'] !== null) {
        $lines[] = "Couldn't read WHM: " . $sites['error'];
    }
    foreach ($sites['sites'] as $s) {
        if ($s['suspended']) {
            continue;
        }
        $d = $s['domain'];
        try {
            $w = ae_watch_check($s);
            $lines[] = "$d: watch " . ae_watch_label($w) . ($w['file_count'] !== null ? " ({$w['file_count']} code files)" : '');
            foreach ($w['new'] as $a) {
                $newAlerts[$d][] = $a;
            }
        } catch (Throwable $e) {
            $lines[] = "$d: watch failed: " . $e->getMessage();
        }
        if (ae_brain_is_stale(ae_brain_get($d))) {
            try {
                $b = ae_brain_score($d);
                $lines[] = "$d: SEO brain " . ($b['reached'] ? $b['score'] . '/100' : "can't reach");
            } catch (Throwable $e) {
                $lines[] = "$d: SEO brain failed: " . $e->getMessage();
            }
        }
        if ($full) {
            try {
                $list = ae_backup_list($s['user']);
                if (!$list || time() - $list[0]['at'] > AE_BACKUP_EVERY_DAYS * 86400) {
                    ae_backup_start('nightly', $s['user'], '');
                    $lines[] = "$d: backup started";
                } elseif ($n = ae_backup_rotate('nightly', $s['user'])) {
                    $lines[] = "$d: removed $n old backup" . ($n === 1 ? '' : 's');
                }
            } catch (Throwable $e) {
                $lines[] = "$d: backup failed: " . $e->getMessage();
            }
        }
    }

    if ($newAlerts && $email) {
        $lines[] = 'Emailed alerts to ' . ae_watch_email($newAlerts) . ' admin(s)';
    }

    $run = ['at' => $started, 'took' => time() - $started, 'full' => $full, 'lines' => $lines];
    file_put_contents(AE_NIGHTLY_LOG, json_encode($run), LOCK_EX);
    flock($lock, LOCK_UN);
    return $run;
}
