<?php
// Admin auth: password, then TOTP. Users live in data/users.json (outside public_html).
declare(strict_types=1);

require_once __DIR__ . '/totp.php';

const AE_USERS_FILE = __DIR__ . '/../data/users.json';
const AE_MAX_FAILS = 5;
const AE_LOCK_SECONDS = 900;
const AE_IDLE_SECONDS = 1800;

function ae_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('ae_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => getenv('AE_DEV') !== '1', // AE_DEV=1 only for local testing over http
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > AE_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
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

/** Step 1: password. On success the session holds a pending user awaiting TOTP. */
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

/** Step 2: authenticator code. */
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

    session_regenerate_id(true);
    unset($_SESSION['pending_email'], $_SESSION['pending_at']);
    $_SESSION['admin_email'] = $email;
    return 'ok';
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

function ae_send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
}
