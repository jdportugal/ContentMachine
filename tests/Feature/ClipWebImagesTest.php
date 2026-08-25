<?php

namespace Tests\Feature;

use App\Jobs\Clips\FetchWebImagesJob;
use App\Services\Capture\SiteCapture;
use App\Services\Capture\SiteResolver;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\WebImageFinder;
use App\Services\Monitoring\ApifyClient;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The fetch that removes the hand-feeding: real images off the web pinned to
 * the planner's suggestions. What matters is that a good image lands and a bad
 * one never does — a wrong picture is worse than the generated one it replaced.
 */
class ClipWebImagesTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-webimg-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));

        Storage::fake(config('contentmachine.clips.disk', 'local'));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    /** A noisy PNG: big enough in both bytes and pixels to pass the guards. */
    private function png(int $w = 900, int $h = 900): string
    {
        $im = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 40000; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1),
                imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    /** A clip parked in review with one image suggestion. */
    private function clipComSugestao(string $prompt = 'the Gronk logo'): array
    {
        $key = ImageRequests::key($prompt);
        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'status' => ClipRecord::STATUS_COLLECTING,
            'title' => 'A short about Gronk',
            'plan' => ['scenes' => [['layers' => [['type' => 'image-reveal', 'params' => ['generate' => $prompt]]]]]],
            'meta' => [
                'image_requests' => [['key' => $key, 'prompt' => $prompt, 'label' => 'the Gronk logo', 'site' => null, 'still' => false]],
                'site_collecting' => [$key => 'search'],
            ],
        ]);

        return [$p, $key];
    }

    /** The finder, with its web search replaced — the download stays real. */
    private function comResultado(array $resultado): void
    {
        $finder = new class(app(SiteResolver::class), app(ApifyClient::class)) extends WebImageFinder
        {
            public array $resultado = [];

            public function find(array $requests, string $title = '', string $summary = ''): array
            {
                return $this->resultado;
            }
        };
        $finder->resultado = $resultado;
        $this->app->instance(WebImageFinder::class, $finder);
    }

    public function test_a_found_image_is_downloaded_and_pinned_to_its_suggestion(): void
    {
        [$p, $key] = $this->clipComSugestao();
        $bytes = $this->png();
        $this->assertGreaterThan(10000, strlen($bytes), 'the fixture must clear the minimum-size guard');

        Http::fake(['cdn.example.com/*' => Http::response($bytes, 200, ['Content-Type' => 'image/png'])]);
        $this->comResultado([$key => ['image' => 'https://cdn.example.com/logo.png', 'page' => 'https://example.com/about', 'site' => '']]);

        (new FetchWebImagesJob($p->id))->handle(
            app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
        );

        $fresh = app(ClipStore::class)->findOrFail($p->id);
        $this->assertCount(1, $fresh->images);
        $this->assertTrue($fresh->images[0]['fetched']);
        $this->assertSame('https://example.com/about', $fresh->images[0]['source']);
        $this->assertSame($fresh->images[0]['id'], $fresh->meta['image_uploads'][$key]);
        Storage::disk(config('contentmachine.clips.disk'))->assertExists($fresh->images[0]['path']);

        // The review screen must stop spinning whatever happened.
        $this->assertEmpty(array_filter($fresh->meta['site_collecting'] ?? []));
    }

    public function test_a_page_url_is_followed_to_its_own_preview_image(): void
    {
        [$p, $key] = $this->clipComSugestao();
        $html = '<html><head><meta property="og:image" content="/media/hero.png"></head><body>x</body></html>';

        Http::fake([
            'example.com/article' => Http::response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'example.com/media/hero.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
        $this->comResultado([$key => ['image' => 'https://example.com/article', 'page' => 'https://example.com/article', 'site' => '']]);

        (new FetchWebImagesJob($p->id))->handle(
            app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
        );

        $fresh = app(ClipStore::class)->findOrFail($p->id);
        $this->assertCount(1, $fresh->images, 'the article page should resolve to its og:image');
        $this->assertArrayHasKey($key, $fresh->meta['image_uploads']);
    }

    /**
     * Everything that is not a usable picture must leave the suggestion pending,
     * so it falls through to generation instead of rendering something broken.
     */
    public function test_rejects_thumbnails_non_images_and_private_addresses(): void
    {
        $casos = [
            'a thumbnail' => ['https://cdn.example.com/tiny.png', $this->png(120, 120), 'image/png'],
            'not an image' => ['https://cdn.example.com/page.txt', str_repeat('x', 50000), 'text/plain'],
            'a private address' => ['http://127.0.0.1/logo.png', $this->png(), 'image/png'],
            'a page with no preview' => ['https://cdn.example.com/bare', '<html><head></head><body>nothing</body></html>', 'text/html'],
        ];

        foreach ($casos as $nome => [$url, $bytes, $tipo]) {
            [$p, $key] = $this->clipComSugestao('logo for '.$nome);
            Http::fake(['*' => Http::response($bytes, 200, ['Content-Type' => $tipo])]);
            $this->comResultado([$key => ['image' => $url, 'page' => '', 'site' => '']]);

            (new FetchWebImagesJob($p->id))->handle(
                app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
            );

            $fresh = app(ClipStore::class)->findOrFail($p->id);
            $this->assertSame([], $fresh->images ?? [], "{$nome} should not become a clip image");
            $this->assertArrayNotHasKey($key, $fresh->meta['image_uploads'] ?? [], "{$nome} should leave the suggestion pending");
            $this->assertEmpty(array_filter($fresh->meta['site_collecting'] ?? []), "{$nome} should stop the spinner");
        }
    }

    public function test_a_clip_that_already_moved_on_is_left_alone(): void
    {
        [$p, $key] = $this->clipComSugestao();
        // The user pressed continue while the search was running: the plan is
        // settled and a late image would just be orphaned.
        $p->update(['status' => ClipRecord::STATUS_RENDERING]);

        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $this->comResultado([$key => ['image' => 'https://cdn.example.com/logo.png', 'page' => '', 'site' => '']]);

        (new FetchWebImagesJob($p->id))->handle(
            app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
        );

        $fresh = app(ClipStore::class)->findOrFail($p->id);
        $this->assertSame([], $fresh->images ?? []);
        $this->assertArrayNotHasKey($key, $fresh->meta['image_uploads'] ?? []);
    }

    /**
     * The image ceiling can leave a suggestion unsearched. Its "searching" mark
     * must still be cleared, or the review screen polls forever and the continue
     * button never comes back.
     */
    public function test_a_suggestion_past_the_image_ceiling_still_stops_spinning(): void
    {
        [$p, $key] = $this->clipComSugestao();
        config(['contentmachine.clips.image_max' => 1]);
        $p->update(['images' => [['id' => 'img_ja', 'path' => 'clips/uploads/ja.png', 'description' => 'already here']]]);

        $this->comResultado([]);

        (new FetchWebImagesJob($p->id))->handle(
            app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
        );

        $fresh = app(ClipStore::class)->findOrFail($p->id);
        $this->assertEmpty(array_filter($fresh->meta['site_collecting'] ?? []));
        $this->assertArrayNotHasKey($key, $fresh->meta['image_uploads'] ?? []);
    }

    public function test_a_search_that_finds_nothing_changes_nothing(): void
    {
        [$p, $key] = $this->clipComSugestao();
        $this->comResultado([]);

        (new FetchWebImagesJob($p->id))->handle(
            app(ClipStore::class), app(WebImageFinder::class), app(SiteResolver::class), app(SiteCapture::class),
        );

        $fresh = app(ClipStore::class)->findOrFail($p->id);
        $this->assertSame([], $fresh->images ?? []);
        $this->assertSame(ClipRecord::STATUS_COLLECTING, $fresh->status);
        $this->assertEmpty(array_filter($fresh->meta['site_collecting'] ?? []));
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            is_dir("$dir/$f") ? $this->rrmdir("$dir/$f") : @unlink("$dir/$f");
        }
        @rmdir($dir);
    }
}
