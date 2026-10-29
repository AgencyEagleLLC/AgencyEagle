<?php
// Site watch (hack alerts). Each night, and on demand, it:
//  - loads the home page: is it up, how fast, and does it carry spam words, hidden iframes,
//    scripts from new hosts or obfuscated JavaScript that weren't there when it was last reviewed;
//  - lists the code files in public_html (through cPanel's file manager API) and compares them
//    with the reviewed baseline, so a new or changed .php/.js/.htaccess file stands out.
// Change alerts stay open until someone marks the site reviewed, which makes today's state the
// new baseline. Down and SSL alerts clear by themselves once fixed.
declare(strict_types=1);
require_once __DIR__ . '/backups.php';
require_once __DIR__ . '/site_scan.php';

const AE_WATCH_DIR = __DIR__ . '/../data/watch';
const AE_WATCH_EXT = '/(\.(php\d?|phtml|phar|inc|js|html?|cgi|pl|py|sh|ini|suspected)|^\.htaccess|^\.user\.ini)$/i';
const AE_WATCH_SKIP = '#(^|/)(\.git|node_modules|cache|\.well-known|wp-content/uploads|wp-content/cache|wp-content/upgrade|error_log)(/|$)#';
const AE_WATCH_MAX_DIRS = 800;
const AE_WATCH_WEB_SECONDS = 35.0; // file-walk budget inside a web request (the proxy gives up at about 60s)
const AE_WATCH_SPAM = [
    'viagra', 'cialis', 'levitra', 'casino', 'poker online', 'slot gacor', 'slot online', 'togel', 'bokep',
    'porn', 'payday loan', 'replica watches', 'essay writing', 'buy followers', 'crack download',
];
const AE_WATCH_JS = ['eval(', 'atob(', 'fromcharcode', 'unescape(', 'document.write(unescape', '\\x', 'base64_decode'];

function ae_watch_path(string $domain): string
{
    if (!is_dir(AE_WATCH_DIR)) {
        mkdir(AE_WATCH_DIR, 0700, true);
    }
    return AE_WATCH_DIR . '/' . preg_replace('/[^a-z0-9.-]/', '', strtolower($domain)) . '.json';
}

function ae_watch_get(string $domain): ?array
{
    $p = ae_watch_path($domain);
    $r = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    return is_array($r) ? $r : null;
}

function ae_watch_put(array $w): void
{
    file_put_contents(ae_watch_path($w['domain']), json_encode($w), LOCK_EX);
}

/** What the home page shows right now. */
function ae_watch_page(string $domain): array
{
    return ae_watch_parse($domain, ae_safe_get('https://' . $domain . '/'));
}

/** Pull the watch signals out of a fetched page (an ae_safe_get result). */
function ae_watch_parse(string $domain, array $r): array
{
    $html = strtolower($r['body']);
    $text = strtolower(html_entity_decode(strip_tags((string)preg_replace('#<(script|style)\b.*?</\1>#is', ' ', $r['body']))));
    $spam = [];
    foreach (AE_WATCH_SPAM as $w) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/', $text) || preg_match('/<a\b[^>]*>[^<]*\b' . preg_quote($w, '/') . '\b/', $html)) {
            $spam[] = $w;
        }
    }
    $hosts = [];
    preg_match_all('#<script\b[^>]*\bsrc\s*=\s*["\']?([^"\'\s>]+)#i', $r['body'], $m);
    foreach ($m[1] as $src) {
        $host = strtolower((string)parse_url(ae_resolve_url($r['url'], $src), PHP_URL_HOST));
        if ($host !== '' && $host !== $domain && $host !== 'www.' . $domain) {
            $hosts[$host] = true;
        }
    }
    preg_match_all('#<script\b(?![^>]*\bsrc=)[^>]*>(.*?)</script>#is', $r['body'], $m);
    $inline = strtolower(implode("\n", $m[1]));
    $js = [];
    foreach (AE_WATCH_JS as $sig) {
        if (substr_count($inline, $sig) >= ($sig === '\\x' ? 20 : 1)) {
            $js[] = $sig;
        }
    }
    $iframes = preg_match_all('#<iframe\b[^>]*(display\s*:\s*none|visibility\s*:\s*hidden|width=["\']?[01]["\'\s>]|height=["\']?[01]["\'\s>])#i', $r['body']);
    return [
        'ok' => $r['ok'],
        'status' => $r['status'],
        'ms' => $r['ms'],
        'error' => $r['error'] !== '' ? $r['error'] : ($r['ok'] ? '' : 'HTTP ' . $r['status']),
        'spam' => $spam,
        'hosts' => array_keys($hosts),
        'js' => $js,
        'iframes' => (int)$iframes,
    ];
}

/**
 * Code files under public_html: ['files' => [relpath => "size:mtime"], 'dirs' => [walked folders], 'truncated' => bool].
 * Walks folders one API call at a time, skipping uploads and caches. $seconds > 0 stops early
 * (web requests are cut off at about a minute); 'dirs' says which folders were covered.
 */
function ae_watch_files(string $user, float $seconds = 0): array
{
    $stop = $seconds > 0 ? microtime(true) + $seconds : INF;
    $root = '/home/' . $user . '/public_html';
    $queue = [''];
    $files = [];
    $calls = 0;
    $dirs = [];
    while ($queue && $calls < AE_WATCH_MAX_DIRS && microtime(true) < $stop) {
        $rel = array_shift($queue);
        $calls++;
        $dirs[] = $rel;
        $res = ae_uapi($user, 'Fileman', 'list_files', ['dir' => $root . ($rel === '' ? '' : '/' . $rel), 'include_mime' => 0, 'show_hidden' => 1]);
        foreach ((array)($res['data'] ?? []) as $f) {
            $name = (string)($f['file'] ?? '');
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }
            $path = $rel === '' ? $name : $rel . '/' . $name;
            if (preg_match(AE_WATCH_SKIP, $path)) {
                continue;
            }
            if (($f['type'] ?? '') === 'dir') {
                $queue[] = $path;
            } elseif (preg_match(AE_WATCH_EXT, $name)) {
                $files[$path] = (int)($f['size'] ?? 0) . ':' . (int)($f['mtime'] ?? 0);
            }
        }
    }
    ksort($files);
    sort($dirs);
    return ['files' => $files, 'dirs' => $dirs, 'truncated' => (bool)$queue];
}

/** Alerts from today's state against the reviewed baseline. Each: ['key', 'kind', 'text', 'items'?]. */
function ae_watch_alerts(array $w, ?array $site): array
{
    $a = [];
    $page = $w['page'];
    $base = $w['baseline'];
    if (!$page['ok']) {
        $a[] = ['key' => 'down', 'kind' => 'down', 'text' => 'Home page didn\'t load (' . $page['error'] . ')'];
    } else {
        if ($new = array_values(array_diff($page['spam'], $base['spam']))) {
            $a[] = ['key' => 'spam:' . implode(',', $new), 'kind' => 'hack', 'text' => 'Spam words appeared on the home page: ' . implode(', ', $new)];
        }
        if ($new = array_values(array_diff($page['hosts'], $base['hosts']))) {
            $a[] = ['key' => 'hosts:' . implode(',', $new), 'kind' => 'hack', 'text' => 'Home page loads scripts from new places: ' . implode(', ', $new)];
        }
        if ($new = array_values(array_diff($page['js'], $base['js']))) {
            $a[] = ['key' => 'js:' . implode(',', $new), 'kind' => 'hack', 'text' => 'Obfuscated JavaScript appeared on the home page (' . implode(', ', $new) . ')'];
        }
        if ($page['iframes'] > $base['iframes']) {
            $a[] = ['key' => 'iframes:' . $page['iframes'], 'kind' => 'hack', 'text' => 'A hidden iframe appeared on the home page'];
        }
    }
    if ($w['files'] !== null && $base['files'] !== null) {
        $added = $changed = [];
        $covered = isset($base['dirs']) ? array_flip($base['dirs']) : null;
        foreach ($w['files'] as $path => $sig) {
            $dir = str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '';
            if ($covered !== null && !isset($covered[$dir])) {
                continue; // the baseline walk never reached this folder, so nothing to compare with
            }
            if (!isset($base['files'][$path])) {
                $added[] = $path;
            } elseif ($base['files'][$path] !== $sig) {
                $changed[] = $path;
            }
        }
        if ($added) {
            $a[] = ['key' => 'added:' . md5(implode("\n", $added)), 'kind' => 'files', 'text' => count($added) . ' new code file' . (count($added) === 1 ? '' : 's'), 'items' => array_slice($added, 0, 25)];
        }
        if ($changed) {
            $a[] = ['key' => 'changed:' . md5(implode("\n", $changed)), 'kind' => 'files', 'text' => count($changed) . ' code file' . (count($changed) === 1 ? '' : 's') . ' changed', 'items' => array_slice($changed, 0, 25)];
        }
    }
    $exp = (int)($site['ssl']['expires'] ?? 0);
    if ($exp > 0 && $exp - time() < 7 * 86400) {
        $a[] = ['key' => 'ssl', 'kind' => 'ssl', 'text' => $exp < time() ? 'SSL certificate expired' : 'SSL certificate expires in under a week'];
    }
    return $a;
}

/**
 * Check one site now and save it. Returns the saved record plus 'new' (alerts not seen on the last check).
 * The first check of a site becomes its baseline.
 */
function ae_watch_check(array $site, float $seconds = 0): array
{
    $prev = ae_watch_get($site['domain']);
    $page = ae_watch_page($site['domain']);
    $files = null;
    $dirs = [];
    $truncated = false;
    $fileError = '';
    try {
        $f = ae_watch_files($site['user'], $seconds);
        $files = $f['files'];
        $dirs = $f['dirs'];
        $truncated = $f['truncated'];
    } catch (RuntimeException $e) {
        $fileError = $e->getMessage();
    }
    $baseline = $prev['baseline'] ?? null;
    if ($baseline === null || ($baseline['files'] === null && $files !== null)) {
        $baseline = [
            'at' => time(),
            'spam' => $page['ok'] ? $page['spam'] : [],
            'hosts' => $page['ok'] ? $page['hosts'] : [],
            'js' => $page['ok'] ? $page['js'] : [],
            'iframes' => $page['ok'] ? $page['iframes'] : 0,
            'files' => $files,
            'dirs' => $dirs,
            'by' => 'first check',
        ];
    } elseif ($files !== null && isset($baseline['dirs'])) {
        // Folders this walk reached for the first time join the baseline as they are now.
        $known = array_flip($baseline['dirs']);
        foreach ($dirs as $d) {
            if (!isset($known[$d])) {
                $baseline['dirs'][] = $d;
                foreach ($files as $path => $sig) {
                    if ((str_contains($path, '/') ? substr($path, 0, strrpos($path, '/')) : '') === $d) {
                        $baseline['files'][$path] = $sig;
                    }
                }
            }
        }
        sort($baseline['dirs']);
    }
    $w = [
        'domain' => $site['domain'],
        'user' => $site['user'],
        'at' => time(),
        'page' => $page,
        'files' => $files,
        'dirs' => $dirs,
        'file_count' => $files === null ? null : count($files),
        'truncated' => $truncated,
        'file_error' => $fileError,
        'baseline' => $baseline,
    ];
    $w['alerts'] = ae_watch_alerts($w, $site);
    $seen = array_column($prev['alerts'] ?? [], 'key');
    $new = array_values(array_filter($w['alerts'], fn($x) => !in_array($x['key'], $seen, true)));
    ae_watch_put($w);
    return $w + ['new' => $new];
}

/** Accept the site as it is now: today's page and files become the baseline. */
function ae_watch_review(string $who, array $site): void
{
    $w = ae_watch_get($site['domain']);
    if ($w === null) {
        return;
    }
    $w['baseline'] = [
        'at' => time(),
        'spam' => $w['page']['ok'] ? $w['page']['spam'] : $w['baseline']['spam'],
        'hosts' => $w['page']['ok'] ? $w['page']['hosts'] : $w['baseline']['hosts'],
        'js' => $w['page']['ok'] ? $w['page']['js'] : $w['baseline']['js'],
        'iframes' => $w['page']['ok'] ? $w['page']['iframes'] : $w['baseline']['iframes'],
        'files' => $w['files'] ?? $w['baseline']['files'],
        'dirs' => $w['files'] !== null ? ($w['dirs'] ?? []) : ($w['baseline']['dirs'] ?? []),
        'by' => $who,
    ];
    $w['alerts'] = ae_watch_alerts($w, $site);
    ae_watch_put($w);
    ae_whm_audit($who, 'watch_reviewed', $site['user'], true);
}

/** 'ok', 'warn' or 'bad' for a pill; null record means not checked yet. */
function ae_watch_tone(?array $w): string
{
    if ($w === null) {
        return 'soon';
    }
    $kinds = array_column($w['alerts'], 'kind');
    if (in_array('hack', $kinds, true) || in_array('down', $kinds, true)) {
        return 'bad';
    }
    return $kinds ? 'warn' : 'ok';
}

function ae_watch_label(?array $w): string
{
    if ($w === null) {
        return 'Not checked';
    }
    $n = count($w['alerts']);
    return $n === 0 ? 'All clear' : $n . ' alert' . ($n === 1 ? '' : 's');
}

/** Email new alerts (['domain' => [alert, ...]]) to the admins. Returns how many were emailed. */
function ae_watch_email(array $newAlerts): int
{
    require_once __DIR__ . '/quiz.php';
    $body = "Site watch found something new on " . count($newAlerts) . " site" . (count($newAlerts) === 1 ? '' : 's') . ":\n\n";
    $hack = false;
    foreach ($newAlerts as $d => $list) {
        $body .= $d . "\n";
        foreach ($list as $a) {
            $hack = $hack || $a['kind'] === 'hack';
            $body .= '  - ' . $a['text'] . "\n";
            foreach (array_slice($a['items'] ?? [], 0, 10) as $item) {
                $body .= '      ' . $item . "\n";
            }
        }
        $body .= "\n";
    }
    $body .= "Review them at https://agencyeagle.com/admin/sites.php?f=attention\n"
        . "If a change was yours, open the site and click \"Mark reviewed\" so it becomes the new normal.\n";
    $to = ae_admin_emails();
    foreach ($to as $addr) {
        ae_mail($addr, ($hack ? 'Possible hack: ' : 'Site watch: ') . implode(', ', array_keys($newAlerts)), $body);
    }
    return count($to);
}

/** Admin addresses for alert emails (the Google allowlist). */
function ae_admin_emails(): array
{
    $p = __DIR__ . '/../data/oauth.conf';
    $c = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    return array_values(array_filter((array)($c['allowed_emails'] ?? []), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
}
