<?php

namespace Tests\Feature\Shorts;

use App\Services\Aggregation\LlmClient;
use App\Services\Shorts\LocalVideoEngine;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Two complaints about the shorts generator: the AI picked segments too short to
 * hold a viewer, and a landscape source stayed landscape instead of filling a
 * vertical frame.
 */
class ShortsFramingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_short_segment_is_grown_to_the_minimum(): void
    {
        $out = ShortsPipeline::aplicarDuracaoMinima(
            [['start_time' => 100.0, 'end_time' => 112.0]], // 12s
            600.0,
        );

        $this->assertSame(30.0, $out[0]['end_time'] - $out[0]['start_time']);
        // Grown around the midpoint (106), so the pick stays centred on itself.
        $this->assertSame(91.0, $out[0]['start_time']);
        $this->assertSame(121.0, $out[0]['end_time']);
    }

    public function test_a_long_enough_segment_is_untouched(): void
    {
        $in = [['start_time' => 10.0, 'end_time' => 55.0, 'title' => 'Keep me']];
        $this->assertSame($in, ShortsPipeline::aplicarDuracaoMinima($in, 600.0));
    }

    public function test_a_segment_at_the_start_slides_inwards_instead_of_truncating(): void
    {
        $out = ShortsPipeline::aplicarDuracaoMinima([['start_time' => 2.0, 'end_time' => 9.0]], 600.0);

        $this->assertSame(0.0, $out[0]['start_time']);
        $this->assertSame(30.0, $out[0]['end_time']);
    }

    public function test_a_segment_at_the_end_slides_inwards(): void
    {
        $out = ShortsPipeline::aplicarDuracaoMinima([['start_time' => 95.0, 'end_time' => 100.0]], 100.0);

        $this->assertSame(70.0, $out[0]['start_time']);
        $this->assertSame(100.0, $out[0]['end_time']);
    }

    public function test_a_source_shorter_than_the_minimum_is_left_as_it_is(): void
    {
        // Nothing to grow into: the whole video is 20s, so the clip becomes the video.
        $out = ShortsPipeline::aplicarDuracaoMinima([['start_time' => 5.0, 'end_time' => 12.0]], 20.0);

        $this->assertSame(0.0, $out[0]['start_time']);
        $this->assertSame(20.0, $out[0]['end_time']);
    }

    public function test_a_zero_length_segment_is_left_alone(): void
    {
        $in = [['start_time' => 10.0, 'end_time' => 10.0]];
        $this->assertSame($in, ShortsPipeline::aplicarDuracaoMinima($in, 600.0));
    }

    public function test_suggesting_widens_the_models_short_picks(): void
    {
        // End to end through sugerirDoVideo: the model returns a 2s segment and
        // the clip that gets created still respects the floor.
        $this->app->instance(LlmClient::class, new class extends LlmClient
        {
            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                return json_encode([
                    'segments' => [['title' => 'Too short', 'description' => 'd', 'start_time' => 10, 'end_time' => 12, 'tags' => []]],
                    'publications' => [],
                ]);
            }
        });

        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Talk', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'hello', 'start' => 0, 'end' => 300]]),
        ], 'Source.');

        $segmentos = app(ShortsPipeline::class)->sugerirDoVideo($fonte->path)['segments'];

        $this->assertSame(30.0, $segmentos[0]['end_time'] - $segmentos[0]['start_time']);
    }

    public function test_the_prompt_demands_the_minimum(): void
    {
        $capturado = '';
        $this->app->instance(LlmClient::class, new class($capturado) extends LlmClient
        {
            public function __construct(public string &$capturado) {}

            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                $this->capturado = $prompt;

                return json_encode(['segments' => [], 'publications' => []]);
            }
        });

        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Talk', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'hello', 'start' => 0, 'end' => 300]]),
        ], 'Source.');

        app(ShortsPipeline::class)->sugerirDoVideo($fonte->path);

        $this->assertStringContainsString('AT LEAST 30 seconds', $capturado);
    }

    /** The post ideas used to be hardcoded to European Portuguese, whatever the project. */
    public function test_the_prompt_asks_for_post_ideas_in_the_project_language(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Talk', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'hello', 'start' => 0, 'end' => 300]]),
        ], 'Source.');

        $this->app->setLocale('pt');
        $prompt = $this->promptDe($fonte->path);
        // The rule must sit ABOVE both outputs and must beat the transcript: buried
        // at the end of item 2, the model just mirrored the video's language.
        $this->assertStringContainsString('Write EVERY word you produce in European Portuguese', $prompt);
        $this->assertStringContainsString('OVERRIDES the transcript', $prompt);
        $this->assertLessThan(
            strpos($prompt, 'TRANSCRIPT:'),
            strpos($prompt, 'LANGUAGE'),
            'the language rule must come before the transcript, not after it',
        );

        $this->app->setLocale('en');
        $this->assertStringContainsString('Write EVERY word you produce in English', $this->promptDe($fonte->path));
    }

    /** The clip description prompt was hardcoded to European Portuguese too. */
    public function test_the_description_prompt_follows_the_project_language(): void
    {
        $clip = app(VaultContract::class)->create(ShortsPipeline::CLIPES, [
            'titulo' => 'A cut', 'tipo' => 'clip', 'inicio' => 0, 'fim' => 40,
            'subtitle_data' => json_encode([['text' => 'hello there', 'start' => 0, 'end' => 2]]),
        ], 'A clip.');

        $espia = $this->espiaLlm();
        $this->app->setLocale('pt');
        app(ShortsPipeline::class)->gerarDescricao($clip->path);
        $this->assertStringContainsString('in European Portuguese', $espia->capturado);

        $espia = $this->espiaLlm();
        $this->app->setLocale('en');
        app(ShortsPipeline::class)->gerarDescricao($clip->path);
        $this->assertStringContainsString('in English', $espia->capturado);
        $this->assertStringContainsString('do NOT mirror the spoken language', $espia->capturado);
    }

    /** Runs sugerirDoVideo against a recording LLM and returns the prompt it got. */
    private function promptDe(string $fontePath): string
    {
        $espia = $this->espiaLlm();
        app(ShortsPipeline::class)->sugerirDoVideo($fontePath);

        return $espia->capturado;
    }

    /** Binds an LLM that records the prompt it is handed. */
    private function espiaLlm(): LlmClient
    {
        $espia = new class extends LlmClient
        {
            public string $capturado = '';

            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                $this->capturado = $prompt;

                return json_encode(['segments' => [], 'publications' => []]);
            }
        };
        $this->app->instance(LlmClient::class, $espia);

        return $espia;
    }

    /**
     * Every framing mode, through real ffmpeg on a real 1280x720 landscape source.
     *
     * @return array<string,array{0:string,1:array{0:int,1:int}}>
     */
    public static function enquadramentos(): array
    {
        return [
            'fill crops to 9:16' => ['fill', [1080, 1920]],
            'blur fits inside 9:16' => ['blur', [1080, 1920]],
            'original is untouched' => ['original', [1280, 720]],
            'an unknown mode is left alone' => ['nonsense', [1280, 720]],
        ];
    }

    #[DataProvider('enquadramentos')]
    public function test_the_framing_decides_the_cut_dimensions(string $framing, array $esperado): void
    {
        $engine = app(LocalVideoEngine::class);
        $dir = sys_get_temp_dir().'/cm-framing-'.bin2hex(random_bytes(4));
        @mkdir($dir, 0775, true);

        $source = "$dir/source.mp4";
        (new Process([
            config('services.shorts.ffmpeg', 'ffmpeg'), '-y',
            '-f', 'lavfi', '-i', 'testsrc=size=1280x720:rate=25:duration=2',
            '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo', '-shortest',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', $source,
        ]))->setTimeout(120)->mustRun();

        $engine->split($source, 0.0, 1.0, "$dir/out.mp4", $framing);
        $meta = $engine->probe("$dir/out.mp4");

        $this->assertSame($esperado, [$meta['width'], $meta['height']]);

        File::deleteDirectory($dir);
    }

    /**
     * "blur" must fill the spare rows with a blurred copy of the video, not with
     * black bars — a plain pad would look identical in dimensions and is the easy
     * thing to regress to.
     */
    public function test_blur_fills_the_spare_rows_with_the_video_not_black(): void
    {
        $engine = app(LocalVideoEngine::class);
        $dir = sys_get_temp_dir().'/cm-blur-'.bin2hex(random_bytes(4));
        @mkdir($dir, 0775, true);

        // A solid orange source: anything sampled from it must be orange-ish.
        $source = "$dir/source.mp4";
        (new Process([
            config('services.shorts.ffmpeg', 'ffmpeg'), '-y',
            '-f', 'lavfi', '-i', 'color=c=0xE08A2A:size=1280x720:rate=25:duration=2',
            '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo', '-shortest',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', $source,
        ]))->setTimeout(120)->mustRun();

        $engine->split($source, 0.0, 1.0, "$dir/blur.mp4", 'blur');

        // 1280x720 fitted to 1080 wide is 1080x608, centred — so y=20 is backdrop.
        [$r, $g, $b] = $this->pixel("$dir/blur.mp4", 540, 20);

        $this->assertGreaterThan(60, $r, 'the top band is black — the backdrop is a pad, not the blurred video');
        $this->assertGreaterThan($b, $r, 'the backdrop should carry the source colour');

        File::deleteDirectory($dir);
    }

    /**
     * RGB of one pixel of the first frame.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function pixel(string $video, int $x, int $y): array
    {
        // format BEFORE crop: under yuv420p a 1px crop rounds to 0 and ffmpeg
        // refuses the filter. 2x2 in RGB is safe; the first pixel is the sample.
        $p = new Process([
            config('services.shorts.ffmpeg', 'ffmpeg'),
            '-i', $video, '-vf', "format=rgb24,crop=2:2:$x:$y",
            '-frames:v', '1', '-f', 'rawvideo', '-',
        ]);
        $p->setTimeout(60)->mustRun();
        $bytes = $p->getOutput();

        $this->assertSame(12, strlen($bytes), 'expected a 2x2 RGB block back');

        return [ord($bytes[0]), ord($bytes[1]), ord($bytes[2])];
    }
}
