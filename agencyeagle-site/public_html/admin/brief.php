<?php
// Site Brief: one Markdown file an AI assistant or developer can read before touching a client's site.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';
require_once __DIR__ . '/../_private/app/whm.php';
require_once __DIR__ . '/../_private/app/brain.php';
require_once __DIR__ . '/../_private/app/backups.php';

ae_require_admin();
$user = (string)($_GET['user'] ?? '');
$site = preg_match('/^[a-z][a-z0-9]{0,15}$/', $user) ? ae_whm_site($user) : null;
if ($site === null) {
    http_response_code(404);
    exit('Site not found');
}
$brain = ae_brain_get($site['domain']);
$backups = ae_backup_cached($user);
$standardsFile = __DIR__ . '/../_private/STANDARDS.md';
$standards = is_file($standardsFile) ? trim((string)file_get_contents($standardsFile)) : '';

$md = "# Site Brief: {$site['domain']}\n\n";
$md .= "Generated " . gmdate('M j, Y H:i') . " UTC by Agency Eagle (agencyeagle.com). Read this whole file before changing anything on the site.\n\n";
$md .= "## The site\n\n";
$md .= "- Domain: https://{$site['domain']}/\n";
$md .= "- Hosting: Agency Eagle, cPanel account `{$site['user']}` on the {$site['plan']} package" . ($site['suspended'] ? " (currently SUSPENDED)" : '') . "\n";
$md .= "- Contact email on the account: " . ($site['email'] ?: 'not set') . "\n";
$md .= "- Agency Eagle standard: plain HTML sites (no WordPress, no page builders), every page a static .html file in `public_html/`. If this site still runs WordPress or another CMS, ask Agency Eagle before changing it.\n";
if ($backups && $backups['files']) {
    $md .= "- Latest full backup: " . gmdate('M j, Y', $backups['files'][0]['at']) . "\n";
}
$md .= "\n## How changes get made\n\n";
$md .= "1. Keep pages as clean, hand-readable HTML that passes every **Block** rule in the standards below.\n";
$md .= "2. Send finished pages to Agency Eagle (help@agencyeagle.com) or upload them through the client admin. We back up the site before every change, so anything can be rolled back.\n";
$md .= "3. Don't add trackers, chat widgets or third-party scripts without asking: they slow the site down and some break the standards.\n";
$md .= "4. Never put passwords, API keys or private customer data in a page.\n\n";
$md .= "## SEO brain\n\n";
if ($brain) {
    $md .= "Score: **{$brain['score']}/100** ({$brain['grade']['label']}), checked " . gmdate('M j, Y', (int)$brain['at']) . ". {$brain['passed']} of {$brain['checked']} checks pass.\n\n";
    if ($brain['fixes']) {
        $md .= "Fix these first, in this order:\n\n";
        foreach ($brain['fixes'] as $i => $f) {
            $md .= ($i + 1) . ". **{$f['label']}**. " . ($f['fix'] !== '' ? $f['fix'] . ' ' : '') . "_Now: {$f['detail']}_\n";
        }
        $md .= "\n";
    }
} else {
    $md .= "Not scored yet. Ask Agency Eagle to run the SEO brain, or follow the standards below.\n\n";
}
if ($standards !== '') {
    $md .= "## Agency Eagle standards\n\n" . preg_replace('/^# .*\n+/', '', $standards) . "\n";
}

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="site-brief-' . $site['domain'] . '.md"');
header('Cache-Control: no-store');
echo $md;
