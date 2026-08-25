<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\DesignSystem\DesignTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClipsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contentmachine.clips.driver', 'fake');
        // Pin the image switch instead of inheriting the developer's .env. With
        // images ON the pipeline deliberately PAUSES at `collecting` to offer the
        // upload step, so these end-to-end-to-`done` tests passed or failed purely
        // on how CLIPS_GENERATE_IMAGES happened to be set on the machine running
        // them. The pause itself is covered below.
        config()->set('contentmachine.clips.generate_images', false);
    }

    private function store(): ClipStore
    {
        return app(ClipStore::class);
    }

    public function test_render_injects_the_design_system_theme(): void
    {
        // Theme tokens saved by the Design System — for the DEFAULT project, since
        // the render job activates the clip's project (default here) and repoints
        // the design-system path at it.
        config(['contentmachine.design_system.path' => config('contentmachine.projects.default_vault').'/design-system.md']);
        app(DesignSystemRepository::class)
            ->writeTokens(DesignTheme::sanitize([
                'colors' => ['bg' => '#0a1230'],
                'fonts' => ['display' => 'Anton'],
                'texture' => ['kind' => 'starfield'],
            ]));

        // Renderer that captures the props it receives.
        $captured = [];
        $this->app->instance(RemotionRenderer::class, new class($captured) implements RemotionRenderer
        {
            public function __construct(public array &$captured) {}

            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                $this->captured = $props;
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'X');

                return $outPath;
            }
        });

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá',
            'status' => ClipRecord::STATUS_PLANNING,
            'transcript' => ['words' => []],
            'plan' => ['scenes' => [['start' => 0, 'end' => 1, 'background' => 'papyrus', 'layers' => []]]],
        ]);

        RenderJob::dispatchSync($p->id);

        $this->assertArrayHasKey('theme', $captured);
        $this->assertSame('#0a1230', $captured['theme']['colors']['bg']);
        $this->assertSame('Anton', $captured['theme']['fonts']['display']);
        $this->assertSame('starfield', $captured['theme']['texture']['kind']);
    }

    public function test_animation_pipeline_reaches_done_with_fakes(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá mundo Brand Machine',
        ]);

        TranscribeJob::dispatch($p->id); // sync queue in tests → runs the whole chain
        $p->refresh();

        $this->assertSame(ClipRecord::STATUS_DONE, $p->status);
        $this->assertNotEmpty($p->plan['scenes']);
        $this->assertNotNull($p->output_path);
    }

    /**
     * With image generation ON the clip must STOP and ask before spending credits:
     * the planner's `generate` requests become upload suggestions, and the clip
     * parks at `collecting` until the user uploads or skips. Turning the switch on
     * is what makes image-reveal layers exist at all, so this is the behaviour that
     * distinguishes the two modes — and it was untested.
     */
    /**
     * One missing picture must not take the whole clip with it.
     *
     * An image id whose FILE is gone was still mapped to its absolute path. The
     * renderer only stages assets it can see on disk, so the dead path travelled
     * into the composition and Remotion aborted the entire render with
     * "staticFile() does not support absolute paths". The reference is emptied
     * instead: that one layer renders blank, every other scene still renders.
     */
    public function test_an_image_whose_file_vanished_does_not_kill_the_render(): void
    {
        $captured = [];
        $this->app->instance(RemotionRenderer::class, new class($captured) implements RemotionRenderer
        {
            public function __construct(public array &$captured) {}

            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                $this->captured = $props;
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'X');

                return $outPath;
            }
        });

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá',
            'status' => ClipRecord::STATUS_PLANNING,
            'transcript' => ['words' => []],
            // The record still references the image; the file behind it is gone.
            'images' => [['id' => 'img_gone', 'path' => 'clips/generated/never-written.png']],
            'plan' => ['scenes' => [[
                'start' => 0, 'end' => 1, 'background' => 'papyrus',
                'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'img_gone']]],
            ]]],
        ]);

        RenderJob::dispatchSync($p->id);

        $src = $captured['scenes'][0]['layers'][0]['params']['src'] ?? null;
        $this->assertSame('', $src, 'a dead image path must be cleared, never handed to staticFile()');
        $this->assertSame(ClipRecord::STATUS_DONE, $this->store()->findOrFail($p->id)->status);
    }

    public function test_with_images_on_the_clip_pauses_to_offer_the_upload_step(): void
    {
        config()->set('contentmachine.clips.generate_images', true);

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá mundo Brand Machine',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipRecord::STATUS_COLLECTING, $p->status);
        $this->assertNotEmpty($p->meta['image_requests'] ?? []);
    }

    public function test_resolves_uploaded_audio_via_the_storage_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('clips/uploads/gravacao.webm', 'RECORDING-BYTES');

        // Transcription that fails unless the audio file is found at the resolved path.
        $this->app->instance(TranscriptionService::class, new class implements TranscriptionService
        {
            public function transcribe(string $audioPath): array
            {
                if (! is_file($audioPath)) {
                    throw new \RuntimeException("audio not found at {$audioPath}");
                }

                return ['duration' => 2.0, 'text' => 'x', 'words' => [['word' => 'x', 'start' => 0.0, 'end' => 2.0]], 'segments' => []];
            }
        });

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'source_path' => 'clips/uploads/gravacao.webm',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipRecord::STATUS_DONE, $p->status);
    }

    public function test_overlay_pipeline_reaches_done_with_fakes(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/example.mp4',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipRecord::STATUS_DONE, $p->status);
        $this->assertNotNull($p->output_path);
    }

    public function test_overlay_plan_carries_per_scene_present_modes(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/example.mp4',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipRecord::STATUS_DONE, $p->status);
        $presents = array_column($p->plan['scenes'], 'present');
        $this->assertContains('video', $presents);
        $this->assertContains('over', $presents);
    }
}
