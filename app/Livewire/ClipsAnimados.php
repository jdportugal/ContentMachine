<?php

namespace App\Livewire;

use App\Jobs\Clips\AnalyzePropertyJob;
use App\Jobs\Clips\AnimatePropertyJob;
use App\Jobs\Clips\CollectSiteJob;
use App\Jobs\Clips\FetchWebImagesJob;
use App\Jobs\Clips\FillChosenVisualsJob;
use App\Jobs\Clips\FinalizeClipPlanJob;
use App\Jobs\Clips\GenerateBackgroundJob;
use App\Jobs\Clips\GenerateRequestImageJob;
use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\ImproveClipJob;
use App\Jobs\Clips\NarratePropertyJob;
use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RegenerateVoiceoverJob;
use App\Jobs\Clips\RenderBackgroundReelJob;
use App\Jobs\Clips\RenderBackgroundSampleJob;
use App\Jobs\Clips\RenderEffectSampleJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\RestagePropertyRoomJob;
use App\Jobs\Clips\TranscribeJob;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\BackgroundPortability;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\ImageProbe;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\Media;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use App\Services\Clips\Store\VfxStore;
use App\Services\Clips\TranscriptRebuilder;
use App\Services\Costs\CostLedger;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectResources;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\RealEstate\PropertyVideoEditor;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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
    public function mount(string $view = 'dashboard'): void
    {
        $this->listingAfterIntroLine = app(EffectStore::class)->afterIntroLine();
        // Route default (see clips-animados.backgrounds) — opening the backgrounds
        // studio by URL must do what the in-page button does, previews included.
        if ($view === 'backgrounds') {
            $this->abrirBackgrounds();
        }

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

        // A clip cut in the Shorts Generator, staged on the clips disk. Open the
        // normal "video + animations" form with that file already attached.
        if (($video = session('animado_video')) !== null) {
            $this->videoPronto = (string) $video;
            $this->videoProntoNome = (string) session('animado_video_nome', 'Cut clip');
            session()->forget(['animado_video', 'animado_video_nome']);
            // The cut carries no music of its own; the picker below owns the choice.
            $this->musica = 'nenhuma';
            $this->musicaVolume = 0.0;
            $this->createType = 'overlay';
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

    /** Own voiceover uploads on the "pick the voice" card, keyed by clip id. */
    public array $takeUploads = [];

    /** The agent's photo uploaded on a property card, keyed by clip id. */
    public array $agentPhotos = [];

    /** null | animation | overlay */
    public ?string $createType = null;

    public string $text = '';

    public $audio = null;

    public $video = null;

    /** News LEAD — optional line pinned on screen for the whole clip. */
    public string $lead = '';

    /** Extra direction for THIS clip, on top of the project's style manual. */
    public string $instructions = '';

    /** Halve this clip's image budget — fewer generated pictures, more text/chart scenes. */
    public bool $poucasImagens = false;

    /** Plan this clip with no image layer at all — nothing generated, filmed or asked for. */
    public bool $semImagens = false;

    /** Build the clip only from the uploaded images — every one shown, nothing generated or fetched. */
    public bool $soReferencias = false;

    /** Where the karaoke captions sit: bottom | middle | top | none. */
    public string $legendas = 'bottom';

    /** Land the clip in the Finished hub as soon as it renders, with no promote click. */
    public bool $paraFinalizados = false;

    /** Let the AI write a bespoke animation per scene instead of using the SFX vocabulary. */
    public bool $freeSfx = false;

    /** which presentation styles the AI may use for a video clip */
    public array $allowedPresents = ['video', 'over', 'split', 'animation'];

    // images (with description) so the planner can use them animated
    public $newImage = null;

    public string $newImageDesc = '';

    /** Bulk upload: many files at once, described automatically at planning. */
    public array $newImages = [];

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

    /** Overlay clip? Then a scene may be switched to the raw source video. */
    /**
     * Output frame for the clip: an aspect key from VfxStore::ASPECTS, or 'auto'
     * — match the uploaded video, which is what an overlay clip did before there
     * was a choice.
     */
    public string $formato = 'auto';

    /** A video already on disk (staged by the Shorts Generator) instead of an upload. */
    public ?string $videoPronto = null;

    public string $videoProntoNome = '';

    public bool $editOverlay = false;

    /** Text-input clip? Then its voiceover is TTS and can be spoken again. */
    public bool $editSpeakable = false;

    /** Set by guardarPlanoComVoz: save the edits, then re-speak instead of re-rendering. */
    private bool $regenerarVoz = false;

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
        $this->createType = in_array($type, ['animation', 'overlay', 'property'], true) ? $type : null;
        $this->afterIntroLine = app(EffectStore::class)->afterIntroLine();
        $this->licence = app(ProjectResources::class)->agent()['licence'];
        $this->resetValidation();
    }

    public function voltar(): void
    {
        $this->reset(['createType', 'propertySource', 'propertyPhotos', 'propertyListingUrl', 'propertyVoice', 'propertyAspect', 'propertyAgent', 'propertyPhone', 'propertyAgency', 'propertyGender', 'propertyFacts', 'propertyPhoto', 'licence', 'afterIntro', 'afterIntroLine', 'text', 'audio', 'video', 'videoPronto', 'videoProntoNome', 'formato', 'lead', 'instructions', 'poucasImagens', 'semImagens', 'soReferencias', 'legendas', 'newImages', 'paraFinalizados', 'freeSfx', 'allowedPresents', 'newImage', 'newImageDesc', 'images', 'imageReplace', 'musica', 'musicaVolume', 'background', 'editingId', 'editTitle', 'editScenes', 'editMode', 'editOverlay', 'editSpeakable', 'editPlanJson', 'editTranscriptText', 'bgPrompt', 'bgVideo', 'bgVideoName', 'editingBgId', 'bgEditPrompt', 'reviewingId', 'reviewUploads', 'libraryPickerKey', 'sceneImageUploads', 'sceneLibraryPicker', 'promptKey', 'promptEdits']);
        $this->resetValidation();
        $this->view = 'dashboard';
    }

    public function adicionarImagem(): void
    {
        $this->validate([
            'newImage' => 'required|file|'.Media::mimesRule().'|max:102400',
            'newImageDesc' => 'nullable|string|max:200',
        ], [
            'newImage.required' => 'Choose an image or video.',
            'newImage.mimes' => 'The file must be an image or a video.',
            'newImage.max' => 'The file is too large (maximum 100 MB).',
        ], ['newImage' => 'file', 'newImageDesc' => 'description']);

        $path = $this->newImage->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        $this->images[] = array_merge(
            ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'description' => trim($this->newImageDesc), 'name' => $this->newImage->getClientOriginalName()],
            $this->probeImage($abs),
        );
        $this->guardarNaBiblioteca($this->newImage, trim($this->newImageDesc));
        $this->reset(['newImage', 'newImageDesc']);
    }

    /**
     * Add every selected file at once, with no description: the planning job
     * describes them by looking at them (ImageDescriber). Kept out of the project
     * library — a listing's twenty room photos would only be noise for the library
     * matcher in later clips.
     */
    public function updatedNewImages(): void
    {
        $this->validate(
            ['newImages.*' => 'file|'.Media::mimesRule().'|max:102400'],
            ['newImages.*.mimes' => 'Every file must be an image or a video.', 'newImages.*.max' => 'A file is too large (maximum 100 MB).'],
        );

        foreach ($this->newImages as $file) {
            $path = $file->store('clips/uploads');
            $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
            $this->images[] = array_merge(
                ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'description' => '', 'name' => $file->getClientOriginalName()],
                $this->probeImage($abs),
            );
        }
        $this->reset('newImages');
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

    /**
     * "Only reference images" with no image attached plans a clip that may show
     * nothing — and the planner then invents image names from the instructions
     * ("main.webp"), which crashed the render. Say so before anything is queued.
     */
    private function exigirImagensSeSoReferencias(): void
    {
        // Every submit path passes here, so the instructions' ceiling is checked
        // once for all of them (the form caps it too; this is the trusted check).
        $this->validate(['instructions' => 'nullable|string|max:10000'], ['instructions.max' => 'The instructions are too long (maximum 10,000 characters).']);

        $temImagem = collect($this->images)->contains(fn ($i) => empty($i['video']));
        if ($this->soReferencias && ! $this->semImagens && ! $temImagem) {
            throw ValidationException::withMessages([
                'newImages.*' => 'Only reference images is on but no image is attached — add the images (or untick the option).',
            ]);
        }
    }

    /** The caption placement to store — anything unexpected falls back to bottom. */
    private function legendasValidas(): string
    {
        return in_array($this->legendas, ClipRecord::CAPTIONS, true) ? $this->legendas : 'bottom';
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
        $library = app(BackgroundLibrary::class);

        return $this->backgrounds()->all()->contains(function (EffectRecord $b) use ($library) {
            if (in_array($b->status, [EffectRecord::STATUS_PENDING, EffectRecord::STATUS_UPDATING], true)) {
                return true;
            }

            // ensureBackgroundPreviews() queues a render for an ACTIVE background
            // that has none — after a design-system change, that is every one of
            // them. Their status never moves, so nothing here called the page busy,
            // wire:poll stayed off, and the renders landed on a page that never
            // asked again: the backgrounds looked permanently broken until a manual
            // reload. A preview on its way is exactly what "busy" means.
            return $b->kind !== BackgroundStore::KIND_VIDEO
                && $b->isActive()
                && $library->previewFileFor($b) === null;
        });
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

    /** A property listing URL to turn into a clip in one go. */
    public string $listingUrl = '';

    /** Optional agent photo for the listing import: used instead of the one on the listing. */
    public $listingAgentPhoto = null;

    /** Optional listing video: cut into one clip per room, used instead of the photos. */
    public $listingVideo = null;

    /** Optional video played full-screen right after the intro. */
    public $listingAfterIntro = null;

    /** What the voiceover says over the after-intro video, right after the intro (starts as the project's line). */
    public string $listingAfterIntroLine = '';

    /** With a property video: also use the listing's photos (off: the reel is all footage). */
    public bool $listingMixPhotos = false;

    /** Notes for the script writer: they override what the listing page says. */
    public string $listingNotes = '';

    /** With a property video: open on the best clips, music only, cut on the beat, before the voice. */
    public bool $listingBeatOpening = false;

    /** The listing's music ('' = the project's). */
    public string $listingMusic = '';

    /** Queue the listing importer: it scrapes, scripts, narrates and collects the images, then plans. */
    public function importarAnuncio(): void
    {
        $this->listingUrl = trim($this->listingUrl);
        $this->validate(
            ['listingUrl' => ['required', 'url:https,http', fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail(ListingScraper::UNSUPPORTED)],
                'listingAgentPhoto' => 'nullable|image|max:10240',
                'listingVideo' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v|max:1048576',
                'listingAfterIntro' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v|max:1048576',
                'listingAfterIntroLine' => 'nullable|string|max:200',
                'listingNotes' => 'nullable|string|max:3000',
                'listingMusic' => 'nullable|string|max:200',
                'listingBeatOpening' => [fn ($a, $v, $fail) => $v && ! $this->listingVideo && ! $this->listingAfterIntro ? $fail('The beat-synced opening is cut from the property video — add one.') : null]],
            ['listingUrl.required' => 'Paste the listing URL.', 'listingUrl.url' => 'That is not a valid URL.'],
        );

        // The two videos in each other's field (the walkthrough as the after-intro): put them right.
        [$property, $afterIntro] = [$this->listingVideo, $this->listingAfterIntro];
        $swapped = self::videosTrocados($property, $afterIntro);
        if ($swapped) {
            [$property, $afterIntro] = [$afterIntro, $property];
        }
        app(ListingImport::class)->start($this->listingUrl, [
            'agent_photo' => $this->listingAgentPhoto?->store('clips/uploads'),
            'listing_video' => $property?->store('clips/uploads'),
            'after_intro' => $afterIntro?->store('clips/uploads'),
            'videos_swapped' => $swapped,
            'mix_photos' => $property !== null && $this->listingMixPhotos,
            'beat_opening' => $property !== null && $this->listingBeatOpening ? ['clips' => []] : null,
            'after_intro_line' => trim($this->listingAfterIntroLine),
            'script_notes' => trim($this->listingNotes)]
            + ($this->listingMusic !== '' ? ['musica' => $this->listingMusic] : []));
        $this->reset('listingUrl', 'listingAgentPhoto', 'listingVideo', 'listingAfterIntro', 'listingNotes', 'listingMixPhotos', 'listingBeatOpening', 'listingMusic');
        $this->listingAfterIntroLine = app(EffectStore::class)->afterIntroLine();
    }

    /**
     * The walkthrough uploaded as the after-intro (and the short brand clip, or
     * nothing, as the property video): an after-intro is a few seconds, a
     * property video is the tour. Over 20 s and longer than the property video
     * means the two are in each other's field.
     */
    private static function videosTrocados(mixed $property, mixed $afterIntro): bool
    {
        $len = function ($upload) {
            try {
                return $upload ? PropertyVideoAnalyzer::probe($upload->getRealPath())['duration'] : 0.0;
            } catch (\RuntimeException) {
                return 0.0;
            }
        };
        $a = $len($afterIntro);

        return $a > 20 && $a > $len($property);
    }

    /** Keep voiceover take $n, drop the others, and let the clip carry on to planning. */
    public function escolherTake(string $id, int $n): void
    {
        $p = $this->clips()->find($id);
        $takes = $p?->meta['voice_takes'] ?? [];
        if (! $p || $p->status !== ClipRecord::STATUS_CHOOSING_VOICE || ! isset($takes[$n])) {
            return;
        }
        Storage::disk(config('contentmachine.clips.disk'))->delete(array_values(array_diff($takes, [$takes[$n]])));
        if (! empty($p->meta['property']['proxy'])) {
            // A voiceover for a reel already cut: cut it again with the voice (animations reused).
            $old = $p->meta['property']['voice'] ?? null;
            if ($old && $old !== $takes[$n]) {
                Storage::disk(config('contentmachine.clips.disk'))->delete($old);
            }
            $p->update([
                'status' => ClipRecord::STATUS_ANIMATING,
                'meta' => array_merge($p->meta, ['voice_takes' => null, 'property' => array_merge($p->meta['property'], ['voice' => $takes[$n]])]),
            ]);
            AnimatePropertyJob::dispatch($p->id);

            return;
        }
        if (! empty($p->meta['property'])) {
            // A walkthrough: the voice is only the narration; the footage gets analysed next.
            $p->update([
                'status' => ClipRecord::STATUS_ANALYZING,
                'meta' => array_merge($p->meta, ['voice_takes' => null, 'property' => array_merge($p->meta['property'], ['voice' => $takes[$n]])]),
            ]);
            AnalyzePropertyJob::dispatch($p->id);

            return;
        }
        $p->update([
            'source_path' => $takes[$n],
            'status' => ClipRecord::STATUS_TRANSCRIBING,
            'meta' => array_merge($p->meta, ['voice_takes' => null]),
        ]);
        TranscribeJob::dispatch($p->id);
    }

    // ---- property walkthrough (footage + listing → staged-room reel) ----

    /** The listing the walkthrough belongs to (voiceover, agent card). */
    public string $propertyListingUrl = '';

    /** none | listing (written and narrated from the listing) | upload (the user's own audio, in $audio) */
    public string $propertyVoice = 'none';

    // Without a listing URL: the agent for the outro card, and the house facts.
    public string $propertyAgent = '';

    public string $propertyPhone = '';

    public string $propertyAgency = 'RE/MAX';

    public string $propertyGender = 'm';

    public string $propertyFacts = '';

    public $propertyPhoto = null;

    /** Made by hand: the licence (AMI) for the agent's card. Starts as the project's; empty → none shown. */
    public string $licence = '';

    /** Made by hand (animation / property): this clip's after-intro video, and the line said over it. */
    public $afterIntro = null;

    public string $afterIntroLine = '';

    /** The reel's final format (PropertyVideoEditor::ASPECTS). */
    public string $propertyAspect = 'source';

    /** Photos instead of a walkthrough video: each one is a room the camera moves through. */
    public string $propertySource = 'video';

    public $propertyPhotos = [];

    public function submitProperty(): void
    {
        $this->propertyListingUrl = trim($this->propertyListingUrl);
        $photos = $this->propertySource === 'photos';
        $this->validate($photos
            ? ['propertyPhotos' => 'required|array|min:1|max:15', 'propertyPhotos.*' => 'image|max:20480']
            : [], [
                'propertyPhotos.required' => 'Add the photos to animate (up to 15).',
                'propertyPhotos.max' => 'Up to 15 photos.',
                'propertyPhotos.*.image' => 'Only photos (jpg, png, webp).',
            ]);
        $this->validate([
            'video' => ($photos ? 'nullable' : 'required').'|file|mimetypes:video/mp4,video/quicktime|max:1024000',
            'propertyListingUrl' => [$this->propertyVoice === 'listing' ? 'required' : 'nullable', 'url:https,http', fn ($a, $v, $fail) => $v === '' || ListingScraper::supports($v) ? null : $fail(ListingScraper::UNSUPPORTED)],
            'propertyVoice' => 'required|in:none,listing,upload',
            'audio' => ($this->propertyVoice === 'upload' ? 'required' : 'nullable').'|file|mimetypes:audio/*,video/webm,video/mp4|max:51200',
        ], [
            'video.required' => 'Upload the walkthrough video (mp4 or mov).',
            'video.mimetypes' => 'Unsupported video format. Use mp4 or mov.',
            'video.max' => 'The video is too large (maximum 1 GB).',
            'propertyListingUrl.required' => 'Paste the listing URL — the voiceover is written from it.',
            'audio.required' => 'Upload your voiceover (mp3, wav or m4a).',
        ]);
        $this->validate([
            'propertyAgent' => 'nullable|string|max:80',
            'propertyPhone' => 'nullable|string|max:40',
            'propertyAgency' => 'nullable|string|max:80',
            'propertyGender' => 'in:m,f',
            'propertyFacts' => 'nullable|string|max:1500',
            'propertyPhoto' => 'nullable|image|max:10240',
            'licence' => 'nullable|string|max:60',
            ...$this->afterIntroRules(),
            'propertyAspect' => 'in:'.implode(',', array_keys(PropertyVideoEditor::ASPECTS)),
        ]);

        $property = ($photos
            ? ['photos' => array_map(fn ($f) => $f->store('clips/uploads'), array_values($this->propertyPhotos))]
            : ['video' => $this->video->store('clips/uploads')]) + ['narrate' => $this->propertyVoice === 'listing', 'aspect' => $this->propertyAspect];
        if ($this->propertyVoice === 'upload' && $this->audio) {
            $property['voice'] = $this->audio->store('clips/uploads');
        }
        $listing = $this->propertyListingUrl !== '';
        // No listing: the agent and facts typed in the form stand in for it.
        $manual = ['url' => $this->propertyListingUrl];
        $images = [];
        // Fields left empty fall back to the project's agent (Design System → Project resources).
        $resources = app(ProjectResources::class);
        $profile = $resources->agent();
        $agentName = trim($this->propertyAgent) ?: $profile['name'];
        if (! $listing && $agentName !== '') {
            $manual += ['agent' => $agentName, 'phone' => trim($this->propertyPhone) ?: $profile['phone'], 'email' => $profile['email'], 'licence' => trim($this->licence),
                'agency' => trim($this->propertyAgency) ?: 'RE/MAX', 'gender' => $this->propertyGender];
            $path = $this->propertyPhoto?->store('clips/uploads');
            if (! $path && ($own = $resources->agentPhotoPath())) {
                $path = 'clips/uploads/'.Str::random(40).'.'.pathinfo($own, PATHINFO_EXTENSION);
                Storage::disk(config('contentmachine.clips.disk'))->put($path, (string) file_get_contents($own));
            }
            if ($path) {
                $images[] = ['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'name' => $manual['agent'].'.'.pathinfo($path, PATHINFO_EXTENSION),
                    'description' => 'Retrato '.($this->propertyGender === 'f' ? 'da consultora' : 'do consultor')." {$manual['agent']}, da {$manual['agency']}."];
            }
        }
        if (! $listing) {
            // "Label: value" per line (a bare line is kept as is).
            $manual['details'] = collect(preg_split('/\R/', $this->propertyFacts))->map(fn ($l) => trim($l))->filter()
                ->mapWithKeys(fn ($l, $i) => str_contains($l, ':') ? [trim(strstr($l, ':', true)) => trim(substr(strstr($l, ':'), 1))] : ["Facto {$i}" => $l])->all();
        }
        $clip = $this->clips()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'status' => $listing ? ClipRecord::STATUS_IMPORTING : ClipRecord::STATUS_ANALYZING,
            'title' => $photos ? 'Fotografias — '.count($property['photos']).' divisões' : $this->video->getClientOriginalName(),
            'images' => $images ?: null,
            'meta' => $this->musicaMeta([
                'listing' => $manual,
                'property' => $property,
                'allowed_present' => ['video', 'over'],
                'formato' => 'auto',
                'licence' => trim($this->licence),
            ] + $this->afterIntroMeta()),
        ]);

        // A listing gives the agent's outro, the house facts and,
        // if asked, the narration; the importer then hands over to the footage.
        $listing ? ImportListingJob::dispatch($clip->id, $this->propertyListingUrl) : AnalyzePropertyJob::dispatch($clip->id);
        $this->voltar();
    }

    /**
     * The sections the user marked on the walkthrough's timeline become the
     * rooms to modernise: save them and make each one's checked modern still.
     *
     * @param  list<array{start:float|int|string,end:float|int|string,note?:string}>  $sections
     */
    public function stageSections(string $id, array $sections): void
    {
        $p = $this->clips()->find($id);
        $duration = (float) ($p?->meta['property']['duration'] ?? 0);
        if (! $p || $p->status !== ClipRecord::STATUS_CHOOSING_SECTIONS || $duration <= 0) {
            return;
        }

        $clean = collect($sections)
            ->map(fn ($s) => [
                'start' => round(max(0.0, min((float) ($s['start'] ?? 0), $duration)), 2),
                'end' => round(max(0.0, min((float) ($s['end'] ?? 0), $duration)), 2),
                'note' => Str::limit(trim((string) ($s['note'] ?? '')), 300, ''),
            ])
            // The picker caps a section at exactly 10 s, which rounding can push to 10.01: trim, never drop.
            ->map(fn ($s) => ['end' => round(min($s['end'], $s['start'] + 10.0), 2)] + $s)
            ->sortBy('start')->values();
        $short = $clean->contains(fn ($s) => $s['end'] - $s['start'] < 1.0);
        $overlap = $clean->contains(fn ($s, $i) => $i > 0 && $s['start'] < $clean[$i - 1]['end'] - 0.01);
        if ($clean->isEmpty() || $clean->count() > 10 || $overlap || $short) {
            $this->addError('sections.'.$id, 'Mark 1 to 10 sections of 1–10 s each, without overlaps.');

            return;
        }

        $p->update([
            'status' => ClipRecord::STATUS_ANALYZING,
            'meta' => array_merge($p->meta, ['property' => array_merge($p->meta['property'], ['sections' => $clean->all()])]),
        ]);
        AnalyzePropertyJob::dispatch($id, true);
    }

    /**
     * Back to the section picker from the approval step or a finished reel: the
     * sections stay marked, and the ones left untouched keep their stills and
     * animations when they're saved again.
     */
    public function reselectSections(string $id): void
    {
        $p = $this->clips()->find($id);
        $ok = [ClipRecord::STATUS_REVIEWING_ROOMS, ClipRecord::STATUS_DONE, ClipRecord::STATUS_FAILED];
        if (! $p || empty($p->meta['property']['proxy']) || ! in_array($p->status, $ok, true)
            || collect($p->meta['property']['rooms'] ?? [])->contains(fn ($r) => ! empty($r['anim']['staging']))) {
            return;
        }
        $p->update(['status' => ClipRecord::STATUS_CHOOSING_SECTIONS, 'error' => null]);
    }

    /** Write and narrate a voiceover for a finished property reel from its listing link and/or the house details typed. */
    public function narrateProperty(string $id, string $info, string $url = ''): void
    {
        $p = $this->clips()->find($id);
        if (! $p || empty($p->meta['property']['proxy']) || $p->status !== ClipRecord::STATUS_DONE) {
            return;
        }
        $info = trim(mb_substr($info, 0, 4000));
        $url = trim($url);
        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError('narrate.'.$id, 'That listing link is not a valid URL.');

            return;
        }
        if ($info === '' && $url === '') {
            $this->addError('narrate.'.$id, 'Paste the listing link or write the house details first.');

            return;
        }
        $p->update(['status' => ClipRecord::STATUS_IMPORTING, 'meta' => array_merge($p->meta, ['step' => 'Writing and narrating the voiceover'])]);
        NarratePropertyJob::dispatch($id, $info, $url);
    }

    /** Remove a finished property reel's voiceover and cut it again without one. */
    public function removePropertyVoice(string $id): void
    {
        $p = $this->clips()->find($id);
        if (! $p || empty($p->meta['property']['voice']) || $p->status !== ClipRecord::STATUS_DONE) {
            return;
        }
        Storage::disk(config('contentmachine.clips.disk'))->delete($p->meta['property']['voice']);
        $prop = $p->meta['property'];
        unset($prop['voice']);
        $p->update(['status' => ClipRecord::STATUS_ANIMATING, 'meta' => array_merge($p->meta, ['property' => $prop])]);
        AnimatePropertyJob::dispatch($id);
    }

    /** Change a property reel's final format before it's cut (a new cut is needed for it to show). */
    public function setPropertyAspect(string $id, string $aspect): void
    {
        $p = $this->clips()->find($id);
        $ok = [ClipRecord::STATUS_CHOOSING_SECTIONS, ClipRecord::STATUS_REVIEWING_ROOMS];
        if (! $p || empty($p->meta['property']) || ! in_array($p->status, $ok, true) || ! array_key_exists($aspect, PropertyVideoEditor::ASPECTS)) {
            return;
        }
        $prop = $p->meta['property'];
        $prop['aspect'] = $aspect;
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
    }

    /** Another take of a room's staged still, with the (possibly edited) prompt. */
    public function restageRoom(string $id, int $i, string $prompt = ''): void
    {
        $p = $this->clips()->find($id);
        $prop = $p?->meta['property'] ?? null;
        if (! $p || $p->status !== ClipRecord::STATUS_REVIEWING_ROOMS || empty($prop['rooms'][$i]['anim'])) {
            return;
        }
        $prop['rooms'][$i]['anim'] = array_merge($prop['rooms'][$i]['anim'], array_filter(['prompt' => trim($prompt)]), ['staging' => true, 'enabled' => true]);
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
        RestagePropertyRoomJob::dispatch($id, $i);
    }

    /** Use another of the room's modernised images (its animation is made again). */
    public function chooseStill(string $id, int $i, int $n): void
    {
        $p = $this->clips()->find($id);
        $prop = $p?->meta['property'] ?? null;
        $pick = $prop['rooms'][$i]['anim']['candidates'][$n] ?? null;
        if (! $p || $p->status !== ClipRecord::STATUS_REVIEWING_ROOMS || ! $pick || ! empty($prop['rooms'][$i]['anim']['staging'])) {
            return;
        }
        $anim = &$prop['rooms'][$i]['anim'];
        if (($anim['staged'] ?? null) === $pick['path']) {
            return;
        }
        if (! empty($anim['video'])) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($anim['video']);
        }
        unset($anim['video'], $anim['clip_problems'], $anim['onset']);
        $anim = array_merge($anim, ['staged' => $pick['path'], 'alignment' => $pick['score'], 'problems' => $pick['problems'], 'error' => null]);
        unset($anim);
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
    }

    /** Animate this room, or keep it as plain footage. */
    public function toggleRoom(string $id, int $i): void
    {
        $p = $this->clips()->find($id);
        $prop = $p?->meta['property'] ?? null;
        if (! $p || $p->status !== ClipRecord::STATUS_REVIEWING_ROOMS || empty($prop['rooms'][$i]['anim'])) {
            return;
        }
        $prop['rooms'][$i]['anim']['enabled'] = empty($prop['rooms'][$i]['anim']['enabled']);
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
    }

    /** A photo's camera: into the room or back out of it (its animation is made again). */
    public function flipRoomMotion(string $id, int $i): void
    {
        $p = $this->clips()->find($id);
        $prop = $p?->meta['property'] ?? null;
        if (! $p || $p->status !== ClipRecord::STATUS_REVIEWING_ROOMS || empty($prop['photos']) || empty($prop['rooms'][$i]['anim'])) {
            return;
        }
        $anim = &$prop['rooms'][$i]['anim'];
        $anim['motion'] = $anim['motion'] === 'backward' ? 'forward' : 'backward';
        if (! empty($anim['video'])) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($anim['video']);
        }
        unset($anim['video'], $anim['clip_problems'], $anim['onset']);
        unset($anim);
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
    }

    /** Stills approved: animate the enabled rooms and cut the reel. */
    public function animateProperty(string $id): void
    {
        $p = $this->clips()->find($id);
        $rooms = $p?->meta['property']['rooms'] ?? [];
        if (! $p || $p->status !== ClipRecord::STATUS_REVIEWING_ROOMS || collect($rooms)->contains(fn ($r) => ! empty($r['anim']['staging']))) {
            return;
        }
        $p->update(['status' => ClipRecord::STATUS_ANIMATING]);
        AnimatePropertyJob::dispatch($id);
    }

    /** A voiceover the user recorded/generated elsewhere, uploaded instead of any take. */
    public function updatedTakeUploads(mixed $value, string $id): void
    {
        $this->validate(
            ["takeUploads.$id" => 'required|file|mimetypes:audio/*,video/webm,video/mp4|max:51200'],
            ["takeUploads.$id.mimetypes" => 'Unsupported audio format. Upload mp3/wav/m4a.'],
        );
        $p = $this->clips()->find($id);
        if ($p && $p->status === ClipRecord::STATUS_CHOOSING_VOICE) {
            $takes = $p->meta['voice_takes'] ?? [];
            $takes[] = $value->store('clips/uploads');
            $p->update(['meta' => array_merge($p->meta, ['voice_takes' => $takes])]);
            $this->escolherTake($id, count($takes) - 1);
        }
        unset($this->takeUploads[$id]);
    }

    /** The agent's photo for a property reel's outro card: replaces the one it had. */
    public function updatedAgentPhotos(mixed $value, string $id): void
    {
        $this->validate(["agentPhotos.$id" => 'required|image|max:10240']);
        $p = $this->clips()->find($id);
        if ($p && ! empty($p->meta['property'])) {
            $this->trocarRetrato($p, $value->store('clips/uploads'), $value->extension() ?: $value->getClientOriginalExtension());
        }
        unset($this->agentPhotos[$id]);
    }

    /** The agent's portrait on a clip, replaced by the one at $path (the old file goes). */
    private function trocarRetrato(ClipRecord $p, string $path, string $ext): void
    {
        $listing = $p->meta['listing'] ?? [];
        $agent = (string) ($listing['agent'] ?? 'agente');
        $isPhoto = fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato');
        foreach (array_filter($p->images ?? [], $isPhoto) as $old) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($old['path']);
        }
        $images = array_values(array_filter($p->images ?? [], fn ($img) => ! $isPhoto($img)));
        // The id changes with the file: scenes that showed the old portrait are pointed at the new one.
        $old = collect($p->images ?? [])->first($isPhoto)['id'] ?? null;
        $new = 'img_'.substr(md5($path), 0, 8);
        $images[] = ['id' => $new, 'path' => $path, 'name' => $agent.'.'.$ext,
            'description' => 'Retrato '.(($listing['gender'] ?? 'm') === 'f' ? 'da consultora' : 'do consultor')." {$agent}, da ".($listing['agency'] ?? 'RE/MAX').'.'];
        $plan = $p->plan;
        if ($old && is_array($plan)) {
            array_walk_recursive($plan, function (&$v) use ($old, $new) {
                if ($v === $old) {
                    $v = $new;
                }
            });
        }
        $p->update(['images' => $images, 'meta' => array_merge($p->meta ?? [], ['agent_photo' => $path]), 'plan' => $plan]);
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

        $this->validate($this->afterIntroRules());
        $this->exigirImagensSeSoReferencias();

        $kind = $this->audio ? 'audio' : 'text';
        $path = $this->audio ? $this->audio->store('clips/uploads') : null;
        $afterIntro = $this->afterIntroMeta();
        // A typed script says the after-intro line right after its first sentence (a recording can't).
        if ($kind === 'text' && ($afterIntro['after_intro'] || app(EffectStore::class)->afterIntroPath())) {
            $this->text = ListingScriptWriter::withAfterIntroLine($this->text, $afterIntro['after_intro_line']);
        }

        $project = $this->clips()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => $kind,
            'title' => $this->tituloDe($this->text),
            'source_text' => $kind === 'text' ? $this->text : null,
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(['background' => $this->background, 'lead' => trim($this->lead), 'instructions' => trim(trim($this->instructions).(trim($this->licence) !== '' ? "\nAgent's licence (AMI): ".trim($this->licence).' — show it small on the outro card.' : '')), 'fewer_images' => $this->poucasImagens, 'no_images' => $this->semImagens, 'only_reference_images' => $this->soReferencias, 'auto_finish' => $this->paraFinalizados, 'free_sfx' => $this->freeSfx] + $afterIntro),
        ]);

        TranscribeJob::dispatch($project->id);
        $this->voltar();
    }

    /** @return array<string,string> */
    private function afterIntroRules(): array
    {
        return [
            'afterIntro' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v|max:1048576',
            'afterIntroLine' => 'nullable|string|max:200',
            'licence' => 'nullable|string|max:60',
        ];
    }

    /** This clip's after-intro (null → the project's) and its line (empty → nothing said over it). */
    private function afterIntroMeta(): array
    {
        return ['after_intro' => $this->afterIntro?->store('clips/uploads'), 'after_intro_line' => trim($this->afterIntroLine)];
    }

    public function submitOverlay(): void
    {
        $this->validate([
            // A staged cut is already on the disk — only an upload needs checking.
            'video' => ($this->videoPronto ? 'nullable' : 'required').'|file|mimetypes:video/mp4,video/quicktime|max:512000',
            'allowedPresents' => 'array|min:1',
            'allowedPresents.*' => 'in:video,over,split,animation',
            'formato' => 'required|in:auto,'.implode(',', array_keys(VfxStore::ASPECTS)),
        ], [
            'video.required' => 'Upload a video (mp4 or mov).',
            'video.mimetypes' => 'Unsupported video format. Use mp4 or mov.',
            'video.max' => 'The video is too large (maximum 500 MB).',
            'allowedPresents.min' => 'Choose at least one style.',
        ], ['video' => 'video']);

        $this->exigirImagensSeSoReferencias();
        $path = $this->video ? $this->video->store('clips/uploads') : $this->videoPronto;
        $titulo = $this->video ? $this->video->getClientOriginalName() : $this->videoProntoNome;

        $project = $this->clips()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'title' => $titulo,
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(['allowed_present' => array_values($this->allowedPresents), 'formato' => $this->formato, 'background' => $this->background, 'lead' => trim($this->lead), 'instructions' => trim($this->instructions), 'fewer_images' => $this->poucasImagens, 'no_images' => $this->semImagens, 'only_reference_images' => $this->soReferencias, 'auto_finish' => $this->paraFinalizados, 'free_sfx' => $this->freeSfx]),
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
        $collecting = $p->meta['site_collecting'] ?? [];
        $siteErrors = $p->meta['site_errors'] ?? [];
        $byId = collect($p->images ?? [])->keyBy('id');

        $videoMode = $p->meta['video_mode'] ?? 'video';

        return array_map(function (array $r) use ($uploads, $asText, $collecting, $siteErrors, $byId, $videoMode) {
            $id = $uploads[$r['key']] ?? null;
            $img = $id ? ($byId[$id] ?? null) : null;
            // The animated-intro suggestion shows its generated START IMAGE (the
            // frame the video would move) until the user uploads/picks something.
            if (! $img && ! empty($r['animado']) && ! empty($r['imagem'])) {
                $img = $byId[$r['imagem']] ?? null;
            }

            return $r + [
                'videoMode' => $videoMode,
                'uploadedId' => $id,
                'path' => $img['path'] ?? null,
                'fromLibrary' => (bool) ($img['library'] ?? false),
                'gerado' => (bool) ($img['generated'] ?? false),
                'fetched' => (bool) ($img['fetched'] ?? false),
                'source' => $img['source'] ?? null,
                'video' => (bool) ($img['video'] ?? false),
                'collecting' => ! empty($collecting[$r['key']]),
                'searching' => ($collecting[$r['key']] ?? null) === 'search',
                'siteError' => $siteErrors[$r['key']] ?? null,
                // upload = the user's own file/library pick · generate = AI image ·
                // text = no image at all (the scene becomes a non-image visual).
                'mode' => $id ? 'upload' : (! empty($asText[$r['key']]) ? 'text' : 'generate'),
            ];
        }, $p->meta['image_requests'] ?? []);
    }

    /** Animated intro: render the approved suggestion as a VIDEO or keep the image only. */
    public function modoVideoIntro(string $modo): void
    {
        $p = $this->clips()->findOrFail($this->reviewingId);
        $p->update(['meta' => array_merge($p->meta ?? [], ['video_mode' => $modo === 'image' ? 'image' : 'video'])]);
    }

    /** Suggestion key whose generation prompt is open for editing, or null. */
    public ?string $promptKey = null;

    /** Edited generation prompts, per suggestion key. @var array<string,string> */
    public array $promptEdits = [];

    /** Open/close the prompt editor for a suggestion, prefilled with its current prompt. */
    public function editarPrompt(string $key): void
    {
        if ($this->promptKey === $key) {
            $this->promptKey = null;

            return;
        }
        $p = $this->clips()->findOrFail($this->reviewingId);
        $req = collect($p->meta['image_requests'] ?? [])->firstWhere('key', $key);
        if (! is_array($req)) {
            return;
        }
        $this->promptEdits[$key] ??= (string) (! empty($req['animado']) ? ($req['imagem_prompt'] ?? '') : ($req['prompt'] ?? ''));
        $this->promptKey = $key;
    }

    /** Animated intro: (re)generate the start image (a new take on the same concept). */
    public function recriarImagemIntro(): void
    {
        $this->gerarImagemAgora(ImageRequests::VIDEO_KEY);
    }

    /**
     * "Generate now": make a suggestion's AI image DURING review, so the user sees
     * the real picture — and only then decides — instead of approving a promise.
     */
    public function gerarImagemAgora(string $key): void
    {
        $p = $this->clips()->findOrFail($this->reviewingId);
        $requests = $p->meta['image_requests'] ?? [];
        $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === $key);
        if ($i === false) {
            return;
        }

        // An edited prompt is persisted onto the suggestion, so the generation —
        // and every later regeneration — uses the user's wording.
        $editado = trim((string) ($this->promptEdits[$key] ?? ''));
        if ($editado !== '') {
            $campo = ! empty($requests[$i]['animado']) ? 'imagem_prompt' : 'prompt';
            $requests[$i][$campo] = $editado;
        }
        $this->promptKey = null;

        $p->update(['meta' => array_merge($p->meta ?? [], [
            'image_requests' => $requests,
            'site_collecting' => array_merge($p->meta['site_collecting'] ?? [], [$key => true]),
            'site_errors' => array_diff_key($p->meta['site_errors'] ?? [], [$key => true]),
        ])]);
        GenerateRequestImageJob::dispatch($p->id, $key);
    }

    /** Is any website capture running for the clip under review? (drives polling) */
    public function getSiteCollectingProperty(): bool
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;

        return $p !== null && array_filter($p->meta['site_collecting'] ?? []) !== [];
    }

    /** "Find image": search the web for the real image behind a suggestion (or try again). */
    public function procurarImagem(string $key): void
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        $req = $p ? collect($p->meta['image_requests'] ?? [])->firstWhere('key', $key) : null;
        if (! $p || ! is_array($req)) {
            return;
        }
        $p->update(['meta' => array_merge($p->meta ?? [], [
            'site_collecting' => array_merge($p->meta['site_collecting'] ?? [], [$key => 'search']),
            'site_errors' => array_diff_key($p->meta['site_errors'] ?? [], [$key => true]),
        ])]);
        FetchWebImagesJob::dispatch($p->id, $key);
    }

    /** "Collect now": film the website behind a suggestion so it can be seen before approving. */
    public function coletarSite(string $key): void
    {
        $p = $this->reviewingId ? $this->clips()->find($this->reviewingId) : null;
        $req = $p ? collect($p->meta['image_requests'] ?? [])->firstWhere('key', $key) : null;
        if (! $p || empty($req['site'])) {
            return;
        }
        $p->update(['meta' => array_merge($p->meta ?? [], [
            'site_collecting' => array_merge($p->meta['site_collecting'] ?? [], [$key => true]),
            'site_errors' => array_diff_key($p->meta['site_errors'] ?? [], [$key => true]),
        ])]);
        CollectSiteJob::dispatch($p->id, $key);
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
        $this->legendas = (string) ($p->meta['captions'] ?? 'bottom');
        $this->images = array_values($p->images ?? []);
        $this->imageReplace = [];
        $this->editOverlay = $p->type === ClipRecord::TYPE_OVERLAY;
        $this->editSpeakable = $p->input_kind === 'text';
        [, $legendas] = self::dividirPalavras($p->transcript['words'] ?? [], $p->plan['scenes'] ?? []);
        $this->editScenes = array_map(function ($s, $i) use ($legendas) {
            [$target, $text] = $this->extractLayerText($s['layers'] ?? []);

            return [
                // Which scene of the STORED plan this row came from. Adding or
                // removing scenes reindexes $editScenes, so the saved subtitles
                // are matched back by this and not by position.
                'origIndex' => $i,
                'subtitles' => $legendas[$i] ?? '',
                'present' => $s['present'] ?? null,
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
                // The whole scene as planned. Saving MERGES edits over this, so keys
                // the editor has no field for (present, …) survive a save+re-render
                // instead of being silently dropped (which turned every overlay
                // scene into a plain video beat).
                'original' => $s,
            ];
        }, $p->plan['scenes'] ?? [], array_keys($p->plan['scenes'] ?? []));
        $this->editPlanJson = json_encode($p->plan ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->editMode = 'cenas';
        $this->listingVideoLength = $this->medirVideoDoImovel($p);
        $this->momentPicker = null;
        $this->resetValidation();
        $this->view = 'editPlan';
    }

    /**
     * Split the transcript words across the plan's scenes: a word belongs to the
     * first scene whose [start, end) contains it; words in no scene (gaps) stay
     * on their own. Returns the ordered runs — each ['scene' => ?int, 'text' =>
     * string] — plus the words of each scene joined, which is what the editor
     * shows as that block's subtitles.
     *
     * Load and save both go through here so a run maps to exactly one textarea.
     *
     * @param  array<int,array<string,mixed>>  $words
     * @param  array<int,array<string,mixed>>  $scenes
     * @return array{0:array<int,array{scene:?int,text:string}>,1:array<int,string>}
     */
    private static function dividirPalavras(array $words, array $scenes): array
    {
        $runs = [];
        $porCena = [];

        foreach ($words as $w) {
            $t = (float) ($w['start'] ?? 0);
            $cena = null;
            foreach ($scenes as $i => $s) {
                if ($t >= (float) ($s['start'] ?? 0) && $t < (float) ($s['end'] ?? 0)) {
                    $cena = $i;
                    break;
                }
            }
            $palavra = trim((string) ($w['word'] ?? ''));
            if ($palavra === '') {
                continue;
            }
            if ($runs === [] || $runs[count($runs) - 1]['scene'] !== $cena) {
                $runs[] = ['scene' => $cena, 'text' => $palavra];
            } else {
                $runs[count($runs) - 1]['text'] .= ' '.$palavra;
            }
            if ($cena !== null) {
                $porCena[$cena] = trim(($porCena[$cena] ?? '').' '.$palavra);
            }
        }

        return [$runs, $porCena];
    }

    /**
     * Rebuild the whole transcript from the per-scene subtitle edits, keeping the
     * timings: the edited text is spliced back where those words were, and
     * TranscriptRebuilder re-times only the spans that actually changed.
     *
     * @param  array<string,mixed>  $transcript
     * @param  array<int,array<string,mixed>>  $storedScenes  scenes as they were on disk (what the editor split on)
     * @return array<string,mixed>
     */
    private function aplicarLegendas(array $transcript, array $storedScenes): array
    {
        [$runs, $porCena] = self::dividirPalavras($transcript['words'] ?? [], $storedScenes);
        if ($runs === []) {
            return $transcript;
        }

        // origIndex → the text now in that scene's textarea.
        $editado = [];
        foreach ($this->editScenes as $row) {
            if (($row['origIndex'] ?? null) !== null) {
                $editado[(int) $row['origIndex']] = trim((string) ($row['subtitles'] ?? ''));
            }
        }

        $partes = [];
        $feitas = [];
        foreach ($runs as $run) {
            $i = $run['scene'];
            if ($i === null) {
                $partes[] = $run['text'];

                continue;
            }
            // A scene's whole textarea goes in at its first run; any later run of
            // the same scene (only possible with overlapping scenes) is already
            // included there, so it is not emitted twice.
            if (isset($feitas[$i])) {
                continue;
            }
            $feitas[$i] = true;
            $texto = $editado[$i] ?? ($porCena[$i] ?? $run['text']);
            if ($texto !== '') {
                $partes[] = $texto;
            }
        }

        $texto = trim(implode(' ', $partes));
        if ($texto === '' || $texto === trim((string) ($transcript['text'] ?? ''))) {
            return $transcript;
        }

        $transcript['text'] = $texto;
        $transcript['words'] = TranscriptRebuilder::rebuild(
            $texto,
            $transcript['words'] ?? [],
            (float) ($transcript['duration'] ?? 0.0),
        );

        return $transcript;
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
            'origIndex' => null, 'subtitles' => '', 'present' => null,
            'start' => 0, 'end' => 1, 'background' => 'papyrus',
            'transitionIn' => 'crossfade', 'transitionOut' => 'cut',
            'karaoke' => false, 'punchWord' => '', 'layers' => [], 'layersSummary' => '—',
            'animacao' => null, 'original' => [],
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

    /**
     * Swap a scene's animation to $slug — rebuilds the first layer for the new
     * primitive.
     *
     * Retyping the layer in place left the OLD primitive's params and a stale
     * `textTarget` behind, which broke the swap two ways. An image-reveal keeps
     * its text in `params.caption` while a text primitive reads `layer.text`, so
     * the scene's text was saved where the new effect never looked and it
     * rendered empty — underline-sweep became a bare underline. And a data
     * primitive (timeline, diagram, a chart) inherited `src`/`caption` instead of
     * the items/nodes/bars it needs, so it drew its empty-state placeholder: the
     * big grey em-dash in the middle of the frame.
     *
     * The pick is always applied. A data primitive is left without its data here
     * on purpose: guardarPlano hands those scenes to FillChosenVisualsJob, which
     * has the planner write items/bars/nodes from what is spoken in the scene.
     * Seeding the showcase sample instead was tried and reverted — "Market share
     * A/B/C" in a real clip is confidently wrong content.
     */
    public function mudarAnimacao(int $i, string $slug, EffectLibrary $library): void
    {
        if (! isset($this->editScenes[$i])) {
            return;
        }
        $layers = $this->editScenes[$i]['layers'] ?? [];
        $old = is_array($layers[0] ?? null) ? $layers[0] : [];

        // Carry the image only into a primitive that shows one — image-reveal, or
        // a custom effect (those take theirs as params.src). Same roster
        // FinalizeClipPlanJob feeds dropDeadLayers.
        $src = $old['params']['src'] ?? null;
        $novo = ['type' => $slug, 'text' => '', 'params' => []];
        if (in_array($slug, $this->slugsComImagem($library), true) && is_string($src) && $src !== '') {
            $novo['params']['src'] = $src;
        }

        $layers[0] = $novo;

        // Point the editor's text field at the key this primitive reads, keeping
        // whatever the user had typed, and write it there.
        $texto = trim((string) ($this->editScenes[$i]['layerText'] ?? ''));
        [$target] = $this->extractLayerText($layers);
        $layers = $this->applyLayerText($layers, $target, $texto);
        $this->editScenes[$i]['textTarget'] = $target;
        $this->editScenes[$i]['layerText'] = $texto;

        $this->editScenes[$i]['layers'] = $layers;
        $this->editScenes[$i]['animacao'] = $slug;
        if (($this->editScenes[$i]['original']['present'] ?? null) === 'video' || ($this->editScenes[$i]['present'] ?? null) === 'video') {
            // Both the stored mode and the picker's value, or the new animation
            // would be saved onto a scene that still presents as plain video.
            unset($this->editScenes[$i]['original']['present']); // validator assigns a graphic mode
            $this->editScenes[$i]['present'] = null;
        }
        $this->editScenes[$i]['layersSummary'] = implode(', ', array_map(fn ($l) => $l['type'] ?? '?', $layers)) ?: '—';
        $this->animacaoPickerCena = null;
    }

    /** Effect slugs that display a picture — image-reveal plus the custom ones. @return string[] */
    private function slugsComImagem(EffectLibrary $library): array
    {
        return array_merge(
            ['image-reveal'],
            array_values(array_filter($library->activeSlugs(), fn ($s) => $library->usesImage($s))),
        );
    }

    /**
     * True for an effect the SCENE EDITOR can never fill: the data primitives
     * (timeline, diagram, the charts), whose items/bars/nodes only the planner
     * writes. Handed none, they draw their empty-state placeholder — the big grey
     * em-dash — and there is no field in this editor to type the data into.
     *
     * An empty image slot is deliberately NOT one of these: it renders the same
     * placeholder, but the editor offers upload/library controls to fill it, and
     * removing a used image is documented to leave the slot behind on purpose.
     * Text primitives are fillable too — the scene's text field feeds them.
     */
    private function precisaDeDadosDoPlanner(string $slug, EffectLibrary $library, SceneVisualFiller $filler): bool
    {
        if (in_array($slug, $this->slugsComImagem($library), true)) {
            return false;
        }
        $imageSlugs = array_values(array_filter($library->activeSlugs(), fn ($s) => $library->usesImage($s)));

        // Probe the primitive with everything the editor CAN give it — a text.
        return ! $filler->layerHasContent(['type' => $slug, 'text' => 'x', 'params' => []], $imageSlugs);
    }

    /**
     * Scene index => layer type, for every scene holding a data primitive with no
     * data. These are the ones FillChosenVisualsJob has the planner write.
     *
     * @return array<int,string>
     */
    private function cenasSemDados(array $plan, SceneVisualFiller $filler, EffectLibrary $library): array
    {
        $imageSlugs = array_values(array_filter($library->activeSlugs(), fn ($s) => $library->usesImage($s)));
        $out = [];
        foreach ($plan['scenes'] ?? [] as $i => $scene) {
            $layer = $scene['layers'][0] ?? null;
            if (! is_array($layer) || $filler->layerHasContent($layer, $imageSlugs)) {
                continue;
            }
            $type = (string) ($layer['type'] ?? '');
            if ($this->precisaDeDadosDoPlanner($type, $library, $filler)) {
                $out[$i] = $type;
            }
        }

        return $out;
    }

    /**
     * Built-in + enabled custom effects the picker offers, deduped by slug, plus
     * the clip's own Free-SFX components. Those are `enabled = false` so the
     * planner never sees them — but the scene the model wrote them FOR is pointing
     * at one, and without them here the editor showed a bare slug with no label and
     * no way back after switching away.
     *
     * @return array<int,array{slug:string,label:string,kind:string}>
     */
    private function animacoesDisponiveis(EffectLibrary $library): array
    {
        $lista = [];
        foreach (array_diff(array_keys(EffectLibrary::BUILTIN_SAMPLES), $library->disabledBuiltins()) as $slug) {
            $lista[$slug] = ['slug' => $slug, 'label' => EffectLibrary::BUILTIN_SAMPLES[$slug]['label'] ?? $slug, 'kind' => 'builtin'];
        }
        foreach ($library->enabled() as $e) {
            $lista[$e->slug] = ['slug' => $e->slug, 'label' => $e->display_name ?: $e->slug, 'kind' => 'custom'];
        }
        foreach ($this->freeSfxDoClipe() as $e) {
            $lista[$e->slug] = ['slug' => $e->slug, 'label' => $e->display_name ?: $e->slug, 'kind' => 'free'];
        }

        return array_values($lista);
    }

    /** The Free-SFX components written for the clip being edited. @return Collection<int,EffectRecord> */
    private function freeSfxDoClipe(): Collection
    {
        return $this->editingId === null || $this->editingId === ''
            ? collect()
            : app(EffectStore::class)->forClip($this->editingId);
    }

    /**
     * Keep a Free-SFX component: hand it to the SFX library, where it becomes an
     * ordinary reusable effect (visible, previewable, offered to the planner).
     * Clearing `clip_id` also means re-planning this clip no longer deletes it.
     */
    public function guardarFreeSfx(string $slug): void
    {
        $effect = $this->freeSfxDoClipe()->firstWhere('slug', $slug);
        if (! $effect) {
            return;
        }

        $effect->update(['clip_id' => null, 'enabled' => true]);
        foreach (array_keys(EffectLibrary::FORMATS) as $formato) {
            RenderEffectSampleJob::dispatch($slug, (string) $effect->sample_text, $effect->sample_params ?? [], $formato);
        }

        $this->dispatch('toast', type: 'ok', message: '«'.($effect->display_name ?: $slug).'» added to the SFX library.');
    }

    // ---- per-scene image (see & replace in the scene editor) ----

    /** Per-scene replacement upload while editing a plan (keyed by scene index). */
    public array $sceneImageUploads = [];

    /** Scene index whose "pick from library" panel is open, or null. */
    public ?int $sceneLibraryPicker = null;

    /** The block whose footage is being picked from the property video, or null. */
    public ?int $momentPicker = null;

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

    /** The clip being edited was given a property video (a listing): its length, for the moment picker. */
    #[Locked]
    public ?float $listingVideoLength = null;

    /** The clip open in an editor, for its view. */
    public function getEditingClipProperty(): ?ClipRecord
    {
        return $this->editingId ? $this->clips()->find($this->editingId) : null;
    }

    /** The property video's length of a clip, for the transcript editor (null: none). */
    public function listingVideoLengthFor(?ClipRecord $p): ?float
    {
        return $this->medirVideoDoImovel($p);
    }

    private function medirVideoDoImovel(?ClipRecord $p): ?float
    {
        $path = $p?->meta['listing_video'] ?? null;
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        if (! is_string($path) || ! $disk->exists($path)) {
            return null;
        }
        try {
            return PropertyVideoAnalyzer::probe($disk->path($path))['duration'];
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * A block's footage slots: the keys of its image layer that show video
     * (src, src2… — a fan shows five), with the moment of the property video
     * each one plays when known. A block showing no video yet has the one slot.
     *
     * @return list<array{key:string,start:?float,end:?float}>
     */
    public function videoSlots(int $i): array
    {
        $li = $this->camadaDeImagem($i);
        $params = $li === null ? [] : ($this->editScenes[$i]['layers'][$li]['params'] ?? []);
        $byId = collect($this->images)->keyBy('id');
        $slots = [];
        foreach (['src', 'src2', 'src3', 'src4', 'src5'] as $key) {
            $img = is_string($params[$key] ?? null) ? $byId->get($params[$key]) : null;
            if ($img && ! empty($img['video'])) {
                $slots[] = ['key' => $key, 'start' => $img['source'][0] ?? null, 'end' => $img['source'][1] ?? null];
            }
        }

        return $slots ?: ($li === null ? [] : [['key' => 'src', 'start' => null, 'end' => null]]);
    }

    /** "Improve with AI" on a clip's card: whose prompt is open, and the prompt. */
    public ?string $improveCardId = null;

    public string $improveCardPrompt = '';

    /**
     * Re-render only the ending (the closing card changed — say, the agent card
     * gained the Licença AMI): its inputs the clip doesn't have yet are taken from
     * the effect's sample (never the agent's own name, phone, e-mail or photo),
     * and only its frames are rendered again, joined onto the clip as it is.
     */
    public function rerenderFinal(string $id): void
    {
        $p = $this->clips()->find($id);
        $scenes = $p?->plan['scenes'] ?? [];
        if (! $p || ! $p->output_path || count($scenes) < 2) {
            return;
        }
        $last = count($scenes) - 1;
        $plan = $p->plan;
        foreach ($plan['scenes'][$last]['layers'] ?? [] as $li => $layer) {
            $sample = (array) (app(EffectStore::class)->all()->firstWhere('slug', $layer['type'] ?? '')?->sample_params ?? []);
            $own = ['name', 'phone', 'email', 'src', 'logo', 'avatar', 'text'];
            foreach (array_diff_key($sample, array_flip($own), (array) ($layer['params'] ?? [])) as $k => $v) {
                $plan['scenes'][$last]['layers'][$li]['params'][$k] = $v;
            }
        }
        $p->update(['plan' => $plan, 'status' => ClipRecord::STATUS_RENDERING, 'error' => null]);
        RenderJob::dispatch($p->id, (float) $scenes[$last]['start']);
    }

    public function abrirMelhoriaClip(string $id): void
    {
        [$this->improveCardId, $this->improveCardPrompt] = [$this->improveCardId === $id ? null : $id, ''];
        $this->resetValidation('improveCardPrompt');
    }

    /**
     * "Improve with AI": the clip reworked as the prompt asks — its script
     * rewritten (when the voice is generated), and its photos, footage, timing
     * and effects planned again with the request as direction; its caption
     * follows. No picture is generated or edited (ImproveClipJob).
     */
    public function melhorarClip(): void
    {
        $this->validate(['improveCardPrompt' => 'required|string|min:3|max:2000'], ['improveCardPrompt.required' => 'Say what to change.']);
        $p = $this->clips()->find((string) $this->improveCardId);
        if (! $p) {
            return;
        }
        $p->update(['meta' => array_merge($p->meta ?? [], ['improve_request' => trim($this->improveCardPrompt)]), 'status' => ClipRecord::STATUS_IMPROVING, 'error' => null]);
        ImproveClipJob::dispatch($p->id);
        [$this->improveCardId, $this->improveCardPrompt] = [null, ''];
    }

    public function abrirMomentos(int $i): void
    {
        $this->momentPicker = $this->momentPicker === $i ? null : $i;
    }

    /**
     * The moments the user marked on the property video for block $i, one per
     * footage slot in order: each is cut into a clip (stretched to the block's
     * length when shorter, so it never freezes) and put in its slot. Saving the
     * edit re-renders with them.
     *
     * @param  list<array{start:float,end:float}>  $moments
     */
    public function usarMomentos(int $i, array $moments): void
    {
        $length = $this->listingVideoLength;
        $slots = $this->videoSlots($i);
        $li = $this->camadaDeImagem($i);
        if ($length === null || $slots === [] || $li === null || ! isset($this->editScenes[$i])) {
            return;
        }
        $moments = array_slice(array_values(array_filter($moments, fn ($m) => is_numeric($m['start'] ?? null) && is_numeric($m['end'] ?? null)
            && $m['end'] > $m['start'] && $m['start'] >= 0 && $m['start'] < $length)), 0, count($slots));
        if ($moments === []) {
            $this->addError("moments.$i", 'Mark at least one moment on the strip.');

            return;
        }
        $p = $this->clips()->findOrFail($this->editingId);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $source = $disk->path($p->meta['listing_video']);
        $block = max(0.5, (float) $this->editScenes[$i]['end'] - (float) $this->editScenes[$i]['start']);
        $layers = $this->editScenes[$i]['layers'];
        foreach ($moments as $k => $m) {
            $start = (float) $m['start'];
            // Shorter than the block: run on to its length (as far as the video goes) — a clip that ends early freezes.
            $end = min($length, max((float) $m['end'], $start + $block));
            $path = 'clips/uploads/'.Str::random(40).'.mp4';
            PropertyVideoAnalyzer::cut($source, $start, $end - $start, $disk->path($path));
            $label = sprintf('%d:%04.1f–%d:%04.1f', intdiv((int) $start, 60), fmod($start, 60), intdiv((int) $end, 60), fmod($end, 60));
            $this->images[] = ['id' => 'vid_'.substr(md5($path), 0, 8), 'path' => $path, 'name' => "momento-{$i}-".($k + 1).'.mp4',
                'description' => "Momento escolhido do vídeo do imóvel ({$label}).", 'transparent' => false, 'tone' => 'mixed', 'video' => true, 'source' => [$start, $end]];
            $layers[$li]['params'][$slots[$k]['key']] = end($this->images)['id'];
        }
        $this->editScenes[$i]['layers'] = $layers;
        $this->momentPicker = null;
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

    public function guardarPlano(PlanValidator $validator, SceneVisualFiller $filler, EffectLibrary $library): void
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
            'editScenes.*.subtitles' => 'nullable|string|max:2000',
            'editScenes.*.present' => 'nullable|in:video,over,split,animation',
        ], [
            'editScenes.min' => 'The clip needs at least one scene.',
            'editScenes.*.end.gt' => 'The end must be greater than the start.',
        ]);

        $p = $this->clips()->findOrFail($this->editingId);
        $oldImages = $p->images ?? [];
        $plan = $p->plan ?? [];
        $plan['scenes'] = array_map(fn ($s) => array_merge(is_array($s['original'] ?? null) ? $s['original'] : [], [
            'start' => (float) $s['start'],
            'end' => (float) $s['end'],
            'background' => $s['background'],
            'transitionIn' => $s['transitionIn'],
            'transitionOut' => $s['transitionOut'] ?? 'cut',
            'karaoke' => (bool) $s['karaoke'],
            'punchWord' => ($s['punchWord'] ?? '') === '' ? null : $s['punchWord'],
            'layers' => $this->applyLayerText($s['layers'] ?? [], $s['textTarget'] ?? 'text', $s['layerText'] ?? ''),
        ], ($s['present'] ?? null) ? ['present' => $s['present']] : []), $this->editScenes);
        $plan = $this->stripMissingImageRefs($plan);

        // Scenes the user swapped to a data primitive (timeline, diagram, a chart)
        // have the type but none of the items/bars/nodes it draws — only the
        // planner writes those. Collect them; FillChosenVisualsJob asks for the
        // data from what is spoken there before the render, so the effect is
        // actually generated instead of drawing its em-dash placeholder.
        $porGerar = $this->cenasSemDados($plan, $filler, $library);
        // Overlay context matters: without it the validator can't keep/assign the
        // per-scene `present` modes (an overlay scene with none shows only video).
        // A mode picked by hand here ("just the raw video for this block") is not
        // necessarily among the styles chosen at creation, and assignPresentModes
        // overwrites any present outside that list — so the picks join the list.
        $allowed = $p->meta['allowed_present'] ?? null;
        if ($allowed !== null) {
            $escolhidos = array_filter(array_column($this->editScenes, 'present'));
            $allowed = array_values(array_unique(array_merge($allowed, $escolhidos)));
        }
        $plan = $validator->validate(
            $plan,
            '', // no transcript check — edited punch words are the user's call
            $p->type === ClipRecord::TYPE_OVERLAY,
            $allowed,
        );

        $p->update([
            'title' => $this->editTitle ?: $p->title,
            'plan' => $plan,
            'images' => $this->images ?: null,
            'transcript' => $this->aplicarLegendas($p->transcript ?? [], $p->plan['scenes'] ?? []),
            'meta' => $this->musicaMeta($p->meta ?? []),
            'status' => ClipRecord::STATUS_RENDERING,
            'error' => null,
        ]);
        $this->pruneImageFiles($oldImages);

        if ($this->regenerarVoz) {
            // Speaks the subtitles just saved, then re-renders on the new audio.
            $p->update(['status' => ClipRecord::STATUS_TRANSCRIBING]);
            RegenerateVoiceoverJob::dispatch($p->id);
        } elseif ($porGerar !== []) {
            $p->update(['status' => ClipRecord::STATUS_PLANNING]);
            FillChosenVisualsJob::dispatch($p->id, $porGerar);
        } else {
            RenderJob::dispatch($p->id);
        }
        $this->voltar();
    }

    /** Save the edits exactly like guardarPlano, then re-speak instead of re-rendering. */
    public function guardarPlanoComVoz(PlanValidator $validator, SceneVisualFiller $filler, EffectLibrary $library): void
    {
        $this->regenerarVoz = true;
        $this->guardarPlano($validator, $filler, $library);
    }

    /** Merge the current music and caption choices into a meta array (base preserved). */
    private function musicaMeta(array $base = []): array
    {
        return array_merge($base, [
            'musica' => trim($this->musica),
            'musica_volume' => $this->musicaVolume,
            'captions' => $this->legendasValidas(),
        ]);
    }

    // =====================================================================
    // Edit transcript (→ replans + re-render)
    // =====================================================================

    /** Editing a listing clip: the listing options, prefilled with what it was made with (uploads replace). */
    public bool $editListing = false;

    public string $editListingUrl = '';

    public $editAgentPhoto = null;

    public $editListingVideo = null;

    public $editAfterIntro = null;

    public string $editAfterIntroLine = '';

    public string $editListingNotes = '';

    public bool $editMixPhotos = false;

    public function editarTranscricao(string $id): void
    {
        $p = $this->clips()->findOrFail($id);
        $this->editingId = $p->id;
        $this->editTranscriptText = (string) ($p->transcript['text'] ?? '');
        // The same options the create form offers, prefilled with what this clip
        // was made with — a re-plan is the moment to change them.
        $m = $p->meta ?? [];
        $this->editOverlay = $p->type === ClipRecord::TYPE_OVERLAY;
        $this->allowedPresents = array_values((array) ($m['allowed_present'] ?? $this->allowedPresents));
        $this->formato = (string) ($m['formato'] ?? 'auto');
        $this->background = (string) ($m['background'] ?? 'auto');
        $this->lead = (string) ($m['lead'] ?? '');
        $this->instructions = (string) ($m['instructions'] ?? '');
        $this->poucasImagens = (bool) ($m['fewer_images'] ?? false);
        $this->semImagens = (bool) ($m['no_images'] ?? false);
        $this->soReferencias = (bool) ($m['only_reference_images'] ?? false);
        $this->legendas = (string) ($m['captions'] ?? 'bottom');
        $this->paraFinalizados = (bool) ($m['auto_finish'] ?? false);
        $this->freeSfx = (bool) ($m['free_sfx'] ?? false);
        $this->musica = (string) ($m['musica'] ?? '');
        $this->musicaVolume = (float) ($m['musica_volume'] ?? 0.1);
        // A listing: the options of the "From a listing" form, as this clip was made.
        // (A property walkthrough reel is rebuilt from its own footage — not from this form.)
        $this->editListing = isset($m['listing']) && empty($m['property']);
        $this->editListingUrl = (string) ($m['listing']['url'] ?? '');
        $this->editAfterIntroLine = (string) (($m['after_intro_line'] ?? '') ?: app(EffectStore::class)->afterIntroLine());
        $this->editListingNotes = (string) ($m['script_notes'] ?? '');
        $this->editMixPhotos = (bool) ($m['mix_photos'] ?? false);
        $this->reset('editAgentPhoto', 'editListingVideo', 'editAfterIntro');
        $this->resetValidation();
        $this->view = 'editTranscript';
    }

    /**
     * The listing options changed in the transcript editor. What the listing is
     * read into (the link, the notes, the property video, mixing in its photos)
     * rebuilds the clip from the listing — a fresh import, true is returned. The
     * rest is applied here and the usual regenerate carries on: a new agent photo
     * or after-intro video is swapped in, a new after-intro line replaces the old
     * one in the transcript (so it is spoken).
     */
    private function aplicarOpcoesDoAnuncio(ClipRecord $p): bool
    {
        $video = 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v|max:1048576';
        $this->validate([
            // Required only of a clip made from a link (one made from typed-in details has none to rebuild from).
            'editListingUrl' => [($p->meta['listing']['url'] ?? '') !== '' ? 'required' : 'nullable', 'url:https,http', fn ($a, $v, $fail) => $v === '' || ListingScraper::supports($v) ? null : $fail(ListingScraper::UNSUPPORTED)],
            'editAgentPhoto' => 'nullable|image|max:10240', 'editListingVideo' => $video, 'editAfterIntro' => $video,
            'editAfterIntroLine' => 'nullable|string|max:200', 'editListingNotes' => 'nullable|string|max:3000',
        ]);
        $m = $p->meta ?? [];
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        [$property, $afterIntro] = [$this->editListingVideo, $this->editAfterIntro];
        if (self::videosTrocados($property, $afterIntro)) {
            [$property, $afterIntro] = [$afterIntro, $property];
        }
        $url = trim($this->editListingUrl);
        $rebuild = $url !== '' && ($url !== ($m['listing']['url'] ?? '') || trim($this->editListingNotes) !== trim((string) ($m['script_notes'] ?? ''))
            || $this->editMixPhotos !== (bool) ($m['mix_photos'] ?? false) || $property !== null);

        $oldLine = trim((string) (($m['after_intro_line'] ?? '') ?: app(EffectStore::class)->afterIntroLine()));
        $line = trim($this->editAfterIntroLine);
        if ($property) {
            $disk->delete(array_filter([$m['listing_video'] ?? null, "clips/listing-preview/{$p->id}.mp4", "clips/listing-preview/{$p->id}-strip.jpg"]));
            $m['listing_video'] = $property->store('clips/uploads');
        }
        if ($afterIntro) {
            $m['after_intro'] = $afterIntro->store('clips/uploads');
        }
        $m = array_merge($m, ['script_notes' => trim($this->editListingNotes), 'mix_photos' => ! empty($m['listing_video']) && $this->editMixPhotos, 'after_intro_line' => $line]);
        $m['listing']['url'] = $url;
        $p->update(['meta' => $m]);
        if ($this->editAgentPhoto) {
            $rebuild
                ? $p->update(['meta' => array_merge($p->meta, ['agent_photo' => $this->editAgentPhoto->store('clips/uploads')])])
                : $this->trocarRetrato($p, $this->editAgentPhoto->store('clips/uploads'), $this->editAgentPhoto->extension() ?: 'jpg');
        }

        if ($rebuild) {
            $p->update(['status' => ClipRecord::STATUS_IMPORTING, 'error' => null]);
            ImportListingJob::dispatch($p->id, $url);
            $this->voltar();

            return true;
        }
        // A new after-intro line is what the voiceover says over the video: in the transcript, in place of the old one.
        if ($line !== $oldLine && $line !== '') {
            $text = trim($this->editTranscriptText);
            $sem = rtrim($oldLine, '.!?');
            $this->editTranscriptText = $sem !== '' && mb_stripos($text, $sem) !== false
                ? (string) preg_replace('/'.preg_quote($sem, '/').'[.!?]?/iu', rtrim($line, '.!?').'.', $text, 1)
                : ListingScriptWriter::withAfterIntroLine($text, $line);
        }

        return false;
    }

    /** The clip being edited has a generated voice: an edited transcript is spoken again. */
    public function getEditingSpokenAgainProperty(): bool
    {
        $p = $this->editingId ? $this->clips()->find($this->editingId) : null;

        return $p !== null && (isset($p->meta['listing']) || $p->input_kind === 'text');
    }

    public function regenerar(): void
    {
        $this->validate(
            [
                'editTranscriptText' => 'required|string|max:5000',
                'allowedPresents' => $this->editOverlay ? 'array|min:1' : 'array',
                'allowedPresents.*' => 'in:video,over,split,animation',
                'formato' => 'required|in:auto,'.implode(',', array_keys(VfxStore::ASPECTS)),
            ],
            ['editTranscriptText.required' => 'The transcript cannot be empty.', 'allowedPresents.min' => 'Choose at least one style.'],
        );

        $this->validate(['instructions' => 'nullable|string|max:10000'], ['instructions.max' => 'The instructions are too long (maximum 10,000 characters).']);

        $p = $this->clips()->findOrFail($this->editingId);
        if ($this->editListing && $this->aplicarOpcoesDoAnuncio($p)) {
            return;   // rebuilt from the listing: a fresh import is on its way
        }
        $p = $this->clips()->findOrFail($this->editingId);
        $transcript = $p->transcript ?? [];
        // A generated voice (a listing, a clip written as text) says the edit: it is
        // spoken again, then planned on the new audio. A recorded voice can't change —
        // there the edit only fixes the words on screen.
        $falar = (isset($p->meta['listing']) || $p->input_kind === 'text')
            && trim($this->editTranscriptText) !== trim((string) ($transcript['text'] ?? ''));
        $transcript['text'] = trim($this->editTranscriptText);
        $transcript['words'] = TranscriptRebuilder::rebuild(
            $this->editTranscriptText,
            $transcript['words'] ?? [],
            (float) ($transcript['duration'] ?? 0.0)
        );

        $p->update([
            'transcript' => $transcript,
            'meta' => $this->musicaMeta(array_merge($p->meta ?? [], [
                'allowed_present' => array_values($this->allowedPresents),
                'formato' => $this->formato,
                'background' => $this->background,
                'lead' => trim($this->lead),
                'instructions' => trim($this->instructions),
                'fewer_images' => $this->poucasImagens,
                'no_images' => $this->semImagens,
                'only_reference_images' => $this->soReferencias,

                'auto_finish' => $this->paraFinalizados,
                'free_sfx' => $this->freeSfx,
            ])),
            'status' => $falar ? ClipRecord::STATUS_TRANSCRIBING : ClipRecord::STATUS_PLANNING,
            'error' => null,
        ]);

        $falar ? RegenerateVoiceoverJob::dispatch($p->id, $transcript['text'], replan: true) : PlanAnimationsJob::dispatch($p->id);
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
        // Voiceover takes still waiting to be picked.
        Storage::disk(config('contentmachine.clips.disk'))->delete(array_values($p->meta['voice_takes'] ?? []));
        // A property walkthrough: the footage, the narration, and each room's frames, still and AI clip.
        $prop = $p->meta['property'] ?? [];
        Storage::disk(config('contentmachine.clips.disk'))->delete(array_values(array_filter([
            $prop['video'] ?? null, $prop['proxy'] ?? null, $prop['preview'] ?? null, $prop['strip'] ?? null, $prop['voice'] ?? null,
            ...($prop['photos'] ?? []),
            ...collect($prop['rooms'] ?? [])->flatMap(fn ($r) => array_values(array_intersect_key($r['anim'] ?? [], array_flip(['start_frame', 'end_frame', 'staged', 'video']))))->all(),
            ...collect($prop['rooms'] ?? [])->flatMap(fn ($r) => array_column($r['anim']['candidates'] ?? [], 'path'))->all(),
        ])));

        // A listing video, and the preview + filmstrip the editor's moment picker made of it.
        Storage::disk(config('contentmachine.clips.disk'))->delete(array_values(array_filter([
            $p->meta['listing_video'] ?? null, "clips/listing-preview/{$id}.mp4", "clips/listing-preview/{$id}-strip.jpg",
        ])));

        // Free-SFX components belong to this clip alone — anything the user chose
        // to keep was moved into the library and no longer answers to its id.
        $library = app(EffectLibrary::class);
        foreach (app(EffectStore::class)->forClip($id) as $effect) {
            $library->remove($effect);
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
        $bgReady = [];   // id => preview version, for both membership and cache-busting
        if ($this->view === 'backgrounds') {
            $bgLibrary = app(BackgroundLibrary::class);
            foreach ($this->backgrounds as $bg) {
                if ($bgLibrary->previewFileFor($bg) !== null) {
                    $bgReady[$bg->id()] = $bgLibrary->previewVersionFor($bg);
                }
            }
        }

        $animacoes = [];
        $sfxReady = [];
        $freeSfxSlugs = [];
        if ($this->view === 'editPlan') {
            $library = app(EffectLibrary::class);
            $animacoes = $this->animacoesDisponiveis($library);
            // Slugs the "Add to SFX library" button applies to (this clip's own).
            $freeSfxSlugs = $this->freeSfxDoClipe()->pluck('slug')->all();
            $sfxReady = array_values(array_filter(
                array_column($animacoes, 'slug'),
                fn ($slug) => $library->previewExists($slug),
            ));
        }

        return view('livewire.clips-animados', [
            'custos' => $this->view === 'dashboard'
                ? app(CostLedger::class)->totaisPorPeca('clip')
                : [],
            'backgrounds' => self::BACKGROUNDS,
            'transitions' => self::TRANSITIONS,
            'musicas' => $music->all(),
            'bgReady' => $bgReady,
            'animacoes' => $animacoes,
            'sfxReady' => $sfxReady,
            'freeSfxSlugs' => $freeSfxSlugs,
        ]);
    }
}
