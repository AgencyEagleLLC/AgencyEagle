<?php
// Account backups through cPanel's own backup system (UAPI Backup and Fileman, via WHM).
// "Back up now" writes a full backup (files, databases, email) into the account's home folder;
// the list is cached per account so the Sites page can show the latest one without asking WHM.
declare(strict_types=1);
require_once __DIR__ . '/whm.php';

const AE_BACKUP_DIR = __DIR__ . '/../data/backups';

function ae_uapi(string $user, string $module, string $func, array $args = []): array
{
    $conf = ae_whm_conf();
    if ($conf === null) {
        throw new RuntimeException('WHM is not connected');
    }
    return ae_whm_call($conf, 'cpanel', [
        'cpanel_jsonapi_user' => $user,
        'cpanel_jsonapi_apiversion' => 3,
        'cpanel_jsonapi_module' => $module,
        'cpanel_jsonapi_func' => $func,
    ] + $args);
}

function ae_backup_cache_path(string $user): string
{
    if (!is_dir(AE_BACKUP_DIR)) {
        mkdir(AE_BACKUP_DIR, 0700, true);
    }
    return AE_BACKUP_DIR . '/' . preg_replace('/[^a-z0-9]/', '', $user) . '.json';
}

/** Backups sitting in the account's home folder, newest first. Refreshes the cache. */
function ae_backup_list(string $user): array
{
    $res = ae_uapi($user, 'Fileman', 'list_files', ['dir' => '/home/' . $user, 'types' => 'file', 'include_mime' => 0]);
    $out = [];
    foreach ((array)($res['data'] ?? []) as $f) {
        $name = (string)($f['file'] ?? '');
        if (preg_match('/^backup-.*\.tar\.gz$/', $name)) {
            $out[] = ['file' => $name, 'size' => (int)($f['size'] ?? 0), 'at' => (int)($f['mtime'] ?? 0)];
        }
    }
    usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
    file_put_contents(ae_backup_cache_path($user), json_encode(['checked' => time(), 'files' => $out]), LOCK_EX);
    return $out;
}

/** The cached list (no WHM call): ['checked' => ts, 'files' => [...]] or null. */
function ae_backup_cached(string $user): ?array
{
    $p = ae_backup_cache_path($user);
    $r = is_file($p) ? json_decode((string)file_get_contents($p), true) : null;
    return is_array($r) ? $r : null;
}

/** Start a full backup into the home folder. cPanel emails the contact address when it finishes. */
function ae_backup_start(string $who, string $user, string $notify): void
{
    try {
        ae_uapi($user, 'Backup', 'fullbackup_to_homedir', $notify !== '' ? ['email' => $notify] : []);
    } catch (RuntimeException $e) {
        ae_whm_audit($who, 'backup', $user, false, $e->getMessage());
        throw $e;
    }
    ae_whm_audit($who, 'backup', $user, true);
}

function ae_human_size(int $bytes): string
{
    foreach (['GB' => 1 << 30, 'MB' => 1 << 20, 'KB' => 1 << 10] as $unit => $n) {
        if ($bytes >= $n) {
            return round($bytes / $n, 1) . ' ' . $unit;
        }
    }
    return $bytes . ' B';
}
