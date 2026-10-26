<?php
// Follow-up emails after the AI-Ready Site Score, the Cedar Pond drip shape (steps a set number of days out)
// on flat files. Ships OFF: nothing sends until data/quiz/followups.on exists (the switch on /admin/leads.php).
// Run daily from cron: php ~/public_html/_private/tools/send-followups.php
declare(strict_types=1);

require_once __DIR__ . '/quiz.php';

function ae_followups_on(): bool
{
    return is_file(ae_quiz_dir() . '/followups.on');
}

function ae_set_followups(bool $on): void
{
    $f = ae_quiz_dir() . '/followups.on';
    $on ? touch($f) : (is_file($f) && unlink($f));
}

/** Steps: key => [lead status it applies to, days after (finished or created), subject, body builder]. */
function ae_followup_steps(): array
{
    return [
        'nudge' => ['started', 1, static fn($l, $r) => 'Your AI-Ready Site Score is almost done', static function ($l, $r) {
            return "You started an AI-Ready Site Score for " . ae_host($l) . " but didn't get to the end.\n\n"
                . "It's ten quick questions and takes about two minutes.\n\n"
                . "Start again here: " . AE_SITE . "/ai-ready/\n";
        }],
        'day2' => ['finished', 2, static fn($l, $r) => 'The #1 fix for ' . ae_host($l), static function ($l, $r) {
            $f = $r['fixes'][0] ?? null;
            if ($f === null) {
                return "Your site scored " . $r['score'] . "/100, which puts you ahead of almost everyone we check. "
                    . "The next step is tracking which AI and search visits turn into customers. That is what our Growth plan does.\n\n"
                    . "Your report: " . ae_report_url($l['token']) . "\n";
            }
            return "Of everything on your AI-Ready report, this is the one we'd fix first:\n\n"
                . $f['fix'] . "\n\n" . $f['detail'] . "\n\n"
                . "We handle this as part of the " . $f['plan'] . " plan, or we can show you how to do it yourself on a free 15-minute call.\n\n"
                . "Your report: " . ae_report_url($l['token']) . "\n";
        }],
        'day5' => ['finished', 5, static fn($l, $r) => 'Why ChatGPT might not recommend you (hint: Bing)', static function ($l, $r) {
            return "Most business owners watch Google. But ChatGPT search and Microsoft Copilot answer from Bing's index.\n\n"
                . "If Bing hasn't indexed your pages, or your robots.txt blocks newer crawlers, those assistants simply don't know you exist, "
                . "even if you rank on Google.\n\n"
                . "Three things to check this week:\n"
                . "1. Verify your site in Bing Webmaster Tools (bing.com/webmasters) and submit your sitemap.\n"
                . "2. Claim your business on Bing Places.\n"
                . "3. Make sure robots.txt doesn't block Bingbot, OAI-SearchBot or PerplexityBot.\n\n"
                . "Your score was " . $r['score'] . "/100. Report: " . ae_report_url($l['token']) . "\n";
        }],
        'day10' => ['finished', 10, static fn($l, $r) => 'Want us to fix ' . ae_host($l) . ' for you?', static function ($l, $r) {
            return "It's been a week and a half since your AI-Ready Site Score (" . $r['score'] . "/100).\n\n"
                . "If you'd like the fixes done for you, here's what we'd recommend: " . $r['offer']['plan'] . ".\n"
                . $r['offer']['pitch'] . "\n\n"
                . "Book a free walkthrough: " . AE_SITE . "/contact/?from=ai-ready\n"
                . "Or rerun your score any time: " . AE_SITE . "/ai-ready/\n";
        }],
    ];
}

function ae_host(array $lead): string
{
    return (string)parse_url($lead['site'] ?? '', PHP_URL_HOST);
}

/** Send every step that is due. Returns lines describing what happened. */
function ae_send_due_followups(bool $dryRun = false): array
{
    $log = [];
    if (!ae_followups_on() && !$dryRun) {
        return ['Follow-ups are off. Turn them on at /admin/leads.php.'];
    }
    $now = time();
    foreach (ae_leads() as $lead) {
        if (!empty($lead['unsubscribed']) || !empty($lead['followups_stopped'])) {
            continue;
        }
        foreach (ae_followup_steps() as $key => [$status, $days, $subjectFn, $bodyFn]) {
            if (($lead['status'] ?? '') !== $status || !empty($lead['emails'][$key])) {
                continue;
            }
            $since = strtotime($status === 'finished' ? ($lead['finished'] ?? '') : ($lead['created'] ?? ''));
            if (!$since || $now - $since < $days * 86400 || $now - $since > ($days + 3) * 86400) {
                continue; // not due yet, or too late to make sense (a missed cron run doesn't send stale mail)
            }
            $report = $status === 'finished' ? ae_build_report($lead) : [];
            $subject = $subjectFn($lead, $report);
            $body = $bodyFn($lead, $report) . "\n-- \nAgency Eagle, P.O. Box 99, Jefferson City, TN 37760 · (865) 300-5397\n"
                . "Stop these emails: " . ae_unsubscribe_url($lead['token']) . "\n";
            if ($dryRun) {
                $log[] = "would send $key to {$lead['email']}: $subject";
                continue;
            }
            $unsub = ae_unsubscribe_url($lead['token']);
            $sent = ae_mail($lead['email'], $subject, $body, AE_SALES_EMAIL, [
                'List-Unsubscribe: <' . $unsub . '>',
                'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            ]);
            ae_with_lead_lock($lead['token'], static function () use ($lead, $key, $sent) {
                $l = ae_lead_load($lead['token']);
                $l['emails'][$key] = $sent ? gmdate('c') : 'failed';
                ae_lead_save($l);
            });
            $log[] = ($sent ? 'sent' : 'FAILED') . " $key to {$lead['email']}";
            break; // at most one email per lead per run
        }
    }
    return $log ?: ['Nothing due.'];
}
