<?php
// RFC 6238 TOTP (Google Authenticator compatible: SHA1, 6 digits, 30s).
declare(strict_types=1);

function totp_new_secret(int $bytes = 20): string
{
    return base32_encode(random_bytes($bytes));
}

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('J', $step), base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Returns the matched time step, or null. Accepts one step of clock drift.
 * Callers must reject a step <= the user's last used step (replay protection).
 */
function totp_verify(string $secret, string $code, ?int $now = null): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return null;
    }
    $step = intdiv($now ?? time(), 30);
    foreach ([0, -1, 1] as $drift) {
        if (hash_equals(totp_code($secret, $step + $drift), $code)) {
            return $step + $drift;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account, string $issuer = 'Agency Eagle'): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}
