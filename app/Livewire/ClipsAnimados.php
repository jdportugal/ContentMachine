<?php

namespace App\Livewire;

use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Models\ClipProject;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\TranscriptRebuilder;
use Illuminate\Support\Facades\File;
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

    /** dashboard | create | editPlan | editTranscript */
    public string $view = 'dashboard';

    // ---- creation ----
    /** null | animation | overlay */
    public ?string $createType = null;
    public string $text = '';
    public $audio = null;
    public $video = null;
    /** which presentation styles the AI may use for a video clip */
    public array $allowedPresents = ['video', 'over', 'split', 'animation'];

    // images (with description) so the planner can use them animated
    public $newImage = null;
    public string $newImageDesc = '';
    /** @var array<int,array{id:string,path:string,description:string}> */
    public array $images = [];

    // background music (same library as shorts) — '' = random, 'nenhuma' = no music
    public string $musica = '';
    public float $musicaVolume = 0.1;

    // ---- editing ----
    public ?int $editingId = null;
    public string $editTitle = '';
    /** @var array<int,array<string,mixed>> scene rows (layers preserved verbatim) */
    public array $editScenes = [];
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

    public function escolherTipo(string $type): void
    {
        $this->createType = in_array($type, ['animation', 'overlay'], true) ? $type : null;
        $this->resetValidation();
    }

    public function voltar(): void
    {
        $this->reset(['createType', 'text', 'audio', 'video', 'allowedPresents', 'newImage', 'newImageDesc', 'images', 'musica', 'musicaVolume', 'editingId', 'editTitle', 'editScenes', 'editMode', 'editPlanJson', 'editTranscriptText']);
        $this->resetValidation();
        $this->view = 'dashboard';
    }

    public function adicionarImagem(): void
    {
        $this->validate([
            'newImage' => 'required|image|max:20480',
            'newImageDesc' => 'required|string|max:200',
        ], [
            'newImage.required' => 'Choose an image.',
            'newImage.image' => 'The file must be an image.',
            'newImage.max' => 'The image is too large (maximum 20 MB).',
            'newImageDesc.required' => 'Describe what the image shows.',
        ], ['newImage' => 'image', 'newImageDesc' => 'description']);

        $path = $this->newImage->store('clips/uploads');
        $abs = Storage::disk(config('contentmachine.clips.disk'))->path($path);
        $this->images[] = [
            'id' => 'img_'.substr(md5($path), 0, 8),
            'path' => $path,
            'description' => trim($this->newImageDesc),
            'transparent' => $this->imageHasAlpha($abs),
            'tone' => $this->imageTone($abs),
        ];
        $this->reset(['newImage', 'newImageDesc']);
    }

    /** Average luminance of the (opaque) pixels → 'light' | 'dark' | 'mixed', for contrast decisions. */
    private function imageTone(string $path): string
    {
        $data = @file_get_contents($path);
        $im = $data ? @imagecreatefromstring($data) : false;
        if (! $im) {
            return 'mixed';
        }
        if (! imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $stepX = max(1, (int) ($w / 24));
        $stepY = max(1, (int) ($h / 24));
        $sum = 0.0;
        $count = 0;
        for ($y = 0; $y < $h; $y += $stepY) {
            for ($x = 0; $x < $w; $x += $stepX) {
                $c = imagecolorat($im, $x, $y);
                if ((($c >> 24) & 0x7F) > 100) {
                    continue; // near-transparent — ignore
                }
                $lum = (0.2126 * (($c >> 16) & 0xFF) + 0.7152 * (($c >> 8) & 0xFF) + 0.0722 * ($c & 0xFF)) / 255;
                $sum += $lum;
                $count++;
            }
        }
        imagedestroy($im);
        if ($count === 0) {
            return 'mixed';
        }
        $avg = $sum / $count;

        return $avg > 0.62 ? 'light' : ($avg < 0.4 ? 'dark' : 'mixed');
    }

    /** Cheap transparency check: PNG colour type (4=GA, 6=RGBA); webp/gif may also have alpha. */
    private function imageHasAlpha(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $bytes = @file_get_contents($path, false, null, 0, 26);

            return strlen((string) $bytes) >= 26 && in_array(ord($bytes[25]), [4, 6], true);
        }

        return in_array($ext, ['webp', 'gif'], true);
    }

    public function removerImagem(int $i): void
    {
        if (isset($this->images[$i])) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($this->images[$i]['path']);
            unset($this->images[$i]);
            $this->images = array_values($this->images);
        }
    }

    // =====================================================================
    // Creation
    // =====================================================================

    public function submitAnimation(): void
    {
        $this->validate([
            'text' => 'required_without:audio|nullable|string|max:5000',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/mp4,audio/aac,audio/ogg,audio/webm,video/webm,video/mp4|max:51200',
        ], [
            'text.required_without' => 'Write a text, record your voice or upload an audio file.',
            'audio.mimetypes' => 'Unsupported audio format. Record again or upload mp3/wav/m4a.',
            'audio.max' => 'The voiceover is too large (maximum 50 MB).',
        ], ['text' => 'text', 'audio' => 'voiceover']);

        $kind = $this->audio ? 'audio' : 'text';
        $path = $this->audio ? $this->audio->store('clips/uploads') : null;

        $project = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => $kind,
            'title' => $this->tituloDe($this->text),
            'source_text' => $kind === 'text' ? $this->text : null,
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(),
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

        $project = ClipProject::create([
            'type' => ClipProject::TYPE_OVERLAY,
            'input_kind' => 'video',
            'title' => $this->video->getClientOriginalName(),
            'source_path' => $path,
            'images' => $this->images ?: null,
            'meta' => $this->musicaMeta(['allowed_present' => array_values($this->allowedPresents)]),
        ]);

        TranscribeJob::dispatch($project->id);
        $this->voltar();
    }

    // =====================================================================
    // Edit clip (animations → re-render)
    // =====================================================================

    public function editarClip(int $id): void
    {
        $p = ClipProject::findOrFail($id);
        $this->editingId = $p->id;
        $this->editTitle = (string) $p->title;
        $this->musica = (string) ($p->meta['musica'] ?? '');
        $this->musicaVolume = (float) ($p->meta['musica_volume'] ?? 0.1);
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
        ];
    }

    public function removerCena(int $i): void
    {
        unset($this->editScenes[$i]);
        $this->editScenes = array_values($this->editScenes);
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

        $p = ClipProject::findOrFail($this->editingId);
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

        $p->update([
            'title' => $this->editTitle ?: $p->title,
            'plan' => $decoded,
            'meta' => $this->musicaMeta($p->meta ?? []),
            'status' => ClipProject::STATUS_RENDERING,
            'error' => null,
        ]);

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

        $p = ClipProject::findOrFail($this->editingId);
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
        $plan = $validator->validate($plan);

        $p->update([
            'title' => $this->editTitle ?: $p->title,
            'plan' => $plan,
            'meta' => $this->musicaMeta($p->meta ?? []),
            'status' => ClipProject::STATUS_RENDERING,
            'error' => null,
        ]);

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

    public function editarTranscricao(int $id): void
    {
        $p = ClipProject::findOrFail($id);
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

        $p = ClipProject::findOrFail($this->editingId);
        $transcript = $p->transcript ?? [];
        $transcript['text'] = trim($this->editTranscriptText);
        $transcript['words'] = TranscriptRebuilder::rebuild(
            $this->editTranscriptText,
            $transcript['words'] ?? [],
            (float) ($transcript['duration'] ?? 0.0)
        );

        $p->update([
            'transcript' => $transcript,
            'status' => ClipProject::STATUS_PLANNING,
            'error' => null,
        ]);

        PlanAnimationsJob::dispatch($p->id);
        $this->voltar();
    }

    // =====================================================================
    // Delete
    // =====================================================================

    public function apagar(int $id): void
    {
        $p = ClipProject::find($id);
        if (! $p) {
            return;
        }

        $dir = storage_path("app/clips/{$p->id}");
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
        if ($p->source_path) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($p->source_path);
        }

        $p->delete();
    }

    // =====================================================================
    // Data for the view
    // =====================================================================

    /** @return \Illuminate\Support\Collection<int,ClipProject> */
    public function getProjectsProperty()
    {
        return ClipProject::latest()->take(50)->get();
    }

    public function getEditingProperty(): ?ClipProject
    {
        return $this->editingId ? ClipProject::find($this->editingId) : null;
    }

    public function getHasActiveProperty(): bool
    {
        return $this->projects->contains(fn (ClipProject $p) => $p->isActive());
    }

    private function tituloDe(?string $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? 'Untitled' : Str::limit($text, 48);
    }

    public function render(\App\Services\Shorts\MusicLibrary $music)
    {
        return view('livewire.clips-animados', [
            'backgrounds' => self::BACKGROUNDS,
            'transitions' => self::TRANSITIONS,
            'musicas' => $music->all(),
        ]);
    }
}
