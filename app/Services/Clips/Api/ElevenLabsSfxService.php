<?php

namespace App\Services\Clips\Api;

use App\Services\Settings\StepKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Generate a short sound effect (SFX) from a text prompt via ElevenLabs. */
class ElevenLabsSfxService
{
    public function generate(string $prompt, string $outPath): string
    {
        $key = StepKey::key('clips_sfx', 'elevenlabs');
        if (! $key) {
            throw new RuntimeException('ELEVENLABS_API_KEY missing for sound generation.');
        }

        $bytes = Http::withHeaders([
            'xi-api-key' => $key,
            'accept' => 'audio/mpeg',
        ])
            ->timeout(120)
            ->post('https://api.elevenlabs.io/v1/sound-generation', [
                'text' => $prompt,
            ])
            ->throw()
            ->body();

        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, $bytes);

        return $outPath;
    }
}
