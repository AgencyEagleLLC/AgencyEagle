<?php
// Site Brief download for a signed-in client.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/clients.php';
require_once __DIR__ . '/../_private/app/brief.php';

$email = ae_require_portal();
$domain = strtolower((string)($_GET['d'] ?? ''));
$site = null;
if (in_array($domain, ae_portal_sites_for($email), true)) {
    foreach (ae_whm_sites()['sites'] as $s) {
        if ($s['domain'] === $domain) {
            $site = $s;
        }
    }
}
if ($site === null) {
    http_response_code(404);
    exit('Site not found');
}
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="site-brief-' . $domain . '.md"');
header('Cache-Control: no-store');
echo ae_site_brief($site);
