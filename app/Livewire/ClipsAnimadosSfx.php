<?php

namespace App\Livewire;

use App\Jobs\Clips\GenerateEffectJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Jobs\Clips\RenderShowreelJob;
use App\Services\Clips\Api\ElevenLabsSfxService;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use App\Services\Projects\ProjectContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The SFX / VFX studio — its own page. Create, refine, allow/disallow and mark
 * effects as intro (the planner opens the video with an intro effect), plus the
 * per-effect sound. Built-ins and custom effects share the same store.
 */
#[Layout('components.layouts.app')]
#[Title('SFX Studio')]
class ClipsAnimadosSfx extends Component
{
    use WithFileUploads;

    /** How many past versions of an effect to keep for "go back". */
    private const MAX_VERSIONS = 10;

    /** Effect whose version history panel is open (its id), or null. */
    public ?string $historyId = null;

    /** When set, the page shows the detail view for one effect (custom id or built-in slug). */
    public ?string $detailKey = null;

    public string $sfxPrompt = '';

    public ?string $editingSfxId = null;

    public string $sfxEditPrompt = '';

    /** Set while creating an override for a built-in effect (the built-in's slug). */
    public ?string $sfxOverrideSlug = null;

    // Per-effect sound editor (keyed by the effect's slug; custom OR built-in).
    public ?string $audioEditingSlug = null;

    public $audioUpload = null;

    public string $audioGenPrompt = '';

    /** Import: an uploaded Brand Machine SFX export file. */
    public $importFile = null;

    public function mount(?string $key = null): void
    {
        $this->detailKey = $key;
        $this->ensurePreviews();
    }

    /** Import effects from an uploaded Brand Machine SFX export file. */
    public function importarSfx(\App\Services\Clips\EffectPortability $port): void
    {
        $this->validate(
            ['importFile' => 'required|file|max:51200'],
            ['importFile.required' => 'Choose an export file first.'],
        );

        try {
            $payload = json_decode((string) file_get_contents($this->importFile->getRealPath()), true);
            if (! is_array($payload)) {
                throw new \RuntimeException('The file is not valid JSON.');
            }
            $n = $port->import($payload);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Import failed: '.$e->getMessage(), type: 'erro');

            return;
        }

        $this->reset('importFile');

        if ($n === 0) {
            $this->dispatch('toast', message: 'No effects found in that file.', type: 'erro');

            return;
        }

        $this->dispatch('toast', message: $n.' effect'.($n === 1 ? '' : 's').' imported — rendering previews…', type: 'ok');
    }

    /**
     * Resolve the effect shown on the detail page. Handles a custom effect (by id),
     * a built-in (by slug), and a built-in override (a record whose slug is a
     * built-in). Null when there is no detail view or the key doesn't resolve.
     *
     * @return array<string,mixed>|null
     */
    public function getDetailProperty(): ?array
    {
        if ($this->detailKey === null) {
            return null;
        }
        $library = app(EffectLibrary::class);

        $rec = $this->effects()->find($this->detailKey);
        if ($rec && ! $library->isBuiltin($rec->slug)) {
            return [
                'kind' => 'custom', 'id' => $rec->id(), 'slug' => $rec->slug, 'label' => $rec->display_name,
                'status' => $rec->status, 'enabled' => (bool) $rec->enabled, 'intro' => (bool) $rec->get('intro', false),
                'description' => (string) $rec->description, 'error' => $rec->error,
                'versions' => count($rec->get('versions', [])), 'record' => $rec,
            ];
        }
        if ($library->isBuiltin($this->detailKey)) {
            $override = $this->effects()->all()->firstWhere('slug', $this->detailKey);

            return [
                'kind' => 'builtin', 'slug' => $this->detailKey,
                'label' => EffectLibrary::BUILTIN_SAMPLES[$this->detailKey]['label'] ?? $this->detailKey,
                'allowed' => $library->builtinAllowed($this->detailKey), 'intro' => $library->builtinIsIntro($this->detailKey),
                'override' => $override?->status, 'overrideId' => $override?->id(),
                'versions' => $override ? count($override->get('versions', [])) : 0, 'record' => $override,
            ];
        }

        return null;
    }

    /** The vault-backed SFX store for the active project. */
    private function effects(): EffectStore
    {
        return app(EffectStore::class);
    }

    public function editarSfx(string $id): void
    {
        $effect = $this->effects()->find($id);
        if (! $effect || ! $effect->isActive()) {
            return; // only live effects can be refined
        }
        $this->editingSfxId = $effect->id();
        $this->sfxEditPrompt = (string) $effect->prompt;
        $this->resetValidation();
    }

    /** Open the refine panel for a built-in: edit its override, or start a new one. */
    public function editarBuiltin(string $slug): void
    {
        if (! app(EffectLibrary::class)->isBuiltin($slug)) {
            return;
        }
        $this->resetValidation();
        $override = $this->effects()->all()->firstWhere('slug', $slug);
        if ($override && $override->isActive()) {
            $this->editingSfxId = $override->id();
            $this->sfxOverrideSlug = null;
            $this->sfxEditPrompt = (string) $override->prompt;
        } else {
            $this->editingSfxId = null;
            $this->sfxOverrideSlug = $slug;
            $this->sfxEditPrompt = '';
        }
    }

    /** Revert a built-in to its default by deleting its override. */
    public function resetBuiltin(string $slug, EffectLibrary $library): void
    {
        if ($override = $this->effects()->all()->firstWhere('slug', $slug)) {
            $library->remove($override);
        }
    }

    public function cancelarSfxEdicao(): void
    {
        $this->reset(['editingSfxId', 'sfxEditPrompt', 'sfxOverrideSlug']);
        $this->resetValidation();
    }

    public function guardarSfxEdicao(): void
    {
        $this->validate(
            ['sfxEditPrompt' => 'required|string|min:8|max:600'],
            [
                'sfxEditPrompt.required' => 'Describe the change you want.',
                'sfxEditPrompt.min' => 'Give a bit more detail (at least 8 characters).',
            ],
        );

        if ($this->editingSfxId) {
            // Refine an existing effect (custom or a built-in override). Snapshot the
            // current committed version first so it can be restored ("go back").
            $effect = $this->effects()->find($this->editingSfxId);
            if ($effect && $effect->isActive()) {
                $effect->update([
                    'versions' => $this->pushVersion($effect->get('versions', []), $this->snapshotVersion($effect)),
                    'prompt' => trim($this->sfxEditPrompt),
                    'status' => EffectRecord::STATUS_UPDATING,
                    'error' => null,
                ]);
                GenerateEffectJob::dispatch($effect->id(), isEdit: true);
            }
        } elseif ($this->sfxOverrideSlug && app(EffectLibrary::class)->isBuiltin($this->sfxOverrideSlug)) {
            // Create an override of a built-in: same slug replaces it in the registry.
            $slug = $this->sfxOverrideSlug;
            $effect = $this->effects()->create([
                'prompt' => trim($this->sfxEditPrompt),
                'slug' => $slug,
                'display_name' => Str::title(str_replace('-', ' ', $slug)).' (custom)',
                'description' => "Override of the built-in {$slug}.",
                'param_schema' => '{}',
                'tsx' => '',
                'status' => EffectRecord::STATUS_PENDING,
            ]);
            GenerateEffectJob::dispatch($effect->id());
        }

        $this->reset(['editingSfxId', 'sfxEditPrompt', 'sfxOverrideSlug']);
    }

    // ── version history (go back to a previous generation) ───────────────

    /** A snapshot of the effect's current committed look/behaviour. @return array<string,mixed> */
    private function snapshotVersion(EffectRecord $effect): array
    {
        return [
            'prompt' => (string) $effect->prompt,
            'tsx' => (string) $effect->tsx,
            'param_schema' => $effect->param_schema,
            'display_name' => $effect->display_name,
            'description' => $effect->description,
            'sample_text' => $effect->sample_text,
            'sample_params' => $effect->sample_params,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Append a snapshot, keeping the most recent MAX_VERSIONS. Skips a no-op
     * snapshot (same tsx as the last one) so repeated failed edits don't pile up.
     *
     * @param  array<int,array<string,mixed>>  $versions
     * @param  array<string,mixed>  $snapshot
     * @return array<int,array<string,mixed>>
     */
    private function pushVersion(array $versions, array $snapshot): array
    {
        $versions = array_values($versions);
        $last = end($versions) ?: null;
        if ($last && ($last['tsx'] ?? null) === $snapshot['tsx']) {
            return $versions;
        }
        $versions[] = $snapshot;

        return array_slice($versions, -self::MAX_VERSIONS);
    }

    public function abrirHistorico(string $id): void
    {
        $this->historyId = $id;
    }

    /** The effect whose history panel is open (custom OR built-in override), or null. */
    public function getHistoryEffectProperty(): ?EffectRecord
    {
        return $this->historyId ? $this->effects()->find($this->historyId) : null;
    }

    public function fecharHistorico(): void
    {
        $this->historyId = null;
    }

    /** Restore a past version of an effect: write its component and re-render the preview. */
    public function reverterSfx(string $id, int $index): void
    {
        $effect = $this->effects()->find($id);
        if (! $effect || ! $effect->isActive()) {
            return; // only a live effect can be restored in place
        }
        $versions = $effect->get('versions', []);
        if (! is_array($versions) || ! isset($versions[$index]) || ! is_array($versions[$index])) {
            return;
        }
        $target = $versions[$index];

        $library = app(EffectLibrary::class);
        // Snapshot the current state too, so the restore is itself reversible.
        $effect->update([
            'prompt' => $target['prompt'] ?? $effect->prompt,
            'tsx' => $target['tsx'] ?? $effect->tsx,
            'param_schema' => $target['param_schema'] ?? $effect->param_schema,
            'display_name' => $target['display_name'] ?? $effect->display_name,
            'description' => $target['description'] ?? $effect->description,
            'sample_text' => $target['sample_text'] ?? $effect->sample_text,
            'sample_params' => $target['sample_params'] ?? $effect->sample_params,
            'versions' => $this->pushVersion($versions, $this->snapshotVersion($effect)),
            'status' => EffectRecord::STATUS_ACTIVE,
            'error' => null,
        ]);
        $library->promote($effect); // write the restored component + rebuild the registry
        @unlink($library->previewPath($effect->slug)); // drop the stale preview so it re-renders
        RenderEffectSampleJob::dispatch($effect->slug, $effect->sample_text, $effect->sample_params ?? []);

        $this->historyId = null;
    }

    /** Dispatch a cached-preview render for any built-in / active effect missing one. */
    public function ensurePreviews(): void
    {
        $library = app(EffectLibrary::class);
        foreach (EffectLibrary::BUILTIN_SAMPLES as $slug => $sample) {
            if (! $library->previewExists($slug)) {
                RenderEffectSampleJob::dispatch($slug, $sample['text'], $sample['params']);
            }
        }
        foreach ($library->active() as $effect) {
            if (! $library->previewExists($effect->slug)) {
                RenderEffectSampleJob::dispatch($effect->slug, $effect->sample_text, $effect->sample_params ?? []);
            }
        }
    }

    /** Render one video cycling through every effect, each with its name centered. */
    public function gerarShowreel(EffectLibrary $library): void
    {
        if ($library->showreelExists()) {
            return; // cached for the current design system + effect set
        }
        $slug = app(ProjectContext::class)->current()->slug;
        Cache::put(RenderShowreelJob::flagKey($slug), true, now()->addMinutes(20));
        RenderShowreelJob::dispatch();
    }

    public function getShowreelReadyProperty(EffectLibrary $library): bool
    {
        return $library->showreelExists();
    }

    /** Cache-buster for the showreel URL — changes only when the reel is rebuilt,
     *  NOT on every render, so polling doesn't reload the video (which made the
     *  page jump). Based on the file's mtime. */
    public function getShowreelVersionProperty(EffectLibrary $library): int
    {
        $path = $library->showreelPath();

        return is_file($path) ? (int) filemtime($path) : 0;
    }

    public function getShowreelBusyProperty(): bool
    {
        $slug = app(ProjectContext::class)->current()->slug;

        return Cache::has(RenderShowreelJob::flagKey($slug));
    }

    public function gerarSfx(): void
    {
        $this->validate(
            ['sfxPrompt' => 'required|string|min:8|max:600'],
            [
                'sfxPrompt.required' => 'Describe the effect you want.',
                'sfxPrompt.min' => 'Give a bit more detail (at least 8 characters).',
            ],
        );

        $effect = $this->effects()->create([
            'prompt' => trim($this->sfxPrompt),
            'slug' => 'pending-'.Str::lower(Str::random(8)),
            'display_name' => Str::limit(trim($this->sfxPrompt), 40),
            'description' => '',
            'param_schema' => '{}',
            'tsx' => '',
            'status' => EffectRecord::STATUS_PENDING,
        ]);

        GenerateEffectJob::dispatch($effect->id());
        $this->sfxPrompt = '';
    }

    /** Allow/disallow a live custom effect for use by the planner in generated videos. */
    public function alternarSfx(string $id): void
    {
        $effect = $this->effects()->find($id);
        if ($effect && $effect->isActive()) {
            $effect->update(['enabled' => ! $effect->enabled]);
        }
    }

    /** Allow/disallow a built-in effect for the planner. */
    public function alternarBuiltin(string $slug, EffectLibrary $library): void
    {
        $library->toggleBuiltin($slug);
    }

    /** Mark/unmark a custom effect as usable at the start of a video. */
    public function alternarIntro(string $id): void
    {
        $effect = $this->effects()->find($id);
        if ($effect && $effect->isActive()) {
            $effect->update(['intro' => ! (bool) $effect->get('intro', false)]);
        }
    }

    /** Mark/unmark a built-in effect as usable at the start of a video. */
    public function alternarIntroBuiltin(string $slug, EffectLibrary $library): void
    {
        $library->toggleIntroBuiltin($slug);
    }

    public function apagarSfx(string $id, EffectLibrary $library): void
    {
        if ($effect = $this->effects()->find($id)) {
            $library->remove($effect);
        }
        if ($this->detailKey !== null) {
            $this->redirect(route('clips-animados.sfx'), navigate: true);
        }
    }

    // ── per-effect sound ─────────────────────────────────────────────────

    public function abrirAudio(string $slug): void
    {
        $this->reset(['audioUpload', 'audioGenPrompt']);
        $this->resetValidation();
        $this->audioEditingSlug = $slug;
    }

    public function fecharAudio(): void
    {
        $this->reset(['audioEditingSlug', 'audioUpload', 'audioGenPrompt']);
        $this->resetValidation();
    }

    public function uploadAudio(): void
    {
        if (! $this->audioEditingSlug) {
            return;
        }
        $this->validate(
            ['audioUpload' => 'required|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/aac,audio/ogg,audio/webm|max:10240'],
            [
                'audioUpload.required' => 'Choose an audio file.',
                'audioUpload.mimetypes' => 'That is not a supported audio file.',
                'audioUpload.max' => 'Audio too large (max 10 MB).',
            ],
        );

        $ext = $this->audioUpload->getClientOriginalExtension() ?: 'mp3';
        $this->effects()->putAudio($this->audioEditingSlug, $this->audioUpload->getRealPath(), $ext);
        $this->fecharAudio();
    }

    public function gerarAudio(ElevenLabsSfxService $sfx): void
    {
        if (! $this->audioEditingSlug) {
            return;
        }
        $this->validate(
            ['audioGenPrompt' => 'required|string|min:3|max:200'],
            ['audioGenPrompt.required' => 'Describe the sound you want.'],
        );

        // ponytail: sync HTTP call — short SFX return in a few seconds. Move to a
        // queued job (like GenerateEffectJob) if generation ever gets slow.
        $tmp = tempnam(sys_get_temp_dir(), 'sfx_').'.mp3';
        try {
            $sfx->generate(trim($this->audioGenPrompt), $tmp);
            $this->effects()->putAudio($this->audioEditingSlug, $tmp, 'mp3');
        } catch (\Throwable $e) {
            $this->addError('audioGenPrompt', 'Generation failed: '.$e->getMessage());

            return;
        } finally {
            @unlink($tmp);
        }
        $this->fecharAudio();
    }

    public function apagarAudio(string $slug): void
    {
        $this->effects()->deleteAudio($slug);
        if ($this->audioEditingSlug === $slug) {
            $this->fecharAudio();
        }
    }

    /** Custom effects only — built-in overrides belong to their built-in tile. */
    public function getEffectsProperty()
    {
        $library = app(EffectLibrary::class);

        return $this->effects()->all()->reject(fn (EffectRecord $e) => $library->isBuiltin($e->slug))->values();
    }

    public function getSfxBusyProperty(): bool
    {
        return $this->effects()->all()->contains(fn (EffectRecord $e) => in_array($e->status, [EffectRecord::STATUS_PENDING, EffectRecord::STATUS_UPDATING], true))
            || collect(EffectLibrary::BUILTIN_SAMPLES)->keys()
                ->contains(fn ($slug) => ! app(EffectLibrary::class)->previewExists($slug));
    }

    public function render(EffectLibrary $library)
    {
        $disabledBuiltins = $library->disabledBuiltins();
        // Overrides are custom effects whose slug matches a built-in.
        $overrides = $this->effects()->all()
            ->filter(fn (EffectRecord $e) => $library->isBuiltin($e->slug))
            ->keyBy('slug');

        $builtins = [];
        $ready = [];
        foreach (EffectLibrary::BUILTIN_SAMPLES as $slug => $sample) {
            $override = $overrides->get($slug);
            $builtins[] = [
                'slug' => $slug,
                'label' => $sample['label'],
                'allowed' => ! in_array($slug, $disabledBuiltins, true),
                'intro' => $library->builtinIsIntro($slug),
                'override' => $override?->status,   // null | pending | updating | active | failed
            ];
            if ($library->previewExists($slug)) {
                $ready[] = $slug;
            }
        }
        foreach ($this->effects as $effect) {
            if ($library->previewExists($effect->slug)) {
                $ready[] = $effect->slug;
            }
        }

        return view('livewire.clips-animados-sfx', [
            'builtins' => $builtins,
            'sfxReady' => $ready,
            'sfxAudio' => $this->effects()->audioSlugs(),
        ]);
    }
}
