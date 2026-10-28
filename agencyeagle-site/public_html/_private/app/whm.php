<?php
// WHM API client for the Sites, Site and Activate pages. Config in data/whm.conf
// ({"host","user","token"}), written by the deploy from GitHub secrets.
// Every change goes through ae_whm_act(), which writes the audit log.
declare(strict_types=1);

const AE_WHM_CONF = __DIR__ . '/../data/whm.conf';
const AE_WHM_CACHE = __DIR__ . '/../data/whm-sites.cache.json';
const AE_WHM_CACHE_SECONDS = 600;
const AE_WHM_AUDIT = __DIR__ . '/../data/whm-audit.jsonl';
const AE_SERVER_IP = '69.16.237.175';

function ae_whm_conf(): ?array
{
    if (!is_file(AE_WHM_CONF)) {
        return null;
    }
    $c = json_decode((string)file_get_contents(AE_WHM_CONF), true);
    return is_array($c) && !empty($c['user']) && !empty($c['token']) ? $c + ['host' => 'host.agencyeagle.com'] : null;
}

/** Calls a WHM API 1 function. Returns its "data" array, or throws with a short reason. */
function ae_whm_call(array $conf, string $fn, array $args = []): array
{
    // Arguments go in the POST body so passwords never land in a URL or access log.
    $ch = curl_init('https://' . $conf['host'] . ':2087/json-api/' . rawurlencode($fn) . '?api.version=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($args),
        CURLOPT_HTTPHEADER => ['Authorization: whm ' . $conf['user'] . ':' . $conf['token']],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('WHM did not answer (' . ($err ?: 'HTTP ' . $status) . ')');
    }
    $json = json_decode($body, true);
    if ($fn === 'cpanel' && is_array($json['result'] ?? null)) {
        // The cPanel bridge returns the UAPI result itself: {"result": {"status", "errors", "data"}}.
        if (($json['result']['status'] ?? 0) !== 1) {
            throw new RuntimeException('cPanel said: ' . implode('; ', (array)($json['result']['errors'] ?? ['request failed'])));
        }
        return $json['result'];
    }
    if (($json['metadata']['result'] ?? 0) !== 1) {
        throw new RuntimeException('WHM said: ' . (string)($json['metadata']['reason'] ?? 'HTTP ' . $status));
    }
    return $json['data'] ?? [];
}

/**
 * Every account on the server with its SSL expiry, cached for 10 minutes.
 * Returns ['sites' => [...], 'fetched' => ts, 'error' => ?string].
 */
function ae_whm_sites(bool $refresh = false): array
{
    if (!$refresh && is_file(AE_WHM_CACHE) && time() - filemtime(AE_WHM_CACHE) < AE_WHM_CACHE_SECONDS) {
        $cached = json_decode((string)file_get_contents(AE_WHM_CACHE), true);
        if (is_array($cached)) {
            return $cached;
        }
    }
    $conf = ae_whm_conf();
    if ($conf === null) {
        return ['sites' => [], 'fetched' => null, 'error' => 'not_configured'];
    }
    try {
        $accts = ae_whm_call($conf, 'listaccts', ['want' => 'user,domain,plan,email,suspended,suspendreason,diskused,disklimit,startdate,unix_startdate,owner']);
        $ssl = [];
        try {
            foreach (ae_whm_call($conf, 'fetch_ssl_vhosts')['vhosts'] ?? [] as $v) {
                $ssl[strtolower((string)($v['servername'] ?? ''))] = [
                    'expires' => (int)($v['crt']['not_after'] ?? 0),
                    'self_signed' => !empty($v['crt']['is_self_signed']),
                ];
            }
        } catch (RuntimeException $e) {
            // SSL details are a bonus; the token may not allow them.
        }
        $sites = [];
        foreach ($accts['acct'] ?? [] as $a) {
            $domain = strtolower((string)($a['domain'] ?? ''));
            $sites[] = [
                'domain' => $domain,
                'user' => (string)($a['user'] ?? ''),
                'plan' => (string)($a['plan'] ?? ''),
                'email' => (string)($a['email'] ?? ''),
                'owner' => (string)($a['owner'] ?? ''),
                'suspended' => !empty($a['suspended']),
                'suspend_reason' => (string)($a['suspendreason'] ?? ''),
                'disk_used' => (string)($a['diskused'] ?? ''),
                'disk_limit' => (string)($a['disklimit'] ?? ''),
                'created' => (int)($a['unix_startdate'] ?? 0),
                'ssl' => $ssl[$domain] ?? null,
            ];
        }
        usort($sites, fn($x, $y) => strcmp($x['domain'], $y['domain']));
        $result = ['sites' => $sites, 'fetched' => time(), 'error' => null];
        file_put_contents(AE_WHM_CACHE, json_encode($result), LOCK_EX);
        chmod(AE_WHM_CACHE, 0600);
        return $result;
    } catch (RuntimeException $e) {
        return ['sites' => [], 'fetched' => null, 'error' => $e->getMessage()];
    }
}

function ae_whm_forget_sites(): void
{
    if (is_file(AE_WHM_CACHE)) {
        unlink(AE_WHM_CACHE);
    }
}

/** One account from the (cached) list, or null. */
function ae_whm_site(string $user): ?array
{
    foreach (ae_whm_sites()['sites'] as $s) {
        if ($s['user'] === $user) {
            return $s;
        }
    }
    return null;
}

function ae_whm_audit(string $who, string $action, string $target, bool $ok, string $detail = ''): void
{
    $line = json_encode(['at' => time(), 'who' => $who, 'action' => $action, 'target' => $target, 'ok' => $ok, 'detail' => $detail]);
    file_put_contents(AE_WHM_AUDIT, $line . "\n", FILE_APPEND | LOCK_EX);
    @chmod(AE_WHM_AUDIT, 0600);
}

/** Newest first. */
function ae_whm_audit_entries(int $limit = 200): array
{
    if (!is_file(AE_WHM_AUDIT)) {
        return [];
    }
    $lines = array_slice(file(AE_WHM_AUDIT, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$limit);
    return array_reverse(array_values(array_filter(array_map(fn($l) => json_decode($l, true), $lines), 'is_array')));
}

/** Runs a WHM change, logs it, and clears the site cache. Returns the WHM data; throws on failure. */
function ae_whm_act(string $who, string $action, string $target, string $fn, array $args): array
{
    $conf = ae_whm_conf();
    if ($conf === null) {
        throw new RuntimeException('WHM is not connected');
    }
    try {
        $data = ae_whm_call($conf, $fn, $args);
    } catch (RuntimeException $e) {
        ae_whm_audit($who, $action, $target, false, $e->getMessage());
        throw $e;
    }
    ae_whm_audit($who, $action, $target, true);
    ae_whm_forget_sites();
    return $data;
}

/** The reseller account the token belongs to: never suspend or delete it from here. */
function ae_whm_is_protected(string $user): bool
{
    $conf = ae_whm_conf();
    return $conf !== null && $user === $conf['user'];
}

function ae_whm_packages(): array
{
    $conf = ae_whm_conf();
    if ($conf === null) {
        return [];
    }
    try {
        $names = array_map(fn($p) => (string)($p['name'] ?? ''), ae_whm_call($conf, 'listpkgs')['pkg'] ?? []);
        sort($names);
        return array_values(array_filter($names));
    } catch (RuntimeException $e) {
        return [];
    }
}

/** A strong random password made of letters, digits and a few symbols cPanel accepts. */
function ae_random_password(int $length = 20): string
{
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%^*-_=+';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    // Make sure every class is present so cPanel's strength check passes.
    return 'Ae7!' . substr($out, 4);
}

/** cPanel username from a domain: lowercase letters and digits, starts with a letter, max 16. */
function ae_username_for(string $domain): string
{
    $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
    $base = ltrim((string)$base, '0123456789');
    if (str_starts_with($base, 'test')) {
        $base = 'site' . $base;
    }
    return substr($base !== '' ? $base : 'site', 0, 16);
}

function ae_valid_domain(string $domain): bool
{
    return (bool)preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
}

/** One clean "coming soon" page for a new account. */
function ae_splash_html(string $business, string $domain, string $email, string $phone): string
{
    $h = static fn($v): string => htmlspecialchars($v, ENT_QUOTES);
    $contact = '<a href="mailto:' . $h($email) . '">' . $h($email) . '</a>';
    if ($phone !== '') {
        $contact .= ' · <a href="tel:' . $h(preg_replace('/[^0-9+]/', '', $phone)) . '">' . $h($phone) . '</a>';
    }
    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $h($business) . ' | New website coming soon</title>
<meta name="description" content="' . $h($business) . ' has a new website on the way. Get in touch any time.">
<link rel="canonical" href="https://' . $h($domain) . '/">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fa;color:#1f2d3a;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;padding:16px;box-sizing:border-box}
main{background:#fff;border-top:6px solid #146ba7;border-radius:10px;padding:40px 32px;max-width:560px;text-align:center;box-shadow:0 10px 30px rgba(20,107,167,.12)}
h1{margin:0 0 12px;color:#34536e;font-size:clamp(1.6rem,4vw,2.2rem)}
p{line-height:1.6;margin:0 0 12px}
a{color:#146ba7}
.small{color:#4f5d6b;font-size:.9rem;margin-top:24px}
</style>
</head>
<body>
<main>
<h1>' . $h($business) . '</h1>
<p>Our new website is on the way.</p>
<p>' . $contact . '</p>
<p class="small">Hosted by <a href="https://agencyeagle.com/">Agency Eagle</a></p>
</main>
</body>
</html>
';
}

/** Saves a file into an account through cPanel UAPI (Fileman::save_file_content). */
function ae_whm_save_file(string $who, string $user, string $dir, string $file, string $content): void
{
    ae_whm_act($who, 'save_file', $user . ':' . $dir . '/' . $file, 'cpanel', [
        'cpanel_jsonapi_user' => $user,
        'cpanel_jsonapi_apiversion' => 3,
        'cpanel_jsonapi_module' => 'Fileman',
        'cpanel_jsonapi_func' => 'save_file_content',
        'dir' => $dir,
        'file' => $file,
        'content' => $content,
    ]);
}
