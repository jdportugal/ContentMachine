<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\VoiceoverService;

class FakeVoiceoverService implements VoiceoverService
{
    public function synthesize(string $text, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, "FAKE-AUDIO:$text");

        return $outPath;
    }
}
