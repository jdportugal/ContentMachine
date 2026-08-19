<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\GenerateEffectJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Livewire\ClipsAnimadosSfx;
use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\CliRemotionRenderer;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\EffectPortability;
use App\Services\Clips\Fake\FakeRemotionRenderer;
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

        Livewire::test(ClipsAnimadosSfx::class)
            ->set('sfxPrompt', 'a glitch flicker that snaps the headline into place')
            ->call('gerarSfx')
            ->assertHasNoErrors()
            ->assertSet('sfxPrompt', '');

        $effect = $this->effects()->all()->sole();
        $this->assertSame('pending', $effect->status);
        $this->assertStringContainsString('glitch flicker', $effect->prompt);
        Queue::assertPushed(GenerateEffectJob::class);
    }

    public function test_opening_sfx_page_dispatches_missing_built_in_previews(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimadosSfx::class); // mount() ensures previews

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

    /** Shadows and scrims are written as neutral rgba() everywhere — including in
     *  the design tokens themselves — so a neutral literal must be allowed. */
    public function test_guard_allows_neutral_black_and_white_rgba(): void
    {
        $tsx = 'import React from "react";'
            ."\nimport { COLORS } from \"../style-tokens\";"
            ."\nexport default () => <div style={{ color: COLORS.teal,"
            ."\n  boxShadow: \"0 2px 18px rgba(0,0,0,0.35)\","
            ."\n  background: \"rgba(255, 255, 255, .06)\" }} />;";

        $this->fakeClaudeReturning([
            'slug' => 'shadowy', 'displayName' => 'Shadowy', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => '', 'sampleParams' => [], 'tsx' => $tsx,
        ]);

        $this->assertSame($tsx, app(EffectGenerator::class)->generate('a soft shadow')['tsx']);
    }

    /**
     * A refine that the model gets wrong must leave the working effect alone.
     *
     * The caller flips the status to `updating` before dispatching, so the job
     * cannot ask "is it active?" to know whether there's a live version to keep —
     * it would answer no, and a rejected rewrite would mark a good effect failed.
     */
    public function test_a_failed_refine_keeps_the_live_effect_active(): void
    {
        $effect = $this->makeEffect([
            'prompt' => 'a soft wipe', 'slug' => 'soft-wipe', 'display_name' => 'Soft wipe',
            'description' => 'x', 'param_schema' => '{}',
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => null;",
            'status' => EffectRecord::STATUS_UPDATING, // what the studio set before dispatching
        ]);

        // The model comes back with a hardcoded colour → the guard rejects it.
        $this->fakeClaudeReturning([
            'slug' => 'soft-wipe', 'displayName' => 'Soft wipe', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => '', 'sampleParams' => [],
            'tsx' => 'import { COLORS } from "../style-tokens";'."\nexport default () => ({ color: \"#ff0000\" });",
        ]);

        (new GenerateEffectJob($effect->id(), isEdit: true))->handle(
            app(EffectGenerator::class), app(EffectLibrary::class), $this->effects(),
            app(RemotionRenderer::class), app(\App\Services\Capture\SiteFootage::class),
        );

        $depois = $this->effects()->find($effect->id());
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $depois->status);          // still usable
        $this->assertStringContainsString('export default () => null;', $depois->tsx); // old code intact
        $this->assertStringContainsString('Edit failed', (string) $depois->error);
    }

    /**
     * A failed effect is exactly the one you need to roll back — restoring it was
     * refused ("only a live effect"), which left a bad rewrite with no way home.
     */
    public function test_a_failed_effect_can_be_restored_from_its_history(): void
    {
        $bom = 'import { COLORS } from "../style-tokens";'."\nexport default () => null; // the good one";
        $effect = $this->makeEffect([
            'prompt' => 'a soft wipe', 'slug' => 'soft-wipe', 'display_name' => 'Soft wipe',
            'description' => 'x', 'param_schema' => '{}', 'tsx' => 'broken',
            'status' => EffectRecord::STATUS_FAILED, 'error' => 'Edit failed: something',
            'versions' => [['prompt' => 'a soft wipe', 'tsx' => $bom, 'created_at' => now()->toIso8601String()]],
        ]);

        Livewire::test(ClipsAnimadosSfx::class, ['key' => $effect->id()])
            ->assertSee('Restore a working version')
            ->call('reverterSfx', $effect->id(), 0);

        $depois = $this->effects()->find($effect->id());
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $depois->status);
        $this->assertSame($bom, $depois->tsx);
        $this->assertNull($depois->error);
        // The component is back on disk, so renders pick it up again.
        $this->assertStringContainsString('the good one', (string) file_get_contents(app(EffectLibrary::class)->effectFile('soft-wipe')));
    }

    /**
     * "Export all" walked only ACTIVE effects, so after a bad regeneration marked
     * them failed the download 404'd — the one moment a backup actually matters.
     */
    public function test_export_all_includes_failed_effects_that_still_have_code(): void
    {
        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'broken-fx', 'display_name' => 'Broken', 'description' => 'x',
            'param_schema' => '{}', 'tsx' => 'export default () => null;',
            'status' => EffectRecord::STATUS_FAILED, 'error' => 'Edit failed: something',
        ]);
        // A record with no component at all is still not exportable.
        $this->makeEffect([
            'prompt' => 'y', 'slug' => 'empty-fx', 'display_name' => 'Empty', 'description' => 'y',
            'param_schema' => '{}', 'tsx' => '', 'status' => EffectRecord::STATUS_PENDING,
        ]);

        $payload = app(EffectPortability::class)->export('all');

        $this->assertNotNull($payload);
        $this->assertSame(['broken-fx'], array_column($payload['effects'], 'slug'));

        $this->comSessaoIniciada()->get(route('clips-animados.sfx-export', 'all'))->assertOk();
    }

    /** A restore must not race a generation that is still running. */
    public function test_restoring_is_refused_while_a_generation_is_in_flight(): void
    {
        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'busy-fx', 'display_name' => 'Busy', 'description' => 'x',
            'param_schema' => '{}', 'tsx' => 'current', 'status' => EffectRecord::STATUS_UPDATING,
            'versions' => [['prompt' => 'x', 'tsx' => 'older']],
        ]);

        Livewire::test(ClipsAnimadosSfx::class)->call('reverterSfx', $effect->id(), 0);

        $this->assertSame('current', $this->effects()->find($effect->id())->tsx);
    }

    /** A literal carrying HUE is still a design-system violation. */
    public function test_guard_rejects_a_tinted_rgba(): void
    {
        $this->fakeClaudeReturning([
            'slug' => 'tinted', 'displayName' => 'Tinted', 'description' => 'x', 'paramSchema' => '{}',
            'sampleText' => '', 'sampleParams' => [],
            'tsx' => 'import React from "react";'
                ."\nimport { COLORS } from \"../style-tokens\";"
                ."\nexport default () => <div style={{ boxShadow: \"0 0 30px rgba(255,180,70,.4)\" }} />;",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/colour literal/i');
        app(EffectGenerator::class)->generate('a tinted glow');
    }

    /** An effect that only moves an image has no colour or type of its own — it
     *  must not be forced to import tokens it never uses. */
    public function test_guard_does_not_demand_tokens_from_a_component_that_paints_nothing(): void
    {
        $tsx = 'import React from "react";'
            ."\nimport { AbsoluteFill, Img, interpolate, useCurrentFrame } from \"remotion\";"
            ."\nimport type { PrimitiveProps } from \"../primitives\";"
            ."\nconst Zoom: React.FC<PrimitiveProps> = ({ anim, frame }) => {"
            ."\n  const f = useCurrentFrame();"
            ."\n  const scale = interpolate(f, [0, 30], [1, 1.2]);"
            ."\n  return <AbsoluteFill><Img src={String(anim.params?.src ?? \"\")} style={{ transform: `scale(\${scale})`, width: frame.width }} /></AbsoluteFill>;"
            ."\n};"
            ."\nexport default Zoom;";

        $this->fakeClaudeReturning([
            'slug' => 'image-zoom-in', 'displayName' => 'Image zoom in', 'description' => 'Zooms an image',
            'paramSchema' => '{}', 'sampleText' => '', 'sampleParams' => [], 'tsx' => $tsx,
        ]);

        $this->assertSame($tsx, app(EffectGenerator::class)->generate('zoom an image in')['tsx']);
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

    /**
     * The contract asks for paramSchema as a one-liner like `{ "intensity"?: number }`,
     * so the model frequently returns a real JSON OBJECT. Casting that to a string
     * raised "Array to string conversion" and killed the whole generation over a
     * cosmetic field.
     */
    public function test_an_object_shaped_param_schema_does_not_break_generation(): void
    {
        $tsx = 'import React from "react";'
            ."\nimport { COLORS } from \"../style-tokens\";"
            ."\nexport default () => null;";

        $this->fakeClaudeReturning([
            'slug' => 'objecty', 'displayName' => ['not', 'a string'], 'description' => ['also' => 'object'],
            'paramSchema' => ['intensity' => 'number', 'nested' => ['a' => 1]],
            'sampleText' => 'Hi', 'sampleParams' => ['intensity' => 2], 'tsx' => $tsx,
        ]);

        $data = app(EffectGenerator::class)->generate('anything');

        $this->assertSame('{"intensity":"number","nested":{"a":1}}', $data['param_schema']);
        $this->assertSame('["not","a string"]', $data['display_name']);
        $this->assertSame('{"also":"object"}', $data['description']);
        $this->assertSame($tsx, $data['tsx']);
    }

    public function test_a_sample_is_rendered_and_cached_per_frame_format(): void
    {
        $library = app(EffectLibrary::class);
        $w = (int) config('contentmachine.clips.width');
        $h = (int) config('contentmachine.clips.height');

        // Half is the SAME width and half the height — not a landscape box.
        $this->assertSame([$w, $h], EffectLibrary::formatSize('portrait', $w, $h));
        $this->assertSame([$w, (int) round($h / 2)], EffectLibrary::formatSize('half', $w, $h));
        $this->assertSame([$h, $w], EffectLibrary::formatSize('landscape', $w, $h));

        // The plan carries the box AND names the format: a 1080×960 frame is wider
        // than it is tall, so the renderer could not tell "half" from "landscape".
        $plano = $library->samplePlan('card', 'Hi', [], 'half');
        $this->assertSame($w, $plano['width']);
        $this->assertSame((int) round($h / 2), $plano['height']);
        $this->assertSame('half', $plano['format']);

        // Each format caches to its own file; portrait keeps the historic name.
        $this->assertNotSame($library->previewPath('card'), $library->previewPath('card', 'half'));
        $this->assertSame($library->previewPath('card'), $library->previewPath('card', 'portrait'));
    }

    public function test_switching_the_preview_format_renders_that_frame_once(): void
    {
        Queue::fake();
        $effect = $this->makeEffect([
            'slug' => 'my-effect', 'display_name' => 'My effect', 'prompt' => 'a soft wipe',
            'tsx' => 'x', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
            'sample_text' => 'Hello', 'sample_params' => ['a' => 1],
        ]);

        Livewire::test(ClipsAnimadosSfx::class, ['key' => $effect->id()])
            ->assertSet('formato', 'portrait')
            ->call('verFormato', 'half')
            ->assertSet('formato', 'half')
            ->call('verFormato', 'bogus')      // unknown format is ignored
            ->assertSet('formato', 'half');

        Queue::assertPushed(RenderEffectSampleJob::class, fn (RenderEffectSampleJob $j) => $j->slug === 'my-effect'
            && $j->format === 'half'
            && $j->text === 'Hello'
            && $j->params === ['a' => 1]);
        Queue::assertNotPushed(RenderEffectSampleJob::class, fn (RenderEffectSampleJob $j) => $j->format === 'bogus');
    }

    public function test_rewriting_all_effects_regenerates_them_keeping_their_prompt(): void
    {
        Queue::fake();
        $vivo = $this->makeEffect([
            'slug' => 'live-one', 'prompt' => 'a soft wipe', 'tsx' => 'x',
            'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
        ]);
        $falhado = $this->makeEffect([
            'slug' => 'broken-one', 'prompt' => 'nope', 'tsx' => '',
            'status' => EffectRecord::STATUS_FAILED,
        ]);

        Livewire::test(ClipsAnimadosSfx::class)->call('tornarResponsivos');

        // Only the live one is rewritten, in place, with its description intact —
        // and its previous version is kept so "go back" undoes the rewrite.
        Queue::assertPushed(GenerateEffectJob::class, fn (GenerateEffectJob $j) => $j->effectId === $vivo->id() && $j->isEdit === true);
        Queue::assertNotPushed(GenerateEffectJob::class, fn (GenerateEffectJob $j) => $j->effectId === $falhado->id());

        $depois = $this->effects()->find($vivo->id());
        $this->assertSame(EffectRecord::STATUS_UPDATING, $depois->status);
        $this->assertSame('a soft wipe', $depois->prompt);
        $this->assertCount(1, $depois->get('versions', []));
    }

    public function test_editing_an_active_effect_regenerates_keeping_its_slug(): void
    {
        Queue::fake();

        $effect = $this->makeEffect([
            'prompt' => 'a soft drop', 'slug' => 'soft-drop', 'display_name' => 'Soft drop',
            'description' => 'Soft drop', 'param_schema' => '{}',
            'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        Livewire::test(ClipsAnimadosSfx::class)
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

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarSfx', $effect->id());
        $this->assertFalse($effect->refresh()->enabled);

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarSfx', $effect->id());
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

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarBuiltin', 'fade');
        $this->assertFalse($library->builtinAllowed('fade'));

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarBuiltin', 'fade');
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

        Livewire::test(ClipsAnimadosSfx::class)
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

        Livewire::test(ClipsAnimadosSfx::class)
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
            'sampleText' => 'Brand Machine', 'sampleParams' => [],
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

        Livewire::test(ClipsAnimadosSfx::class)->call('resetBuiltin', 'seal-stamp');

        $this->assertNull($this->effects()->find($override->id()));
        $this->assertFileDoesNotExist($library->effectFile('seal-stamp'));
    }

    // ── per-effect sound (audio attached to an effect) ───────────────────

    public function test_audio_store_round_trips_by_slug(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'aud_').'.wav';
        file_put_contents($src, 'RIFFfake');

        $store = $this->effects();
        $this->assertNull($store->audioPath('fade'));

        $store->putAudio('fade', $src, 'wav');
        $this->assertNotNull($store->audioPath('fade'));
        $this->assertStringEndsWith('/fade.wav', $store->audioPath('fade'));
        $this->assertContains('fade', $store->audioSlugs());

        // Prefix slug must not match (fade.* is literal "fade." + ext).
        $this->assertNull($store->audioPath('fade-in'));

        // Replacing swaps the extension, leaving exactly one file.
        $mp3 = tempnam(sys_get_temp_dir(), 'aud_').'.mp3';
        file_put_contents($mp3, 'ID3fake');
        $store->putAudio('fade', $mp3, 'mp3');
        $this->assertStringEndsWith('/fade.mp3', $store->audioPath('fade'));
        $this->assertCount(1, glob($store->audioDir().'/fade.*'));

        $store->deleteAudio('fade');
        $this->assertNull($store->audioPath('fade'));

        @unlink($src);
        @unlink($mp3);
    }

    public function test_uploading_a_sound_attaches_it_to_the_effect(): void
    {
        Livewire::test(ClipsAnimadosSfx::class)
            ->call('abrirAudio', 'slide')
            ->assertSet('audioEditingSlug', 'slide')
            ->set('audioUpload', \Illuminate\Http\UploadedFile::fake()->create('whoosh.mp3', 8, 'audio/mpeg'))
            ->call('uploadAudio')
            ->assertHasNoErrors()
            ->assertSet('audioEditingSlug', null);

        $this->assertNotNull($this->effects()->audioPath('slide'));
    }

    public function test_generating_a_sound_calls_elevenlabs_and_stores_the_mp3(): void
    {
        config(['services.elevenlabs.key' => 'test-key']);
        Http::fake(['api.elevenlabs.io/v1/sound-generation' => Http::response('ID3-fake-mp3-bytes')]);

        Livewire::test(ClipsAnimadosSfx::class)
            ->call('abrirAudio', 'seal-stamp')
            ->set('audioGenPrompt', 'a heavy wax stamp thud')
            ->call('gerarAudio')
            ->assertHasNoErrors()
            ->assertSet('audioEditingSlug', null);

        $path = $this->effects()->audioPath('seal-stamp');
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.mp3', $path);
        $this->assertSame('ID3-fake-mp3-bytes', file_get_contents($path));
    }

    public function test_render_job_attaches_effect_audio_to_the_layers_using_it(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'aud_').'.mp3';
        file_put_contents($src, 'ID3fake');
        $this->effects()->putAudio('kinetic-text', $src, 'mp3');

        $scenes = [
            ['layers' => [['type' => 'kinetic-text', 'text' => 'Hi'], ['type' => 'fade', 'text' => 'x']]],
        ];
        $method = new \ReflectionMethod(\App\Jobs\Clips\RenderJob::class, 'attachEffectAudio');
        $method->setAccessible(true);
        $out = $method->invoke(new \App\Jobs\Clips\RenderJob('p1'), $scenes, $this->effects());

        $this->assertSame($this->effects()->audioPath('kinetic-text'), $out[0]['layers'][0]['audioSrc']);
        $this->assertArrayNotHasKey('audioSrc', $out[0]['layers'][1]); // fade has no sound
        @unlink($src);
    }

    // ── intro effects (used at the start of a video) ─────────────────────

    public function test_marking_a_custom_effect_as_intro_lists_it_and_tells_the_planner(): void
    {
        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'logo-burst', 'display_name' => 'Logo burst',
            'description' => 'x', 'param_schema' => '{}', 'tsx' => 'export default () => null;',
            'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true,
        ]);
        $library = app(EffectLibrary::class);

        $this->assertNotContains('logo-burst', $library->introSlugs());

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarIntro', $effect->id());
        $this->assertTrue((bool) $effect->refresh()->get('intro'));
        $this->assertContains('logo-burst', $library->introSlugs());

        // The planner is told to open with it.
        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function sys(): string
            {
                return $this->systemPrompt('dense');
            }
        };
        $prompt = $planner->sys();
        $this->assertStringContainsString('OPENING', $prompt);
        $this->assertStringContainsString('logo-burst', $prompt);

        // Unmarking removes it again.
        Livewire::test(ClipsAnimadosSfx::class)->call('alternarIntro', $effect->id());
        $this->assertFalse((bool) $effect->refresh()->get('intro'));
        $this->assertNotContains('logo-burst', $library->introSlugs());
    }

    public function test_marking_a_builtin_as_intro_lists_it_and_drops_when_disallowed(): void
    {
        $library = app(EffectLibrary::class);

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarIntroBuiltin', 'seal-stamp');
        $this->assertTrue($library->builtinIsIntro('seal-stamp'));
        $this->assertContains('seal-stamp', $library->introSlugs());

        // A disallowed built-in is not offered as an intro even if flagged.
        $library->toggleBuiltin('seal-stamp');
        $this->assertNotContains('seal-stamp', $library->introSlugs());

        // Unknown slug is a no-op.
        $library->toggleIntroBuiltin('not-a-real-primitive');
        $this->assertNotContains('not-a-real-primitive', $library->introSlugs());
    }

    public function test_editing_an_effect_snapshots_the_previous_version(): void
    {
        Queue::fake();

        $effect = $this->makeEffect([
            'prompt' => 'a soft drop', 'slug' => 'soft-drop', 'display_name' => 'Soft drop',
            'description' => 'Soft drop', 'param_schema' => '{}', 'tsx' => 'export default () => "V1";',
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        Livewire::test(ClipsAnimadosSfx::class)
            ->call('editarSfx', $effect->id())
            ->set('sfxEditPrompt', 'make it bounce twice on landing')
            ->call('guardarSfxEdicao')
            ->assertHasNoErrors();

        $versions = $effect->refresh()->get('versions', []);
        $this->assertCount(1, $versions);
        $this->assertSame('export default () => "V1";', $versions[0]['tsx']);
        $this->assertSame('a soft drop', $versions[0]['prompt']);        // the prompt that made V1
        $this->assertSame('make it bounce twice on landing', $effect->prompt); // now the new prompt
        Queue::assertPushed(GenerateEffectJob::class);

        // The history panel renders its versions on the effect's detail page.
        Livewire::test(ClipsAnimadosSfx::class, ['key' => $effect->id()])
            ->call('abrirHistorico', $effect->id())
            ->assertSet('historyId', $effect->id())
            ->assertSee('Version history')
            ->assertSee('restore');
    }

    public function test_detail_page_resolves_a_custom_effect(): void
    {
        Queue::fake();
        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'neon-pulse', 'display_name' => 'Neon Pulse', 'description' => 'a neon glow',
            'param_schema' => '{}', 'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        Livewire::test(ClipsAnimadosSfx::class, ['key' => $effect->id()])
            ->assertSet('detailKey', $effect->id())
            ->assertSee('Neon Pulse')
            ->assertSee('Refine with AI')
            ->assertSee('Delete');
    }

    public function test_detail_page_resolves_a_builtin_by_slug(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimadosSfx::class, ['key' => 'seal-stamp'])
            ->assertSet('detailKey', 'seal-stamp')
            ->assertSee('built-in')
            ->assertSee('Customize with AI');
    }

    public function test_deleting_from_the_detail_page_redirects_to_the_gallery(): void
    {
        $effect = $this->makeEffect([
            'prompt' => 'x', 'slug' => 'neon-pulse', 'display_name' => 'Neon', 'description' => 'x',
            'param_schema' => '{}', 'tsx' => 'export default () => null;', 'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        Livewire::test(ClipsAnimadosSfx::class, ['key' => $effect->id()])
            ->call('apagarSfx', $effect->id())
            ->assertRedirect(route('clips-animados.sfx'));

        $this->assertNull($this->effects()->find($effect->id()));
    }

    public function test_reverting_restores_a_previous_version_and_re_renders_its_preview(): void
    {
        Queue::fake();

        $effect = $this->makeEffect([
            'prompt' => 'gold version', 'slug' => 'soft-drop', 'display_name' => 'Soft drop',
            'description' => 'now gold', 'param_schema' => '{ "g": true }', 'tsx' => 'export default () => "V2";',
            'sample_text' => 'Hi', 'sample_params' => [], 'status' => EffectRecord::STATUS_ACTIVE,
            'versions' => [[
                'prompt' => 'a soft drop', 'tsx' => 'export default () => "V1";', 'param_schema' => '{}',
                'display_name' => 'Soft drop', 'description' => 'Soft drop', 'sample_text' => 'Hi', 'sample_params' => [],
                'created_at' => now()->toIso8601String(),
            ]],
        ]);

        Livewire::test(ClipsAnimadosSfx::class)->call('reverterSfx', $effect->id(), 0);

        $effect->refresh();
        $this->assertSame('export default () => "V1";', $effect->tsx);   // restored
        $this->assertSame('active', $effect->status);
        // The just-replaced V2 is kept in history so the restore is reversible.
        $this->assertTrue(collect($effect->get('versions', []))->contains(fn ($v) => ($v['tsx'] ?? '') === 'export default () => "V2";'));
        // The restored component is written to the registry and the preview re-renders.
        $this->assertSame('export default () => "V1";', file_get_contents(app(EffectLibrary::class)->effectFile('soft-drop')));
        Queue::assertPushed(RenderEffectSampleJob::class, fn ($job) => $job->slug === 'soft-drop');
    }

    public function test_showreel_url_cache_buster_is_the_file_mtime_not_the_render_time(): void
    {
        Queue::fake();
        $path = app(EffectLibrary::class)->showreelPath();
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'fake mp4');
        touch($path, 1600000000); // a fixed mtime in the past
        clearstatcache(true, $path);

        // The video src is busted by the file's mtime (stable across renders), not
        // now() — so polling no longer reloads the reel and jumps the page.
        Livewire::test(ClipsAnimadosSfx::class)->assertSee('?v=1600000000', false);
    }

    public function test_uses_image_detects_image_based_effects(): void
    {
        $library = app(EffectLibrary::class);

        // Built-in image layer.
        $this->assertTrue($library->usesImage('image-reveal'));
        // Built-in text effect.
        $this->assertFalse($library->usesImage('kinetic-text'));

        // Custom effect whose component renders an <Img> → image-based.
        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'logo-assemble', 'display_name' => 'Logo', 'description' => 'assembles a logo',
            'param_schema' => '{ "src"?: "<image id>" }', 'status' => EffectRecord::STATUS_ACTIVE,
            'tsx' => 'import { Img } from "remotion";'."\nexport default ({ anim }) => <Img src={anim.params.src} />;",
        ]);
        // Custom effect with no image at all.
        $this->makeEffect([
            'prompt' => 'x', 'slug' => 'glow-pulse', 'display_name' => 'Glow', 'description' => 'a pulsing glow',
            'param_schema' => '{ "intensity"?: number }', 'status' => EffectRecord::STATUS_ACTIVE,
            'tsx' => 'export default () => null;',
        ]);

        $this->assertTrue($library->usesImage('logo-assemble'));
        $this->assertFalse($library->usesImage('glow-pulse'));
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

    /**
     * Regression: the clip service bindings must read clips.driver at RESOLVE time,
     * not at register() time. On the deployed image there is no .env and the
     * SettingsOverlay flips clips.driver to 'api' during boot() — after every
     * provider's register(). An eager binding would freeze to the fake stub and
     * produce "FAKE-VIDEO" previews/renders in production.
     */
    public function test_remotion_renderer_honours_driver_changed_after_register(): void
    {
        config(['contentmachine.clips.driver' => 'fake']);
        $this->assertInstanceOf(FakeRemotionRenderer::class, app(RemotionRenderer::class));

        config(['contentmachine.clips.driver' => 'api']);
        $this->assertInstanceOf(CliRemotionRenderer::class, app(RemotionRenderer::class));
    }

    public function test_export_then_import_recreates_the_effect_with_a_fresh_unique_slug(): void
    {
        Queue::fake();

        $original = $this->makeEffect([
            'prompt' => 'glitch', 'slug' => 'glitch-flicker', 'display_name' => 'Glitch',
            'description' => 'A glitch flicker', 'tsx' => 'export default () => null;',
            'sample_text' => 'HELLO', 'sample_params' => ['intensity' => 3],
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $port = app(EffectPortability::class);
        $payload = $port->export($original->id());

        $this->assertSame('brand-machine/sfx', $payload['type']);
        $this->assertCount(1, $payload['effects']);
        $this->assertStringContainsString('export default', $payload['effects'][0]['tsx']);
        $this->assertSame(['intensity' => 3], $payload['effects'][0]['sample_params']);

        // Import the same payload back → a NEW record with a collision-free slug.
        $this->assertSame(1, $port->import($payload));

        $all = $this->effects()->all();
        $this->assertCount(2, $all);
        $imported = $all->first(fn (EffectRecord $r) => $r->id() !== $original->id());
        $this->assertNotSame('glitch-flicker', $imported->slug);
        $this->assertSame('Glitch', $imported->display_name);
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $imported->status);

        Queue::assertPushed(RenderEffectSampleJob::class);
    }

    /**
     * A custom effect named after a built-in IS an override of it (that's what the
     * studio's "Customize" produces). Importing must keep the name, or the built-in
     * quietly goes back to its default and the import leaves a duplicate beside it.
     */
    public function test_importing_an_override_of_a_builtin_keeps_its_slug(): void
    {
        Queue::fake();

        $payload = [
            'type' => 'brand-machine/sfx',
            'version' => 1,
            'effects' => [[
                'slug' => 'diagram', 'display_name' => 'Diagram (custom)', 'description' => 'My diagram',
                'prompt' => 'a nicer diagram', 'tsx' => 'export default () => null;',
                'sample_text' => '', 'sample_params' => [],
            ]],
        ];

        $this->assertSame(1, app(EffectPortability::class)->import($payload));

        $imported = $this->effects()->all()->sole();
        $this->assertSame('diagram', $imported->slug);
        // Still overriding: the built-in registry resolves this slug to the record.
        $this->assertContains('diagram', app(EffectLibrary::class)->activeSlugs());

        // A SECOND import can't take the name — that one gets a fresh slug.
        app(EffectPortability::class)->import($payload);
        $slugs = $this->effects()->all()->pluck('slug')->sort()->values()->all();
        $this->assertSame(['diagram', 'diagram-2'], $slugs);
    }

    public function test_import_rejects_a_file_that_is_not_an_sfx_export(): void
    {
        $this->expectException(\RuntimeException::class);
        app(EffectPortability::class)->import(['type' => 'something-else', 'effects' => []]);
    }

    public function test_export_and_import_a_builtin_reapplies_its_intro(): void
    {
        $library = app(EffectLibrary::class);
        $library->toggleIntroBuiltin('fade'); // a built-in customization worth carrying
        $this->assertTrue($library->builtinIsIntro('fade'));

        $port = app(EffectPortability::class);
        $payload = $port->export('fade');

        $this->assertTrue($payload['effects'][0]['builtin']);
        $this->assertTrue($payload['effects'][0]['intro']);
        $this->assertArrayNotHasKey('tsx', $payload['effects'][0]); // built-ins carry no source

        // Turn it off, then import → the built-in reference re-applies it (non-destructive).
        $library->toggleIntroBuiltin('fade');
        $this->assertFalse($library->builtinIsIntro('fade'));

        $this->assertSame(1, $port->import($payload));
        $this->assertTrue(app(EffectLibrary::class)->builtinIsIntro('fade'));
    }

    public function test_export_all_includes_customized_builtins(): void
    {
        app(EffectLibrary::class)->toggleIntroBuiltin('fade');

        $payload = app(EffectPortability::class)->export('all');
        $slugs = array_column($payload['effects'], 'slug');

        $this->assertContains('fade', $slugs);
    }
}
