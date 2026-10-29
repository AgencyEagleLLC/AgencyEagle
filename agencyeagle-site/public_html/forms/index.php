<?php
// Form endpoint for hosted client sites: <form action="https://agencyeagle.com/forms/" method="post">
// with a hidden "site" field. Optional "_next" sends the visitor to a thank-you page on that site.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/forms.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}
$clean = static fn(string $k, int $max): string => trim(mb_substr(str_replace(["\r", "\n"], ' ', (string)($_POST[$k] ?? '')), 0, $max));
$site = ae_forms_site($clean('site', 253));
$thanks = '/forms/thanks.php' . ($site ? '?site=' . rawurlencode($site['domain']) : '');

// The form must be on the site it names (browsers send Origin on cross-site posts).
$from = strtolower((string)parse_url((string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
if ($site === null || !in_array($from, [$site['domain'], 'www.' . $site['domain']], true)) {
    http_response_code(400);
    echo 'This form isn\'t set up to send from here.';
    exit;
}
$next = (string)($_POST['_next'] ?? '');
$nextHost = strtolower((string)parse_url($next, PHP_URL_HOST));
if (preg_match('#^https://#i', $next) && in_array($nextHost, [$site['domain'], 'www.' . $site['domain']], true)) {
    $thanks = $next;
}
if (($_POST['website'] ?? '') !== '') { // honeypot filled: a bot. Pretend it worked.
    header('Location: ' . $thanks, true, 303);
    exit;
}
$lead = [
    'name' => $clean('name', 100),
    'email' => $clean('email', 200),
    'phone' => $clean('phone', 40),
    'message' => trim(mb_substr((string)($_POST['message'] ?? ''), 0, 5000)),
    'page' => mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 300),
];
if ($lead['name'] === '' || $lead['message'] === '' || !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo 'Please go back and fill in your name, a valid email and a message.';
    exit;
}
if (ae_forms_rate_limited()) {
    http_response_code(429);
    echo 'Too many messages from your connection. Please try again in an hour, or call the business directly.';
    exit;
}
ae_forms_save($site, $lead);
header('Location: ' . $thanks, true, 303);
