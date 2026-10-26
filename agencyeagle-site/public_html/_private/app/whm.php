<?php
// Read-only WHM API client for the Sites page. Config in data/whm.conf
// ({"host","user","token"}), written by the deploy from GitHub secrets.
declare(strict_types=1);

const AE_WHM_CONF = __DIR__ . '/../data/whm.conf';
const AE_WHM_CACHE = __DIR__ . '/../data/whm-sites.cache.json';
const AE_WHM_CACHE_SECONDS = 600;

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
    $url = 'https://' . $conf['host'] . ':2087/json-api/' . rawurlencode($fn) . '?' . http_build_query(['api.version' => 1] + $args);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
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
