<?php
// Form leads for hosted client sites. A plain HTML form on the client's site posts to
// https://agencyeagle.com/forms/ with a hidden "site" field; the lead is saved here
// (data/forms/<domain>.jsonl), emailed to the client, and shown in their portal.
declare(strict_types=1);
require_once __DIR__ . '/clients.php';
require_once __DIR__ . '/whm.php';
require_once __DIR__ . '/quiz.php';

const AE_FORMS_DIR = __DIR__ . '/../data/forms';
const AE_FORMS_PER_HOUR = 8; // per visitor IP, across all sites

function ae_forms_path(string $domain): string
{
    if (!is_dir(AE_FORMS_DIR)) {
        mkdir(AE_FORMS_DIR, 0700, true);
    }
    return AE_FORMS_DIR . '/' . preg_replace('/[^a-z0-9.-]/', '', strtolower($domain)) . '.jsonl';
}

/** Newest first. */
function ae_forms_leads(string $domain, int $limit = 0): array
{
    $p = ae_forms_path($domain);
    if (!is_file($p)) {
        return [];
    }
    $out = [];
    foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $r = json_decode($line, true);
        if (is_array($r)) {
            $out[] = $r;
        }
    }
    $out = array_reverse($out);
    return $limit > 0 ? array_slice($out, 0, $limit) : $out;
}

/** The hosted site a form may post for, or null. */
function ae_forms_site(string $domain): ?array
{
    $domain = strtolower(preg_replace('/^www\./i', '', trim($domain)));
    foreach (ae_whm_sites()['sites'] as $s) {
        if ($s['domain'] === $domain && !$s['suspended']) {
            return $s;
        }
    }
    return null;
}

/** True when this visitor IP has sent too many forms in the last hour. Counts this one. */
function ae_forms_rate_limited(): bool
{
    $file = AE_FORMS_DIR . '/rate.json';
    if (!is_dir(AE_FORMS_DIR)) {
        mkdir(AE_FORMS_DIR, 0700, true);
    }
    $ip = ae_client_ip_hash();
    $fh = fopen($file, 'c+');
    flock($fh, LOCK_EX);
    $all = json_decode((string)stream_get_contents($fh), true) ?: [];
    $cut = time() - 3600;
    foreach ($all as $k => $times) {
        $all[$k] = array_values(array_filter($times, fn($t) => $t > $cut));
        if (!$all[$k]) {
            unset($all[$k]);
        }
    }
    $limited = count($all[$ip] ?? []) >= AE_FORMS_PER_HOUR;
    if (!$limited) {
        $all[$ip][] = time();
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($all));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $limited;
}

/** Save a lead and email it. $lead has name, email, phone, message, page. */
function ae_forms_save(array $site, array $lead): void
{
    $lead = ['at' => time()] + $lead;
    file_put_contents(ae_forms_path($site['domain']), json_encode($lead) . "\n", FILE_APPEND | LOCK_EX);
    $to = ae_client_emails($site['domain']) ?: array_filter([$site['email']]);
    $body = "New message from your website, " . $site['domain'] . "\n\n"
        . 'Name: ' . $lead['name'] . "\n"
        . 'Email: ' . $lead['email'] . "\n"
        . ($lead['phone'] !== '' ? 'Phone: ' . $lead['phone'] . "\n" : '')
        . ($lead['page'] !== '' ? 'Page: ' . $lead['page'] . "\n" : '')
        . "\n" . $lead['message'] . "\n\n"
        . "Reply to this email to answer them. All your leads: https://" . $site['domain'] . "/admin\n";
    foreach ($to as $addr) {
        ae_mail($addr, 'New lead from ' . $site['domain'] . ': ' . $lead['name'], $body, filter_var($lead['email'], FILTER_VALIDATE_EMAIL) ? $lead['email'] : AE_SALES_EMAIL);
    }
}

/** Ready-to-paste form HTML for a client's site. */
function ae_forms_snippet(string $domain): string
{
    return '<form action="https://agencyeagle.com/forms/" method="post">' . "\n"
        . '  <input type="hidden" name="site" value="' . $domain . '">' . "\n"
        . '  <p><label for="f-name">Name</label><br><input id="f-name" name="name" required autocomplete="name"></p>' . "\n"
        . '  <p><label for="f-email">Email</label><br><input id="f-email" name="email" type="email" required autocomplete="email"></p>' . "\n"
        . '  <p><label for="f-phone">Phone</label><br><input id="f-phone" name="phone" type="tel" autocomplete="tel"></p>' . "\n"
        . '  <p><label for="f-message">Message</label><br><textarea id="f-message" name="message" rows="5" required></textarea></p>' . "\n"
        . '  <p style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></p>' . "\n"
        . '  <p><button type="submit">Send</button></p>' . "\n"
        . '</form>';
}

/** CSV download of every lead. */
function ae_forms_csv(string $domain): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9.-]/', '', $domain) . '-leads.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date (UTC)', 'Name', 'Email', 'Phone', 'Message', 'Page']);
    foreach (ae_forms_leads($domain) as $l) {
        $row = [gmdate('Y-m-d H:i', (int)$l['at']), $l['name'], $l['email'], $l['phone'], $l['message'], $l['page']];
        // Keep spreadsheet apps from running a lead's text as a formula.
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v, $row));
    }
    exit;
}
