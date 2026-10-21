<?php

namespace App\Livewire;

use App\Jobs\Clips\GenerateEffectJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Jobs\Clips\RenderEffectUseJob;
use App\Jobs\Clips\RenderShowreelJob;
use App\Services\Clips\Api\ElevenLabsSfxService;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\EffectPortability;
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
#[Title('Estúdio SFX')]
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

    /** Optional mockup of the final frame — makes the new effect a template of it. */
    public $sfxReference = null;

    // "Use this effect": render it with your own inputs.
    public bool $usoAberto = false;

    /** @var array<string,string> text/JSON value per param key */
    public array $usoCampos = [];

    /** @var array<string,mixed> uploaded file per image param key */
    public array $usoImagens = [];

    public string $usoTexto = '';

    public float $usoDuracao = 5;

    /** Filename of the last render asked for (under uses/), or null. */
    public ?string $usoSaida = null;

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
    public function importarSfx(EffectPortability $port): void
    {
        $this->validate(
            ['importFile' => 'required|file|max:51200'],
            ['importFile.required' => 'Escolha primeiro um ficheiro exportado.'],
        );

        try {
            $payload = json_decode((string) file_get_contents($this->importFile->getRealPath()), true);
            if (! is_array($payload)) {
                throw new \RuntimeException('O ficheiro não é JSON válido.');
            }
            $n = $port->import($payload);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'A importação falhou: '.$e->getMessage(), type: 'erro');

            return;
        }

        $this->reset('importFile');

        if ($n === 0) {
            $this->dispatch('toast', message: 'Não foram encontrados efeitos nesse ficheiro.', type: 'erro');

            return;
        }

        $this->dispatch('toast', message: $n.' efeito'.($n === 1 ? '' : 's').' importado'.($n === 1 ? '' : 's').' — a renderizar pré-visualizações…', type: 'ok');
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
                'status' => $rec->status, 'enabled' => (bool) $rec->enabled, 'intro' => (bool) $rec->get('intro', false), 'outro' => (bool) $rec->get('outro', false),
                'description' => (string) $rec->description, 'error' => $rec->error,
                'prompt' => (string) $rec->prompt,
                'versions' => count($rec->get('versions', [])), 'record' => $rec,
                'formatos' => $this->formatosProntos($library, $rec->slug),
            ];
        }
        if ($library->isBuiltin($this->detailKey)) {
            $override = $this->effects()->all()->firstWhere('slug', $this->detailKey);

            return [
                'kind' => 'builtin', 'slug' => $this->detailKey,
                'label' => EffectLibrary::BUILTIN_SAMPLES[$this->detailKey]['label'] ?? $this->detailKey,
                'allowed' => $library->builtinAllowed($this->detailKey), 'intro' => $library->builtinIsIntro($this->detailKey), 'outro' => $library->builtinIsOutro($this->detailKey),
                'override' => $override?->status, 'overrideId' => $override?->id(),
                'prompt' => (string) ($override?->prompt ?? ''),
                'versions' => $override ? count($override->get('versions', [])) : 0, 'record' => $override,
                'formatos' => $this->formatosProntos($library, $this->detailKey),
            ];
        }

        return null;
    }

    /**
     * Regenerate every live custom effect against the current contract.
     *
     * Effects written before the format contract size themselves from
     * useVideoConfig() — the whole composition — so in the half-frame box they
     * lay out for a frame twice as tall as they get and the bottom is clipped.
     * Their prompt (what the effect IS) doesn't change; only the code is rewritten,
     * and the previous version is snapshotted so "go back" undoes it.
     */
    public function tornarResponsivos(EffectLibrary $library): void
    {
        $n = 0;
        foreach ($this->effects()->all() as $effect) {
            if (! $effect->isActive() || trim((string) $effect->prompt) === '') {
                continue;
            }
            $effect->update([
                'versions' => $this->pushVersion($effect->get('versions', []), $this->snapshotVersion($effect)),
                'status' => EffectRecord::STATUS_UPDATING,
                'error' => null,
            ]);
            // Previews are invalidated by GenerateEffectJob once the rewrite lands:
            // deleting them here dropped the half/landscape ones with nothing left
            // to re-render them, and dropped the portrait one before its
            // replacement existed, so the card went blank for the whole rewrite.
            GenerateEffectJob::dispatch($effect->id(), isEdit: true);
            $n++;
        }

        $this->dispatch('toast', type: $n ? 'ok' : 'erro', message: $n
            ? $n.' efeito'.($n === 1 ? '' : 's').' a ser reescrito'.($n === 1 ? '' : 's').' para vertical, meio ecrã e horizontal…'
            : 'Não há efeitos ativos para reescrever.');
    }

    /** How many effects are sitting failed with a working version to go back to. */
    public function getRecuperaveisProperty(): int
    {
        return $this->effects()->all()
            ->filter(fn (EffectRecord $e) => $e->status === EffectRecord::STATUS_FAILED && $this->codigoBomDe($e) !== null)
            ->count();
    }

    /**
     * Bring every failed effect back to its last working component.
     *
     * A failed generation never overwrites the record's code, so in most cases the
     * effect's own `tsx` is still the good one and only its status is wrong — that
     * is what the rewrite pass got wrong. Where the code really is gone, the last
     * snapshot in its history is used instead.
     */
    public function recuperarFalhados(EffectLibrary $library): void
    {
        $n = 0;
        foreach ($this->effects()->all() as $effect) {
            if ($effect->status !== EffectRecord::STATUS_FAILED) {
                continue;
            }
            $tsx = $this->codigoBomDe($effect);
            if ($tsx === null) {
                continue;
            }
            $effect->update(['tsx' => $tsx, 'status' => EffectRecord::STATUS_ACTIVE, 'error' => null]);
            $library->promote($effect); // rewrites <slug>.tsx and rebuilds the registry
            $n++;
        }

        $this->dispatch('toast', type: $n ? 'ok' : 'erro', message: $n
            ? $n.' efeito'.($n === 1 ? '' : 's').' recuperado'.($n === 1 ? '' : 's').'.'
            : 'Nada a recuperar — nenhum efeito com falha tem uma versão funcional guardada.');
    }

    /** The last component known to work for an effect: its own, else its newest snapshot. */
    private function codigoBomDe(EffectRecord $effect): ?string
    {
        if (trim((string) $effect->tsx) !== '') {
            return (string) $effect->tsx;
        }
        foreach (array_reverse((array) $effect->get('versions', [])) as $v) {
            if (is_array($v) && trim((string) ($v['tsx'] ?? '')) !== '') {
                return (string) $v['tsx'];
            }
        }

        return null;
    }

    /**
     * Which formats have a cached preview, as a token that changes when the file
     * does (0 = none). Still falsy when missing, so it reads as a yes/no; the
     * value is what the view hangs off the URL so a re-render is actually fetched
     * instead of served from the browser's copy of the previous one.
     *
     * @return array<string,int>
     */
    private function formatosProntos(EffectLibrary $library, string $slug): array
    {
        return collect(EffectLibrary::FORMATS)
            ->map(fn ($label, $formato) => $library->previewVersion($slug, $formato))
            ->all();
    }

    /** The vault-backed SFX store for the active project. */
    private function effects(): EffectStore
    {
        return app(EffectStore::class);
    }

    public function editarSfx(string $id): void
    {
        $effect = $this->effects()->find($id);
        // Live effects are refined; failed ones retried ("Try again"). Only one
        // still generating is refused, since that job would overwrite the edit.
        if (! $effect || ! in_array($effect->status, [EffectRecord::STATUS_ACTIVE, EffectRecord::STATUS_FAILED], true)) {
            return;
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
                'sfxEditPrompt.required' => 'Descreva a alteração que quer.',
                'sfxEditPrompt.min' => 'Dê um pouco mais de detalhe (pelo menos 8 caracteres).',
            ],
        );

        if ($this->editingSfxId) {
            // Refine an existing effect (custom or a built-in override). Snapshot the
            // current committed version first so it can be restored ("go back").
            $effect = $this->effects()->find($this->editingSfxId);
            if ($effect && in_array($effect->status, [EffectRecord::STATUS_ACTIVE, EffectRecord::STATUS_FAILED], true)) {
                // A failed CREATE has no component to fall back on: retry it as a
                // create, so a second failure marks it failed again instead of
                // "active" with nothing to render.
                $live = trim((string) $effect->tsx) !== '';
                $effect->update([
                    'versions' => $live ? $this->pushVersion($effect->get('versions', []), $this->snapshotVersion($effect)) : $effect->get('versions', []),
                    'prompt' => trim($this->sfxEditPrompt),
                    'status' => $live ? EffectRecord::STATUS_UPDATING : EffectRecord::STATUS_PENDING,
                    'error' => null,
                ]);
                GenerateEffectJob::dispatch($effect->id(), isEdit: $live);
            }
        } elseif ($this->sfxOverrideSlug && app(EffectLibrary::class)->isBuiltin($this->sfxOverrideSlug)) {
            // Create an override of a built-in: same slug replaces it in the registry.
            $slug = $this->sfxOverrideSlug;
            $effect = $this->effects()->create([
                'prompt' => trim($this->sfxEditPrompt),
                'slug' => $slug,
                'display_name' => Str::title(str_replace('-', ' ', $slug)).' (personalizado)',
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
        // A FAILED effect is the one you most need to roll back — refusing it
        // there left a bad rewrite with no way home. Only a generation still in
        // flight is refused, since the job would overwrite the restore.
        if (! $effect || in_array($effect->status, [EffectRecord::STATUS_PENDING, EffectRecord::STATUS_UPDATING], true)) {
            return;
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
        // Going back to an older version changes the code, so every framing's
        // preview is stale — not just portrait, which used to be the only one
        // dropped while half and landscape kept showing the version we left.
        @unlink($library->previewPath($effect->slug));
        RenderEffectSampleJob::dispatch($effect->slug, $effect->sample_text, $effect->sample_params ?? []);
        foreach ($library->dropSecondaryPreviews($effect->slug) as $formato) {
            RenderEffectSampleJob::dispatch($effect->slug, $effect->sample_text, $effect->sample_params ?? [], $formato);
        }

        $this->historyId = null;
    }

    /** Which box the open effect is being previewed in: portrait|half|landscape. */
    public string $formato = EffectLibrary::FORMAT_PORTRAIT;

    /**
     * Switch the preview to another format, rendering it on first view.
     *
     * An effect is ONE component that lays itself out for whichever box it gets,
     * so this is a preview of the same code in a different frame — not a separate
     * variant to maintain.
     */
    public function verFormato(string $formato): void
    {
        if (! array_key_exists($formato, EffectLibrary::FORMATS)) {
            return;
        }
        $this->formato = $formato;

        $d = $this->detail;
        if ($d === null || app(EffectLibrary::class)->previewExists($d['slug'], $formato)) {
            return;
        }

        [$texto, $params] = $this->amostraDe($d);
        RenderEffectSampleJob::dispatch($d['slug'], $texto, $params, $formato);
    }

    /** The sample text/params to preview an effect with. @return array{0:?string,1:array<string,mixed>} */
    private function amostraDe(array $d): array
    {
        $rec = $d['record'] ?? null;
        if ($rec instanceof EffectRecord && ! app(EffectLibrary::class)->isBuiltin($d['slug'])) {
            return [$rec->sample_text, $rec->sample_params ?? []];
        }
        $sample = EffectLibrary::BUILTIN_SAMPLES[$d['slug']] ?? ['text' => '', 'params' => []];

        return [$sample['text'], $sample['params']];
    }

    /**
     * Dispatch a cached-preview render for every built-in / active effect missing
     * one, in every framing.
     *
     * All three, not just portrait: a sample render takes ~20s, so leaving half and
     * landscape to the tab click meant a wait every time one was first opened. The
     * jobs are idempotent (each returns immediately if its file is already there),
     * so this backfills what a library predates and then costs nothing — until a
     * design-system change invalidates the previews and it rebuilds them.
     *
     * ponytail: queued on the same worker as generation, so a first run on a large
     * library makes a new effect wait behind the backfill. Give the renders their
     * own queue if that becomes the complaint.
     */
    public function ensurePreviews(): void
    {
        $library = app(EffectLibrary::class);
        $formatos = array_keys(EffectLibrary::FORMATS);

        foreach (EffectLibrary::BUILTIN_SAMPLES as $slug => $sample) {
            foreach ($formatos as $formato) {
                if (! $library->previewExists($slug, $formato)) {
                    RenderEffectSampleJob::dispatch($slug, $sample['text'], $sample['params'], $formato);
                }
            }
        }
        foreach ($this->effects()->all() as $effect) {
            if (! $effect->isActive()) {
                continue;
            }
            foreach ($formatos as $formato) {
                if (! $library->previewExists($effect->slug, $formato)) {
                    RenderEffectSampleJob::dispatch($effect->slug, $effect->sample_text, $effect->sample_params ?? [], $formato);
                }
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
                'sfxPrompt.required' => 'Descreva o efeito que quer.',
                'sfxPrompt.min' => 'Dê um pouco mais de detalhe (pelo menos 8 caracteres).',
            ],
        );
        $this->validate(
            ['sfxReference' => 'nullable|image|max:10240'],
            ['sfxReference.image' => 'A referência tem de ser uma imagem.', 'sfxReference.max' => 'Imagem demasiado grande (máx. 10 MB).'],
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

        if ($this->sfxReference) {
            // Before dispatch: the job reads it to build the template.
            $this->effects()->putReference($effect->id(), $this->sfxReference->getRealPath(),
                $this->sfxReference->getClientOriginalExtension() ?: 'png');
        }

        GenerateEffectJob::dispatch($effect->id());
        $this->reset(['sfxPrompt', 'sfxReference']);
    }

    // ── use: render the effect with your own inputs ─────────────────────

    /**
     * The inputs the open effect takes: image slots (typed `image` in its
     * paramSchema, plus `src` wherever it is declared) and every other param its
     * sample carries, with the sample value as the default.
     *
     * @return array{imagens:array<int,string>,campos:array<string,string>,texto:string}
     */
    public function getUsoInputsProperty(): array
    {
        $d = $this->detail;
        if ($d === null) {
            return ['imagens' => [], 'campos' => [], 'texto' => ''];
        }
        [$texto, $params] = $this->amostraDe($d);
        $rec = $d['record'] ?? null;
        $schema = $rec instanceof EffectRecord ? (string) $rec->param_schema : '';

        preg_match_all('/["\']?(\w+)["\']?\??\s*:\s*image\b/', $schema, $m);
        $imagens = array_values(array_unique(array_merge(
            preg_match('/["\']?\bsrc\b["\']?\??\s*:/', $schema) || array_key_exists('src', $params) ? ['src'] : [],
            $m[1],
        )));

        $campos = [];
        foreach ($params as $k => $v) {
            if (! in_array($k, $imagens, true)) {
                $campos[$k] = is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        return ['imagens' => $imagens, 'campos' => $campos, 'texto' => (string) $texto];
    }

    public function abrirUso(): void
    {
        $inputs = $this->usoInputs;
        $this->usoCampos = $inputs['campos'];
        $this->usoTexto = $inputs['texto'];
        $this->usoImagens = [];
        $this->usoAberto = true;
        $this->resetValidation();
    }

    public function fecharUso(): void
    {
        $this->reset(['usoAberto', 'usoCampos', 'usoImagens', 'usoTexto']);
    }

    public function renderizarUso(EffectLibrary $library): void
    {
        $d = $this->detail;
        if ($d === null) {
            return;
        }
        $this->validate([
            'usoImagens.*' => 'nullable|image|max:20480',
            'usoDuracao' => 'required|numeric|min:1|max:30',
        ], [
            'usoImagens.*.image' => 'Isso não é uma imagem.',
            'usoImagens.*.max' => 'Imagem demasiado grande (máx. 20 MB).',
        ]);

        $dir = $library->previewDir().'/uses';
        @mkdir($dir.'/inputs', 0777, true);

        // Complex params (lists, objects) are edited as JSON; anything that doesn't
        // parse goes through as the plain string it is.
        $params = [];
        foreach ($this->usoCampos as $k => $v) {
            $json = json_decode((string) $v, true);
            $params[$k] = is_array($json) ? $json : (string) $v;
        }
        // Copied out of Livewire's temp dir: the render runs later, on a worker.
        foreach ($this->usoImagens as $k => $file) {
            if ($file && in_array($k, $this->usoInputs['imagens'], true)) {
                $path = $dir.'/inputs/'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'png');
                copy($file->getRealPath(), $path);
                $params[$k] = $path;
            }
        }

        $this->usoSaida = $d['slug'].'-'.Str::lower(Str::random(10)).'.mp4';
        RenderEffectUseJob::dispatch($d['slug'], $this->usoTexto, $params, $this->formato, (float) $this->usoDuracao, $dir.'/'.$this->usoSaida);
    }

    /** 'none' | 'rendering' | 'ready' | 'failed' for the last "use" render. */
    public function getUsoEstadoProperty(EffectLibrary $library): string
    {
        if ($this->usoSaida === null) {
            return 'none';
        }
        $out = $library->previewDir().'/uses/'.$this->usoSaida;

        return match (true) {
            is_file($out) => 'ready',
            is_file($out.'.err') => 'failed',
            default => 'rendering',
        };
    }

    public function getUsoErroProperty(EffectLibrary $library): string
    {
        return $this->usoSaida ? (string) @file_get_contents($library->previewDir().'/uses/'.$this->usoSaida.'.err') : '';
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

    /** Mark/unmark a custom effect as usable at the end of a video. */
    public function alternarOutro(string $id): void
    {
        $effect = $this->effects()->find($id);
        if ($effect && $effect->isActive()) {
            $effect->update(['outro' => ! (bool) $effect->get('outro', false)]);
        }
    }

    /** Mark/unmark a built-in effect as usable at the end of a video. */
    public function alternarOutroBuiltin(string $slug, EffectLibrary $library): void
    {
        $library->toggleOutroBuiltin($slug);
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
                'audioUpload.required' => 'Escolha um ficheiro de áudio.',
                'audioUpload.mimetypes' => 'Esse ficheiro de áudio não é suportado.',
                'audioUpload.max' => 'Áudio demasiado grande (máx. 10 MB).',
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
            ['audioGenPrompt.required' => 'Descreva o som que quer.'],
        );

        // ponytail: sync HTTP call — short SFX return in a few seconds. Move to a
        // queued job (like GenerateEffectJob) if generation ever gets slow.
        $tmp = tempnam(sys_get_temp_dir(), 'sfx_').'.mp3';
        try {
            $sfx->generate(trim($this->audioGenPrompt), $tmp);
            $this->effects()->putAudio($this->audioEditingSlug, $tmp, 'mp3');
        } catch (\Throwable $e) {
            $this->addError('audioGenPrompt', 'A geração falhou: '.$e->getMessage());

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
        // The open effect waiting on the framing you are LOOKING at counts as busy.
        // Without it nothing polls while a half/landscape sample renders — those are
        // only ever missing one at a time, never on the pending/updating list — so
        // the render landed and the panel sat on "Rendering the half portrait
        // preview…" until the page was reloaded by hand.
        if ($this->usoEstado === 'rendering') {
            return true;
        }
        $aberto = $this->detail['slug'] ?? null;
        if ($aberto !== null && ! app(EffectLibrary::class)->previewExists($aberto, $this->formato)) {
            return true;
        }

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
        $ready = [];   // slug => preview version, for both membership and cache-busting
        foreach (EffectLibrary::BUILTIN_SAMPLES as $slug => $sample) {
            $override = $overrides->get($slug);
            $builtins[] = [
                'slug' => $slug,
                'label' => $sample['label'],
                'allowed' => ! in_array($slug, $disabledBuiltins, true),
                'intro' => $library->builtinIsIntro($slug),
                'outro' => $library->builtinIsOutro($slug),
                'override' => $override?->status,   // null | pending | updating | active | failed
            ];
            if ($library->previewExists($slug)) {
                $ready[$slug] = $library->previewVersion($slug);
            }
        }
        foreach ($this->effects as $effect) {
            if ($library->previewExists($effect->slug)) {
                $ready[$effect->slug] = $library->previewVersion($effect->slug);
            }
        }

        return view('livewire.clips-animados-sfx', [
            'builtins' => $builtins,
            'sfxReady' => $ready,
            'sfxAudio' => $this->effects()->audioSlugs(),
        ]);
    }
}
