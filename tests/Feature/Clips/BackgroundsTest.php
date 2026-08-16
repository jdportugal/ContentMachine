<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\GenerateBackgroundJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\BackgroundGenerator;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\EffectRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class BackgroundsTest extends TestCase
{
    use RefreshDatabase;

    private string $remotionTemp;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real remotion/src during tests.
        $this->remotionTemp = sys_get_temp_dir().'/cm-remotion-bg-'.uniqid();
        mkdir($this->remotionTemp.'/src/backgrounds', 0775, true);
        config([
            'contentmachine.clips.remotion_path' => $this->remotionTemp,
            'contentmachine.clips.backgrounds_previews' => $this->remotionTemp.'/bg-previews',
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->remotionTemp);
        parent::tearDown();
    }

    private function store(): BackgroundStore
    {
        return app(BackgroundStore::class);
    }

    public function test_gerar_background_creates_pending_code_background_and_dispatches(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->set('bgPrompt', 'a slow aurora drifting over deep ink with floating particles')
            ->call('gerarBackground')
            ->assertHasNoErrors()
            ->assertSet('bgPrompt', '');

        $bg = $this->store()->all()->sole();
        $this->assertSame('pending', $bg->status);
        $this->assertSame('code', $bg->kind);
        $this->assertStringContainsString('aurora', $bg->prompt);
        Queue::assertPushed(GenerateBackgroundJob::class);
    }

    public function test_upload_background_stores_an_active_video_and_its_file(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->set('bgVideoName', 'City timelapse')
            ->set('bgVideo', UploadedFile::fake()->create('city.mp4', 200, 'video/mp4'))
            ->call('uploadBackground')
            ->assertHasNoErrors();

        $bg = $this->store()->all()->sole();
        $this->assertSame('video', $bg->kind);
        $this->assertSame('active', $bg->status);
        $this->assertSame('city-timelapse', $bg->slug);
        $this->assertFileExists($this->store()->videoPath($bg->id()));
    }

    public function test_alternar_background_toggles_the_enabled_flag(): void
    {
        $bg = $this->store()->create([
            'kind' => 'code', 'slug' => 'aurora', 'display_name' => 'Aurora', 'description' => 'x',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
        ]);

        Livewire::test(ClipsAnimados::class)->call('alternarBackground', $bg->id());
        $this->assertFalse($this->store()->find($bg->id())->enabled);

        Livewire::test(ClipsAnimados::class)->call('alternarBackground', $bg->id());
        $this->assertTrue($this->store()->find($bg->id())->enabled);
    }

    public function test_resolve_choice_covers_manual_auto_none_and_empty(): void
    {
        $library = app(BackgroundLibrary::class);

        // No backgrounds: everything resolves to null (themed backdrop).
        $this->assertNull($library->resolveChoice('auto'));
        $this->assertNull($library->resolveChoice('none'));
        $this->assertNull($library->resolveChoice('whatever'));

        $this->store()->create([
            'kind' => 'code', 'slug' => 'aurora', 'display_name' => 'Aurora', 'description' => 'x',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
        ]);

        // Manual pick honoured when enabled, dropped when unknown/disabled.
        $this->assertSame('aurora', $library->resolveChoice('aurora'));
        $this->assertNull($library->resolveChoice('does-not-exist'));

        // none is always null; auto honours the planner's pick, else the only enabled one.
        $this->assertNull($library->resolveChoice('none', 'aurora'));
        $this->assertSame('aurora', $library->resolveChoice('auto', 'aurora'));
        $this->assertSame('aurora', $library->resolveChoice('auto', 'ghost')); // bad pick → random enabled
    }

    public function test_sync_filesystem_registers_code_backgrounds_skips_video_and_drops_orphans(): void
    {
        $library = app(BackgroundLibrary::class);

        $this->store()->create([
            'kind' => 'code', 'slug' => 'aurora', 'display_name' => 'Aurora', 'description' => 'x',
            'tsx' => "// aurora\nexport default () => null;", 'status' => EffectRecord::STATUS_ACTIVE,
        ]);
        $this->store()->create([
            'kind' => 'video', 'slug' => 'city', 'display_name' => 'City', 'description' => 'x',
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        file_put_contents($library->backgroundFile('ghost'), 'export default () => null;');

        $library->syncFilesystem();

        $index = file_get_contents($library->backgroundsDir().'/index.ts');
        $this->assertStringContainsString('"aurora": Bg_aurora', $index);
        $this->assertStringNotContainsString('city', $index); // video has no source file
        $this->assertFileExists($library->backgroundFile('aurora'));
        $this->assertFileDoesNotExist($library->backgroundFile('ghost'));
    }

    public function test_reel_entries_include_code_and_video_backgrounds(): void
    {
        $this->store()->create([
            'kind' => 'code', 'slug' => 'aurora', 'display_name' => 'Aurora', 'description' => 'x',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);
        $video = $this->store()->create([
            'kind' => 'video', 'slug' => 'city', 'display_name' => 'City', 'description' => 'x',
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $entries = app(BackgroundLibrary::class)->reelEntries();
        $this->assertCount(2, $entries);

        $byKind = collect($entries)->keyBy('kind');
        $this->assertSame('aurora', $byKind['code']['slug']);
        $this->assertNull($byKind['code']['src']);
        $this->assertSame($this->store()->videoPath($video->id()), $byKind['video']['src']);

        $props = app(BackgroundLibrary::class)->reelProps();
        $this->assertSame($entries, $props['entries']);
        $this->assertGreaterThan(0, $props['perSeconds']);
    }

    public function test_generator_rejects_design_token_use(): void
    {
        // A background must be independent of the design system: importing the
        // themeable tokens is what turned a requested gold into another brand's accent.
        $this->fakeClaudeReturning([
            'slug' => 'bad-bg', 'displayName' => 'Bad', 'description' => 'x',
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/style-tokens/');
        app(BackgroundGenerator::class)->generate('anything');
    }

    public function test_generator_paints_the_literal_colours_from_the_prompt(): void
    {
        // Hardcoded hex is now REQUIRED — the background renders the exact colour asked.
        $tsx = 'import React from "react";'
            ."\nimport { AbsoluteFill } from \"remotion\";"
            ."\nimport type { PrimitiveProps } from \"../primitives\";"
            ."\nconst B: React.FC<PrimitiveProps> = () => <AbsoluteFill style={{ backgroundColor: \"#C8941E\" }} />;"
            ."\nexport default B;";

        $this->fakeClaudeReturning([
            'slug' => 'Gold Barcode', 'displayName' => 'Gold barcode', 'description' => 'Gold with drifting bars', 'tsx' => $tsx,
        ]);

        $data = app(BackgroundGenerator::class)->generate('gold #C8941E background with drifting bars');

        $this->assertSame('gold-barcode', $data['slug']);
        $this->assertSame($tsx, $data['tsx']);
        $this->assertStringContainsString('#C8941E', $data['tsx']);
    }

    /** Fake the Anthropic API so BackgroundGenerator::generate() returns the given payload. */
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
