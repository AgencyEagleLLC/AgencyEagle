<?php
// AI-Ready Site Score: the quiz, its lead records and the scoring.
// Shape borrowed from the Cedar Pond quiz engine (lib/quiz-engine.js): the email gate comes before
// question one, questions are only sent after the gate, and the server does all the scoring.
// Each lead is one JSON file in data/quiz/leads/<token>.json.
declare(strict_types=1);

require_once __DIR__ . '/site_scan.php';

const AE_QUIZ_DIR = __DIR__ . '/../data/quiz';
const AE_QUIZ_STARTS_PER_HOUR = 6;   // per IP
const AE_SALES_EMAIL = 'sales@agencyeagle.com';
const AE_SITE = 'https://agencyeagle.com';

/** The 8 scored questions (30 points) plus 2 unscored ones that tell sales who they're talking to. */
function ae_quiz_questions(): array
{
    return [
        ['id' => 'bing_wmt', 'group' => 'found', 'points' => 4,
         'q' => 'Is your website verified in Bing Webmaster Tools?',
         'why' => "ChatGPT search and Microsoft Copilot answer from Bing's index. Bing Webmaster Tools is how you see what Bing knows about you and fix it.",
         'options' => [['yes', 'Yes', 4], ['no', 'No', 0], ['unsure', "Not sure / what's that?", 0]]],
        ['id' => 'gsc', 'group' => 'found', 'points' => 4,
         'q' => 'Is your website set up in Google Search Console?',
         'why' => "Search Console shows which pages Google indexed, which it skipped, and why. Google's AI Overviews come from the same index.",
         'options' => [['yes', 'Yes', 4], ['no', 'No', 0], ['unsure', 'Not sure', 0]]],
        ['id' => 'gbp', 'group' => 'local', 'points' => 4,
         'q' => 'Have you claimed and filled out your Google Business Profile?',
         'why' => 'When someone asks an assistant for "the best ___ near me", your Business Profile (hours, photos, services, reviews) is a main source.',
         'options' => [['complete', 'Yes, fully filled out', 4], ['partial', 'Claimed, but not kept up', 2], ['no', 'No', 0], ['unsure', 'Not sure', 0]]],
        ['id' => 'bing_places', 'group' => 'local', 'points' => 3,
         'q' => 'Are you listed on Bing Places or Apple Business Connect?',
         'why' => 'Copilot reads Bing Places. Siri and Apple Maps read Apple Business Connect. Most businesses have neither.',
         'options' => [['both', 'Both', 3], ['one', 'One of them', 2], ['no', 'Neither', 0], ['unsure', 'Not sure', 0]]],
        ['id' => 'reviews', 'group' => 'local', 'points' => 3,
         'q' => 'How many Google reviews does your business have?',
         'why' => 'Review count, rating and how recent they are help decide which businesses an AI recommends by name.',
         'options' => [['50', '50 or more', 3], ['10', '10 to 49', 2], ['1', '1 to 9', 1], ['0', 'None / not sure', 0]]],
        ['id' => 'answers', 'group' => 'content', 'points' => 4,
         'q' => "Does your site answer your customers' real questions?",
         'why' => "AI assistants quote pages that answer a question plainly: what you do, prices or price ranges, service area, how it works, FAQs.",
         'options' => [['yes', 'Yes: FAQ, pricing and service-area pages', 4], ['some', 'Some of it', 2], ['no', "Not really, it's mostly a brochure", 0]]],
        ['id' => 'fresh', 'group' => 'content', 'points' => 4,
         'q' => 'How often do you add or update content on your site?',
         'why' => 'Fresh pages get crawled more often, and new content is more chances to be the answer.',
         'options' => [['monthly', 'At least monthly', 4], ['quarterly', 'Every few months', 2], ['rarely', 'Once a year or less', 0]]],
        ['id' => 'nap', 'group' => 'local', 'points' => 4,
         'q' => 'Is your business name, address and phone exactly the same everywhere online?',
         'why' => 'Mismatched listings (old phone numbers, old addresses) make AI tools unsure which details are right, so they recommend someone else.',
         'options' => [['yes', 'Yes, I keep them matched', 4], ['unsure', 'Probably, but I have not checked', 1], ['no', 'No, some are out of date', 0]]],
        ['id' => 'platform', 'group' => 'info', 'points' => 0,
         'q' => 'What is your website built on?',
         'options' => [['wordpress', 'WordPress', 0], ['builder', 'Wix, Squarespace, GoDaddy or similar', 0], ['custom', 'Custom / HTML', 0], ['unsure', 'Not sure', 0]]],
        ['id' => 'who', 'group' => 'info', 'points' => 0,
         'q' => 'Who takes care of your website today?',
         'options' => [['me', 'Me or my staff', 0], ['agency', 'A web company or freelancer', 0], ['ai', 'I use AI tools to update it', 0], ['nobody', 'Honestly, nobody', 0]]],
    ];
}

/** What the browser gets: questions and options, never the points. */
function ae_quiz_public_questions(): array
{
    return array_map(static fn($q) => [
        'id' => $q['id'],
        'q' => $q['q'],
        'why' => $q['why'] ?? '',
        'options' => array_map(static fn($o) => ['value' => $o[0], 'label' => $o[1]], $q['options']),
    ], ae_quiz_questions());
}

function ae_quiz_dir(string $sub = ''): string
{
    $dir = AE_QUIZ_DIR . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir;
}

function ae_new_token(): string
{
    return bin2hex(random_bytes(16));
}

function ae_valid_token(string $t): bool
{
    return (bool)preg_match('/^[a-f0-9]{32}$/', $t);
}

function ae_lead_path(string $token): string
{
    return ae_quiz_dir('leads') . '/' . $token . '.json';
}

function ae_lead_load(string $token): ?array
{
    if (!ae_valid_token($token) || !is_file(ae_lead_path($token))) {
        return null;
    }
    return json_decode((string)file_get_contents(ae_lead_path($token)), true) ?: null;
}

function ae_lead_save(array $lead): void
{
    $path = ae_lead_path($lead['token']);
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($lead, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    chmod($tmp, 0600);
    rename($tmp, $path);
}

/** Run $fn while holding this lead's lock, so the background scan and the finish never write over each other. */
function ae_with_lead_lock(string $token, callable $fn): mixed
{
    $fh = fopen(ae_quiz_dir('leads') . '/' . $token . '.lock', 'c');
    flock($fh, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/** Scan the lead's site once and store it. Safe to call from both scan.php and finish.php. */
function ae_ensure_scan(string $token): ?array
{
    return ae_with_lead_lock($token, static function () use ($token) {
        $lead = ae_lead_load($token);
        if ($lead === null) {
            return null;
        }
        if (empty($lead['scan'])) {
            $lead['scan'] = ae_scan_site($lead['site']);
            $lead['scanned'] = gmdate('c');
            ae_lead_save($lead);
        }
        return $lead;
    });
}

/** Signed value for unsubscribe links, so nobody can unsubscribe someone else. */
function ae_sign(string $token): string
{
    $keyFile = ae_quiz_dir() . '/secret.key';
    if (!is_file($keyFile)) {
        file_put_contents($keyFile, bin2hex(random_bytes(32)), LOCK_EX);
        chmod($keyFile, 0600);
    }
    return substr(hash_hmac('sha256', $token, trim((string)file_get_contents($keyFile))), 0, 24);
}

function ae_unsubscribe_url(string $token): string
{
    return AE_SITE . '/ai-ready/unsubscribe.php?t=' . $token . '&s=' . ae_sign($token);
}

/** All leads, newest first. */
function ae_leads(): array
{
    $out = [];
    foreach (glob(ae_quiz_dir('leads') . '/*.json') ?: [] as $f) {
        $l = json_decode((string)file_get_contents($f), true);
        if (is_array($l)) {
            $out[] = $l;
        }
    }
    usort($out, static fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));
    return $out;
}

function ae_client_ip_hash(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|ae-quiz');
}

/** True when this IP has started too many quizzes in the last hour. Records the attempt. */
function ae_rate_limited(): bool
{
    $file = ae_quiz_dir('rate') . '/' . ae_client_ip_hash() . '.json';
    $now = time();
    $hits = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $hits = array_values(array_filter($hits, static fn($t) => $t > $now - 3600));
    if (count($hits) >= AE_QUIZ_STARTS_PER_HOUR) {
        return true;
    }
    $hits[] = $now;
    file_put_contents($file, json_encode($hits), LOCK_EX);
    return false;
}

/** Funnel counter: one file of {date: {event: count}}. Events: view, start, finish, report, cta. */
function ae_funnel_hit(string $event): void
{
    if (!in_array($event, ['view', 'start', 'finish', 'report', 'cta'], true)) {
        return;
    }
    $file = ae_quiz_dir() . '/funnel.json';
    $fh = fopen($file, 'c+');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fh), true) ?: [];
    $day = gmdate('Y-m-d');
    $data[$day][$event] = ($data[$day][$event] ?? 0) + 1;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
}

function ae_funnel_totals(int $days = 30): array
{
    $file = ae_quiz_dir() . '/funnel.json';
    $data = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $since = gmdate('Y-m-d', time() - ($days - 1) * 86400);
    $tot = ['view' => 0, 'start' => 0, 'finish' => 0, 'report' => 0, 'cta' => 0];
    foreach ($data as $day => $events) {
        if ($day >= $since) {
            foreach ($events as $e => $n) {
                $tot[$e] = ($tot[$e] ?? 0) + $n;
            }
        }
    }
    return $tot;
}

/** Score the answers on the server. Unknown answers count as 0. */
function ae_score_answers(array $answers): array
{
    $points = 0;
    $max = 0;
    $items = [];
    foreach (ae_quiz_questions() as $q) {
        $given = (string)($answers[$q['id']] ?? '');
        $label = '';
        $earned = 0;
        foreach ($q['options'] as [$value, $optLabel, $pts]) {
            if ($value === $given) {
                $earned = $pts;
                $label = $optLabel;
            }
        }
        if ($q['points'] > 0) {
            $points += $earned;
            $max += $q['points'];
            $items[] = ['id' => $q['id'], 'q' => $q['q'], 'answer' => $label, 'points' => $earned, 'max' => $q['points'],
                        'pass' => $earned >= $q['points'] ? true : ($earned > 0 ? 'partial' : false)];
        }
    }
    return ['points' => $points, 'max' => $max, 'items' => $items];
}

function ae_grade(int $score): array
{
    foreach ([[90, 'A', 'AI-ready'], [80, 'B', 'Close, with a few gaps'], [70, 'C', 'Findable, but easy to pass over'], [55, 'D', 'Mostly invisible to AI'], [0, 'F', 'AI assistants can barely see you']] as [$min, $g, $label]) {
        if ($score >= $min) {
            return ['letter' => $g, 'label' => $label];
        }
    }
    return ['letter' => 'F', 'label' => ''];
}

/** How each missed item gets fixed, and which plan does it. */
function ae_fix_for(string $id): array
{
    $p99 = ['plan' => '$99/mo Hosting and portal', 'url' => '/pricing/'];
    $p200 = ['plan' => '$200/mo Growth', 'url' => '/pricing/'];
    $build = ['plan' => '$5,000 done-for-you build', 'url' => '/pricing/'];
    $fixes = [
        'https' => ['Get the site loading reliably over HTTPS.', $p99],
        'http_redirect' => ['Forward every http:// and www address to one secure address.', $p99],
        'robots' => ['Add a robots.txt that welcomes search and AI crawlers and lists your sitemap.', $p99],
        'search_bots' => ['Unblock Googlebot and Bingbot in robots.txt. This one is urgent.', $p99],
        'ai_bots' => ['Decide which AI assistants may read your site, and allow the ones that send customers (ChatGPT search, Perplexity, Claude).', $p99],
        'noindex' => ['Remove the "noindex" tag so search engines can list your home page. This one is urgent.', $p99],
        'sitemap' => ['Publish an XML sitemap of every page you want found, and keep it updated automatically.', $p99],
        'sitemap_listed' => ['Point robots.txt to your sitemap and submit it to Bing and Google.', $p99],
        'canonical' => ['Give every page a canonical tag with its one official address.', $p99],
        'title' => ['Rewrite page titles to say who you are and what you do, in 15 to 65 characters.', $p99],
        'description' => ['Write a meta description for each page that sums it up in one sentence.', $p99],
        'h1' => ['Give each page exactly one clear main heading.', $p99],
        'schema' => ['Add schema.org structured data (business name, phone, address, hours, services, FAQs).', $p99],
        'schema_business' => ['Add LocalBusiness or Organization schema so assistants know exactly who and where you are.', $p99],
        'llms' => ['Publish an llms.txt that tells AI tools what you do and which pages matter.', $p99],
        'mobile' => ['Make the site mobile-friendly. Google ranks the phone version first.', $build],
        'lang' => ['Declare the page language.', $p99],
        'speed' => ['Speed up the site. Plain HTML hosting loads in well under a second.', $build],
        'social' => ['Add Open Graph tags so shared links show a proper preview.', $p99],
        'alt' => ['Describe every image with alt text.', $p99],
        'bing_wmt' => ['Verify the site in Bing Webmaster Tools and submit your sitemap. We set this up for you.', $p99],
        'gsc' => ['Set up Google Search Console and connect it to your reports.', $p200],
        'gbp' => ['Claim and complete your Google Business Profile, then keep posting to it.', $p99],
        'bing_places' => ['Claim Bing Places and Apple Business Connect.', $p99],
        'reviews' => ['Start a simple review-request routine after every job.', $p200],
        'answers' => ['Add FAQ, pricing and service-area pages written the way customers ask.', $p99],
        'fresh' => ['Publish something new every month. Our AI Brain drafts it in your voice for you to approve.', $p99],
        'nap' => ['Match your name, address and phone on every listing.', $p99],
    ];
    [$text, $plan] = $fixes[$id] ?? ['', $p99];
    return ['fix' => $text] + $plan;
}

/** Combine scan + answers into the report the browser and the email both show. */
function ae_build_report(array $lead): array
{
    $scan = $lead['scan'] ?? ['points' => 0, 'max' => 70, 'checks' => [], 'reached' => false];
    $quiz = ae_score_answers($lead['answers'] ?? []);
    $score = (int)round(($scan['points'] ?? 0) + $quiz['points']);
    $missed = [];
    foreach ($scan['checks'] as $c) {
        if ($c['pass'] !== true) {
            $missed[] = ['id' => $c['id'], 'label' => $c['label'], 'lost' => $c['max'] - $c['points'], 'detail' => $c['detail']] + ae_fix_for($c['id']);
        }
    }
    $whyById = array_column(ae_quiz_questions(), 'why', 'id');
    foreach ($quiz['items'] as $i) {
        if ($i['pass'] !== true) {
            $missed[] = ['id' => $i['id'], 'label' => $i['q'], 'lost' => $i['max'] - $i['points'], 'detail' => 'You answered: ' . ($i['answer'] ?: 'no answer') . '. ' . ($whyById[$i['id']] ?? '')] + ae_fix_for($i['id']);
        }
    }
    // Urgent blockers first, then by points lost.
    $urgent = ['noindex' => 3, 'search_bots' => 2, 'https' => 1];
    usort($missed, static fn($a, $b) => [($urgent[$b['id']] ?? 0), $b['lost']] <=> [($urgent[$a['id']] ?? 0), $a['lost']]);
    $platform = (string)($lead['answers']['platform'] ?? '');
    $wordpress = $platform === 'wordpress' || !empty($scan['facts']['page']['wordpress']);
    $groups = [
        'crawl' => 'Can AI crawlers reach you?',
        'index' => 'Can they index you?',
        'understand' => 'Do they understand who you are?',
        'ready' => 'Ready for AI answers',
    ];
    $byGroup = [];
    foreach ($groups as $g => $label) {
        $cs = array_values(array_filter($scan['checks'], static fn($c) => $c['group'] === $g));
        $byGroup[] = ['id' => $g, 'label' => $label, 'points' => (int)round(array_sum(array_column($cs, 'points'))),
                      'max' => array_sum(array_column($cs, 'max')), 'checks' => $cs];
    }
    if (!$scan['reached']) {
        $byGroup = [['id' => 'crawl', 'label' => $groups['crawl'], 'points' => 0, 'max' => 70, 'checks' => $scan['checks']]];
    }
    $byGroup[] = ['id' => 'quiz', 'label' => 'What you told us (things a robot can\'t see)', 'points' => $quiz['points'], 'max' => $quiz['max'],
                  'checks' => array_map(static fn($i) => ['label' => $i['q'], 'points' => $i['points'], 'max' => $i['max'], 'pass' => $i['pass'], 'detail' => 'You answered: ' . ($i['answer'] ?: 'no answer')], $quiz['items'])];

    if ($score < 55 || $wordpress) {
        $offer = ['plan' => '$5,000 done-for-you build', 'pitch' => $wordpress
            ? 'WordPress sites need constant plugin updates and are the most hacked sites on the web. We rebuild yours as a fast, plain HTML site that passes every check on this list, then host it for $99 a month. 30-day money-back guarantee.'
            : 'There is a lot to fix here. A rebuild is usually faster and cheaper than patching. We build a fast, plain HTML site that passes every check on this list, then host it for $99 a month. 30-day money-back guarantee.'];
    } elseif ($score < 80) {
        $offer = ['plan' => '$99/mo Hosting and portal', 'pitch' => 'Most of your gaps are the technical basics our AI Brain SEO and standards check fix on every page, every time it changes. Hosting, backups, monitoring and social post tools are included.'];
    } else {
        $offer = ['plan' => '$200/mo Growth', 'pitch' => "You've got the basics. Growth adds funnel tracking, Google Analytics and Meta and Google pixels, so you can see which AI and search visits turn into customers."];
    }
    return [
        'name' => $lead['name'] ?? '',
        'site' => $lead['site'] ?? '',
        'created' => $lead['created'] ?? '',
        'score' => $score,
        'grade' => ae_grade($score),
        'scan_points' => (int)round($scan['points'] ?? 0),
        'quiz_points' => $quiz['points'],
        'reached' => (bool)($scan['reached'] ?? false),
        'scan_error' => $scan['error'] ?? '',
        'wordpress' => $wordpress,
        'groups' => $byGroup,
        'fixes' => array_slice($missed, 0, 5),
        'all_fixes' => count($missed),
        'offer' => $offer,
    ];
}

function ae_report_url(string $token): string
{
    return AE_SITE . '/ai-ready/report/?t=' . $token;
}

/** Plain-text email: the score, the top fixes and the link to the full report. */
function ae_report_email_text(array $r, string $token): string
{
    $first = trim(explode(' ', $r['name'])[0] ?? '');
    $lines = [];
    $lines[] = ($first !== '' ? "Hi $first," : 'Hi,');
    $lines[] = '';
    $lines[] = 'Here is the AI-Ready Site Score for ' . $r['site'] . ':';
    $lines[] = '';
    $lines[] = '  ' . $r['score'] . ' / 100  (grade ' . $r['grade']['letter'] . ': ' . $r['grade']['label'] . ')';
    $lines[] = '';
    if ($r['fixes']) {
        $lines[] = 'Your top fixes:';
        foreach ($r['fixes'] as $i => $f) {
            $lines[] = ($i + 1) . '. ' . $f['fix'];
        }
        $lines[] = '';
    }
    $lines[] = 'Full report: ' . ae_report_url($token);
    $lines[] = '';
    $lines[] = 'What we recommend: ' . $r['offer']['plan'];
    $lines[] = $r['offer']['pitch'];
    $lines[] = '';
    $lines[] = 'Want us to walk you through it? Reply to this email or call (865) 300-5397.';
    $lines[] = '';
    $lines[] = 'Agency Eagle';
    $lines[] = 'P.O. Box 99, Jefferson City, TN 37760';
    return implode("\n", $lines) . "\n";
}

function ae_mail(string $to, string $subject, string $body, string $replyTo = AE_SALES_EMAIL, array $extraHeaders = []): bool
{
    $headers = array_merge([
        'From: Agency Eagle <no-reply@agencyeagle.com>',
        'Reply-To: ' . $replyTo,
        'Content-Type: text/plain; charset=utf-8',
    ], $extraHeaders);
    if (getenv('AE_DEV') === '1') {
        file_put_contents(ae_quiz_dir() . '/dev-mail.log', "To: $to\nSubject: $subject\n" . implode("\n", $headers) . "\n\n$body\n-----\n", FILE_APPEND);
        return true;
    }
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

/** One line of input, newlines stripped so it can never inject an email header. */
function ae_clean_line(mixed $v, int $max): string
{
    return trim(mb_substr(preg_replace('/[\r\n\t\x00]+/', ' ', (string)$v), 0, $max));
}

function ae_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** JSON body of a POST from /assets/ai-ready.js, or an error response. */
function ae_read_json_post(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ae_json(['error' => 'POST only'], 405);
    }
    $raw = file_get_contents('php://input', false, null, 0, 20000);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        ae_json(['error' => 'Bad request'], 400);
    }
    return $data;
}
