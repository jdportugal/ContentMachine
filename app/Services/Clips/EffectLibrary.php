<?php

namespace App\Services\Clips;

use App\Models\ClipEffect;
use App\Models\DisabledBuiltinEffect;
use App\Services\DesignSystem\DesignSystemRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The SFX library: bridges the custom-effect DB rows with the Remotion
 * filesystem, the planner vocabulary and the showcase previews.
 *
 * The DB (clip_effects) is the source of truth. The filesystem — one
 * remotion/src/effects/<slug>.tsx per active effect plus a generated index.ts —
 * is a render artifact rebuilt from the active rows by syncFilesystem().
 */
class EffectLibrary
{
    /** Built-in primitives + a representative sample so the showcase can render each. */
    public const BUILTIN_SAMPLES = [
        'kinetic-text' => ['label' => 'Kinetic text', 'text' => 'Kinetic text in motion', 'params' => []],
        'fade' => ['label' => 'Fade', 'text' => 'Fade in', 'params' => []],
        'slide' => ['label' => 'Slide', 'text' => 'Slide in', 'params' => ['direction' => 'left']],
        'scale' => ['label' => 'Scale', 'text' => 'Scale up', 'params' => []],
        'highlight' => ['label' => 'Highlight', 'text' => 'Highlighted', 'params' => []],
        'fleuron-draw' => ['label' => 'Fleuron', 'text' => '', 'params' => []],
        'seal-stamp' => ['label' => 'Seal stamp', 'text' => 'IATECA', 'params' => []],
        'underline-sweep' => ['label' => 'Underline sweep', 'text' => 'Underlined', 'params' => []],
        'ambient' => ['label' => 'Ambient', 'text' => '', 'params' => []],
        'count-up' => ['label' => 'Count up', 'text' => '', 'params' => ['from' => 0, 'to' => 95, 'suffix' => '%']],
        'image-reveal' => ['label' => 'Image reveal', 'text' => '', 'params' => ['caption' => 'Image reveal', 'variant' => 'rise']],
        'timeline' => ['label' => 'Timeline', 'text' => '', 'params' => ['caption' => 'Evolution', 'items' => [['label' => '2019'], ['label' => '2023'], ['label' => '2026', 'highlight' => true]]]],
        'bar-chart' => ['label' => 'Bar chart', 'text' => '', 'params' => ['title' => 'Market share', 'unit' => '%', 'bars' => [['label' => 'A', 'value' => 45], ['label' => 'B', 'value' => 30, 'highlight' => true], ['label' => 'C', 'value' => 25]]]],
        'line-chart' => ['label' => 'Line chart', 'text' => '', 'params' => ['title' => 'Growth', 'series' => [['label' => 'Users', 'points' => [10, 20, 45, 80]]]]],
        'pie-chart' => ['label' => 'Pie chart', 'text' => '', 'params' => ['title' => 'Split', 'slices' => [['label' => 'A', 'value' => 50], ['label' => 'B', 'value' => 30], ['label' => 'C', 'value' => 20, 'highlight' => true]]]],
        'scatter-chart' => ['label' => 'Scatter chart', 'text' => '', 'params' => ['title' => 'Price vs speed', 'xLabel' => 'Price', 'yLabel' => 'Speed', 'points' => [['label' => 'A', 'x' => 2, 'y' => 8], ['label' => 'B', 'x' => 6, 'y' => 4, 'highlight' => true]]]],
        'comparison' => ['label' => 'Comparison', 'text' => '', 'params' => ['left' => ['title' => 'Before', 'points' => ['Slow', 'Manual']], 'right' => ['title' => 'After', 'points' => ['Fast', 'Automatic']]]],
        'bullet-list' => ['label' => 'Bullet list', 'text' => '', 'params' => ['title' => 'Steps', 'items' => ['Install', 'Configure', 'Run']]],
        'card' => ['label' => 'Card', 'text' => '', 'params' => ['title' => 'Definition', 'lines' => ['A reusable brand effect.']]],
        'terminal' => ['label' => 'Terminal', 'text' => '', 'params' => ['lines' => ['$ npm run build', '✓ done']]],
        'diagram' => ['label' => 'Diagram', 'text' => '', 'params' => ['title' => 'Flow', 'layout' => 'vertical', 'nodes' => [['label' => 'Input'], ['label' => 'Process'], ['label' => 'Output']]]],
    ];

    private const CANDIDATE_STUB = <<<'TSX'
import React from "react";
import type { PrimitiveProps } from "../primitives";

// Candidate slot (reset by EffectLibrary). See effects/_candidate.tsx notes.
const Candidate: React.FC<PrimitiveProps> = () => null;
export default Candidate;
TSX;

    public function __construct(private DesignSystemRepository $design) {}

    // ── planner vocabulary ───────────────────────────────────────────────

    /** @return Collection<int,ClipEffect> */
    public function active(): Collection
    {
        // Tolerate the table not existing yet (e.g. planner prompt built in a
        // unit test with no migrations, or before the first migrate).
        try {
            if (! Schema::hasTable('clip_effects')) {
                return collect();
            }
        } catch (\Throwable) {
            return collect();
        }

        return ClipEffect::where('status', ClipEffect::STATUS_ACTIVE)->orderBy('display_name')->get();
    }

    /**
     * Active AND allowed effects — the ones the planner may use. Disallowed
     * effects stay in active() (registered/previewable) but drop out here.
     *
     * @return Collection<int,ClipEffect>
     */
    public function enabled(): Collection
    {
        return $this->active()->where('enabled', true)->values();
    }

    /** Allowed custom slugs, usable by the planner as scene-layer `type` values. @return string[] */
    public function activeSlugs(): array
    {
        return $this->enabled()->pluck('slug')->all();
    }

    public function isBuiltin(string $slug): bool
    {
        return array_key_exists($slug, self::BUILTIN_SAMPLES);
    }

    /** Built-in slugs the user has disallowed for the planner. @return string[] */
    public function disabledBuiltins(): array
    {
        try {
            if (! Schema::hasTable('disabled_builtin_effects')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return DisabledBuiltinEffect::pluck('slug')->all();
    }

    public function builtinAllowed(string $slug): bool
    {
        return ! in_array($slug, $this->disabledBuiltins(), true);
    }

    /** Allow/disallow a built-in for the planner (no-op for non-built-in slugs). */
    public function toggleBuiltin(string $slug): void
    {
        if (! $this->isBuiltin($slug)) {
            return;
        }
        $row = DisabledBuiltinEffect::where('slug', $slug)->first();
        if ($row) {
            $row->delete();
        } else {
            DisabledBuiltinEffect::create(['slug' => $slug]);
        }
    }

    /** Prompt block describing the allowed custom effects (name + schema), or '' if none. */
    public function promptBlock(): string
    {
        $effects = $this->enabled();
        if ($effects->isEmpty()) {
            return '';
        }
        $lines = $effects->map(fn (ClipEffect $e) => "- {$e->slug}: {$e->description} — params: {$e->param_schema}")->implode("\n");

        return "\n\n=== CUSTOM BRAND EFFECTS (generated, follow the design system — use like any other layer type) ===\n".$lines;
    }

    // ── filesystem sync ──────────────────────────────────────────────────

    public function effectsDir(): string
    {
        return rtrim((string) config('contentmachine.clips.remotion_path'), '/').'/src/effects';
    }

    public function effectFile(string $slug): string
    {
        return $this->effectsDir().'/'.$slug.'.tsx';
    }

    public function candidatePath(): string
    {
        return $this->effectsDir().'/_candidate.tsx';
    }

    public function writeCandidate(string $tsx): void
    {
        file_put_contents($this->candidatePath(), $tsx);
    }

    public function resetCandidate(): void
    {
        file_put_contents($this->candidatePath(), self::CANDIDATE_STUB);
    }

    /** Write an effect's source to disk, mark it active, and rebuild index.ts. */
    public function promote(ClipEffect $effect): void
    {
        file_put_contents($this->effectFile($effect->slug), $effect->tsx);
        $effect->update(['status' => ClipEffect::STATUS_ACTIVE, 'error' => null]);
        $this->resetCandidate();
        $this->syncFilesystem();
    }

    /** Delete an effect: its DB row, its source file, its preview, then rebuild index.ts. */
    public function remove(ClipEffect $effect): void
    {
        @unlink($this->effectFile($effect->slug));
        if ($effect->preview_path) {
            @unlink($effect->preview_path);
        }
        $effect->delete();
        $this->syncFilesystem();
    }

    /**
     * Rebuild remotion/src/effects/ from the active rows: write each active
     * effect's <slug>.tsx (idempotent), drop orphan files, regenerate index.ts.
     * Safe to run anytime — reconstructs the folder from the DB.
     */
    public function syncFilesystem(): void
    {
        $dir = $this->effectsDir();
        @mkdir($dir, 0777, true);

        $active = $this->active();
        $keep = ['_candidate']; // never treated as a custom effect file

        foreach ($active as $effect) {
            $keep[] = $effect->slug;
            $file = $this->effectFile($effect->slug);
            if (! is_file($file) || file_get_contents($file) !== $effect->tsx) {
                file_put_contents($file, $effect->tsx);
            }
        }

        // Remove orphan <slug>.tsx files (deleted/failed effects).
        foreach (glob($dir.'/*.tsx') ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (! in_array($name, $keep, true)) {
                @unlink($file);
            }
        }

        file_put_contents($dir.'/index.ts', $this->renderIndex($active));
    }

    /** @param  Collection<int,ClipEffect>  $active */
    private function renderIndex(Collection $active): string
    {
        $imports = $active->map(fn (ClipEffect $e) => "import {$this->ident($e->slug)} from \"./{$e->slug}\";")->implode("\n");
        $entries = $active->map(fn (ClipEffect $e) => "  \"{$e->slug}\": {$this->ident($e->slug)},")->implode("\n");

        $header = "import type React from \"react\";\nimport type { PrimitiveProps } from \"../primitives\";";
        $note = "// AUTO-GENERATED by App\\Services\\Clips\\EffectLibrary::syncFilesystem().\n// Do NOT edit by hand — it is rebuilt from the active `clip_effects` rows.";
        $body = $entries === '' ? '{}' : "{\n{$entries}\n}";

        return "{$header}\n".($imports !== '' ? $imports."\n" : '')."\n{$note}\nexport const CUSTOM_PRIMITIVES: Record<string, React.FC<PrimitiveProps>> = {$body};\n";
    }

    /** Slug → a valid JS import identifier (kebab-case → Effect_snake_case). */
    private function ident(string $slug): string
    {
        return 'Effect_'.preg_replace('/[^a-z0-9]+/i', '_', $slug);
    }

    // ── sample plans (for previews / test-renders) ───────────────────────

    /** Flat ClipComposition plan rendering a single known effect (built-in or active). */
    public function samplePlan(string $slug, ?string $text, array $params): array
    {
        $c = config('contentmachine.clips');
        $dur = 2.5;
        $plan = [
            'duration' => $dur,
            'width' => (int) $c['width'],
            'height' => (int) $c['height'],
            'fps' => (int) $c['fps'],
            'mode' => 'dense',
            'transparent' => false,
            'animations' => [[
                'start' => 0,
                'end' => $dur,
                'primitive' => $slug,
                'text' => $text ?? '',
                'params' => $params ?: (object) [],
            ]],
        ];
        if ($theme = $this->design->readTokens()) {
            $plan['theme'] = $theme;
        }

        return $plan;
    }

    /** SampleEffect props for the isolated candidate test-render. */
    public function candidateSampleProps(?string $text, array $params): array
    {
        $c = config('contentmachine.clips');
        $props = [
            'text' => ($text ?? '') !== '' ? $text : 'SAMPLE',
            'params' => $params ?: (object) [],
            'duration' => 2.5,
            'width' => (int) $c['width'],
            'height' => (int) $c['height'],
            'fps' => (int) $c['fps'],
            'transparent' => false,
        ];
        if ($theme = $this->design->readTokens()) {
            $props['theme'] = $theme;
        }

        return $props;
    }

    // ── previews (cached, keyed by the active design system) ─────────────

    /** Preview cache invalidates when the design system changes. */
    public function designHash(): string
    {
        return substr(md5($this->design->read().json_encode($this->design->readTokens())), 0, 8);
    }

    public function previewDir(): string
    {
        return (string) config('contentmachine.clips.effects_previews', storage_path('app/clips/effects'));
    }

    public function previewPath(string $slug): string
    {
        return $this->previewDir().'/'.$slug.'-'.$this->designHash().'.mp4';
    }

    public function previewExists(string $slug): bool
    {
        return is_file($this->previewPath($slug));
    }
}
