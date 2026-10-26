<?php
// Live AI-readiness scan of a visitor's website: robots.txt, search and AI crawler access,
// sitemap, noindex, on-page basics, JSON-LD, llms.txt and speed. Worth 70 of the 100 points.
// Check list follows the Cedar Pond AI search review (lib/ai-search-review.js), rebuilt for PHP.
// Every request goes through ae_safe_get(), which refuses private addresses (no SSRF).
declare(strict_types=1);

const AE_SCAN_UA = 'AgencyEagleAIReadyCheck/1.0 (+https://agencyeagle.com/ai-ready/)';
const AE_SCAN_TIMEOUT = 10;
const AE_SCAN_MAX_BYTES = 3_000_000;

// Crawlers behind AI answers. Bing's index feeds ChatGPT search and Copilot, so Bingbot is scored with Googlebot.
const AE_AI_BOTS = [
    'GPTBot' => 'OpenAI model training',
    'OAI-SearchBot' => 'ChatGPT search',
    'ChatGPT-User' => 'ChatGPT browsing',
    'ClaudeBot' => 'Claude',
    'PerplexityBot' => 'Perplexity',
    'Google-Extended' => 'Gemini',
    'Applebot-Extended' => 'Apple Intelligence',
];

/** Normalize what the visitor typed into https://host/ form. Returns null when it is not a usable public URL. */
function ae_normalize_site(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 300) {
        return null;
    }
    if (!preg_match('#^https?://#i', $raw)) {
        $raw = 'https://' . $raw;
    }
    $u = parse_url($raw);
    if (!$u || empty($u['host']) || isset($u['user']) || isset($u['pass'])) {
        return null;
    }
    $host = strtolower(rtrim($u['host'], '.'));
    if (!preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host)) {
        return null; // also refuses bare IP addresses and "localhost"
    }
    if (isset($u['port']) && !in_array((int)$u['port'], [80, 443], true)) {
        return null;
    }
    return 'https://' . $host . '/';
}

/** Public IPv4 for a host, or null. Pinned into curl so DNS can't change between check and fetch. */
function ae_public_ip(string $host): ?string
{
    $ips = @gethostbynamel($host) ?: [];
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null; // any private answer poisons the host
        }
    }
    return $ips[0] ?? null;
}

/**
 * GET a public URL, following up to 5 redirects by hand so every hop is checked.
 * Returns ['ok', 'status', 'url', 'headers' (lowercase => value), 'body', 'ms', 'hops', 'error'].
 */
function ae_safe_get(string $url, int $maxBytes = AE_SCAN_MAX_BYTES): array
{
    $out = ['ok' => false, 'status' => 0, 'url' => $url, 'headers' => [], 'body' => '', 'ms' => 0, 'hops' => [], 'error' => ''];
    $start = microtime(true);
    for ($hop = 0; $hop <= 5; $hop++) {
        $u = parse_url($url);
        $scheme = strtolower($u['scheme'] ?? '');
        $host = strtolower($u['host'] ?? '');
        $port = (int)($u['port'] ?? ($scheme === 'http' ? 80 : 443));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || !in_array($port, [80, 443], true) || isset($u['user'])) {
            $out['error'] = 'redirected to an address we will not follow';
            return $out;
        }
        $testIp = getenv('AE_DEV') === '1' ? (string)getenv('AE_SCAN_TEST_IP') : ''; // local tests point every host at a fixture server
        $ip = $testIp !== '' ? $testIp : ae_public_ip($host);
        if ($ip === null) {
            $out['error'] = "couldn't find a public address for $host";
            return $out;
        }
        $headers = [];
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => ["$host:$port:$ip"],
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => AE_SCAN_TIMEOUT,
            CURLOPT_USERAGENT => AE_SCAN_UA,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain,application/xml;q=0.9,*/*;q=0.5'],
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $headers[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body, $maxBytes): int {
                $body .= $chunk;
                return strlen($body) > $maxBytes ? 0 : strlen($chunk); // 0 aborts the transfer
            },
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);
        $out['hops'][] = ['url' => $url, 'status' => $status];
        if ($err && !($err === CURLE_WRITE_ERROR && $status)) {
            $out['error'] = $err === CURLE_OPERATION_TIMEDOUT ? 'timed out' : ($errMsg ?: 'could not connect');
            $out['ms'] = (int)round((microtime(true) - $start) * 1000);
            return $out;
        }
        if ($status >= 300 && $status < 400 && !empty($headers['location'])) {
            $url = ae_resolve_url($url, $headers['location']);
            continue;
        }
        $out['ok'] = $status >= 200 && $status < 300;
        $out['status'] = $status;
        $out['url'] = $url;
        $out['headers'] = $headers;
        $out['body'] = $body;
        $out['ms'] = (int)round((microtime(true) - $start) * 1000);
        return $out;
    }
    $out['error'] = 'too many redirects';
    return $out;
}

function ae_resolve_url(string $base, string $ref): string
{
    if (preg_match('#^https?://#i', $ref)) {
        return $ref;
    }
    $b = parse_url($base);
    $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (str_starts_with($ref, '//')) {
        return $b['scheme'] . ':' . $ref;
    }
    if (str_starts_with($ref, '/')) {
        return $root . $ref;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
    return $root . $dir . $ref;
}

/** Parse robots.txt into groups: [['agents' => [...], 'rules' => [['allow'|'disallow', path], ...]], ...] plus sitemaps. */
function ae_parse_robots(string $txt): array
{
    $groups = [];
    $sitemaps = [];
    $cur = null;
    $lastWasAgent = false;
    foreach (preg_split('/\r\n|\r|\n/', $txt) as $line) {
        $line = trim(preg_replace('/#.*/', '', $line));
        if ($line === '' || !str_contains($line, ':')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        $k = strtolower($k);
        if ($k === 'sitemap') {
            $sitemaps[] = $v;
        } elseif ($k === 'user-agent') {
            if (!$lastWasAgent || $cur === null) {
                $groups[] = ['agents' => [], 'rules' => []];
                $cur = count($groups) - 1;
            }
            $groups[$cur]['agents'][] = strtolower($v);
            $lastWasAgent = true;
            continue;
        } elseif (($k === 'allow' || $k === 'disallow') && $cur !== null) {
            $groups[$cur]['rules'][] = [$k, $v];
        }
        $lastWasAgent = false;
    }
    return ['groups' => $groups, 'sitemaps' => $sitemaps];
}

/** Does robots.txt let this crawler fetch the home page? Uses the most specific matching group, like Google does. */
function ae_robots_allows(array $robots, string $agent, string $path = '/'): bool
{
    $agent = strtolower($agent);
    $match = null;
    $best = -1;
    foreach ($robots['groups'] as $g) {
        foreach ($g['agents'] as $a) {
            $len = $a === '*' ? 0 : (str_contains($agent, $a) ? strlen($a) : -1);
            if ($len > $best) {
                $best = $len;
                $match = $g;
            }
        }
    }
    if ($match === null) {
        return true;
    }
    $verdict = true;
    $longest = -1;
    foreach ($match['rules'] as [$type, $rule]) {
        if ($rule === '') {
            continue; // "Disallow:" with nothing blocks nothing
        }
        $re = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '#')) . '#';
        if (preg_match($re, $path) && strlen($rule) > $longest) {
            $longest = strlen($rule);
            $verdict = $type === 'allow';
        }
    }
    return $verdict;
}

/** Pull what the scan needs out of the home page HTML. */
function ae_read_page(string $html): array
{
    $get = static function (string $re) use ($html): string {
        return preg_match($re, $html, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    };
    $metaContent = static function (string $attr, string $name) use ($html): string {
        foreach ([
            '#<meta\s[^>]*' . $attr . '\s*=\s*["\']' . preg_quote($name, '#') . '["\'][^>]*content\s*=\s*["\']([^"\']*)["\']#i',
            '#<meta\s[^>]*content\s*=\s*["\']([^"\']*)["\'][^>]*' . $attr . '\s*=\s*["\']' . preg_quote($name, '#') . '["\']#i',
        ] as $re) {
            if (preg_match($re, $html, $m)) {
                return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        return '';
    };
    $ldTypes = [];
    $ldBlocks = 0;
    $ldBroken = 0;
    if (preg_match_all('#<script[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $mm)) {
        foreach ($mm[1] as $blob) {
            $ldBlocks++;
            $data = json_decode(trim($blob), true);
            if (!is_array($data)) {
                $ldBroken++;
                continue;
            }
            array_walk_recursive($data, static function ($v, $k) use (&$ldTypes) {
                if ($k === '@type') {
                    $ldTypes[] = (string)$v;
                }
            });
        }
    }
    $imgs = preg_match_all('#<img\b[^>]*>#i', $html, $im) ? $im[0] : [];
    $noAlt = count(array_filter($imgs, static fn($t) => !preg_match('/\salt\s*=/i', $t)));
    $canonical = '';
    if (preg_match('#<link\s[^>]*rel\s*=\s*["\']canonical["\'][^>]*>#i', $html, $cm) && preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $cm[0], $hm)) {
        $canonical = trim($hm[1]);
    }
    return [
        'title' => preg_replace('/\s+/', ' ', $get('#<title[^>]*>(.*?)</title>#is')),
        'description' => $metaContent('name', 'description'),
        'robots_meta' => strtolower($metaContent('name', 'robots') . ' ' . $metaContent('name', 'bingbot') . ' ' . $metaContent('name', 'googlebot')),
        'viewport' => $metaContent('name', 'viewport'),
        'og_title' => $metaContent('property', 'og:title'),
        'lang' => $get('#<html[^>]*\slang\s*=\s*["\']([^"\']+)["\']#i'),
        'h1' => preg_match_all('#<h1[\s>]#i', $html),
        'canonical' => $canonical,
        'ld_blocks' => $ldBlocks,
        'ld_broken' => $ldBroken,
        'ld_types' => array_values(array_unique($ldTypes)),
        'images' => count($imgs),
        'images_no_alt' => $noAlt,
        'wordpress' => (bool)preg_match('#wp-content|wp-includes|<meta[^>]+generator[^>]+wordpress#i', $html),
        'builder' => preg_match('#<meta[^>]+generator[^>]+content\s*=\s*["\']([^"\']+)#i', $html, $gm) ? $gm[1] : '',
        'bytes' => strlen($html),
    ];
}

/** Business-type schema that tells an AI who you are. */
function ae_is_business_type(string $type): bool
{
    return (bool)preg_match('/Organization|Business|Service|Store|Restaurant|Physician|Dentist|Attorney|LegalService|MedicalClinic|Contractor|Agency|Hotel|Corporation|Plumber|Electrician|RealEstateAgent|AutoRepair|Salon|Venue|Person/i', $type);
}

/**
 * Run the full scan. Returns ['site', 'host', 'reached', 'error', 'checks' => [...], 'points', 'max', 'facts'].
 * Each check: id, group, label, points, max, pass (true|false|'partial'), detail.
 */
function ae_scan_site(string $site): array
{
    $host = parse_url($site, PHP_URL_HOST);
    $checks = [];
    $add = static function (string $id, string $group, string $label, int $max, float $earned, string $detail) use (&$checks) {
        $earned = max(0, min($max, $earned));
        $checks[] = [
            'id' => $id, 'group' => $group, 'label' => $label, 'max' => $max,
            'points' => round($earned, 1),
            'pass' => $earned >= $max ? true : ($earned > 0 ? 'partial' : false),
            'detail' => $detail,
        ];
    };

    $home = ae_safe_get($site);
    if (!$home['ok'] && $home['status'] === 0) {
        // Some sites still only answer on http://. Try it before giving up.
        $plain = ae_safe_get('http://' . $host . '/');
        if ($plain['ok']) {
            $home = $plain;
        }
    }
    $facts = ['final_url' => $home['url'], 'status' => $home['status'], 'ms' => $home['ms'], 'error' => $home['error']];
    if (!$home['ok']) {
        $why = $home['error'] ?: 'it answered with HTTP ' . $home['status'];
        $add('https', 'crawl', 'Your home page loads for search engines', 5, 0, "We couldn't load $site ($why). If we can't, Bing and Google's crawlers likely can't either.");
        return ['site' => $site, 'host' => $host, 'reached' => false, 'error' => $why, 'checks' => $checks, 'facts' => $facts] + ae_scan_totals($checks);
    }
    $finalHost = strtolower((string)parse_url($home['url'], PHP_URL_HOST));
    $isHttps = str_starts_with($home['url'], 'https://');
    $origin = ($isHttps ? 'https://' : 'http://') . $finalHost;
    $page = ae_read_page($home['body']);
    $facts += ['final_host' => $finalHost, 'page' => $page, 'server' => $home['headers']['server'] ?? '', 'x_robots' => $home['headers']['x-robots-tag'] ?? ''];

    // ---- Can search and AI crawlers reach you? (22) ----
    $add('https', 'crawl', 'Home page loads securely over HTTPS', 5, $isHttps ? 5 : 1,
        $isHttps ? 'Loads over HTTPS (status ' . $home['status'] . ').' : 'Your site loads over plain http://. Browsers mark it "Not secure" and search engines rank secure pages first.');
    $redirect = ae_safe_get('http://' . $finalHost . '/', 200_000);
    $redirectsToHttps = $redirect['ok'] && str_starts_with($redirect['url'], 'https://');
    $add('http_redirect', 'crawl', 'http:// sends visitors to https://', 2, $redirectsToHttps ? 2 : 0,
        $redirectsToHttps ? 'The insecure address forwards to the secure one.' : 'http://' . $finalHost . ' does not forward to https://, so search engines may index two copies of your site.');

    $robotsRes = ae_safe_get($origin . '/robots.txt', 500_000);
    $robotsType = strtolower($robotsRes['headers']['content-type'] ?? '');
    $robotsOk = $robotsRes['ok'] && !str_contains($robotsType, 'html');
    $robots = ae_parse_robots($robotsOk ? $robotsRes['body'] : '');
    $facts['robots_found'] = $robotsOk;
    $add('robots', 'crawl', 'Has a robots.txt file', 3, $robotsOk ? 3 : 0,
        $robotsOk ? 'Found /robots.txt.' : 'No /robots.txt. It is the first file every search and AI crawler asks for, and it is where you point them to your sitemap.');

    $google = ae_robots_allows($robots, 'Googlebot');
    $bing = ae_robots_allows($robots, 'Bingbot');
    $blocked = array_keys(array_filter(['Googlebot' => !$google, 'Bingbot' => !$bing]));
    $add('search_bots', 'crawl', 'Google and Bing are allowed in', 6, ($google ? 3 : 0) + ($bing ? 3 : 0),
        $blocked ? 'robots.txt blocks ' . implode(' and ', $blocked) . ' from your home page. ' . (!$bing ? 'ChatGPT search and Microsoft Copilot answer from Bing\'s index, so they can\'t see you either.' : '')
            : 'Googlebot and Bingbot can crawl your home page. Bing matters more than most people think: ChatGPT search and Copilot answer from it.');

    $aiAllowed = [];
    $aiBlocked = [];
    foreach (AE_AI_BOTS as $bot => $who) {
        if (ae_robots_allows($robots, $bot)) {
            $aiAllowed[] = $bot;
        } else {
            $aiBlocked[] = "$bot ($who)";
        }
    }
    $facts['ai_blocked'] = $aiBlocked;
    $add('ai_bots', 'crawl', 'AI assistants are allowed to read your site', 6, 6 * count($aiAllowed) / count(AE_AI_BOTS),
        $aiBlocked ? 'robots.txt blocks ' . implode(', ', $aiBlocked) . '. Those assistants can\'t quote or recommend pages they aren\'t allowed to read.'
            : 'None of the major AI crawlers (ChatGPT, Claude, Perplexity, Gemini, Apple) are blocked.');

    // ---- Can they index it? (18) ----
    $noindex = str_contains($page['robots_meta'], 'noindex') || str_contains(strtolower($facts['x_robots']), 'noindex');
    $add('noindex', 'index', 'Home page is not hidden from search (no "noindex")', 6, $noindex ? 0 : 6,
        $noindex ? 'Your home page tells search engines not to index it (a "noindex" tag). This is common after a site launch when a developer forgets to switch it off. Nothing else matters until it\'s removed.'
            : 'No noindex tag or header on your home page.');

    $sitemapUrls = $robots['sitemaps'] ?: [$origin . '/sitemap.xml', $origin . '/sitemap_index.xml'];
    $sitemap = null;
    foreach (array_slice($sitemapUrls, 0, 3) as $su) {
        $r = ae_safe_get($su, 2_000_000);
        if ($r['ok'] && preg_match('#<(urlset|sitemapindex)\b#i', $r['body'])) {
            $sitemap = $r;
            break;
        }
    }
    $locs = $sitemap ? preg_match_all('#<loc>#i', $sitemap['body']) : 0;
    $facts['sitemap_urls'] = $locs;
    $add('sitemap', 'index', 'Has an XML sitemap', 5, $sitemap ? 5 : 0,
        $sitemap ? 'Found a sitemap at ' . $sitemap['url'] . '.' : 'No sitemap found in robots.txt or at /sitemap.xml. A sitemap is the list of pages you want Bing, Google and AI tools to know about.');
    $add('sitemap_listed', 'index', 'robots.txt points crawlers to the sitemap', 3, $robots['sitemaps'] ? 3 : 0,
        $robots['sitemaps'] ? 'robots.txt has a Sitemap: line.' : 'Add a "Sitemap:" line to robots.txt so every crawler finds it without guessing (Bing relies on this).');

    $canon = $page['canonical'];
    $canonHost = strtolower((string)parse_url($canon, PHP_URL_HOST));
    $canonOk = $canon !== '' && ($canonHost === '' || $canonHost === $finalHost);
    $add('canonical', 'index', 'Home page names its one official address (canonical)', 4, $canonOk ? 4 : 0,
        $canon === '' ? 'No canonical tag. Without it, search engines guess which version of a page (www, non-www, with tracking codes) is the real one.'
            : ($canonOk ? 'Canonical tag points to this site.' : 'Your canonical tag points to ' . $canonHost . ', which tells search engines this page lives somewhere else.'));

    // ---- Do they understand who you are? (18) ----
    $tl = mb_strlen($page['title']);
    $add('title', 'understand', 'Clear page title', 4, $tl === 0 ? 0 : ($tl >= 15 && $tl <= 65 ? 4 : 2),
        $tl === 0 ? 'Your home page has no <title>.' : "Title is $tl characters: \"" . mb_substr($page['title'], 0, 90) . '"' . ($tl < 15 || $tl > 65 ? ' (aim for 15 to 65, with your business name and what you do).' : '.'));
    $dl = mb_strlen($page['description']);
    $add('description', 'understand', 'Meta description that sums up the business', 3, $dl === 0 ? 0 : ($dl >= 50 && $dl <= 165 ? 3 : 1.5),
        $dl === 0 ? 'No meta description. Search engines and AI tools often use it as the one-line summary of who you are.' : "Description is $dl characters" . ($dl < 50 || $dl > 165 ? ' (aim for 50 to 165).' : '.'));
    $add('h1', 'understand', 'One main heading (H1)', 3, $page['h1'] === 1 ? 3 : ($page['h1'] > 1 ? 1.5 : 0),
        $page['h1'] === 1 ? 'Exactly one H1.' : ($page['h1'] === 0 ? 'No H1 heading. The H1 is how a crawler knows what the page is about.' : $page['h1'] . ' H1 headings. Use one, so the main topic is unmistakable.'));
    $ldOk = $page['ld_blocks'] > 0 && $page['ld_broken'] === 0;
    $add('schema', 'understand', 'Structured data (schema.org JSON-LD)', 5, $ldOk ? 5 : ($page['ld_blocks'] > 0 ? 2 : 0),
        $page['ld_blocks'] === 0 ? 'No structured data. This is the machine-readable "business card" (name, phone, address, hours, services) that AI answers pull from.'
            : ($ldOk ? 'Found ' . $page['ld_blocks'] . ' structured data block(s): ' . implode(', ', array_slice($page['ld_types'], 0, 6)) . '.' : $page['ld_broken'] . ' structured data block(s) have broken JSON, so they are ignored.'));
    $biz = array_values(array_filter($page['ld_types'], 'ae_is_business_type'));
    $add('schema_business', 'understand', 'Schema says what kind of business you are', 3, $biz ? 3 : 0,
        $biz ? 'Describes you as: ' . implode(', ', array_slice($biz, 0, 3)) . '.' : 'No Organization or LocalBusiness schema, so assistants have to guess your name, location and phone from the page text.');

    // ---- Ready for AI answers and fast (12) ----
    $llms = ae_safe_get($origin . '/llms.txt', 300_000);
    $llmsOk = $llms['ok'] && !str_contains(strtolower($llms['headers']['content-type'] ?? ''), 'html') && trim($llms['body']) !== '';
    $add('llms', 'ready', 'Has an llms.txt guide for AI tools', 2, $llmsOk ? 2 : 0,
        $llmsOk ? 'Found /llms.txt.' : 'No /llms.txt. It is a new, simple file that tells AI tools what your business does and which pages matter most. Few sites have one yet, so it\'s an easy edge.');
    $vp = str_contains($page['viewport'], 'width=device-width');
    $add('mobile', 'ready', 'Mobile-friendly setup', 3, $vp ? 3 : 0,
        $vp ? 'Has a mobile viewport tag.' : 'No mobile viewport tag. Google ranks the mobile version of your site, and this page will render as a tiny desktop page on phones.');
    $add('lang', 'ready', 'Page declares its language', 1, $page['lang'] !== '' ? 1 : 0,
        $page['lang'] !== '' ? 'Language: ' . $page['lang'] . '.' : 'No lang attribute on <html>.');
    $ms = $home['ms'];
    $add('speed', 'ready', 'Home page responds quickly', 3, $ms <= 1500 ? 3 : ($ms <= 3000 ? 1.5 : 0),
        'Took ' . number_format($ms / 1000, 1) . ' seconds to download from our server' . ($ms > 1500 ? '. Slow pages get crawled less often and lose visitors.' : '.'));
    $add('social', 'ready', 'Share preview (Open Graph)', 2, $page['og_title'] !== '' ? 2 : 0,
        $page['og_title'] !== '' ? 'Has Open Graph tags for link previews.' : 'No Open Graph tags, so links shared on Facebook, LinkedIn and in chat apps show a blank or random preview.');
    $add('alt', 'ready', 'Images have descriptions (alt text)', 1, $page['images'] === 0 || $page['images_no_alt'] === 0 ? 1 : 0,
        $page['images_no_alt'] ? $page['images_no_alt'] . ' of ' . $page['images'] . ' images have no alt text, so search and AI tools can\'t tell what they show.' : 'Images are described.');

    return ['site' => $site, 'host' => $host, 'reached' => true, 'error' => '', 'checks' => $checks, 'facts' => $facts] + ae_scan_totals($checks);
}

function ae_scan_totals(array $checks): array
{
    return [
        'points' => round(array_sum(array_column($checks, 'points')), 1),
        'max' => 70, // a site we can't reach still scores out of 70
    ];
}
