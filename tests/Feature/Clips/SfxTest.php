<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\GenerateEffectJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SfxTest extends TestCase
{
    use RefreshDatabase;

    private string $remotionTemp;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real remotion/src/effects during tests.
        $this->remotionTemp = sys_get_temp_dir().'/cm-remotion-'.uniqid();
        mkdir($this->remotionTemp.'/src/effects', 0775, true);
        config([
            'contentmachine.clips.remotion_path' => $this->remotionTemp,
            'contentmachine.clips.effects_previews' => $this->remotionTemp.'/previews',
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->remotionTemp);
        parent::tearDown();
    }

    private function effects(): EffectStore
    {
        return app(EffectStore::class);
    }

    /** @param array<string,mixed> $attrs */
    private function makeEffect(array $attrs): EffectRecord
    {
        return $this->effects()->create($attrs);
    }

    public function test_gerar_sfx_creates_pending_effect_and_dispatches_generation(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->set('sfxPrompt', 'a glitch flicker that snaps the headline into place')
            ->call('gerarSfx')
            ->assertHasNoErrors()
            ->assertSet('sfxPrompt', '');

        $effect = $this->effects()->all()->sole();
        $this->assertSame('pending', $effect->status);
        $this->assertStringContainsString('glitch flicker', $effect->prompt);
        Queue::assertPushed(GenerateEffectJob::class);
    }

    public function test_opening_sfx_dispatches_missing_built_in_previews(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)->call('abrirSfx')->assertSet('view', 'sfx');

        Queue::assertPushed(RenderEffectSampleJob::class);
    }

    public function test_active_effect_joins_the_planner_vocabulary_and_survives_sanitizing(): void
    {
        $this->makeEffect([
            'prompt' => 'glitch', 'slug' => 'glitch-flicker', 'display_name' => 'Glitch',
            'description' => 'Glitch flicker', 'param_schema' => '{ "intensity"?: number }',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function types(): array
            {
                return $this->allLayerTypes();
            }

            public function sanitize(array $layers): array
            {
                return $this->sanitizeLayers($layers);
            }
        };

        $this->assertContains('glitch-flicker', $planner->types());

        $kept = $planner->sanitize([['type' => 'glitch-flicker', 'text' => 'Hi', 'params' => []]]);
        $this->assertCount(1, $kept);
        $this->assertSame('glitch-flicker', $kept[0]['type']);
    }

    public function test_sync_filesystem_registers_active_effects_and_drops_orphans(): void
    {
        $library = app(EffectLibrary::class);

        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'neon-pulse', 'display_name' => 'Neon',
            'description' => 'Neon pulse', 'param_schema' => '{}',
            'tsx' => "// neon\nexport default () => null;", 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        file_put_contents($library->effectsDir().'/ghost.tsx', 'export default () => null;');

        $library->syncFilesystem();

        $index = file_get_contents($library->effectsDir().'/index.ts');
        $this->assertStringContainsString('"neon-pulse": Effect_neon_pulse', $index);
        $this->assertStringContainsString('import Effect_neon_pulse from "./neon-pulse";', $index);
        $this->assertFileExists($library->effectFile('neon-pulse'));
        $this->assertFileDoesNotExist($library->effectsDir().'/ghost.tsx');
        $this->assertContains('neon-pulse', $library->activeSlugs());
    }

    public function test_generator_rejects_hardcoded_brand_colours(): void
    {
        $this->fakeClaudeReturning([
            'slug' => 'bad-fx', 'displayName' => 'Bad', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => 'Hi', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'
                ."\nexport default () => ({ color: \"#ff0000\" });",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/hex/i');
        app(EffectGenerator::class)->generate('anything');
    }

    public function test_generator_accepts_token_based_component(): void
    {
        $tsx = 'import React from "react";'
            ."\nimport { AbsoluteFill } from \"remotion\";"
            ."\nimport { COLORS } from \"../style-tokens\";"
            ."\nimport type { PrimitiveProps } from \"../primitives\";"
            ."\nconst E: React.FC<PrimitiveProps> = () => <AbsoluteFill style={{ backgroundColor: COLORS.papyrus }} />;"
            ."\nexport default E;";

        $this->fakeClaudeReturning([
            'slug' => 'Soft Wipe', 'displayName' => 'Soft wipe', 'description' => 'A soft wipe',
            'paramSchema' => '{}', 'sampleText' => 'Hi', 'sampleParams' => ['intensity' => 2], 'tsx' => $tsx,
        ]);

        $data = app(EffectGenerator::class)->generate('a soft wipe');

        $this->assertSame('soft-wipe', $data['slug']);
        $this->assertSame($tsx, $data['tsx']);
        $this->assertSame(['intensity' => 2], $data['sample_params']);
    }

    public function test_editing_an_active_effect_regenerates_keeping_its_slug(): void
    {
        Queue::fake();

        $effect = $this->makeEffect([
            'prompt' => 'a soft drop', 'slug' => 'soft-drop', 'display_name' => 'Soft drop',
            'description' => 'Soft drop', 'param_schema' => '{}',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarSfx', $effect->id())
            ->assertSet('editingSfxId', $effect->id())
            ->assertSet('sfxEditPrompt', 'a soft drop')
            ->set('sfxEditPrompt', 'a soft drop that bounces twice')
            ->call('guardarSfxEdicao')
            ->assertHasNoErrors()
            ->assertSet('editingSfxId', null);

        $effect->refresh();
        $this->assertSame('updating', $effect->status);
        $this->assertSame('a soft drop that bounces twice', $effect->prompt);
        $this->assertSame('soft-drop', $effect->slug);

        Queue::assertPushed(GenerateEffectJob::class, fn (GenerateEffectJob $job) => $job->isEdit === true && $job->effectId === $effect->id());
    }

    public function test_generator_keeps_the_given_slug_and_skips_the_collision_check(): void
    {
        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'soft-drop', 'display_name' => 'Soft drop',
            'description' => 'x', 'param_schema' => '{}',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $this->fakeClaudeReturning([
            'slug' => 'a-totally-different-name', 'displayName' => 'Soft drop', 'description' => 'x',
            'paramSchema' => '{}', 'sampleText' => 'Hi', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
        ]);

        $data = app(EffectGenerator::class)->generate('bounce twice', keepSlug: 'soft-drop');

        $this->assertSame('soft-drop', $data['slug']);
    }

    public function test_disallowing_an_effect_removes_it_from_the_planner_but_keeps_it_registered(): void
    {
        $library = app(EffectLibrary::class);

        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'neon-pulse', 'display_name' => 'Neon',
            'description' => 'Neon pulse', 'param_schema' => '{}',
            'tsx' => "// neon\nexport default () => null;", 'status' => EffectRecord::STATUS_ACTIVE,
            'enabled' => true,
        ]);
        $library->syncFilesystem();

        $this->assertContains('neon-pulse', $library->activeSlugs());

        $effect->update(['enabled' => false]);

        $this->assertNotContains('neon-pulse', $library->activeSlugs());
        $this->assertTrue($library->active()->contains('slug', 'neon-pulse'));
        $this->assertFileExists($library->effectFile('neon-pulse'));
    }

    public function test_alternar_sfx_toggles_the_allowed_flag(): void
    {
        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'neon-pulse', 'display_name' => 'Neon',
            'description' => 'x', 'param_schema' => '{}',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
        ]);

        Livewire::test(ClipsAnimados::class)->call('alternarSfx', $effect->id());
        $this->assertFalse($effect->refresh()->enabled);

        Livewire::test(ClipsAnimados::class)->call('alternarSfx', $effect->id());
        $this->assertTrue($effect->refresh()->enabled);
    }

    public function test_disabling_a_builtin_removes_it_from_the_planner(): void
    {
        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function types(): array
            {
                return $this->allLayerTypes();
            }

            public function sanitize(array $layers): array
            {
                return $this->sanitizeLayers($layers);
            }
        };
        $library = app(EffectLibrary::class);

        $this->assertContains('seal-stamp', $planner->types());

        $library->toggleBuiltin('seal-stamp');

        $this->assertNotContains('seal-stamp', $planner->types());
        $this->assertCount(0, $planner->sanitize([['type' => 'seal-stamp', 'text' => 'x', 'params' => []]]));

        $library->toggleBuiltin('seal-stamp');
        $this->assertContains('seal-stamp', $planner->types());
    }

    public function test_alternar_builtin_toggles_and_ignores_unknown_slugs(): void
    {
        $library = app(EffectLibrary::class);

        Livewire::test(ClipsAnimados::class)->call('alternarBuiltin', 'fade');
        $this->assertFalse($library->builtinAllowed('fade'));

        Livewire::test(ClipsAnimados::class)->call('alternarBuiltin', 'fade');
        $this->assertTrue($library->builtinAllowed('fade'));

        $library->toggleBuiltin('not-a-real-primitive');
        $this->assertSame([], $library->disabledBuiltins());
    }

    public function test_only_active_effects_can_be_edited(): void
    {
        $failed = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'pending-abcd1234', 'display_name' => 'Broken',
            'description' => '', 'param_schema' => '{}', 'tsx' => '', 'status' => EffectRecord::STATUS_FAILED,
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarSfx', $failed->id())
            ->assertSet('editingSfxId', null);
    }

    public function test_generator_uniquifies_a_slug_that_already_exists(): void
    {
        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'non-linear-diagram', 'display_name' => 'Diagram',
            'description' => 'x', 'param_schema' => '{}', 'tsx' => 'export default () => null;',
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $this->fakeClaudeReturning([
            'slug' => 'non-linear-diagram', 'displayName' => 'Diagram', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => '', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
        ]);

        // No more "already exists" error — the slug is made unique instead.
        $data = app(EffectGenerator::class)->generate('another diagram');
        $this->assertSame('non-linear-diagram-2', $data['slug']);
    }

    public function test_generator_uniquifies_a_builtin_name_for_new_effects(): void
    {
        $this->fakeClaudeReturning([
            'slug' => 'diagram', 'displayName' => 'Diagram', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => '', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
        ]);

        // A fresh effect must not silently override the built-in 'diagram'.
        $data = app(EffectGenerator::class)->generate('a diagram-like effect');
        $this->assertSame('diagram-2', $data['slug']);
    }

    public function test_editing_a_builtin_creates_an_override_with_its_slug(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('editarBuiltin', 'seal-stamp')
            ->assertSet('sfxOverrideSlug', 'seal-stamp')
            ->assertSet('editingSfxId', null)
            ->set('sfxEditPrompt', 'make the seal gold and larger with a soft shine')
            ->call('guardarSfxEdicao')
            ->assertHasNoErrors()
            ->assertSet('sfxOverrideSlug', null);

        $override = $this->effects()->all()->firstWhere('slug', 'seal-stamp');
        $this->assertNotNull($override);
        $this->assertSame('pending', $override->status);
        Queue::assertPushed(GenerateEffectJob::class);
    }

    public function test_override_generation_tells_the_model_the_builtin_param_contract(): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(function ($request) {
            $userMsg = (string) ($request->data()['messages'][0]['content'] ?? '');
            // The override must be told the card's REAL params (title/lines), not invent its own.
            $this->assertStringContainsString('OVERRIDE CONTRACT', $userMsg);
            $this->assertStringContainsString('title', $userMsg);
            $this->assertStringContainsString('lines', $userMsg);

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode([
                'slug' => 'card', 'displayName' => 'Def', 'description' => 'x', 'paramSchema' => '{}',
                'sampleText' => '', 'sampleParams' => ['title' => 'X', 'lines' => ['y']],
                'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
            ])]]]);
        });

        $data = app(EffectGenerator::class)->generate('type the definition letter by letter', keepSlug: 'card');
        $this->assertSame('card', $data['slug']);
    }

    public function test_generator_allows_a_builtin_slug_for_overrides(): void
    {
        $this->fakeClaudeReturning([
            'slug' => 'ignored-by-override', 'displayName' => 'Seal', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => 'IATECA', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
        ]);

        // Keeping a built-in slug is allowed (it overrides the built-in).
        $data = app(EffectGenerator::class)->generate('a gold seal', keepSlug: 'seal-stamp');
        $this->assertSame('seal-stamp', $data['slug']);
    }

    public function test_resetting_a_builtin_removes_the_override_and_its_file(): void
    {
        $library = app(EffectLibrary::class);
        $override = $this->makeEffect([
            'prompt' => 'gold seal', 'slug' => 'seal-stamp', 'display_name' => 'Seal (custom)',
            'description' => 'Override of the built-in seal-stamp.', 'param_schema' => '{}',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);
        $library->syncFilesystem();
        $this->assertFileExists($library->effectFile('seal-stamp'));

        Livewire::test(ClipsAnimados::class)->call('resetBuiltin', 'seal-stamp');

        $this->assertNull($this->effects()->find($override->id()));
        $this->assertFileDoesNotExist($library->effectFile('seal-stamp'));
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
