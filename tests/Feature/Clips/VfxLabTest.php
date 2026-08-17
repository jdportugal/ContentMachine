<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\GenerateVfxJob;
use App\Livewire\ClipsAnimadosVfx;
use App\Services\Clips\CliRemotionRenderer;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\VfxStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class VfxLabTest extends TestCase
{
    use RefreshDatabase;

    private string $remotionTemp;

    /** Props/paths the fake renderer was last called with. */
    private array $rendered = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real remotion/src/effects during tests.
        $this->remotionTemp = sys_get_temp_dir().'/cm-vfx-'.uniqid();
        mkdir($this->remotionTemp.'/src/effects', 0775, true);
        config(['contentmachine.clips.remotion_path' => $this->remotionTemp]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->remotionTemp);
        parent::tearDown();
    }

    private function store(): VfxStore
    {
        return app(VfxStore::class);
    }

    // ── the form ─────────────────────────────────────────────────────────

    public function test_generating_stores_the_chosen_size_and_dispatches_the_job(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimadosVfx::class)
            ->set('prompt', 'the words CHAPTER ONE slam in with a gold light sweep')
            ->set('aspect', '16:9')
            ->set('duration', 6)
            ->set('transparent', true)
            ->call('gerarVfx')
            ->assertHasNoErrors()
            ->assertSet('prompt', '');

        $vfx = $this->store()->all()->sole();
        $this->assertSame(EffectRecord::STATUS_PENDING, $vfx->status);
        $this->assertSame(1920, $vfx->get('width'));
        $this->assertSame(1080, $vfx->get('height'));
        $this->assertSame(6.0, (float) $vfx->get('duration'));
        $this->assertTrue($vfx->get('transparent'));
        Queue::assertPushed(GenerateVfxJob::class);
    }

    public function test_a_too_long_render_is_rejected(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimadosVfx::class)
            ->set('prompt', 'a slow drifting starfield wipe')
            ->set('duration', 60)
            ->call('gerarVfx')
            ->assertHasErrors('duration');

        $this->assertCount(0, $this->store()->all());
        Queue::assertNothingPushed();
    }

    // ── the job ──────────────────────────────────────────────────────────

    public function test_job_renders_the_requested_canvas_and_publishes_a_downloadable_file(): void
    {
        $this->fakeClaudeReturning($this->payload());
        $this->spyRenderer();

        $vfx = $this->store()->create([
            'prompt' => 'a gold light sweep across the headline',
            'aspect' => '16:9', 'width' => 1920, 'height' => 1080,
            'duration' => 6, 'transparent' => false,
        ]);

        app()->call([new GenerateVfxJob($vfx->id()), 'handle']);

        // The render must use the requested canvas, not the project's 9:16 default.
        $this->assertSame(1920, $this->rendered['props']['width']);
        $this->assertSame(1080, $this->rendered['props']['height']);
        $this->assertSame(6.0, (float) $this->rendered['props']['duration']);
        $this->assertFalse($this->rendered['props']['transparent']);
        // Rendered through the ISOLATED candidate entry, never the production bundle.
        $this->assertSame('src/sample.ts', $this->rendered['entry']);
        $this->assertSame('SampleEffect', $this->rendered['composition']);

        $vfx = $this->store()->find($vfx->id());
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $vfx->status);
        $this->assertSame('mp4', $vfx->get('ext'));
        $this->assertNotNull($this->store()->videoFor($vfx));
    }

    public function test_a_transparent_render_is_written_as_a_mov(): void
    {
        $this->fakeClaudeReturning($this->payload());
        $this->spyRenderer();

        $vfx = $this->store()->create([
            'prompt' => 'a floating badge with no backdrop',
            'aspect' => '16:9', 'width' => 1920, 'height' => 1080,
            'duration' => 4, 'transparent' => true,
        ]);

        app()->call([new GenerateVfxJob($vfx->id()), 'handle']);

        $this->assertTrue($this->rendered['props']['transparent']);
        // ProRes cannot be written into an .mp4 container.
        $this->assertStringEndsWith('.mov', $this->rendered['out']);

        $vfx = $this->store()->find($vfx->id());
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $vfx->status);
        $this->assertSame('mov', $vfx->get('ext'));
        $this->assertStringEndsWith('.mov', (string) $this->store()->videoFor($vfx));
    }

    public function test_a_failed_generation_marks_the_record_failed_and_leaves_no_video(): void
    {
        // A component that hardcodes a brand colour instead of reading the tokens —
        // exactly what the design-system guard exists to reject.
        $this->fakeClaudeReturning(array_merge($this->payload(), [
            'tsx' => str_replace('COLORS.gold', '"#d4af37"', $this->payload()['tsx']),
        ]));
        $this->spyRenderer();

        $vfx = $this->store()->create([
            'prompt' => 'something the model gets wrong',
            'aspect' => '1:1', 'width' => 1080, 'height' => 1080,
            'duration' => 3, 'transparent' => false,
        ]);

        app()->call([new GenerateVfxJob($vfx->id()), 'handle']);

        $vfx = $this->store()->find($vfx->id());
        $this->assertSame(EffectRecord::STATUS_FAILED, $vfx->status);
        $this->assertStringContainsString('hardcoded hex colour', (string) $vfx->error);
        $this->assertNull($this->store()->videoFor($vfx));
        $this->assertSame([], $this->rendered);
    }

    // ── the asset ────────────────────────────────────────────────────────

    public function test_media_route_serves_and_downloads_a_finished_vfx_but_404s_while_pending(): void
    {
        $vfx = $this->store()->create([
            'prompt' => 'a sweep', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080,
            'duration' => 3, 'transparent' => false,
        ]);

        $this->comSessaoIniciada()
            ->get(route('clips-animados.vfx-media', $vfx->id()))
            ->assertNotFound();

        file_put_contents($this->store()->videoPath($vfx->id(), 'mp4'), 'FAKE-VIDEO');
        $vfx->update(['status' => EffectRecord::STATUS_ACTIVE, 'ext' => 'mp4']);

        $this->comSessaoIniciada()
            ->get(route('clips-animados.vfx-media', $vfx->id()))
            ->assertOk();

        $this->comSessaoIniciada()
            ->get(route('clips-animados.vfx-media', $vfx->id()).'?download=1')
            ->assertOk()
            ->assertDownload();
    }

    public function test_deleting_removes_the_record_and_its_video(): void
    {
        $vfx = $this->store()->create([
            'prompt' => 'a sweep', 'aspect' => '16:9', 'width' => 1920, 'height' => 1080,
            'duration' => 3, 'transparent' => false,
        ]);
        $video = $this->store()->videoPath($vfx->id(), 'mp4');
        file_put_contents($video, 'FAKE-VIDEO');

        Livewire::test(ClipsAnimadosVfx::class)->call('apagarVfx', $vfx->id());

        $this->assertCount(0, $this->store()->all());
        $this->assertFileDoesNotExist($video);
    }

    /**
     * Alpha needs ALL FOUR flags: without the PNG image format and the
     * yuva444p10le pixel format the render is silently opaque, which is worse
     * than an error — you only find out in the editing timeline.
     */
    public function test_transparent_render_args_carry_every_flag_alpha_needs(): void
    {
        $args = (new CliRemotionRenderer)->buildRenderArgs(
            ['transparent' => true], '/tmp/out.mov', '/tmp/props.json', 'src/sample.ts', 'SampleEffect'
        );

        $this->assertContains('--codec=prores', $args);
        $this->assertContains('--prores-profile=4444', $args);
        $this->assertContains('--image-format=png', $args);
        $this->assertContains('--pixel-format=yuva444p10le', $args);

        $opaque = (new CliRemotionRenderer)->buildRenderArgs(
            ['transparent' => false], '/tmp/out.mp4', '/tmp/props.json'
        );
        $this->assertContains('--codec=h264', $opaque);
        $this->assertNotContains('--pixel-format=yuva444p10le', $opaque);
    }

    // ── navigation ───────────────────────────────────────────────────────

    /**
     * SFX and VFX live under ONE nav entry ("Effects Studio") with subtabs, so
     * each page must offer both tabs and the sidebar must not list them
     * separately.
     */
    public function test_both_studio_pages_show_the_subtabs_under_a_single_nav_entry(): void
    {
        foreach (['/clips-animados/sfx', '/clips-animados/vfx'] as $url) {
            $page = $this->comSessaoIniciada()->get($url)->assertOk();

            // Both subtabs are reachable from either page.
            $page->assertSee(route('clips-animados.sfx'))
                ->assertSee(route('clips-animados.vfx'))
                ->assertSee('SFX Studio')
                ->assertSee('VFX Lab')
                // One consolidated sidebar entry, not two.
                ->assertSee('Effects Studio')
                ->assertSee('SFX · VFX', false);
        }
    }

    /**
     * The sidebar highlights exactly ONE entry. 'clips-animados*' would also match
     * the studio's routes, so Animated Clips and Effects Studio would both light up.
     */
    public function test_only_one_sidebar_entry_is_highlighted_on_a_studio_page(): void
    {
        $current = fn (string $url) => substr_count(
            (string) $this->comSessaoIniciada()->get($url)->getContent(),
            'aria-current="page"'
        );

        // One per page: the sidebar entry + the active subtab on the studio pages.
        $this->assertSame(1, $current('/clips-animados'));
        $this->assertSame(2, $current('/clips-animados/sfx'));
        $this->assertSame(2, $current('/clips-animados/vfx'));
    }

    // ── the prompt contract ──────────────────────────────────────────────

    /**
     * The canvas rules must describe the REQUESTED frame, not the project's 9:16
     * default, and must forbid a full-frame background — a component that paints
     * one covers the brand backdrop the composition draws behind it, so the
     * background appears to vanish when the animation starts.
     */
    public function test_the_system_prompt_states_the_canvas_and_bans_a_full_frame_background(): void
    {
        $landscape = $this->systemPromptFor(['width' => 1920, 'height' => 1080]);

        $this->assertStringContainsString('1920x1080', $landscape);
        $this->assertStringContainsString('LANDSCAPE', $landscape);
        $this->assertStringContainsString('NEVER PAINT A FULL-FRAME BACKGROUND', $landscape);
        $this->assertStringNotContainsString('PORTRAIT (tall)', $landscape);

        $portrait = $this->systemPromptFor(['width' => 1080, 'height' => 1920]);
        $this->assertStringContainsString('PORTRAIT', $portrait);
        $this->assertStringNotContainsString('LANDSCAPE', $portrait);

        // The SFX studio (no canvas given) still gets the project's own size.
        $default = $this->systemPromptFor([]);
        $this->assertStringContainsString(
            config('contentmachine.clips.width').'x'.config('contentmachine.clips.height'),
            $default
        );
        $this->assertStringContainsString('NEVER PAINT A FULL-FRAME BACKGROUND', $default);

        // Alpha renders have no backdrop at all, so the ban is restated as absolute.
        $alpha = $this->systemPromptFor(['width' => 1920, 'height' => 1080, 'transparent' => true]);
        $this->assertStringContainsString('TRANSPARENT OUTPUT', $alpha);
        $this->assertStringNotContainsString('TRANSPARENT OUTPUT', $landscape);
    }

    /** @param array<string,mixed> $canvas */
    private function systemPromptFor(array $canvas): string
    {
        $m = new \ReflectionMethod(EffectGenerator::class, 'systemPrompt');
        $m->setAccessible(true);

        return (string) $m->invoke(app(EffectGenerator::class), $canvas);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /** Record what the renderer was asked to produce, and write a stand-in file. */
    private function spyRenderer(): void
    {
        $this->app->bind(RemotionRenderer::class, fn () => new class($this->rendered) implements RemotionRenderer
        {
            public function __construct(private array &$seen) {}

            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                $this->seen = ['props' => $props, 'out' => $outPath, 'entry' => $entry, 'composition' => $composition];
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'FAKE-VIDEO');

                return $outPath;
            }
        });
    }

    /** A component payload that passes EffectGenerator::guard() (tokens only, no literals). */
    private function payload(): array
    {
        return [
            'slug' => 'gold-sweep',
            'displayName' => 'Gold sweep',
            'description' => 'A light sweep across the headline.',
            'paramSchema' => '{}',
            'sampleText' => 'CHAPTER ONE',
            'sampleParams' => [],
            'tsx' => 'import React from "react";'
                ."\n".'import { AbsoluteFill } from "remotion";'
                ."\n".'import { COLORS, FONTS } from "../style-tokens";'
                ."\n".'import type { PrimitiveProps } from "../primitives";'
                ."\n".'const GoldSweep: React.FC<PrimitiveProps> = ({ anim }) => ('
                ."\n".'  <AbsoluteFill style={{ color: COLORS.gold, fontFamily: FONTS.display }}>{anim.text}</AbsoluteFill>'
                ."\n".');'
                ."\n".'export default GoldSweep;',
        ];
    }

    /** Fake the Anthropic API so EffectGenerator::generate() returns the given payload as its TSX JSON. */
    private function fakeClaudeReturning(array $payload): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            ]),
        ]);
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
            $path = $dir.'/'.$f;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
