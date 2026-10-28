<?php
// The Sites page is the admin home; everything else is in its top nav.
declare(strict_types=1);
require_once __DIR__ . '/../_private/app/auth.php';

ae_require_admin();
header('Location: /admin/sites.php');
