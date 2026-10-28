<?php
// Admin auth. Primary: Google sign-in (OpenID Connect) limited to the allowed emails.
// Backup: password + authenticator code (users in data/users.json, set up from
// /admin/security.php after a Google sign-in, or with tools/create-admin.php over SSH).
// Emergency: a one-time break-glass link that can only be issued over SSH.
// Google config lives in data/oauth.conf (written by the deploy from GitHub secrets).
declare(strict_types=1);

require_once __DIR__ . '/totp.php';

const AE_USERS_FILE = __DIR__ . '/../data/users.json';
const AE_MAX_FAILS = 5;
const AE_LOCK_SECONDS = 900;
const AE_OAUTH_CONF = __DIR__ . '/../data/oauth.conf';
const AE_BREAKGLASS_FILE = __DIR__ . '/../data/breakglass.token';
const AE_BREAKGLASS_SECONDS = 600;
const AE_IDLE_SECONDS = 1800;
const AE_GOOGLE_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
const AE_GOOGLE_TOKEN = 'https://oauth2.googleapis.com/token';

function ae_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('ae_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/', // shared by /admin and the client /portal; each checks its own session key
        'secure' => getenv('AE_DEV') !== '1', // AE_DEV=1 only for local testing over http
        'httponly' => true,
        'samesite' => 'Lax', // Lax so the cookie survives the redirect back from Google
    ]);
    session_start();
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > AE_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

/** ['client_id' => ..., 'client_secret' => ..., 'allowed_emails' => [...]] or null if not set up. */
function ae_oauth_conf(): ?array
{
    if (!is_file(AE_OAUTH_CONF)) {
        return null;
    }
    $conf = json_decode((string)file_get_contents(AE_OAUTH_CONF), true);
    if (!is_array($conf) || empty($conf['client_id']) || empty($conf['client_secret'])) {
        return null;
    }
    $conf['allowed_emails'] = array_values(array_filter(array_map(
        fn($e) => strtolower(trim((string)$e)),
        (array)($conf['allowed_emails'] ?? [])
    )));
    return $conf['allowed_emails'] === [] ? null : $conf;
}

function ae_redirect_uri(): string
{
    return 'https://' . ($_SERVER['HTTP_HOST'] ?? 'agencyeagle.com') . '/admin/oauth-callback.php';
}

function ae_b64url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/** URL that starts Google sign-in (state + nonce + PKCE kept in the session). */
function ae_google_login_url(array $conf, string $purpose = 'admin'): string
{
    $_SESSION['oauth_purpose'] = $purpose;
    $_SESSION['oauth_state'] = ae_b64url(random_bytes(24));
    $_SESSION['oauth_nonce'] = ae_b64url(random_bytes(24));
    $_SESSION['oauth_verifier'] = ae_b64url(random_bytes(48));
    $_SESSION['oauth_at'] = time();
    return AE_GOOGLE_AUTH . '?' . http_build_query([
        'client_id' => $conf['client_id'],
        'redirect_uri' => ae_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email',
        'state' => $_SESSION['oauth_state'],
        'nonce' => $_SESSION['oauth_nonce'],
        'code_challenge' => ae_b64url(hash('sha256', $_SESSION['oauth_verifier'], true)),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ]);
}

/**
 * Finish Google sign-in. Returns 'ok' (admin), 'portal' (client portal) or a short error key.
 * Admins must be in the allowlist; portal users must own at least one site (clients.php).
 */
function ae_google_callback(array $conf, array $query): string
{
    $purpose = $_SESSION['oauth_purpose'] ?? 'admin';
    unset($_SESSION['oauth_purpose']);
    $state = $_SESSION['oauth_state'] ?? '';
    $nonce = $_SESSION['oauth_nonce'] ?? '';
    $verifier = $_SESSION['oauth_verifier'] ?? '';
    $fresh = time() - ($_SESSION['oauth_at'] ?? 0) < 600;
    unset($_SESSION['oauth_state'], $_SESSION['oauth_nonce'], $_SESSION['oauth_verifier'], $_SESSION['oauth_at']);

    if ($state === '' || !$fresh || !hash_equals($state, (string)($query['state'] ?? ''))) {
        return 'expired';
    }
    if (empty($query['code'])) {
        return 'cancelled';
    }

    $ch = curl_init(AE_GOOGLE_TOKEN);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => (string)$query['code'],
            'client_id' => $conf['client_id'],
            'client_secret' => $conf['client_secret'],
            'redirect_uri' => ae_redirect_uri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $token = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || empty($token['id_token'])) {
        return 'google';
    }

    // The ID token came straight from Google's token endpoint over TLS, so its
    // signature check can be skipped (OpenID Connect Core 3.1.3.7); the claims are still checked.
    $parts = explode('.', (string)$token['id_token']);
    $claims = count($parts) === 3 ? json_decode((string)base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
    if (!is_array($claims)
        || !in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
        || ($claims['aud'] ?? '') !== $conf['client_id']
        || ($claims['exp'] ?? 0) < time()
        || !hash_equals($nonce, (string)($claims['nonce'] ?? ''))
        || ($claims['email_verified'] ?? false) !== true) {
        return 'google';
    }
    $email = strtolower((string)($claims['email'] ?? ''));
    if ($purpose === 'portal') {
        require_once __DIR__ . '/clients.php';
        if (!ae_portal_sites_for($email)) {
            return 'not_allowed';
        }
        session_regenerate_id(true);
        $_SESSION['portal_email'] = $email;
        return 'portal';
    }
    if (!in_array($email, $conf['allowed_emails'], true)) {
        return 'not_allowed';
    }
    ae_sign_in($email);
    return 'ok';
}

function ae_users(): array
{
    if (!is_file(AE_USERS_FILE)) {
        return [];
    }
    return json_decode((string)file_get_contents(AE_USERS_FILE), true) ?: [];
}

function ae_save_users(array $users): void
{
    $tmp = AE_USERS_FILE . '.tmp';
    file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
    chmod($tmp, 0600);
    rename($tmp, AE_USERS_FILE);
}

function ae_locked(array $user): bool
{
    return ($user['locked_until'] ?? 0) > time();
}

function ae_record_fail(array &$users, string $email): void
{
    $users[$email]['fails'] = ($users[$email]['fails'] ?? 0) + 1;
    if ($users[$email]['fails'] >= AE_MAX_FAILS) {
        $users[$email]['locked_until'] = time() + AE_LOCK_SECONDS;
        $users[$email]['fails'] = 0;
    }
    ae_save_users($users);
}

/** Backup login step 1: password. On success the session holds a pending user awaiting TOTP. */
function ae_check_password(string $email, string $password): string
{
    $email = strtolower(trim($email));
    $users = ae_users();
    $user = $users[$email] ?? null;
    if ($user === null) {
        password_verify($password, '$2y$10$5xNHXCdGYMutI2PTO7mxP.6NiWS3jcKC5t.QIvNjfq6q71ydMFPAG'); // even out timing
        return 'invalid';
    }
    if (ae_locked($user)) {
        return 'locked';
    }
    if (!password_verify($password, $user['password_hash'])) {
        ae_record_fail($users, $email);
        return 'invalid';
    }
    session_regenerate_id(true);
    $_SESSION['pending_email'] = $email;
    $_SESSION['pending_at'] = time();
    return 'ok';
}

/** Backup login step 2: authenticator code. */
function ae_check_totp(string $code): string
{
    $email = $_SESSION['pending_email'] ?? null;
    if ($email === null || time() - ($_SESSION['pending_at'] ?? 0) > 300) {
        unset($_SESSION['pending_email'], $_SESSION['pending_at']);
        return 'expired';
    }
    $users = ae_users();
    $user = $users[$email] ?? null;
    if ($user === null || ae_locked($user)) {
        return 'locked';
    }
    $step = totp_verify($user['totp_secret'], $code);
    if ($step === null || $step <= ($user['totp_last_step'] ?? -1)) {
        ae_record_fail($users, $email);
        return 'invalid';
    }
    $users[$email]['totp_last_step'] = $step;
    $users[$email]['fails'] = 0;
    $users[$email]['last_login'] = gmdate('c');
    ae_save_users($users);

    unset($_SESSION['pending_email'], $_SESSION['pending_at']);
    ae_sign_in($email);
    return 'ok';
}

/** Break-glass: tools/break-glass.php (SSH only) writes a hashed one-time token. */
function ae_use_breakglass(string $token): bool
{
    if ($token === '' || !is_file(AE_BREAKGLASS_FILE)) {
        return false;
    }
    $saved = json_decode((string)file_get_contents(AE_BREAKGLASS_FILE), true);
    unlink(AE_BREAKGLASS_FILE); // single use, even on a wrong guess
    if (!is_array($saved) || ($saved['expires'] ?? 0) < time()
        || !hash_equals((string)($saved['hash'] ?? ''), hash('sha256', $token))) {
        return false;
    }
    ae_sign_in('break-glass (SSH)');
    return true;
}

function ae_sign_in(string $who): void
{
    session_regenerate_id(true);
    $_SESSION['admin_email'] = $who;
}

function ae_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ae_check_csrf(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function ae_require_admin(): string
{
    ae_session_start();
    if (empty($_SESSION['admin_email'])) {
        header('Location: /admin/');
        exit;
    }
    return $_SESSION['admin_email'];
}

/** The signed-in client's email, or a redirect to the portal sign-in. */
function ae_require_portal(): string
{
    ae_session_start();
    if (empty($_SESSION['portal_email'])) {
        header('Location: /portal/');
        exit;
    }
    return $_SESSION['portal_email'];
}

function ae_send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
}

/** Stylesheet URL with a version stamp, so a deploy is never hidden behind a cached copy. */
function ae_css_href(): string
{
    $f = __DIR__ . '/../../assets/site.css';
    return '/assets/site.css?v=' . (is_file($f) ? filemtime($f) : '1');
}
