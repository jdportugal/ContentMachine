<?php

namespace App\Services\Clips\Contracts;

interface VoiceoverService
{
    /**
     * Synthesize speech from text to $outPath (mp3). Returns the path written.
     */
    public function synthesize(string $text, string $outPath): string;
}
