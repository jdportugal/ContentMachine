<?php

namespace App\Livewire;

use App\Jobs\Clips\FinalizeClipPlanJob;
use App\Jobs\Clips\GenerateBackgroundJob;
use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RenderBackgroundReelJob;
use App\Jobs\Clips\RenderBackgroundSampleJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\BackgroundPortability;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\ImageProbe;
use App\Services\Clips\Media;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\TranscriptRebuilder;
use App\Services\Projects\ProjectContext;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Animated Clips')]
class ClipsAnimados extends Component
{
    use WithFileUploads;

    public const BACKGROUNDS = ['papyrus', 'vellum', 'ink', 'video'];

    // Keep in sync with the Remotion `Transition` type (remotion/src/types.ts).
    public const TRANSITIONS = ['cut', 'crossfade', 'whip', 'slide', 'zoom'];

    /** dashboard | create | editPlan | editTranscript | backgrounds */
    public string $view = 'dashboard';

    /**
     * Arriving from Content Repurpose ("turn this post into a video") opens the
     * creation form with the post's text already in the script box — the mirror
     * of how Oficina picks up session('oficina_brief') coming the other way.
     */
    public function mount(): void
    {
        if (($seed = session('animado_texto')) !== null) {
            session()->forget('animado_texto');
            $this->text = (string) $seed;
            // News bits carry an on-screen LEAD as a `> …` line: lift it into the
            // lead field and out of the script, so it is shown, not spoken.
            if (preg_match('/^>\s*(\S.*)$/m', $this->text, $m)) {
                $this->lead = trim($m[1]);
                $this->text = trim(str_replace($m[0], '', $this->text));
            }
            $this->createType = 'animation';
            $this->view = 'create';
        }
    }

    // ---- Backgrounds studio ----
    public string $bgPrompt = '';

    public $bgVideo = null;

    public string $bgVideoName = '';

    /** Uploaded Brand Machine background export file, awaiting import. */
    public $importBackgroundFile = null;

    public ?string $editingBgId = null;

    public string $bgEditPrompt = '';

    /** Backdrop choice for a new clip: 'auto' | 'none' | an enabled background slug. */
    public string $background = 'auto';

    // ---- creation ----
    // ---- collect images (review of the planner's image suggestions) ----
    public ?string $reviewingId = null;

    /** Per-suggestion upload files, keyed by the suggestion's key. */
    public array $reviewUploads = [];

    /** null | animation | overlay */
    public ?string $createType = null;

    public string $text = '';

    public $audio = null;

    public $video = null;

    /** News LEAD — optional line pinned on screen for the whole clip. */
    public string $lead = '';

    /** which presentation styles the AI may use for a video clip */
    public array $allowedPresents = ['video', 'over', 'split', 'animation'];

    // images (with description) so the planner can use them animated
    public $newImage = null;

    public string $newImageDesc = '';

    /** @var array<int,array{id:string,path:string,description:string}> */
    public array $images = [];

    /** Per-row replacement uploads while editing a clip (keyed by image index). */
    public array $imageReplace = [];

    // background music (same library as shorts) — '' = random, 'nenhuma' = no music
    public string $musica = '';

    public float $musicaVolume = 0.1;

    // ---- editing ----
    public ?string $editingId = null;

    public string $editTitle = '';

    /** @var array<int,array<string,mixed>> scene rows (layers preserved verbatim) */
    public array $editScenes = [];

    /** Index of the scene whose "change animation" picker is open, or null. */
    public ?int $animacaoPickerCena = null;

    /** 'cenas' (field editor) | 'json' (raw Remotion plan) */
    public string $editMode = 'cenas';

    public string $editPlanJson = '';

    public string $editTranscriptText = '';

    // =====================================================================
    // Navigation
    // =====================================================================

    public function novoClip(): void
    {
        $this->reset(['createType', 'text', 'audio', 'video']);
        $this->resetValidation();
        $this->view = 'create';
    }

    /** Promotes a finished clip to the Finished hub (ready to publish). */
    public function promover(string $id): void
    {
        app(ClipStore::class)->find($id)?->update(['finished' => true]);
    }

    public function escolherTipo(string $type): void
    {
        $this->createType = in_array($type, ['animation', 'overlay'], true) ? $type : null;
        $this->resetValidation();
    }

    public function voltar(): void
    {
        $this->reset(['createType', 'text', 'audio', 'video', 'lead', 'allowedPresents', 'newImage', 'newImageDesc', 'images', 'imageReplace', 'musica', 'musicaVolume', 'background', 'editingId', 'editTitle', 'editScenes', 'editMode', 'editPlanJson', 'editTranscriptText', 'bgPrompt', 'bgVideo', 'bgVideoName', 'editingBgId', 'bgEditPrompt', 'reviewingId', 'reviewUploads', 'libraryPickerKey', 'sceneImageUploads', 'sceneLibraryPicker']);
        $this->resetValidation();
        $this->view = 'dashboard';
    }

    public function adicionarImagem(): void
    {
        $this->validate([
            'newImage' => 'required|file|'.Media::mimesRule().'|max:102400',
            'newImageDesc' => 'required|string|max:200',
        ], [
            'newImage.required' => 'Choose an image or video.',
            'newImage.mimes' => 'The file must be an image or a video.',
            'newImage.max' => 'The file is too large (maximum 100 MB).',
            'newImageDesc.required' => 'Describe what it shows.',
        ], ['newImage' => 'file', 'newImageDesc' => 'description']);

        $path = $this->newImage->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        $this->images[] = array_merge(
            ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'description' => trim($this->newImageDesc)],
            $this->probeImage($abs),
        );
        $this->guardarNaBiblioteca($this->newImage, trim($this->newImageDesc));
        $this->reset(['newImage', 'newImageDesc']);
    }

    /** Replace an already-uploaded image's file, keeping its id so the plan (which
     *  references images by id) keeps using it — the new picture just shows on
     *  re-render. Fires when a per-row replace file input receives a file. */
    public function updatedImageReplace(mixed $value, string $key): void
    {
        $i = (int) $key;
        if (! $value || ! isset($this->images[$i])) {
            unset($this->imageReplace[$i]);

            return;
        }
        $this->validateOnly("imageReplace.$i", ["imageReplace.$i" => 'file|'.Media::mimesRule().'|max:102400'], [
            "imageReplace.$i.mimes" => 'The file must be an image or a video.',
            "imageReplace.$i.max" => 'The file is too large (maximum 100 MB).',
        ]);

        $path = $value->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        // Same id/description; swap the file (old file is cleaned up on save).
        $this->images[$i] = array_merge($this->images[$i], ['path' => $path], $this->probeImage($abs));
        $this->guardarNaBiblioteca($value, (string) ($this->images[$i]['description'] ?? ''));
        unset($this->imageReplace[$i]);
    }

    public function removerImagem(int $i): void
    {
        if (! isset($this->images[$i])) {
            return;
        }
        // When editing a saved clip, defer file deletion to save (so a cancel keeps
        // the clip intact). During creation the upload is discardable immediately.
        if (! $this->editingId) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($this->images[$i]['path']);
        }
        unset($this->images[$i]);
        $this->images = array_values($this->images);
    }

    /** Keep any user upload in the project's Assets library for reuse. Best-effort. */
    private function guardarNaBiblioteca(mixed $upload, string $description = ''): void
    {
        try {
            app(ImageLibrary::class)->add($upload->getRealPath(), $upload->getClientOriginalName(), $description);
        } catch (\Throwable) {
            // Never block a clip upload on a library write.
        }
    }

    /** @return array{transparent:bool,tone:string,video:bool} */
    private function probeImage(string $abs): array
    {
        if (Media::isVideo($abs)) {
            return ['transparent' => false, 'tone' => 'mixed', 'video' => true];
        }

        return ['transparent' => ImageProbe::hasAlpha($abs), 'tone' => ImageProbe::tone($abs), 'video' => false];
    }

    /** Delete uploaded files removed/replaced during editing (paths gone from $this->images). */
    private function pruneImageFiles(array $oldImages): void
    {
        $keep = array_column($this->images, 'path');
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        foreach ($oldImages as $img) {
            $path = $img['path'] ?? null;
            if (is_string($path) && $path !== '' && ! in_array($path, $keep, true)) {
                $disk->delete($path);
            }
        }
    }

    /** Blank image-id references in the plan that no longer point to an uploaded image
     *  (removed while editing) so the render shows a placeholder instead of 404ing. */
    private function stripMissingImageRefs(array $plan): array
    {
        $valid = array_column($this->images, 'id');
        $walk = function (&$node) use (&$walk, $valid) {
            if (is_array($node)) {
                foreach ($node as &$v) {
                    $walk($v);
                }
                unset($v);
            } elseif (is_string($node) && preg_match('/^(img|gen)_/', $node) && ! in_array($node, $valid, true)) {
                $node = '';
            }
        };
        if (empty($plan['scenes']) || ! is_array($plan['scenes'])) {
            return $plan;
        }
        foreach ($plan['scenes'] as &$scene) {
            if (! empty($scene['layers']) && is_array($scene['layers'])) {
                $walk($scene['layers']);
            }
        }
        unset($scene);

        return $plan;
    }

    // =====================================================================
    // Backgrounds studio
    // =====================================================================

    public function abrirBackgrounds(): void
    {
        $this->reset(['editingBgId', 'bgEditPrompt', 'bgPrompt', 'bgVideo', 'bgVideoName']);
        $this->resetValidation();
        $this->view = 'backgrounds';
        $this->ensureBackgroundPreviews();
    }

    /** The vault-backed background store for the active project. */
    private function backgrounds(): BackgroundStore
    {
        return app(BackgroundStore::class);
    }

    /** Render a preview for any active code background missing one (video previews are the file itself). */
    public function ensureBackgroundPreviews(): void
    {
        $library = app(BackgroundLibrary::class);
        foreach ($library->active() as $bg) {
            if ($bg->kind !== BackgroundStore::KIND_VIDEO && $library->previewFileFor($bg) === null) {
                RenderBackgroundSampleJob::dispatch($bg->slug);
            }
        }
    }

    /** Generate a new CODE background from a description. */
    public function gerarBackground(): void
    {
        $this->validate(
            ['bgPrompt' => 'required|string|min:8|max:600'],
            [
                'bgPrompt.required' => 'Describe the background you want.',
                'bgPrompt.min' => 'Give a bit more detail (at least 8 characters).',
            ],
        );

        $bg = $this->backgrounds()->create([
            'kind' => BackgroundStore::KIND_CODE,
            'prompt' => trim($this->bgPrompt),
            'slug' => 'pending-'.Str::lower(Str::random(8)),
            'display_name' => Str::limit(trim($this->bgPrompt), 40),
            'description' => '',
            'tsx' => '',
            'status' => EffectRecord::STATUS_PENDING,
        ]);

        GenerateBackgroundJob::dispatch($bg->id());
        $this->bgPrompt = '';
    }

    /** Upload an mp4 as a VIDEO background (looped to fill any clip length). */
    public function uploadBackground(): void
    {
        $this->validate([
            'bgVideo' => 'required|file|mimetypes:video/mp4,video/quicktime|max:512000',
            'bgVideoName' => 'required|string|max:60',
        ], [
            'bgVideo.required' => 'Choose a video file.',
            'bgVideo.mimetypes' => 'Unsupported video format. Use mp4 or mov.',
            'bgVideo.max' => 'The video is too large (maximum 500 MB).',
            'bgVideoName.required' => 'Give the background a name.',
        ], ['bgVideo' => 'video', 'bgVideoName' => 'name']);

        $name = trim($this->bgVideoName);
        $slug = $this->uniqueBackgroundSlug(Str::slug($name) ?: 'background');

        $bg = $this->backgrounds()->create([
            'kind' => BackgroundStore::KIND_VIDEO,
            'slug' => $slug,
            'display_name' => $name,
            'description' => "Video backdrop «{$name}».",
            'status' => EffectRecord::STATUS_ACTIVE,
        ]);

        $target = $this->backgrounds()->videoPath($bg->id());
        @mkdir(dirname($target), 0775, true);
        copy($this->bgVideo->getRealPath(), $target);

        $this->reset(['bgVideo', 'bgVideoName']);
    }

    private function uniqueBackgroundSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while ($this->backgrounds()->slugExists($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function editarBackground(string $id): void
    {
        $bg = $this->backgrounds()->find($id);
        if (! $bg || ! $bg->isActive() || $bg->kind === BackgroundStore::KIND_VIDEO) {
            return; // only live code backgrounds can be refined
        }
        $this->editingBgId = $bg->id();
        $this->bgEditPrompt = (string) $bg->prompt;
        $this->resetValidation();
    }

    public function cancelarBackgroundEdicao(): void
    {
        $this->reset(['editingBgId', 'bgEditPrompt']);
        $this->resetValidation();
    }

    public function guardarBackgroundEdicao(): void
    {
        $this->validate(
            ['bgEditPrompt' => 'required|string|min:8|max:600'],
            [
                'bgEditPrompt.required' => 'Describe the change you want.',
                'bgEditPrompt.min' => 'Give a bit more detail (at least 8 characters).',
            ],
        );

        $bg = $this->backgrounds()->find($this->editingBgId);
        if ($bg && $bg->isActive() && $bg->kind !== BackgroundStore::KIND_VIDEO) {
            $bg->update([
                'prompt' => trim($this->bgEditPrompt),
                'status' => EffectRecord::STATUS_UPDATING,
                'error' => null,
            ]);
            GenerateBackgroundJob::dispatch($bg->id(), isEdit: true);
        }

        $this->reset(['editingBgId', 'bgEditPrompt']);
    }

    /** Allow/disallow a live background for the planner (and manual picking). */
    public function alternarBackground(string $id): void
    {
        $bg = $this->backgrounds()->find($id);
        if ($bg && $bg->isActive()) {
            $bg->update(['enabled' => ! $bg->enabled]);
        }
    }

    public function apagarBackground(string $id, BackgroundLibrary $library): void
    {
        if ($bg = $this->backgrounds()->find($id)) {
            $library->remove($bg);
        }
    }

    /** Import backgrounds from an uploaded Brand Machine export file. */
    public function importarBackgrounds(BackgroundPortability $port): void
    {
        $this->validate(
            ['importBackgroundFile' => 'required|file|max:512000'],
            ['importBackgroundFile.required' => 'Choose an export file first.'],
        );

        try {
            $payload = json_decode((string) file_get_contents($this->importBackgroundFile->getRealPath()), true);
            if (! is_array($payload)) {
                throw new \RuntimeException('The file is not valid JSON.');
            }
            $n = $port->import($payload);
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Import failed: '.$e->getMessage(), type: 'erro');

            return;
        }

        $this->reset('importBackgroundFile');
        $this->ensureBackgroundPreviews();

        $this->dispatch(
            'toast',
            message: $n === 0 ? 'No backgrounds found in that file.' : $n.' background'.($n === 1 ? '' : 's').' imported.',
            type: $n === 0 ? 'erro' : 'ok',
        );
    }

    /** @return Collection<int,EffectRecord> */
    public function getBackgroundsProperty()
    {
        return $this->backgrounds()->all();
    }

    /** Enabled backgrounds for the new-clip picker. @return Collection<int,EffectRecord> */
    public function getEnabledBackgroundsProperty()
    {
        return $this->backgrounds()->enabled();
    }

    public function getBackgroundsBusyProperty(): bool
    {
        return $this->backgrounds()->all()->contains(fn (EffectRecord $b) => in_array($b->status, [EffectRecord::STATUS_PENDING, EffectRecord::STATUS_UPDATING], true));
    }

    /** Render one video cycling through every background, each with its name centered. */
    public function gerarBackgroundReel(BackgroundLibrary $library): void
    {
        if ($library->reelExists() || $library->active()->isEmpty()) {
            return; // cached for the current design system + background set, or nothing to show
        }
        $slug = app(ProjectContext::class)->current()->slug;
        Cache::put(RenderBackgroundReelJob::flagKey($slug), true, now()->addMinutes(20));
        RenderBackgroundReelJob::dispatch();
    }

    public function getBackgroundReelReadyProperty(BackgroundLibrary $library): bool
    {
        return $library->reelExists();
    }

    public function getBackgroundReelBusyProperty(): bool
    {
        $slug = app(ProjectContext::class)->current()->slug;

        return Cache::has(RenderBackgroundReelJob::flagKey($slug));
    }

    // =====================================================================
    // Creation
    // =====================================================================

    /** The vault-backed clip store for the active project. */
    private function clips(): ClipStore
    {
        return app(ClipStore::class);
    }

    public function submitAnimation(): void
    {
        $this->validate([
            'text' => 'required_without:audio|nullable|string|max:5000',
            'audio' => 'nullable|file|mimetypes:audio/*,video/webm,video/mp4|max:51200',
        ], [
            'text.required_without' => 'Write a text, record your voice or upload an audio file.',
            'audio.mimetypes' => 'Unsupported audio format. Record again or upload mp3/wav/m4a.',
            'audio.max' => 'The voiceover is too large (maximum 50 MB).',
        ], ['text' => 'text', 'audio' => 'voiceover']);

        $kind = $this->audio ? 'audio' : 'text';
        $path = $this->audio ? $this->audio->store('clips/uploads') : null;

        $project = $this->clips()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => $kind,
            'title' => $this->tituloDe($this->text),
            'source_text' => $kind === 'text' ? $this->text : null,
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(['background' => $this->background, 'lead' => trim($this->lead)]),
        ]);

        TranscribeJob::dispatch($project->id);
        $this->voltar();
    }

    public function submitOverlay(): void
    {
        $this->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:512000',
            'allowedPresents' => 'array|min:1',
            'allowedPresents.*' => 'in:video,over,split,animation',
        ], [
            'video.required' => 'Upload a video (mp4 or mov).',
            'video.mimetypes' => 'Unsupported video format. Use mp4 or mov.',
            'video.max' => 'The video is too large (maximum 500 MB).',
            'allowedPresents.min' => 'Choose at least one style.',
        ], ['video' => 'video']);

        $path = $this->video->store('clips/uploads');

        $project = $this->clips()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'title' => $this->video->getClientOriginalName(),
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(['allowed_present' => array_values($this->allowedPresents), 'background' => $this->background, 'lead' => trim($this->lead)]),
        ]);

        TranscribeJob::dispatch($project->id);
        $this->voltar();
    }

    // =====================================================================
    // Collect images (upload your own for a suggestion, or let it be generated)
    // =====================================================================

    public function revisarImagens(string $id): void
    {
        $this->clips()->findOrFail($id);
        $this->reviewingId = $id;
        $this->reset('reviewUploads');
        $this->resetValidation();
        $this->view = 'reviewImages';
    }

    /** The planner's image suggestions, each with the user's upload state. @return array<int,array<string,mixed>> */
    public function getImageRequestsProperty(): array
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        if (! $p) {
            return [];
        }
        $uploads = $p->meta['image_uploads'] ?? [];
        $asText = $p->meta['image_text'] ?? [];
        $byId = collect($p->images ?? [])->keyBy('id');

        return array_map(function (array $r) use ($uploads, $asText, $byId) {
            $id = $uploads[$r['key']] ?? null;
            $img = $id ? ($byId[$id] ?? null) : null;

            return $r + [
                'uploadedId' => $id,
                'path' => $img['path'] ?? null,
                'fromLibrary' => (bool) ($img['library'] ?? false),
                'video' => (bool) ($img['video'] ?? false),
                // upload = the user's own file/library pick · generate = AI image ·
                // text = no image at all (the scene becomes a non-image visual).
                'mode' => $id ? 'upload' : (! empty($asText[$r['key']]) ? 'text' : 'generate'),
            ];
        }, $p->meta['image_requests'] ?? []);
    }

    /**
     * Choose what a suggestion becomes: 'generate' (AI image, the default) or
     * 'text' (no image — the scene turns into a card/list/diagram built from what
     * is said there). Either way any file the user had pinned to it is dropped.
     * 'upload' is not set here — it happens by uploading or picking from the library.
     */
    public function modoImagem(string $key, string $mode): void
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        if (! $p || ! in_array($mode, ['generate', 'text'], true)) {
            return;
        }
        $uploads = $p->meta['image_uploads'] ?? [];
        $asText = $p->meta['image_text'] ?? [];
        $images = $this->dropImage($p->images ?? [], $uploads[$key] ?? null);
        unset($uploads[$key]);
        if ($mode === 'text') {
            $asText[$key] = true;
        } else {
            unset($asText[$key]);
        }

        $p->update([
            'images' => $images,
            'meta' => array_merge($p->meta ?? [], ['image_uploads' => $uploads, 'image_text' => $asText]),
        ]);
        $this->libraryPickerKey = null;
    }

    /** A file dropped on a suggestion's upload input: store it and pin it to that suggestion. */
    public function updatedReviewUploads(mixed $value, string $key): void
    {
        if (! $value || ! $this->reviewingId) {
            unset($this->reviewUploads[$key]);

            return;
        }
        $this->validateOnly("reviewUploads.$key", ["reviewUploads.$key" => 'file|'.Media::mimesRule().'|max:102400'], [
            "reviewUploads.$key.mimes" => 'The file must be an image or a video.',
            "reviewUploads.$key.max" => 'The file is too large (maximum 100 MB).',
        ]);

        $p = $this->clips()->findOrFail($this->reviewingId);
        $req = collect($p->meta['image_requests'] ?? [])->firstWhere('key', $key);
        if (! $req) {
            unset($this->reviewUploads[$key]);

            return;
        }

        $path = $value->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        $desc = ($req['label'] ?? '') ?: (trim(Str::after($req['prompt'], 'Illustrate this moment:')) ?: $req['prompt']);
        $entry = array_merge(
            ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'description' => $desc],
            $this->probeImage($abs),
        );
        $this->guardarNaBiblioteca($value, $desc);

        $uploads = $p->meta['image_uploads'] ?? [];
        // Replace any previous upload for this suggestion (drops its file).
        $images = $this->dropImage($p->images ?? [], $uploads[$key] ?? null);
        $images[] = $entry;
        $uploads[$key] = $entry['id'];

        $p->update([
            'images' => $images,
            'meta' => array_merge($p->meta ?? [], [
                'image_uploads' => $uploads,
                'image_text' => array_diff_key($p->meta['image_text'] ?? [], [$key => true]), // an upload cancels "no image"
            ]),
        ]);
        unset($this->reviewUploads[$key]);
    }

    /** Which suggestion's library picker is open (its key), or null. */
    public ?string $libraryPickerKey = null;

    /** Images in the project's library, for the collect-screen picker. @return array<int,array<string,mixed>> */
    public function getLibraryImagesProperty(): array
    {
        return app(ImageLibrary::class)->all();
    }

    public function abrirBibliotecaImagens(string $key): void
    {
        $this->libraryPickerKey = $this->libraryPickerKey === $key ? null : $key;
    }

    /** Pin a specific library image to a suggestion (replaces any current choice). */
    public function usarImagemBiblioteca(string $key, string $libId): void
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        if (! $p) {
            return;
        }
        $entry = app(ImageLibrary::class)->attachToClip($libId);
        if ($entry === null) {
            return;
        }
        $uploads = $p->meta['image_uploads'] ?? [];
        $images = $this->dropImage($p->images ?? [], $uploads[$key] ?? null);
        $images[] = $entry;
        $uploads[$key] = $entry['id'];
        $p->update([
            'images' => $images,
            'meta' => array_merge($p->meta ?? [], [
                'image_uploads' => $uploads,
                'image_text' => array_diff_key($p->meta['image_text'] ?? [], [$key => true]), // a library pick cancels "no image"
            ]),
        ]);
        $this->libraryPickerKey = null;
    }

    /** Continue: generate whatever was left, then render. */
    public function finalizarImagens(): void
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        if ($p) {
            $p->update(['status' => ClipRecord::STATUS_PLANNING, 'error' => null]);
            FinalizeClipPlanJob::dispatch($p->id);
        }
        $this->voltar();
    }

    /** Remove an image (by id) from a list and delete its file. @return array<int,array<string,mixed>> */
    private function dropImage(array $images, ?string $id): array
    {
        if (! $id) {
            return array_values($images);
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $kept = [];
        foreach ($images as $img) {
            if (($img['id'] ?? null) === $id) {
                if (! empty($img['path'])) {
                    $disk->delete($img['path']);
                }

                continue;
            }
            $kept[] = $img;
        }

        return array_values($kept);
    }

    // =====================================================================
    // Edit clip (animations → re-render)
    // =====================================================================

    public function editarClip(string $id): void
    {
        $p = $this->clips()->findOrFail($id);
        $this->editingId = $p->id;
        $this->editTitle = (string) $p->title;
        $this->musica = (string) ($p->meta['musica'] ?? '');
        $this->musicaVolume = (float) ($p->meta['musica_volume'] ?? 0.1);
        $this->images = array_values($p->images ?? []);
        $this->imageReplace = [];
        $this->editScenes = array_map(function ($s) {
            [$target, $text] = $this->extractLayerText($s['layers'] ?? []);

            return [
                'start' => $s['start'] ?? 0,
                'end' => $s['end'] ?? 0,
                'background' => $s['background'] ?? 'papyrus',
                'transitionIn' => $s['transitionIn'] ?? 'cut',
                'transitionOut' => $s['transitionOut'] ?? 'cut',
                'karaoke' => (bool) ($s['karaoke'] ?? false),
                'punchWord' => $s['punchWord'] ?? '',
                'layerText' => $text,
                'textTarget' => $target,
                'layers' => $s['layers'] ?? [], // preserved; text merged back on save
                'layersSummary' => implode(', ', array_map(fn ($l) => $l['type'] ?? '?', $s['layers'] ?? [])) ?: '—',
                'animacao' => $s['layers'][0]['type'] ?? null,
            ];
        }, $p->plan['scenes'] ?? []);
        $this->editPlanJson = json_encode($p->plan ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->editMode = 'cenas';
        $this->resetValidation();
        $this->view = 'editPlan';
    }

    /** Find a scene's primary editable text (element text, title, or caption). @return array{0:string,1:string} */
    private function extractLayerText(array $layers): array
    {
        $layer = $layers[0] ?? null;
        if (! $layer) {
            return ['text', ''];
        }
        if (isset($layer['params']['title'])) {
            return ['title', (string) $layer['params']['title']];
        }
        if (isset($layer['params']['caption'])) {
            return ['caption', (string) $layer['params']['caption']];
        }

        return ['text', (string) ($layer['text'] ?? '')];
    }

    public function adicionarCena(): void
    {
        $this->editScenes[] = [
            'start' => 0, 'end' => 1, 'background' => 'papyrus',
            'transitionIn' => 'crossfade', 'transitionOut' => 'cut',
            'karaoke' => false, 'punchWord' => '', 'layers' => [], 'layersSummary' => '—',
            'animacao' => null,
        ];
    }

    public function removerCena(int $i): void
    {
        unset($this->editScenes[$i]);
        $this->editScenes = array_values($this->editScenes);
        $this->animacaoPickerCena = null;
    }

    /** Open the animation picker for a scene, queueing any missing sample renders. */
    public function escolherAnimacao(int $i, EffectLibrary $library): void
    {
        $this->animacaoPickerCena = $i;

        // Make sure every pickable animation has a rendered sample to show.
        foreach (EffectLibrary::BUILTIN_SAMPLES as $slug => $s) {
            if (! in_array($slug, $library->disabledBuiltins(), true) && ! $library->previewExists($slug)) {
                RenderEffectSampleJob::dispatch($slug, $s['text'], $s['params']);
            }
        }
        foreach ($library->enabled() as $e) {
            if (! $library->previewExists($e->slug)) {
                RenderEffectSampleJob::dispatch($e->slug, $e->sample_text, $e->sample_params ?? []);
            }
        }
    }

    public function fecharAnimacoes(): void
    {
        $this->animacaoPickerCena = null;
    }

    /** Swap a scene's animation to $slug — sets the first layer's effect type. */
    public function mudarAnimacao(int $i, string $slug): void
    {
        if (! isset($this->editScenes[$i])) {
            return;
        }
        $layers = $this->editScenes[$i]['layers'] ?? [];
        if (empty($layers)) {
            $layers = [['type' => $slug, 'params' => []]];
        } else {
            $layers[0]['type'] = $slug;
        }

        $this->editScenes[$i]['layers'] = $layers;
        $this->editScenes[$i]['animacao'] = $slug;
        $this->editScenes[$i]['layersSummary'] = implode(', ', array_map(fn ($l) => $l['type'] ?? '?', $layers)) ?: '—';
        $this->animacaoPickerCena = null;
    }

    /** Built-in + enabled custom effects the picker offers, deduped by slug. @return array<int,array{slug:string,label:string,kind:string}> */
    private function animacoesDisponiveis(EffectLibrary $library): array
    {
        $lista = [];
        foreach (array_diff(array_keys(EffectLibrary::BUILTIN_SAMPLES), $library->disabledBuiltins()) as $slug) {
            $lista[$slug] = ['slug' => $slug, 'label' => EffectLibrary::BUILTIN_SAMPLES[$slug]['label'] ?? $slug, 'kind' => 'builtin'];
        }
        foreach ($library->enabled() as $e) {
            $lista[$e->slug] = ['slug' => $e->slug, 'label' => $e->display_name ?: $e->slug, 'kind' => 'custom'];
        }

        return array_values($lista);
    }

    // ---- per-scene image (see & replace in the scene editor) ----

    /** Per-scene replacement upload while editing a plan (keyed by scene index). */
    public array $sceneImageUploads = [];

    /** Scene index whose "pick from library" panel is open, or null. */
    public ?int $sceneLibraryPicker = null;

    /**
     * The layer in a scene that shows an image — the one already carrying a src,
     * otherwise the first image-reveal (an image slot waiting to be filled).
     * Returns null when nothing in the scene shows an image at all.
     *
     * Shared by the editor UI and aplicarImagemCena() on purpose: if the two
     * disagreed, the editor would offer an upload that silently went nowhere.
     */
    private function camadaDeImagem(int $i): ?int
    {
        $layers = $this->editScenes[$i]['layers'] ?? [];
        foreach ($layers as $li => $layer) {
            $src = is_array($layer) ? ($layer['params']['src'] ?? null) : null;
            if (is_string($src) && $src !== '') {
                return (int) $li;
            }
        }
        foreach ($layers as $li => $layer) {
            if (is_array($layer) && ($layer['type'] ?? null) === 'image-reveal') {
                return (int) $li;
            }
        }

        return null;
    }

    /**
     * The image slot of every scene that shows one, so the editor can preview and
     * replace it. `id` is null when the slot is still EMPTY — the scene shows an
     * image but none has been supplied yet (the user picked "upload my own" and
     * has not uploaded, or the image it referenced was removed). Those scenes get
     * the upload/library controls too; without them there is no way to give a
     * segment its picture from the editor.
     *
     * @return array<int,array{id:?string,layerIndex:int,library:bool,video:bool}>
     */
    public function getSceneImagesProperty(): array
    {
        $byId = collect($this->images)->keyBy('id');
        $out = [];
        foreach ($this->editScenes as $i => $scene) {
            $li = $this->camadaDeImagem($i);
            if ($li === null) {
                continue;
            }
            $src = $scene['layers'][$li]['params']['src'] ?? null;
            $img = (is_string($src) && $byId->has($src)) ? $byId[$src] : null;
            $out[$i] = [
                'id' => $img['id'] ?? null,
                'layerIndex' => $li,
                'library' => (bool) ($img['library'] ?? false),
                'video' => (bool) ($img['video'] ?? false),
            ];
        }

        return $out;
    }

    public function updatedSceneImageUploads(mixed $value, string $key): void
    {
        $i = (int) $key;
        if (! $value || ! isset($this->editScenes[$i])) {
            unset($this->sceneImageUploads[$key]);

            return;
        }
        $this->validateOnly("sceneImageUploads.$i", ["sceneImageUploads.$i" => 'file|'.Media::mimesRule().'|max:102400'], [
            "sceneImageUploads.$i.mimes" => 'The file must be an image or a video.',
            "sceneImageUploads.$i.max" => 'The file is too large (maximum 100 MB).',
        ]);

        $path = $value->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        $entry = array_merge(
            ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'description' => ''],
            $this->probeImage($abs),
        );
        $this->aplicarImagemCena($i, $entry);
        $this->guardarNaBiblioteca($value);
        unset($this->sceneImageUploads[$key]);
    }

    public function abrirBibliotecaCena(int $i): void
    {
        $this->sceneLibraryPicker = $this->sceneLibraryPicker === $i ? null : $i;
    }

    public function usarImagemBibliotecaCena(int $i, string $libId): void
    {
        $entry = app(ImageLibrary::class)->attachToClip($libId);
        if ($entry !== null) {
            $this->aplicarImagemCena($i, $entry);
        }
        $this->sceneLibraryPicker = null;
    }

    /** Point a scene's image layer at a new image (added to the clip's images). */
    private function aplicarImagemCena(int $i, array $entry): void
    {
        $target = $this->camadaDeImagem($i);
        if ($target === null) {
            return; // nothing in this scene shows an image
        }
        $layers = $this->editScenes[$i]['layers'] ?? [];
        $layers[$target]['params'] = array_merge($layers[$target]['params'] ?? [], ['src' => $entry['id']]);
        $this->editScenes[$i]['layers'] = $layers;
        $this->images[] = $entry;
    }

    /**
     * Save the raw Remotion plan (props) verbatim and re-render. No opinionated
     * normalization — what you write is what Remotion gets (plus runtime audio,
     * karaoke words and image staging added at render time).
     */
    public function guardarJson(): void
    {
        $decoded = json_decode($this->editPlanJson, true);
        if (! is_array($decoded)) {
            $this->addError('editPlanJson', 'Invalid JSON: '.json_last_error_msg());

            return;
        }
        if (! isset($decoded['scenes']) || ! is_array($decoded['scenes'])) {
            $this->addError('editPlanJson', 'The plan needs a "scenes" list.');

            return;
        }

        $p = $this->clips()->findOrFail($this->editingId);
        $c = config('contentmachine.clips');
        // Keep render essentials sane if the author removed them.
        $decoded['duration'] = (float) ($decoded['duration'] ?? $p->plan['duration'] ?? 0.0);
        $decoded['width'] = (int) ($decoded['width'] ?? $c['width']);
        $decoded['height'] = (int) ($decoded['height'] ?? $c['height']);
        $decoded['fps'] = (int) ($decoded['fps'] ?? $c['fps']);

        if ($decoded['duration'] <= 0) {
            $this->addError('editPlanJson', 'The plan needs "duration" (seconds) greater than zero.');

            return;
        }

        $oldImages = $p->images ?? [];
        $decoded = $this->stripMissingImageRefs($decoded);

        $p->update([
            'title' => $this->editTitle ?: $p->title,
            'plan' => $decoded,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta($p->meta ?? []),
            'status' => ClipRecord::STATUS_RENDERING,
            'error' => null,
        ]);
        $this->pruneImageFiles($oldImages);

        RenderJob::dispatch($p->id);
        $this->voltar();
    }

    /** Merge the edited text back into the scene's first layer (text/title/caption). */
    private function applyLayerText(array $layers, string $target, string $text): array
    {
        if (empty($layers)) {
            return $layers;
        }
        if ($target === 'title') {
            $layers[0]['params']['title'] = $text;
        } elseif ($target === 'caption') {
            $layers[0]['params']['caption'] = $text;
        } else {
            $layers[0]['text'] = $text === '' ? null : $text;
        }

        return $layers;
    }

    public function guardarPlano(PlanValidator $validator): void
    {
        $this->validate([
            'editTitle' => 'nullable|string|max:120',
            'editScenes' => 'array|min:1',
            'editScenes.*.start' => 'required|numeric|min:0',
            'editScenes.*.end' => 'required|numeric|gt:editScenes.*.start',
            'editScenes.*.background' => 'required|in:'.implode(',', self::BACKGROUNDS),
            'editScenes.*.transitionIn' => 'required|in:'.implode(',', self::TRANSITIONS),
            'editScenes.*.punchWord' => 'nullable|string|max:60',
            'editScenes.*.layerText' => 'nullable|string|max:280',
        ], [
            'editScenes.min' => 'The clip needs at least one scene.',
            'editScenes.*.end.gt' => 'The end must be greater than the start.',
        ]);

        $p = $this->clips()->findOrFail($this->editingId);
        $oldImages = $p->images ?? [];
        $plan = $p->plan ?? [];
        $plan['scenes'] = array_map(fn ($s) => [
            'start' => (float) $s['start'],
            'end' => (float) $s['end'],
            'background' => $s['background'],
            'transitionIn' => $s['transitionIn'],
            'transitionOut' => $s['transitionOut'] ?? 'cut',
            'karaoke' => (bool) $s['karaoke'],
            'punchWord' => ($s['punchWord'] ?? '') === '' ? null : $s['punchWord'],
            'layers' => $this->applyLayerText($s['layers'] ?? [], $s['textTarget'] ?? 'text', $s['layerText'] ?? ''),
        ], $this->editScenes);
        $plan = $this->stripMissingImageRefs($plan);
        $plan = $validator->validate($plan);

        $p->update([
            'title' => $this->editTitle ?: $p->title,
            'plan' => $plan,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta($p->meta ?? []),
            'status' => ClipRecord::STATUS_RENDERING,
            'error' => null,
        ]);
        $this->pruneImageFiles($oldImages);

        RenderJob::dispatch($p->id);
        $this->voltar();
    }

    /** Merge the current music choice into a meta array (base preserved). */
    private function musicaMeta(array $base = []): array
    {
        return array_merge($base, [
            'musica' => trim($this->musica),
            'musica_volume' => $this->musicaVolume,
        ]);
    }

    // =====================================================================
    // Edit transcript (→ replans + re-render)
    // =====================================================================

    public function editarTranscricao(string $id): void
    {
        $p = $this->clips()->findOrFail($id);
        $this->editingId = $p->id;
        $this->editTranscriptText = (string) ($p->transcript['text'] ?? '');
        $this->resetValidation();
        $this->view = 'editTranscript';
    }

    public function regenerar(): void
    {
        $this->validate(
            ['editTranscriptText' => 'required|string|max:5000'],
            ['editTranscriptText.required' => 'The transcript cannot be empty.'],
        );

        $p = $this->clips()->findOrFail($this->editingId);
        $transcript = $p->transcript ?? [];
        $transcript['text'] = trim($this->editTranscriptText);
        $transcript['words'] = TranscriptRebuilder::rebuild(
            $this->editTranscriptText,
            $transcript['words'] ?? [],
            (float) ($transcript['duration'] ?? 0.0)
        );

        $p->update([
            'transcript' => $transcript,
            'status' => ClipRecord::STATUS_PLANNING,
            'error' => null,
        ]);

        PlanAnimationsJob::dispatch($p->id);
        $this->voltar();
    }

    // =====================================================================
    // Delete
    // =====================================================================

    public function apagar(string $id): void
    {
        $p = $this->clips()->find($id);
        if (! $p) {
            return;
        }

        // The uploaded source lives on the storage disk; the record + its render
        // outputs are removed by the store.
        if ($p->source_path) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($p->source_path);
        }

        $p->delete();
    }

    // =====================================================================
    // Data for the view
    // =====================================================================

    /** @return Collection<int,ClipRecord> */
    public function getProjectsProperty()
    {
        return $this->clips()->all()->take(50);
    }

    public function getEditingProperty(): ?ClipRecord
    {
        return $this->editingId ? $this->clips()->find($this->editingId) : null;
    }

    public function getHasActiveProperty(): bool
    {
        return $this->projects->contains(fn (ClipRecord $p) => $p->isActive());
    }

    private function tituloDe(?string $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? 'Untitled' : Str::limit($text, 48);
    }

    public function render(MusicLibrary $music)
    {
        $bgReady = [];
        if ($this->view === 'backgrounds') {
            $bgLibrary = app(BackgroundLibrary::class);
            foreach ($this->backgrounds as $bg) {
                if ($bgLibrary->previewFileFor($bg) !== null) {
                    $bgReady[] = $bg->id();
                }
            }
        }

        $animacoes = [];
        $sfxReady = [];
        if ($this->view === 'editPlan') {
            $library = app(EffectLibrary::class);
            $animacoes = $this->animacoesDisponiveis($library);
            $sfxReady = array_values(array_filter(
                array_column($animacoes, 'slug'),
                fn ($slug) => $library->previewExists($slug),
            ));
        }

        return view('livewire.clips-animados', [
            'backgrounds' => self::BACKGROUNDS,
            'transitions' => self::TRANSITIONS,
            'musicas' => $music->all(),
            'bgReady' => $bgReady,
            'animacoes' => $animacoes,
            'sfxReady' => $sfxReady,
        ]);
    }
}
