<?php

namespace Tests\Feature\Clips;

use App\Services\Aggregation\LlmClient;
use App\Services\Capture\SiteCapture;
use App\Services\Capture\SiteFootage;
use App\Services\Capture\SiteResolver;
use App\Services\Clips\CliRemotionRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Filming a real website and showing it inside an effect.
 *
 * The URL comes from a model, so it is untrusted input that ends up driving a
 * real browser — the validation around it carries most of the risk here.
 */
class SiteCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(?string $resposta): SiteResolver
    {
        return new SiteResolver(new class($resposta) extends LlmClient
        {
            public function __construct(private ?string $resposta) {}

            public function paraPasso(string $passo): static
            {
                return $this;
            }

            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                return $this->resposta;
            }
        });
    }

    // ── what the model may hand us ───────────────────────────────────────

    public function test_a_plain_url_is_accepted(): void
    {
        $this->assertSame(
            'https://claude.com/product/claude-code',
            $this->resolver(null)->normalizar('https://claude.com/product/claude-code')
        );
    }

    /** Models wrap URLs in prose or markdown however firmly you ask them not to. */
    public function test_a_url_buried_in_prose_is_recovered(): void
    {
        $r = $this->resolver(null);

        $this->assertSame('https://example.com/docs', $r->normalizar('Sure! The page is https://example.com/docs.'));
        $this->assertSame('https://example.com', $r->normalizar('`https://example.com`'));
    }

    public function test_none_and_nonsense_are_rejected(): void
    {
        $r = $this->resolver(null);

        $this->assertNull($r->normalizar('NONE'));
        $this->assertNull($r->normalizar(''));
        $this->assertNull($r->normalizar('I am not sure which site you mean'));
    }

    /**
     * The URL drives a real browser inside the container, so a private address
     * would be an SSRF — a generated string must never reach the LAN.
     */
    public function test_private_and_local_addresses_are_refused(): void
    {
        $r = $this->resolver(null);

        $this->assertNull($r->normalizar('http://localhost:8080/admin'));
        $this->assertNull($r->normalizar('http://127.0.0.1/'));
        $this->assertNull($r->normalizar('http://192.168.1.1/'));
        $this->assertNull($r->normalizar('http://10.0.0.5/secret'));
        $this->assertNull($r->normalizar('http://[::1]/'));
        $this->assertNull($r->normalizar('http://router.local/'));
    }

    public function test_non_http_schemes_are_refused(): void
    {
        $r = $this->resolver(null);

        $this->assertNull($r->normalizar('file:///etc/passwd'));
        $this->assertNull($r->normalizar('ftp://example.com/x'));
    }

    // ── resolving ────────────────────────────────────────────────────────

    public function test_a_url_that_does_not_respond_is_not_used(): void
    {
        Http::fake(['*' => Http::response('gone', 404)]);

        $this->assertNull($this->resolver('https://example.com/dead')->resolve('show the example site'));
    }

    public function test_a_reachable_url_is_returned(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200)]);

        $this->assertSame(
            'https://example.com/live',
            $this->resolver('https://example.com/live')->resolve('show the example site')
        );
    }

    public function test_no_site_in_the_prompt_means_no_capture(): void
    {
        $this->assertNull($this->resolver('NONE')->resolve('a gold light sweep across a headline'));
    }

    // ── the effect still gets built when capture fails ───────────────────

    /**
     * Footage is a bonus. A dead URL, a browser crash or a missing script must
     * not cost you the effect itself.
     */
    public function test_a_failed_capture_reports_the_url_but_yields_no_footage(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $capture = new class extends SiteCapture
        {
            public function __construct() {}

            public function capture(string $url, int $width = 1920, int $height = 1080, float $seconds = 6.0, int $fps = 30): string
            {
                throw new \RuntimeException('chrome exited');
            }
        };

        $r = (new SiteFootage($this->resolver('https://example.com/x'), $capture))
            ->forPrompt('show the example site', 1920, 1080, 5.0);

        $this->assertNull($r['path']);
        $this->assertSame('https://example.com/x', $r['url'], 'the URL should be reported so a wrong guess is visible');
        $this->assertStringContainsString('chrome exited', (string) $r['error']);
    }

    public function test_no_url_means_no_capture_is_attempted(): void
    {
        $capture = new class extends SiteCapture
        {
            public function __construct() {}

            public function capture(string $url, int $width = 1920, int $height = 1080, float $seconds = 6.0, int $fps = 30): string
            {
                throw new \RuntimeException('should never be called');
            }
        };

        $r = (new SiteFootage($this->resolver('NONE'), $capture))->forPrompt('a gold sweep', 1920, 1080, 5.0);

        $this->assertNull($r['path']);
        $this->assertNull($r['url']);
        $this->assertNull($r['error']);
    }

    // ── caching ──────────────────────────────────────────────────────────

    public function test_the_same_page_at_the_same_size_reuses_one_capture(): void
    {
        $c = new SiteCapture;

        $a = $c->path('https://example.com', 1920, 1080, 6.0);
        $b = $c->path('https://example.com', 1920, 1080, 6.0);
        $diferente = $c->path('https://example.com', 1080, 1920, 6.0);

        $this->assertSame($a, $b, 'ten effects about one product should film it once');
        $this->assertNotSame($a, $diferente, 'a different frame needs its own capture');
    }

    // ── reaching Remotion ────────────────────────────────────────────────

    /**
     * The capture arrives as params.src on the SampleEffect path. Only `scenes`
     * used to be staged, so a VFX capture would never have reached Remotion's
     * public/ and the component would have rendered an empty frame.
     */
    public function test_a_capture_in_params_is_staged_into_remotions_public_dir(): void
    {
        $remotion = sys_get_temp_dir().'/cm-stage-'.uniqid();
        mkdir($remotion.'/public', 0775, true);
        config(['contentmachine.clips.remotion_path' => $remotion]);

        $video = $remotion.'/capture.mp4';
        file_put_contents($video, 'VIDEO');

        try {
            [$props, $staged] = (new CliRemotionRenderer)->stageProps(['params' => ['src' => $video]]);

            $this->assertCount(1, $staged, 'the capture should have been copied into public/');
            $this->assertFileExists($staged[0]);
            $this->assertStringStartsWith($remotion.'/public/', $staged[0]);

            // Remotion resolves staticFile() by basename, so the absolute path
            // must have been rewritten — otherwise the component renders blank.
            $this->assertSame(basename($staged[0]), $props['params']['src']);
            $this->assertStringNotContainsString('/', $props['params']['src']);
        } finally {
            array_map('unlink', glob($remotion.'/public/*') ?: []);
            @unlink($video);
            @rmdir($remotion.'/public');
            @rmdir($remotion);
        }
    }

    /** A remote URL is left alone — Remotion loads http(s) sources directly. */
    public function test_a_remote_url_in_params_is_not_staged(): void
    {
        [$props, $staged] = (new CliRemotionRenderer)->stageProps([
            'params' => ['src' => 'https://example.com/video.mp4'],
        ]);

        $this->assertSame([], $staged);
        $this->assertSame('https://example.com/video.mp4', $props['params']['src']);
    }
}
