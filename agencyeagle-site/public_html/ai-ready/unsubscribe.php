<?php
// One-click unsubscribe from the follow-up emails.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/quiz.php';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$sig = (string)($_GET['s'] ?? $_POST['s'] ?? '');
$ok = ae_valid_token($token) && hash_equals(ae_sign($token), $sig) && ae_lead_load($token) !== null;
if ($ok) {
    ae_with_lead_lock($token, static function () use ($token) {
        $l = ae_lead_load($token);
        $l['unsubscribed'] = gmdate('c');
        ae_lead_save($l);
    });
}
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {   // RFC 8058 one-click from the mail app
    http_response_code($ok ? 200 : 400);
    exit;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Unsubscribed | Agency Eagle</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body>
<main id="main" class="page-head">
  <div class="container">
    <h1><?= $ok ? "You're unsubscribed" : "That link didn't work" ?></h1>
    <p><?= $ok ? "We won't send you any more follow-up emails about your AI-Ready Site Score." : 'Please email <a href="mailto:help@agencyeagle.com">help@agencyeagle.com</a> and we will remove you by hand.' ?> <a href="/">Back to Agency Eagle</a></p>
  </div>
</main>
</body>
</html>
