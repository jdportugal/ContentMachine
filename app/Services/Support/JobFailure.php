<?php

namespace App\Services\Support;

use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a job's exception into something the user can act on.
 *
 * A failed job used to surface as "check that the worker is running", which is
 * the wrong advice most of the time: the usual cause is a provider refusing the
 * call because the account is out of credit or over its rate limit. Naming that
 * is the difference between "try again in an hour" and hunting a phantom bug.
 */
class JobFailure
{
    /** Is this a provider saying "not now" — rate limit, quota, credits? */
    public static function ehLimite(Throwable $e): bool
    {
        $m = $e->getMessage();

        return (bool) preg_match(
            '/\b(429|402)\b|rate.?limit|quota|insufficient|out of credit|credits?\b|usage limit|too many requests|overloaded/i',
            $m,
        );
    }

    /** A short, human reason for the failure. */
    public static function motivo(Throwable $e): string
    {
        $m = $e->getMessage();

        if (self::ehLimite($e)) {
            return 'The AI provider refused the request — usage limit reached or credits exhausted. '
                .'It will work again once the limit resets; you can also set a different provider key in Settings.';
        }

        if (stripos($m, 'database is locked') !== false) {
            return 'The database was busy and the job could not finish. Run it again.';
        }

        if (stripos($m, 'has timed out') !== false) {
            return 'The job ran past its time limit and was stopped. Try a smaller run.';
        }

        if (stripos($m, 'No LLM is configured') !== false || stripos($m, 'not configured') !== false) {
            return 'No AI provider is configured — add a key in Settings.';
        }

        return 'The job failed: '.Str::limit(trim($m), 160);
    }
}
