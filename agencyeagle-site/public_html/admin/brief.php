<?php
// Site Brief: one Markdown file an AI assistant or developer can read before touching a client's site.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/brief.php';

ae_require_admin();
$user = (string)($_GET['user'] ?? '');
$site = preg_match('/^[a-z][a-z0-9]{0,15}$/', $user) ? ae_whm_site($user) : null;
if ($site === null) {
    http_response_code(404);
    exit('Site not found');
}
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="site-brief-' . $site['domain'] . '.md"');
header('Cache-Control: no-store');
echo ae_site_brief($site);
