<?php

namespace Tests\Feature;

use App\Services\Clips\Api\OpenAiTranscriptionService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Whisper rejects uploads over 25 MB, so oversized audio must be re-encoded to
 * speech-grade mono before upload. A WAV is uncompressed, so a couple of minutes
 * of tone is enough to cross the limit without a slow encode.
 */
class WhisperUploadLimitTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        parent::tearDown();
    }

    private function fit(string $path): string
    {
        $m = new ReflectionMethod(OpenAiTranscriptionService::class, 'fitUnderUploadLimit');

        return $m->invoke(new OpenAiTranscriptionService, $path);
    }

    public function test_small_file_is_sent_as_is(): void
    {
        $this->files[] = $small = tempnam(sys_get_temp_dir(), 'aud').'.wav';
        exec('ffmpeg -y -f lavfi -i "sine=frequency=440:duration=1" '.escapeshellarg($small).' 2>/dev/null');

        $this->assertSame($small, $this->fit($small));
    }

    public function test_oversized_file_is_reencoded_under_the_limit(): void
    {
        $this->files[] = $big = tempnam(sys_get_temp_dir(), 'aud').'.wav';
        // 160s of 48 kHz stereo 16-bit WAV ≈ 30 MB — over the 25 MB cap.
        exec('ffmpeg -y -f lavfi -i "sine=frequency=440:duration=160" -ar 48000 -ac 2 '.escapeshellarg($big).' 2>/dev/null');
        $this->assertGreaterThan(26214400, filesize($big));

        $this->files[] = $out = $this->fit($big);

        $this->assertNotSame($big, $out);
        $this->assertLessThanOrEqual(26214400, filesize($out));
    }
}
