<?php
// SEO brain for hosted sites: runs the AI-Ready live scan against a client's domain,
// turns it into a score out of 100 with the top fixes, and keeps the latest result per domain.
declare(strict_types=1);
require_once __DIR__ . '/site_scan.php';
require_once __DIR__ . '/quiz.php';

const AE_BRAIN_DIR = __DIR__ . '/../data/brain';
const AE_BRAIN_STALE_DAYS = 7;

function ae_brain_path(string $domain): string
{
    if (!is_dir(AE_BRAIN_DIR)) {
        mkdir(AE_BRAIN_DIR, 0700, true);
    }
    return AE_BRAIN_DIR . '/' . preg_replace('/[^a-z0-9.-]/', '', strtolower($domain)) . '.json';
}

function ae_brain_get(string $domain): ?array
{
    $p = ae_brain_path($domain);
    $r = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    return is_array($r) ? $r : null;
}

function ae_brain_is_stale(?array $r): bool
{
    return $r === null || time() - (int)$r['at'] > AE_BRAIN_STALE_DAYS * 86400;
}

/** Scan the live site now and save the result. */
function ae_brain_score(string $domain): array
{
    $scan = ae_scan_site('https://' . $domain . '/');
    $score = (int)round($scan['points'] / $scan['max'] * 100);
    $fixes = [];
    foreach ($scan['checks'] as $c) {
        if ($c['pass'] !== true) {
            $fixes[] = ['id' => $c['id'], 'label' => $c['label'], 'lost' => $c['max'] - $c['points'], 'detail' => $c['detail'], 'fix' => ae_fix_for($c['id'])['fix']];
        }
    }
    usort($fixes, fn($a, $b) => $b['lost'] <=> $a['lost']);
    $result = [
        'domain' => $domain,
        'at' => time(),
        'reached' => $scan['reached'],
        'error' => $scan['error'],
        'score' => $score,
        'grade' => ae_grade($score),
        'fixes' => $fixes,
        'passed' => count($scan['checks']) - count($fixes),
        'checked' => count($scan['checks']),
    ];
    file_put_contents(ae_brain_path($domain), json_encode($result), LOCK_EX);
    return $result;
}

function ae_brain_tone(?array $r): string
{
    if ($r === null) {
        return 'soon';
    }
    return $r['score'] >= 80 ? 'ok' : ($r['score'] >= 60 ? 'warn' : 'bad');
}
