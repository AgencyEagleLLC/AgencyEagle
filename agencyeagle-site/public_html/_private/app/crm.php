<?php
// CRM: contacts moving through a sales pipeline, with notes and follow-up tasks.
// Everything lives in one file, data/crm/crm.json, read and written under a lock.
// Quiz leads (data/quiz/leads) and contact-form messages flow in on their own.
declare(strict_types=1);

date_default_timezone_set('America/New_York'); // task due dates are Knoxville days

const AE_CRM_DIR = __DIR__ . '/../data/crm';
const AE_CRM_FILE = AE_CRM_DIR . '/crm.json';

/** Pipeline stages in board order. Won = paying client. */
function ae_crm_stages(): array
{
    return [
        'lead' => 'New lead',
        'contacted' => 'Contacted',
        'call' => 'Call booked',
        'proposal' => 'Proposal sent',
        'won' => 'Client',
        'lost' => 'Lost',
    ];
}

/** Plans we sell, with their monthly price. */
function ae_crm_plans(): array
{
    return [
        '' => ['None yet', 0],
        'starter' => ['$99/mo plan', 99],
        'growth' => ['$200/mo Growth plan', 200],
    ];
}

const AE_CRM_BUILD_PRICE = 5000;

function ae_crm_sources(): array
{
    return ['manual' => 'Added by hand', 'quiz' => 'AI-Ready quiz', 'contact' => 'Contact form', 'referral' => 'Referral', 'call' => 'Phone call'];
}

function ae_crm_empty(): array
{
    return ['contacts' => [], 'synced' => []];
}

/** Read the whole CRM without locking (for display). */
function ae_crm_load(): array
{
    if (!is_file(AE_CRM_FILE)) {
        return ae_crm_empty();
    }
    $data = json_decode((string)file_get_contents(AE_CRM_FILE), true);
    return is_array($data) && isset($data['contacts']) ? $data : ae_crm_empty();
}

/** Read, change and save the CRM while holding its lock. $fn gets the data by reference. */
function ae_crm_update(callable $fn): mixed
{
    if (!is_dir(AE_CRM_DIR)) {
        mkdir(AE_CRM_DIR, 0700, true);
    }
    $fh = fopen(AE_CRM_DIR . '/crm.lock', 'c');
    flock($fh, LOCK_EX);
    try {
        $data = ae_crm_load();
        $result = $fn($data);
        $tmp = AE_CRM_FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, AE_CRM_FILE);
        return $result;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function ae_crm_id(): string
{
    return bin2hex(random_bytes(8));
}

function ae_crm_valid_id(string $id): bool
{
    return (bool)preg_match('/^[a-f0-9]{16}$/', $id);
}

function ae_crm_line(mixed $v, int $max): string
{
    return trim(mb_substr(preg_replace('/[\r\n\t\x00]+/', ' ', (string)$v), 0, $max));
}

function ae_crm_text(mixed $v, int $max): string
{
    return trim(mb_substr(str_replace("\x00", '', (string)$v), 0, $max));
}

function ae_crm_new_contact(array $fields, string $source, string $by): array
{
    $now = gmdate('c');
    return array_merge([
        'id' => ae_crm_id(),
        'name' => '', 'business' => '', 'email' => '', 'phone' => '', 'website' => '',
        'stage' => 'lead', 'plan' => '', 'build' => false, 'source' => $source,
        'next_step' => '', 'lead_tokens' => [], 'score' => null,
        'notes' => [], 'tasks' => [],
        'created' => $now, 'updated' => $now, 'stage_changed' => $now, 'created_by' => $by,
    ], $fields);
}

/** Index of the contact with this id (or email), or null. */
function ae_crm_find(array $data, string $id = '', string $email = ''): ?int
{
    $email = strtolower(trim($email));
    foreach ($data['contacts'] as $i => $c) {
        if (($id !== '' && $c['id'] === $id) || ($email !== '' && strtolower($c['email']) === $email)) {
            return $i;
        }
    }
    return null;
}

function ae_crm_add_note(array &$contact, string $text, string $by): void
{
    $contact['notes'][] = ['id' => ae_crm_id(), 'at' => gmdate('c'), 'by' => $by, 'text' => $text];
    $contact['updated'] = gmdate('c');
}

/** Monthly and one-time value of a contact's deal. */
function ae_crm_value(array $c): array
{
    return [ae_crm_plans()[$c['plan'] ?? ''][1] ?? 0, !empty($c['build']) ? AE_CRM_BUILD_PRICE : 0];
}

/** A contact-form message: new contact, or a note on the one with that email. */
function ae_crm_inbound(string $source, string $name, string $email, string $phone, string $message, array $extra = []): void
{
    ae_crm_update(static function (array &$data) use ($source, $name, $email, $phone, $message, $extra) {
        $i = ae_crm_find($data, '', $email);
        if ($i === null) {
            $data['contacts'][] = ae_crm_new_contact(array_merge(['name' => $name, 'email' => $email, 'phone' => $phone], $extra), $source, 'website');
            $i = array_key_last($data['contacts']);
        } elseif ($data['contacts'][$i]['phone'] === '' && $phone !== '') {
            $data['contacts'][$i]['phone'] = $phone;
        }
        ae_crm_add_note($data['contacts'][$i], ae_crm_sources()[$source] . ' message: ' . $message, 'website');
    });
}

/**
 * Pull AI-Ready quiz leads into the CRM. New emails become contacts in "New lead";
 * known emails get the quiz linked and the newest score. Returns how many contacts were added.
 */
function ae_crm_sync_quiz_leads(array $leads): int
{
    $synced = ae_crm_load()['synced'] ?? [];
    $todo = array_filter($leads, static fn($l) => !empty($l['token']) && !empty($l['email'])
        && ($synced[$l['token']] ?? '') !== ($l['status'] ?? 'started'));
    if ($todo === []) {
        return 0;
    }
    return ae_crm_update(static function (array &$data) use ($todo) {
        $added = 0;
        foreach (array_reverse($todo) as $l) { // oldest first
            $i = ae_crm_find($data, '', (string)$l['email']);
            if ($i === null) {
                $data['contacts'][] = ae_crm_new_contact([
                    'name' => (string)($l['name'] ?? ''), 'business' => (string)($l['business'] ?? ''),
                    'email' => (string)$l['email'], 'phone' => (string)($l['phone'] ?? ''), 'website' => (string)($l['site'] ?? ''),
                    'created' => (string)($l['created'] ?? gmdate('c')),
                ], 'quiz', 'website');
                $i = array_key_last($data['contacts']);
                $added++;
            }
            $c = &$data['contacts'][$i];
            if (!in_array($l['token'], $c['lead_tokens'], true)) {
                $c['lead_tokens'][] = $l['token'];
            }
            if (($l['status'] ?? '') === 'finished') {
                $c['score'] = ['score' => $l['score'] ?? null, 'grade' => $l['grade'] ?? '', 'token' => $l['token']];
            }
            foreach (['phone' => 'phone', 'business' => 'business', 'website' => 'site'] as $k => $lk) {
                if ($c[$k] === '' && !empty($l[$lk])) {
                    $c[$k] = (string)$l[$lk];
                }
            }
            unset($c);
            $data['synced'][$l['token']] = $l['status'] ?? 'started';
        }
        return $added;
    });
}

/** Open tasks across all contacts, soonest due first: [[contact, task], ...]. */
function ae_crm_open_tasks(array $data): array
{
    $out = [];
    foreach ($data['contacts'] as $c) {
        foreach ($c['tasks'] as $t) {
            if (empty($t['done'])) {
                $out[] = [$c, $t];
            }
        }
    }
    usort($out, static fn($a, $b) => strcmp($a[1]['due'] ?: '9999', $b[1]['due'] ?: '9999'));
    return $out;
}

/** Headline numbers for the CRM home page. */
function ae_crm_totals(array $data): array
{
    $t = ['mrr' => 0, 'pipeline_mrr' => 0, 'builds_won' => 0, 'pipeline_builds' => 0, 'clients' => 0, 'open' => 0, 'due' => 0];
    $today = date('Y-m-d');
    foreach ($data['contacts'] as $c) {
        [$monthly, $build] = ae_crm_value($c);
        if ($c['stage'] === 'won') {
            $t['clients']++;
            $t['mrr'] += $monthly;
            $t['builds_won'] += $build;
        } elseif ($c['stage'] !== 'lost') {
            $t['open']++;
            $t['pipeline_mrr'] += $monthly;
            $t['pipeline_builds'] += $build;
        }
        foreach ($c['tasks'] as $task) {
            if (empty($task['done']) && ($task['due'] ?? '') !== '' && $task['due'] <= $today) {
                $t['due']++;
            }
        }
    }
    return $t;
}

function ae_crm_money(int $n): string
{
    return '$' . number_format($n);
}
