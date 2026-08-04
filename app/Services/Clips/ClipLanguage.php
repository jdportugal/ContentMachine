<?php

namespace App\Services\Clips;

/**
 * The language every text the studio WRITES for a clip must be in: the ACTIVE
 * PROJECT's language (chosen when the project is created), not whatever the
 * transcription happened to detect. The spoken words stay as spoken (karaoke,
 * punch words); everything generated — card/chart labels, research labels,
 * title/description/tags — follows this.
 */
final class ClipLanguage
{
    private const NAMES = ['pt' => 'European Portuguese', 'en' => 'English'];

    /** Prompt-friendly name of the active project's language. */
    public static function name(): string
    {
        $code = strtolower(substr((string) app()->getLocale(), 0, 2));

        return self::NAMES[$code] ?? ($code !== '' ? $code : 'English');
    }
}
