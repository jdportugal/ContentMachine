<?php

namespace App\Services\Projects;

/**
 * The language everything the studio WRITES must be in: the ACTIVE PROJECT's
 * language (chosen when the project is created), not whatever a transcription
 * detected nor a hardcoded default. Clip scene labels, news topics, item
 * summaries and the news report all follow this; only the spoken words stay as
 * spoken (karaoke, punch words).
 *
 * Reads the locale ProjectActivator sets — in web requests via the
 * SetActiveProject middleware, and in queued jobs that `use RunsInProject`.
 */
final class ProjectLanguage
{
    private const NAMES = ['pt' => 'European Portuguese', 'en' => 'English'];

    /** Prompt-friendly name of the active project's language. */
    public static function name(): string
    {
        $code = strtolower(substr((string) app()->getLocale(), 0, 2));

        return self::NAMES[$code] ?? ($code !== '' ? $code : 'English');
    }
}
