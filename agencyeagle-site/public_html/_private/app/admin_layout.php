<?php
// Shared header for the admin pages that manage hosting accounts.
declare(strict_types=1);

function ae_admin_head(string $title, string $email): void
{
    $h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES);
    echo '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>' . $h($title) . ' | Agency Eagle Admin</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body class="admin">
<header class="admin-bar">
  <strong>Agency Eagle Admin</strong>
  <span>' . $h($email) . ' · <a href="/admin/dashboard.php">Dashboard</a> · <a href="/admin/sites.php">Sites</a> · <a href="/admin/audit.php">Audit log</a> · <a href="/admin/logout.php">Sign out</a></span>
</header>
<main class="container">
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
