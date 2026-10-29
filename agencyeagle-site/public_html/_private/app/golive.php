<?php
// Go-live for a client site, the last Activate step: check the domain points here and loads over
// HTTPS, then announce it to search engines (IndexNow: Bing, Yandex, Seznam, Naver) with a first
// SEO score, site watch baseline and backup, and email the client their /admin link.
// What has been done is kept in data/golive/<domain>.json.
declare(strict_types=1);
require_once __DIR__ . '/watch.php';
require_once __DIR__ . '/brain.php';
require_once __DIR__ . '/clients.php';

const AE_GOLIVE_DIR = __DIR__ . '/../data/golive';

function ae_golive_path(string $domain): string
{
    if (!is_dir(AE_GOLIVE_DIR)) {
        mkdir(AE_GOLIVE_DIR, 0700, true);
    }
    return AE_GOLIVE_DIR . '/' . preg_replace('/[^a-z0-9.-]/', '', strtolower($domain)) . '.json';
}

function ae_golive_get(string $domain): array
{
    $p = ae_golive_path($domain);
    $r = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    return is_array($r) ? $r : [];
}

function ae_golive_put(string $domain, array $g): void
{
    file_put_contents(ae_golive_path($domain), json_encode($g), LOCK_EX);
}

/** Live checks: ['dns' => bool, 'www' => bool, 'ips' => [...], 'https' => bool, 'error' => string]. */
function ae_golive_check(array $site): array
{
    $ips = @gethostbynamel($site['domain']) ?: [];
    $www = @gethostbynamel('www.' . $site['domain']) ?: [];
    $out = ['dns' => in_array(AE_SERVER_IP, $ips, true), 'www' => in_array(AE_SERVER_IP, $www, true), 'ips' => $ips, 'https' => false, 'error' => ''];
    if ($out['dns']) {
        $r = ae_safe_get('https://' . $site['domain'] . '/', 200_000);
        $out['https'] = $r['ok'] && str_starts_with($r['url'], 'https://');
        $out['error'] = $r['ok'] ? '' : ($r['error'] ?: 'HTTP ' . $r['status']);
    }
    return $out;
}

/**
 * Tell search engines the site is live: put an IndexNow key file on the site, then submit the
 * home page and up to 200 sitemap URLs. Also runs the first SEO score and site watch, and starts
 * a first backup. Returns plain-language results, one per line.
 */
function ae_golive_announce(string $who, array $site): array
{
    $d = $site['domain'];
    $g = ae_golive_get($d);
    $key = $g['indexnow_key'] ?? bin2hex(random_bytes(16));
    ae_whm_save_file($who, $site['user'], 'public_html', $key . '.txt', $key);
    $urls = ['https://' . $d . '/'];
    $sm = ae_safe_get('https://' . $d . '/sitemap.xml', 2_000_000);
    if ($sm['ok'] && preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $sm['body'], $m)) {
        foreach ($m[1] as $u) {
            if (strtolower((string)parse_url($u, PHP_URL_HOST)) === $d || strtolower((string)parse_url($u, PHP_URL_HOST)) === 'www.' . $d) {
                $urls[] = html_entity_decode($u);
            }
        }
    }
    $urls = array_slice(array_values(array_unique($urls)), 0, 200);
    $ch = curl_init('https://api.indexnow.org/indexnow');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS => json_encode(['host' => $d, 'key' => $key, 'keyLocation' => 'https://' . $d . '/' . $key . '.txt', 'urlList' => $urls]),
    ]);
    curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $lines = [];
    $ok = $status === 200 || $status === 202;
    $lines[] = $ok
        ? 'Sent ' . count($urls) . ' page' . (count($urls) === 1 ? '' : 's') . ' to Bing and the other IndexNow search engines.'
        : 'IndexNow didn\'t accept the submission (HTTP ' . $status . '). Try again in a few minutes.';
    $lines[] = $sm['ok'] ? 'Google reads the sitemap on its next crawl. Add the site in Google Search Console to speed that up.' : 'No sitemap.xml found. Add one so Google finds every page.';
    try {
        $b = ae_brain_score($d);
        $lines[] = 'First SEO brain score: ' . ($b['reached'] ? $b['score'] . '/100.' : 'couldn\'t reach the site.');
    } catch (Throwable $e) {
        $lines[] = 'SEO brain didn\'t run: ' . $e->getMessage();
    }
    try {
        $w = ae_watch_check($site, 20);
        $lines[] = 'Site watch is set: ' . strtolower(ae_watch_label($w)) . '.';
    } catch (Throwable $e) {
        $lines[] = 'Site watch didn\'t run: ' . $e->getMessage();
    }
    try {
        ae_backup_start($who, $site['user'], '');
        $lines[] = 'First full backup started.';
    } catch (Throwable $e) {
        $lines[] = 'Backup didn\'t start: ' . $e->getMessage();
    }
    $g['indexnow_key'] = $key;
    if ($ok) {
        $g['announced_at'] = time();
        $g['announced_urls'] = count($urls);
    }
    ae_golive_put($d, $g);
    ae_whm_audit($who, 'go_live_announce', $d, $ok, 'IndexNow HTTP ' . $status . ', ' . count($urls) . ' URLs');
    return $lines;
}

function ae_golive_welcome_text(string $domain): string
{
    return "Hi,\n\n"
        . "Your new website is live at https://$domain/\n\n"
        . "Your site admin is at https://$domain/admin\n"
        . "Sign in there with this Google account to:\n"
        . "  - see your SEO score and the top fixes for Google, Bing and AI assistants\n"
        . "  - make a backup or download one\n"
        . "  - get your Site Brief, a file you can hand to Claude, ChatGPT or a developer\n\n"
        . "We watch your site for downtime and hacking, keep backups, and renew your SSL certificate automatically.\n\n"
        . "Questions or changes? Reply to this email or call (865) 300-5397.\n\n"
        . "Thanks for hosting with Agency Eagle.\n"
        . "https://agencyeagle.com/\n";
}

/** Email the welcome note to everyone with portal access. Returns the addresses it went to. */
function ae_golive_welcome(string $who, array $site): array
{
    $to = ae_client_emails($site['domain']);
    if (!$to) {
        throw new RuntimeException('Add the client\'s Google email under Client portal access first.');
    }
    foreach ($to as $addr) {
        if (!ae_mail($addr, 'Your new website is live: ' . $site['domain'], ae_golive_welcome_text($site['domain']), 'help@agencyeagle.com')) {
            throw new RuntimeException('The server didn\'t accept the email to ' . $addr . '.');
        }
    }
    $g = ae_golive_get($site['domain']);
    $g['welcomed_at'] = time();
    $g['welcomed_to'] = $to;
    ae_golive_put($site['domain'], $g);
    ae_whm_audit($who, 'welcome_email', $site['domain'], true, implode(', ', $to));
    return $to;
}
