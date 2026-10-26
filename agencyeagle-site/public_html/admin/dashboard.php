<?php
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

$email = ae_require_admin();
ae_send_security_headers();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Dashboard | Agency Eagle Admin</title>
<link rel="stylesheet" href="/assets/site.css">
</head>
<body class="admin">
<header class="admin-bar">
  <strong>Agency Eagle Admin</strong>
  <span><?= htmlspecialchars($email) ?> · <a href="/admin/logout.php">Sign out</a></span>
</header>
<main class="container">
  <h1>Clients</h1>
  <p>Client list, Site Brief downloads, backups and uploads arrive here in the next build steps.</p>
  <h2>Marketing</h2>
  <p><a class="button" href="/admin/leads.php">AI-Ready Site Score leads</a></p>
  <h2>Security</h2>
  <p><a class="button ghost" href="/admin/security.php">Backup login (password + authenticator)</a></p>
</main>
</body>
</html>
