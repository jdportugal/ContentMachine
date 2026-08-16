<?php

namespace Tests\Feature;

use App\Services\Clips\Api\LocalWhisperTranscriptionService;
use RuntimeException;
use Tests\TestCase;

/**
 * The local (keyless) transcription path: run the Whisper script and reshape its
 * segment/word output into the clip pipeline's transcript envelope. The script is
 * stubbed by a shell script so the test needs no Python and no model download —
 * what is under test is the process call and the reshaping, not Whisper itself.
 */
class LocalWhisperTranscriptionTest extends TestCase
{
    private string $stub = '';

    protected function tearDown(): void
    {
        if ($this->stub !== '' && is_file($this->stub)) {
            unlink($this->stub);
        }
        parent::tearDown();
    }

    private function stubScript(string $saida, int $codigo = 0): void
    {
        $this->stub = tempnam(sys_get_temp_dir(), 'whisper-stub').'.sh';
        file_put_contents($this->stub, "#!/bin/sh\ncat <<'JSON'\n{$saida}\nJSON\nexit {$codigo}\n");

        config([
            'services.shorts.python' => '/bin/sh',
            'services.shorts.transcribe_script' => $this->stub,
        ]);
    }

    public function test_segments_and_words_become_the_clip_transcript_envelope(): void
    {
        $this->stubScript(json_encode(['subtitle_data' => [
            ['start' => 0.0, 'end' => 1.5, 'text' => ' Olá mundo ', 'words' => [
                ['word' => 'Olá', 'start' => 0.0, 'end' => 0.7],
                ['word' => 'mundo', 'start' => 0.7, 'end' => 1.5],
            ]],
            ['start' => 1.5, 'end' => 3.0, 'text' => 'segunda frase', 'words' => [
                ['word' => 'segunda', 'start' => 1.5, 'end' => 2.2],
                ['word' => 'frase', 'start' => 2.2, 'end' => 3.0],
            ]],
        ]]));

        $t = (new LocalWhisperTranscriptionService)->transcribe('/tmp/audio.m4a');

        $this->assertSame(3.0, $t['duration']);
        $this->assertSame('Olá mundo segunda frase', $t['text']);
        $this->assertCount(4, $t['words']);
        $this->assertCount(2, $t['segments']);
        $this->assertSame(['word' => 'mundo', 'start' => 0.7, 'end' => 1.5], $t['words'][1]);
        $this->assertSame(['start' => 1.5, 'end' => 3.0, 'text' => 'segunda frase'], $t['segments'][1]);
    }

    public function test_a_failing_script_raises_instead_of_returning_an_empty_transcript(): void
    {
        $this->stubScript('faster-whisper is not installed', 2);

        $this->expectException(RuntimeException::class);
        (new LocalWhisperTranscriptionService)->transcribe('/tmp/audio.m4a');
    }
}
