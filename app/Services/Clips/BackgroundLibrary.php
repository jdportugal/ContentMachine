<?php

namespace App\Services\Clips;

use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\EffectRecord;
use App\Services\DesignSystem\DesignSystemRepository;
use Illuminate\Support\Collection;

/**
 * The BACKGROUNDS library: the full-frame backdrop a clip renders behind its
 * scenes. Mirrors EffectLibrary (SFX), but a background is a whole-clip backdrop,
 * not a scene layer — so it never enters the planner's layer vocabulary.
 *
 * The vault (BackgroundStore, per project) is the source of truth. For `code`
 * backgrounds the filesystem — one remotion/src/backgrounds/<slug>.tsx per active
 * code background plus a generated index.ts — is a render artifact rebuilt by
 * syncFilesystem(). `video` backgrounds are plain mp4 files, staged into Remotion
 * at render time. Because the folder is global, callers re-sync for the active
 * project before rendering (see RenderJob).
 */
class BackgroundLibrary
{
    public function __construct(private DesignSystemRepository $design, private BackgroundStore $store) {}

    // ── planner vocabulary ───────────────────────────────────────────────

    /** @return Collection<int,EffectRecord> */
    public function active(): Collection
    {
        return $this->store->active();
    }

    /** @return Collection<int,EffectRecord> active AND allowed — the ones the planner may pick */
    public function enabled(): Collection
    {
        return $this->store->enabled();
    }

    /**
     * Resolve a clip's background choice to a concrete slug (or null = none).
     * $choice is 'auto' | 'none' | a specific enabled slug. On 'auto' the planner's
     * suggestion is honoured if it's a currently-enabled background, else a random
     * enabled one, else none.
     */
    public function resolveChoice(string $choice, ?string $plannerPick = null): ?string
    {
        $choice = trim($choice);
        if ($choice === 'none') {
            return null;
        }

        $enabledSlugs = $this->enabled()->pluck('slug')->all();

        if ($choice !== '' && $choice !== 'auto') {
            return in_array($choice, $enabledSlugs, true) ? $choice : null; // a manual pick, disabled since
        }

        // auto: honour the planner's pick if enabled, else a random enabled one, else none.
        if ($plannerPick && in_array($plannerPick, $enabledSlugs, true)) {
            return $plannerPick;
        }

        return empty($enabledSlugs) ? null : $enabledSlugs[array_rand($enabledSlugs)];
    }

    /** Prompt block listing the enabled backgrounds so the planner can pick one, or '' if none. */
    public function promptBlock(): string
    {
        $backgrounds = $this->enabled();
        if ($backgrounds->isEmpty()) {
            return '';
        }
        $lines = $backgrounds->map(fn (EffectRecord $b) => "- {$b->slug}: {$b->description}")->implode("\n");

        return "\n\n=== CLIP BACKGROUNDS (a full-screen animated backdrop behind ALL scenes — pick the ONE that best fits the clip's topic and mood) ===\n"
            .$lines
            ."\nReturn your choice as a top-level \"background\" key = the chosen slug (or omit it for the default themed backdrop). This is NOT a scene layer — never put it in a scene's layers.";
    }

    // ── filesystem sync (code backgrounds only) ──────────────────────────

    public function backgroundsDir(): string
    {
        return rtrim((string) config('contentmachine.clips.remotion_path'), '/').'/src/backgrounds';
    }

    public function backgroundFile(string $slug): string
    {
        return $this->backgroundsDir().'/'.$slug.'.tsx';
    }

    /** Write a code background's source to disk, mark it active, and rebuild index.ts. */
    public function promote(EffectRecord $background): void
    {
        file_put_contents($this->backgroundFile($background->slug), $background->tsx);
        $background->update(['status' => EffectRecord::STATUS_ACTIVE, 'error' => null]);
        $this->syncFilesystem();
    }

    /** Remove a background: its vault record (+ any video/preview file), source file, then rebuild. */
    public function remove(EffectRecord $background): void
    {
        if ($background->kind !== BackgroundStore::KIND_VIDEO) {
            @unlink($this->backgroundFile($background->slug));
        }
        if ($background->preview_path) {
            @unlink($background->preview_path);
        }
        $background->delete(); // also unlinks the vault mp4 for video backgrounds
        $this->syncFilesystem();
    }

    /**
     * Rebuild remotion/src/backgrounds/ from the active CODE backgrounds: write each
     * <slug>.tsx (idempotent), drop orphan files, regenerate index.ts. Video
     * backgrounds have no source file and are skipped here. Safe to run anytime.
     */
    public function syncFilesystem(): void
    {
        $dir = $this->backgroundsDir();
        @mkdir($dir, 0777, true);

        $code = $this->active()->filter(fn (EffectRecord $b) => $b->kind !== BackgroundStore::KIND_VIDEO && (string) $b->tsx !== '')->values();
        $keep = [];

        foreach ($code as $bg) {
            $keep[] = $bg->slug;
            $file = $this->backgroundFile($bg->slug);
            if (! is_file($file) || file_get_contents($file) !== $bg->tsx) {
                file_put_contents($file, $bg->tsx);
            }
        }

        foreach (glob($dir.'/*.tsx') ?: [] as $file) {
            if (! in_array(pathinfo($file, PATHINFO_FILENAME), $keep, true)) {
                @unlink($file);
            }
        }

        file_put_contents($dir.'/index.ts', $this->renderIndex($code));
    }

    /** @param  Collection<int,EffectRecord>  $code */
    private function renderIndex(Collection $code): string
    {
        $imports = $code->map(fn (EffectRecord $b) => "import {$this->ident($b->slug)} from \"./{$b->slug}\";")->implode("\n");
        $entries = $code->map(fn (EffectRecord $b) => "  \"{$b->slug}\": {$this->ident($b->slug)},")->implode("\n");

        $header = "import type React from \"react\";\nimport type { PrimitiveProps } from \"../primitives\";";
        $note = "// AUTO-GENERATED by App\\Services\\Clips\\BackgroundLibrary::syncFilesystem().\n// Do NOT edit by hand — it is rebuilt from the active project's background records.";
        $body = $entries === '' ? '{}' : "{\n{$entries}\n}";

        return "{$header}\n".($imports !== '' ? $imports."\n" : '')."\n{$note}\nexport const CUSTOM_BACKGROUNDS: Record<string, React.FC<PrimitiveProps>> = {$body};\n";
    }

    /** Slug → a valid JS import identifier. */
    private function ident(string $slug): string
    {
        return 'Bg_'.preg_replace('/[^a-z0-9]+/i', '_', $slug);
    }

    // ── sample plan (for code-background previews) ───────────────────────

    /** A flat ClipComposition plan that renders only this code background as the backdrop. */
    public function samplePlan(string $slug): array
    {
        $c = config('contentmachine.clips');
        $plan = [
            'duration' => 3.0,
            'width' => (int) $c['width'],
            'height' => (int) $c['height'],
            'fps' => (int) $c['fps'],
            'mode' => 'dense',
            'transparent' => false,
            'background' => ['kind' => 'code', 'slug' => $slug],
            'animations' => [],
        ];
        if ($theme = $this->design->readTokens()) {
            $plan['theme'] = $theme;
        }

        return $plan;
    }

    // ── previews (cached, keyed by the active design system for code) ─────

    public function designHash(): string
    {
        return substr(md5($this->design->read().json_encode($this->design->readTokens())), 0, 8);
    }

    public function previewDir(): string
    {
        return (string) config('contentmachine.clips.backgrounds_previews', storage_path('app/clips/backgrounds'));
    }

    /** Cache path for a code background's preview render (design-system aware). */
    public function previewPath(string $slug): string
    {
        return $this->previewDir().'/'.$slug.'-'.$this->designHash().'.mp4';
    }

    /** The servable preview file for a background, or null if not ready. */
    public function previewFileFor(EffectRecord $background): ?string
    {
        if ($background->kind === BackgroundStore::KIND_VIDEO) {
            $file = $this->store->videoPath($background->id());

            return is_file($file) ? $file : null;
        }
        $file = $this->previewPath($background->slug);

        return is_file($file) ? $file : null;
    }

    // ── reel (one video cycling through every background, name centered) ──

    /** Every active background to feature in the reel, in order. */
    public function reelEntries(): array
    {
        return $this->active()->map(fn (EffectRecord $b) => [
            'slug' => $b->slug,
            'label' => $b->display_name ?: \Illuminate\Support\Str::title(str_replace('-', ' ', $b->slug)),
            'kind' => $b->kind === BackgroundStore::KIND_VIDEO ? 'video' : 'code',
            'src' => $b->kind === BackgroundStore::KIND_VIDEO ? $this->store->videoPath($b->id()) : null,
        ])->values()->all();
    }

    /** Props for the BackgroundReel composition (video srcs are staged by the renderer). */
    public function reelProps(): array
    {
        $c = config('contentmachine.clips');
        $props = [
            'entries' => $this->reelEntries(),
            'perSeconds' => 2.4,
            'width' => (int) $c['width'],
            'height' => (int) $c['height'],
            'fps' => (int) $c['fps'],
        ];
        if ($theme = $this->design->readTokens()) {
            $props['theme'] = $theme;
        }

        return $props;
    }

    /** Cache key: invalidates when the design system OR the featured background set changes. */
    private function reelHash(): string
    {
        $sig = array_map(fn ($e) => $e['slug'].':'.$e['kind'].':'.$e['label'], $this->reelEntries());

        return substr(md5($this->designHash().'|'.implode(',', $sig)), 0, 8);
    }

    public function reelPath(): string
    {
        return $this->previewDir().'/reel-'.$this->reelHash().'.mp4';
    }

    public function reelExists(): bool
    {
        return is_file($this->reelPath());
    }
}
