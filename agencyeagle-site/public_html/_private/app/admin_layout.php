<?php
// Shared header and nav for the hosting admin pages (Sites, Site, Activate, Audit log).
declare(strict_types=1);

function ae_admin_head(string $title, string $email, string $active = 'sites'): void
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
    $nav = [
        'sites' => ['/admin/sites.php', 'Sites'],
        'crm' => ['/admin/crm/', 'CRM'],
        'leads' => ['/admin/leads.php', 'Leads'],
        'audit' => ['/admin/audit.php', 'Audit log'],
        'security' => ['/admin/security.php', 'Security'],
    ];
    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . $h($title) . ' | Agency Eagle Admin</title>
<link rel="stylesheet" href="' . ae_css_href() . '">
</head>
<body class="admin console">
<header class="console-bar">
  <a class="console-brand" href="/admin/sites.php"><strong>Agency Eagle</strong> <span class="console-pill">Admin</span></a>
  <nav class="console-nav" aria-label="Main">';
    foreach ($nav as $key => [$href, $label]) {
        echo '<a href="' . $h($href) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . $h($label) . '</a>';
    }
    echo '</nav>
  <span class="console-user">' . $h($email) . ' · <a href="/admin/logout.php">Sign out</a></span>
</header>
<main class="console-main">
';
}

function ae_admin_foot(): void
{
    echo "</main>\n</body>\n</html>\n";
}

/** One-time message carried across a redirect. */
function ae_flash(?array $set = null): ?array
{
    if ($set !== null) {
        $_SESSION['flash'] = $set;
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Header for the client portal (white bar, like the client mockup). */
function ae_portal_head(string $title, ?string $email, ?string $domain = null): void
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . $h($title) . ' | Agency Eagle</title>
<link rel="stylesheet" href="' . ae_css_href() . '">
</head>
<body class="admin console portal">
<header class="console-bar portal-bar">
  <a class="console-brand" href="/portal/"><strong>' . ($domain ? $h($domain) : 'Agency Eagle') . '</strong> <span class="console-pill">Site admin</span></a>
  ' . ($email ? '<span class="console-user">' . $h($email) . ' · <a href="/portal/logout.php">Sign out</a></span>' : '<span class="console-user">Hosted by <a href="https://agencyeagle.com/">Agency Eagle</a></span>') . '
</header>
<main class="console-main">
';
}
