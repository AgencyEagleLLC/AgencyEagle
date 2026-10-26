<?php
// Quiz gate: name, email and website before question one. Only then are the questions sent.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$in = ae_read_json_post();
if (($in['company_url'] ?? '') !== '') {          // honeypot filled: answer like a success, store nothing
    ae_json(['token' => ae_new_token(), 'questions' => ae_quiz_public_questions()]);
}
$name = ae_clean_line($in['name'] ?? '', 100);
$email = strtolower(ae_clean_line($in['email'] ?? '', 200));
$phone = ae_clean_line($in['phone'] ?? '', 40);
$business = ae_clean_line($in['business'] ?? '', 120);
$site = ae_normalize_site((string)($in['site'] ?? ''));

$errors = [];
if ($name === '') {
    $errors['name'] = 'Please enter your name.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email. Your report is sent there.';
}
if ($site === null) {
    $errors['site'] = 'Please enter your website address, like yourbusiness.com.';
}
if ($errors) {
    ae_json(['errors' => $errors], 422);
}
if (ae_rate_limited()) {
    ae_json(['errors' => ['form' => 'Too many scores from this connection in the last hour. Please try again later, or call (865) 300-5397.']], 429);
}

$token = ae_new_token();
ae_lead_save([
    'token' => $token,
    'created' => gmdate('c'),
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'business' => $business,
    'site' => $site,
    'ip_hash' => ae_client_ip_hash(),
    'referrer' => ae_clean_line($in['ref'] ?? '', 300),
    'utm' => array_map(static fn($v) => ae_clean_line($v, 100), array_intersect_key((array)($in['utm'] ?? []), array_flip(['source', 'medium', 'campaign', 'content', 'term']))),
    'status' => 'started',
    'emails' => [],
]);
ae_funnel_hit('start');
ae_json(['token' => $token, 'site' => $site, 'questions' => ae_quiz_public_questions()]);
