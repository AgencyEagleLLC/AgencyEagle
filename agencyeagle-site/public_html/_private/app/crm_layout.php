<?php
// Shared header and nav for the /admin/crm pages.
declare(strict_types=1);

function ae_h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

function ae_crm_head(string $title, string $email, string $active): void
{
    ae_send_security_headers();
    $nav = ['index' => ['/admin/crm/', 'Pipeline'], 'list' => ['/admin/crm/?view=list', 'All contacts'], 'tasks' => ['/admin/crm/tasks.php', 'Tasks'], 'leads' => ['/admin/leads.php', 'Quiz leads']];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= ae_h($title) ?> | Agency Eagle CRM</title>
<link rel="stylesheet" href="<?= ae_css_href() ?>">
</head>
<body class="admin">
<header class="admin-bar">
  <strong><a href="/admin/dashboard.php">Agency Eagle Admin</a></strong>
  <span><?= ae_h($email) ?> · <a href="/admin/logout.php">Sign out</a></span>
</header>
<nav class="crm-nav" aria-label="CRM">
  <?php foreach ($nav as $key => [$href, $label]): ?>
    <a href="<?= ae_h($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= ae_h($label) ?></a>
  <?php endforeach ?>
</nav>
<main class="container crm">
<?php
}

function ae_crm_foot(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function ae_crm_date(string $iso): string
{
    return $iso === '' ? '' : date('M j, Y', strtotime($iso));
}
