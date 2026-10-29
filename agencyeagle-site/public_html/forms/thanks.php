<?php
// Shown after a client-site form is sent, unless the form gave its own "_next" page.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/forms.php';
require_once __DIR__ . '/../_private/app/auth.php';

ae_send_security_headers();
$site = ae_forms_site((string)($_GET['site'] ?? ''));
$domain = $site['domain'] ?? '';
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Message sent</title>
<link rel="stylesheet" href="<?= ae_css_href() ?>">
</head>
<body class="console">
<main class="console-main forms-thanks">
  <section class="console-card console-pad">
    <h1>Thanks, your message was sent</h1>
    <p><?= $domain !== '' ? 'The team at ' . $h($domain) . ' will get back to you soon.' : 'We\'ll get back to you soon.' ?></p>
<?php if ($domain !== ''): ?>
    <p><a class="button" href="https://<?= $h($domain) ?>/">Back to <?= $h($domain) ?></a></p>
<?php endif; ?>
  </section>
</main>
</body>
</html>
