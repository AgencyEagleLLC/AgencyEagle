<?php
// Shared header and nav for the /admin/crm pages.
declare(strict_types=1);

require_once __DIR__ . '/admin_layout.php';

function ae_h(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES);
}

function ae_crm_head(string $title, string $email, string $active): void
{
    ae_send_security_headers();
    $nav = ['index' => ['/admin/crm/', 'Pipeline'], 'list' => ['/admin/crm/?view=list', 'All contacts'], 'tasks' => ['/admin/crm/tasks.php', 'Tasks'], 'leads' => ['/admin/leads.php', 'Quiz leads']];
    ae_admin_head($title . ' | CRM', $email, 'crm');
    ?>
<nav class="crm-nav" aria-label="CRM">
  <?php foreach ($nav as $key => [$href, $label]): ?>
    <a href="<?= ae_h($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= ae_h($label) ?></a>
  <?php endforeach ?>
</nav>
<div class="crm">
<?php
}

function ae_crm_foot(): void
{
    echo "</div>\n";
    ae_admin_foot();
}

function ae_crm_date(string $iso): string
{
    return $iso === '' ? '' : date('M j, Y', strtotime($iso));
}
