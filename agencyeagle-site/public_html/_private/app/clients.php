<?php
// Who may use the client portal for which site: data/clients.json maps a domain to the
// Google accounts allowed in ({"newclient.com": {"emails": ["owner@gmail.com"]}}).
declare(strict_types=1);

const AE_CLIENTS_FILE = __DIR__ . '/../data/clients.json';

function ae_clients(): array
{
    $c = is_file(AE_CLIENTS_FILE) ? json_decode((string)file_get_contents(AE_CLIENTS_FILE), true) : null;
    return is_array($c) ? $c : [];
}

function ae_client_emails(string $domain): array
{
    return ae_clients()[$domain]['emails'] ?? [];
}

/** Replace the allowed emails for a site. Returns the cleaned list. */
function ae_client_set_emails(string $domain, array $emails): array
{
    $clean = array_values(array_unique(array_filter(array_map(fn($e) => strtolower(trim((string)$e)), $emails), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    $all = ae_clients();
    if ($clean) {
        $all[$domain] = ['emails' => $clean];
    } else {
        unset($all[$domain]);
    }
    file_put_contents(AE_CLIENTS_FILE, json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod(AE_CLIENTS_FILE, 0600);
    return $clean;
}

/** Domains this Google account may manage. */
function ae_portal_sites_for(string $email): array
{
    $email = strtolower($email);
    $out = [];
    foreach (ae_clients() as $domain => $c) {
        if (in_array($email, $c['emails'] ?? [], true)) {
            $out[] = $domain;
        }
    }
    sort($out);
    return $out;
}

/** The /admin page installed on a client's site: it sends them to their portal. */
function ae_client_admin_stub(string $domain): string
{
    $url = 'https://agencyeagle.com/portal/?site=' . rawurlencode($domain);
    return "<?php\n// Agency Eagle client admin: sign in at the Agency Eagle portal.\nheader('Location: " . $url . "', true, 302);\n";
}
