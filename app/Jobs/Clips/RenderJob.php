<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\BeatOpening;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class RenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    /** @param  ?float  $from  re-render only from this second (the ending), joined onto the clip already rendered */
    /**
     * @param  bool  $limpo  render a free video's CLEAN copy (no watermark) to clip-limpo.mp4, next to the
     *                       watermarked one, so a purchase can hand it over at once. Leaves the clip's status
     *                       and output alone; a failure is logged, never shown as the clip failing.
     */
    public function __construct(public string $projectId, public ?float $from = null, public bool $limpo = false)
    {
        $this->captureProject();
    }

    public function handle(RemotionRenderer $renderer, MusicLibrary $music, ClipStore $store, EffectLibrary $effects, BackgroundLibrary $backgrounds, BackgroundStore $backgroundStore): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        if ($this->limpo) {
            $this->renderLimpo($store, $renderer, $music, $backgroundStore);

            return;
        }

        try {
            $p->update(['status' => ClipRecord::STATUS_RENDERING]);

            // (The shared remotion effects/backgrounds folders are filled with this project's by the renderer, under its lock.)

            [$plan, $opening, $dir, $track] = $this->montarPlano($p, $store, $music, $backgroundStore);

            $out = $this->from !== null && is_file("$dir/clip.mp4")
                ? $this->renderTail($renderer, $plan, $dir, $this->from + $opening)
                : $renderer->render($plan, "$dir/clip.mp4");
            // Clear the previous failure: a clip that renders after a failed attempt
            // kept the old message and showed as done WITH an error next to it.
            $atributos = ['output_path' => $out, 'status' => ClipRecord::STATUS_DONE, 'error' => null];
            // "Send straight to Finished": the same flag the promote button sets, set
            // here instead, so the clip lands in the hub without the extra click.
            if (! empty($p->meta['auto_finish'])) {
                $atributos['finished'] = true;
            }
            $p->update($atributos);

            // A free video: render its clean copy right away, so paying delivers it instantly.
            if (! empty($p->meta['free_lead']) && $this->from === null) {
                $p->update(['meta' => array_merge((array) $p->refresh()->meta, ['limpo_pendente' => true, 'musica_usada' => $track])]);
                self::dispatch($p->id, null, true);
            }

            // Auto clips: schedule on Blotato's next free slot as soon as the render lands.
            if (! empty($p->meta['auto']) && ! $p->get('publish_state')) {
                AutoPublishClipJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);

            // A render killed by the clock will be killed by it again: each retry
            // costs another full ceiling of worker time and cannot end differently.
            // Fail it outright and leave the retries for the transient failures
            // they exist for — three attempts at 1500s is 75 minutes of a blocked
            // worker, and the clip flips rendering→failed once per attempt.
            if ($e instanceof ProcessTimedOutException) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * Everything the renderer needs for this clip: the plan with the opening, captions, audio,
     * background, music, theme and (for a free video, unless rendering its clean copy) the watermark.
     *
     * @return array{0: array<string,mixed>, 1: float, 2: string, 3: ?string}
     */
    private function montarPlano(ClipRecord $p, ClipStore $store, MusicLibrary $music, BackgroundStore $backgroundStore): array
    {
        $dir = $store->storageDir($p->id);
        @mkdir($dir, 0777, true);
        $plan = $p->plan;
        // Resolved once: a random pick must be the same track the opening was cut to.
        // A free video's clean copy reuses the track the watermarked one was cut to.
        $usada = (string) ($p->meta['musica_usada'] ?? '');
        $track = $this->limpo && $usada !== '' && is_file($usada) ? $usada : $this->resolveMusic($p, $music);
        [$plan, $opening] = $this->prependOpening($plan, $p, $track);

        // Karaoke captions read absolute word timestamps from the transcript.
        $plan['words'] = $p->transcript['words'] ?? [];

        // Resolve image-reveal layers' image ids to absolute file paths.
        $plan['scenes'] = $this->resolveImages($plan['scenes'] ?? [], $p);

        // Attach each effect's sound (sfx-audio/<slug>.*) to the layers using it.
        $plan['scenes'] = $this->attachEffectAudio($plan['scenes'], app(EffectStore::class));

        // Resolve the chosen backdrop slug → a concrete component/video the
        // renderer understands. A vanished/removed background drops to the theme.
        $plan['background'] = $this->resolveBackground($plan['background'] ?? null, $backgroundStore);
        if ($plan['background'] === null) {
            unset($plan['background']);
        }

        $plan['audioSrc'] = $p->audio_path;

        // An "after intro" video (SceneVisualFiller::insertAfterIntro): its scene names it by clips-disk
        // path, and the voiceover waits for it where the plan says.
        foreach ($plan['scenes'] as &$scene) {
            if (! empty($scene['plain']) && is_string($src = $scene['layers'][0]['params']['src'] ?? null) && Storage::disk(config('contentmachine.clips.disk'))->exists($src)) {
                $scene['layers'][0]['params']['src'] = Storage::disk(config('contentmachine.clips.disk'))->path($src);
            }
        }
        unset($scene);
        if (($gap = $plan['voice_gap'] ?? null) && $gap['seconds'] > 0) {
            $plan['audioSrc'] = $p->audio_path ? $this->openVoiceGap($p->audio_path, (float) $gap['at'], (float) $gap['seconds'], "$dir/voice-gap.m4a") : null;
            $plan['words'] = array_map(fn ($w) => (float) $w['start'] >= $gap['at']
                ? array_merge($w, ['start' => $w['start'] + $gap['seconds'], 'end' => $w['end'] + $gap['seconds']]) : $w, $plan['words']);
        }
        // The voice starts when the music-only opening ends.
        if ($opening > 0) {
            $plan['audioSrc'] = $plan['audioSrc'] ? $this->delayVoice($plan['audioSrc'], $opening, "$dir/voice-opening.m4a") : null;
            $plan['words'] = array_map(fn ($w) => array_merge($w, ['start' => $w['start'] + $opening, 'end' => $w['end'] + $opening]), $plan['words']);
        }

        // Where the karaoke captions sit — or none at all. Bottom is the default.
        $captions = (string) ($p->meta['captions'] ?? 'bottom');
        $plan['captions'] = in_array($captions, ClipRecord::CAPTIONS, true) ? $captions : 'bottom';

        // The news LEAD, pinned on screen for the whole clip (opt-in per clip).
        if (($lead = trim((string) ($p->meta['lead'] ?? ''))) !== '') {
            $plan['banner'] = $lead;
        }

        // Free videos from the landing page carry the watermark; paid ones (and a free one's clean copy) don't.
        if (! empty($p->meta['free_lead']) && ! $this->limpo) {
            $plan['watermark'] = (string) config('contentmachine.leads.watermark');
        }

        // Background music (mixed as a looping <Audio> track by Remotion).
        if ($track) {
            $plan['musicSrc'] = $track;
            $plan['musicVolume'] = (float) ($p->meta['musica_volume'] ?? 0.1);
            if ($opening > 0) {
                $plan['musicIntro'] = ['seconds' => $opening, 'volume' => (float) config('contentmachine.real_estate.opening_music_volume', 0.7)];
            }
        }

        // Design System theme (colors/fonts/texture) — makes the animation
        // match the brand. Null → the renderer uses the Brand Machine defaults.
        if ($theme = app(DesignSystemRepository::class)->readTokens()) {
            $plan['theme'] = $theme;
        }

        // Overlay clips: Remotion composites the source video per-scene (over / split /
        // video / animation). Everything renders in one opaque pass — no ffmpeg step.
        if ($p->type === ClipRecord::TYPE_OVERLAY) {
            $plan['transparent'] = false;
            $plan['videoSrc'] = Storage::disk(config('contentmachine.clips.disk'))->path($p->source_path);
        }

        return [$plan, $opening, $dir, $track];
    }

    /** The clean copy of a free video (see $limpo). Never touches the clip's status or output. */
    private function renderLimpo(ClipStore $store, RemotionRenderer $renderer, MusicLibrary $music, BackgroundStore $backgroundStore): void
    {
        $p = $store->findOrFail($this->projectId);
        try {
            [$plan, , $dir] = $this->montarPlano($p, $store, $music, $backgroundStore);
            $limpo = $renderer->render($plan, "$dir/clip-limpo.mp4");
            $p->refresh();
            $meta = array_merge((array) $p->meta, ['limpo_path' => $limpo, 'limpo_pendente' => false]);
            $atributos = ['meta' => $meta];
            // Paid while this was rendering: hand the clean file over now.
            if (! empty($meta['pago'])) {
                $atributos['output_path'] = $limpo;
                $atributos['meta']['free_lead'] = false;
            }
            $p->update($atributos);
        } catch (\Throwable $e) {
            Log::warning('Cópia limpa falhou', ['clip' => $this->projectId, 'erro' => $e->getMessage()]);
            $p->refresh();
            $meta = array_merge((array) $p->meta, ['limpo_pendente' => false]);
            $p->update(['meta' => $meta]);
            // Paid and no clean copy: render the clip itself clean, the slow way.
            if (! empty($meta['pago'])) {
                $p->update(['meta' => array_merge($meta, ['free_lead' => false])]);
                self::dispatch($p->id);
            }
        }
    }

    /**
     * Resolve the clip's music choice to an absolute file path, or null.
     * Mirrors the shorts pipeline: 'nenhuma' → none, ''/'aleatoria' → random
     * track, a name → that track (falling back to random if it vanished).
     */
    private function resolveMusic(ClipRecord $p, MusicLibrary $music): ?string
    {
        $choice = trim((string) ($p->meta['musica'] ?? ''));

        if ($choice === 'nenhuma') {
            return null;
        }
        if ($choice === '' || $choice === 'aleatoria') {
            return $music->randomPath();
        }

        return $music->pathFor($choice) ?? $music->randomPath();
    }

    /**
     * Resolve a stored background slug into the concrete shape ClipComposition
     * expects: code → {kind:code, slug}; video → {kind:video, src:<abs mp4 path>}
     * (staged into Remotion's public/ by the renderer). A slug that no longer maps
     * to an active background → null (falls back to the themed backdrop). An already
     * resolved array (e.g. a hand-edited plan) is passed through.
     */
    private function resolveBackground(mixed $background, BackgroundStore $store): ?array
    {
        if (is_array($background)) {
            return $background; // already resolved (raw plan edit)
        }
        if (! is_string($background) || $background === '') {
            return null;
        }

        $rec = $store->findBySlug($background);
        if (! $rec || ! $rec->isActive()) {
            return null;
        }

        return $rec->kind === BackgroundStore::KIND_VIDEO
            ? ['kind' => 'video', 'src' => $store->videoPath($rec->id())]
            : ['kind' => 'code', 'slug' => $rec->slug];
    }

    /**
     * Only the ending again (an outro card changed): the frames from $from on are
     * rendered and joined onto the clip already rendered, cut on that frame —
     * everything before it stays exactly as it was.
     */
    private function renderTail(RemotionRenderer $renderer, array $plan, string $dir, float $from): string
    {
        $fps = (int) ($plan['fps'] ?? config('contentmachine.clips.fps', 30));
        $first = (int) round($from * $fps);
        $plan['frameRange'] = [$first, max($first, (int) ceil((float) $plan['duration'] * $fps) - 1)];
        $renderer->render($plan, "$dir/tail.mp4");
        $cut = $first / $fps;
        (new Process(['ffmpeg', '-y', '-v', 'error', '-i', "$dir/clip.mp4", '-i', "$dir/tail.mp4", '-filter_complex',
            "[0:v]trim=end={$cut},setpts=PTS-STARTPTS[v0];[0:a]atrim=end={$cut},asetpts=PTS-STARTPTS[a0];[v0][a0][1:v][1:a]concat=n=2:v=1:a=1[v][a]",
            '-map', '[v]', '-map', '[a]', '-c:v', 'libx264', '-crf', '17', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k',
            '-movflags', '+faststart', "$dir/clip-joined.mp4"]))->setTimeout(600)->mustRun();
        rename("$dir/clip-joined.mp4", "$dir/clip.mp4");
        @unlink("$dir/tail.mp4");

        return "$dir/clip.mp4";
    }

    /**
     * A beat-synced opening (meta.beat_opening, set by the listing import): the
     * property's three best clips, music only, changing on the track's bars,
     * put before the reel — every scene moves back by its length. Done at render
     * time so re-planning, improving or re-voicing the clip never has to keep it.
     *
     * @return array{0:array,1:float} [plan, the opening's length in seconds (0: none)]
     */
    private function prependOpening(array $plan, ClipRecord $p, ?string $track): array
    {
        $want = $p->meta['beat_opening'] ?? null;
        if (! is_array($want) || ! $track || empty($plan['scenes'])) {
            return [$plan, 0.0];
        }
        $videos = collect($p->images ?? [])->filter(fn ($i) => ! empty($i['video']) && str_starts_with((string) ($i['id'] ?? ''), 'vid_'))->pluck('id');
        // The clips ranked at import, else the first videos.
        $ranked = collect($want['clips'] ?? [])->intersect($videos)->values()->concat($videos)->unique()->values();
        if ($ranked->isEmpty()) {
            return [$plan, 0.0];
        }
        $cuts = BeatOpening::cuts($track, min(3, $ranked->count()));
        // Each clip plays its turn plus the dissolves around it (MontageScene: 0.15 s each side, 0.5 s into the reel).
        $turns = [];
        foreach (array_slice($cuts, 1) as $i => $cut) {
            $turns[] = $cut - $cuts[$i] + ($i > 0 ? 0.15 : 0) + ($i < count($cuts) - 2 ? 0.15 : 0.5);
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $paths = collect($p->images)->pluck('path', 'id');
        $length = $ranked->mapWithKeys(fn ($id) => [$id => rescue(fn () => PropertyVideoAnalyzer::probe($disk->path($paths[$id]))['duration'], 0.0, false)]);
        // Strongest first, skipping one that would have to slow down visibly (below 85%) for its turn.
        $clips = [];
        foreach ($turns as $turn) {
            $pick = $ranked->diff($clips)->first(fn ($id) => $length[$id] >= 0.85 * $turn) ?? $ranked->diff($clips)->first();
            if ($pick === null) {
                break;
            }
            $clips[] = $pick;
        }
        $cuts = array_slice($cuts, 0, count($clips) + 1);
        $rates = array_map(fn ($id, $turn) => $length[$id] > 0 ? round(min(1.0, $length[$id] / $turn), 3) : 1.0, $clips, array_slice($turns, 0, count($clips)));
        $seconds = (float) end($cuts);
        foreach ($plan['scenes'] as &$scene) {
            $scene['start'] = (float) $scene['start'] + $seconds;
            $scene['end'] = (float) $scene['end'] + $seconds;
        }
        unset($scene);
        $plan['scenes'][0]['transitionIn'] = 'cut';
        array_unshift($plan['scenes'], [
            'start' => 0.0, 'end' => $seconds, 'present' => 'animation', 'background' => 'ink', 'punchWord' => null, 'karaoke' => false,
            'transitionIn' => 'cut', 'transitionOut' => 'cut', 'montage' => true,
            'layers' => [['type' => 'montage', 'text' => '', 'params' => ['clips' => $clips, 'cuts' => $cuts, 'rates' => $rates]]],
        ]);
        $plan['duration'] = (float) ($plan['duration'] ?? 0) + $seconds;

        return [$plan, $seconds];
    }

    /** The voiceover $seconds later (silence before it). */
    private function delayVoice(string $audio, float $seconds, string $out): string
    {
        $src = is_file($audio) ? $audio : Storage::disk(config('contentmachine.clips.disk'))->path($audio);
        $ms = (int) round($seconds * 1000);
        (new Process(['ffmpeg', '-y', '-v', 'error', '-i', $src, '-af', "adelay={$ms}:all=1", '-c:a', 'aac', '-b:a', '192k', $out]))->setTimeout(120)->mustRun();

        return $out;
    }

    /** The voiceover with $seconds of silence opened at $at. */
    private function openVoiceGap(string $audio, float $at, float $seconds, string $out): string
    {
        $ms = (int) round($seconds * 1000);
        (new Process(['ffmpeg', '-y', '-v', 'error', '-i', $audio, '-filter_complex',
            "[0:a]atrim=end={$at},asetpts=PTS-STARTPTS[a];[0:a]atrim=start={$at},asetpts=PTS-STARTPTS,adelay={$ms}:all=1[b];[a][b]concat=n=2:v=0:a=1",
            '-c:a', 'aac', '-b:a', '192k', $out]))->setTimeout(120)->mustRun();

        return $out;
    }

    /** Set layer.audioSrc to the effect's sound file (by slug), for layers that have one. */
    private function attachEffectAudio(array $scenes, EffectStore $effects): array
    {
        foreach ($scenes as &$scene) {
            if (empty($scene['layers']) || ! is_array($scene['layers'])) {
                continue;
            }
            foreach ($scene['layers'] as &$layer) {
                $slug = is_array($layer) ? ($layer['type'] ?? null) : null;
                if ($slug && ($path = $effects->audioPath($slug))) {
                    $layer['audioSrc'] = $path;
                }
            }
            unset($layer);
        }
        unset($scene);

        return $scenes;
    }

    /** Map image-reveal layers' `params.src` (an image id) to the absolute file path. */
    private function resolveImages(array $scenes, ClipRecord $p): array
    {
        $byId = [];
        $transById = [];
        $toneById = [];
        foreach ($p->images ?? [] as $img) {
            if (isset($img['id'], $img['path'])) {
                $byId[$img['id']] = $img['path'];
                $transById[$img['id']] = (bool) ($img['transparent'] ?? false);
                $toneById[$img['id']] = $img['tone'] ?? 'mixed';
            }
        }
        if (empty($byId)) {
            return $scenes;
        }

        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $darkBackground = ['ink'];

        // Propagate transparency + a contrasting backing (so an image whose tone
        // matches its scene background is never lost) onto image-reveal layers/nodes.
        foreach ($scenes as &$scene) {
            $bgDark = in_array($scene['background'] ?? 'papyrus', $darkBackground, true);
            foreach ($scene['layers'] ?? [] as &$layer) {
                $params = &$layer['params'];
                if (($layer['type'] ?? null) === 'image-reveal') {
                    $id = $params['src'] ?? '';
                    if (isset($transById[$id])) {
                        $params['transparent'] = $transById[$id];
                        $backing = $this->backingFor($toneById[$id] ?? 'mixed', $bgDark, $scene['background'] ?? 'papyrus');
                        if ($backing) {
                            $params['backing'] = $backing;
                        }
                    }
                }
                foreach ($params['nodes'] ?? [] as &$node) {
                    $imgId = $node['image'] ?? $node['img'] ?? null;
                    if ($imgId && isset($transById[$imgId])) {
                        $node['transparent'] = $transById[$imgId];
                    }
                }
                unset($node, $params);
            }
            unset($layer);
        }
        unset($scene);

        // Replace any image-id string, anywhere in a layer's params (image-reveal.src,
        // bar.image, timeline item.image, comparison.image, …), with its file path.
        //
        // An id whose FILE is gone must be cleared, not mapped. The renderer only
        // stages assets it can see on disk (is_file), so a dead path travelled all
        // the way into the composition and blew up the whole render on
        // `staticFile() does not support absolute paths` — one missing picture
        // taking the entire clip with it. Leaving the bare id behind is no better:
        // ImageReveal staticFile()s any non-http value, so that throws too.
        //
        // Emptied, ImageReveal skips staticFile() and renders an empty <Img>: that
        // ONE layer is blank, and every other scene still renders. Blank rather
        // than absent because dropping the layer here is after the bare-scene
        // passes, so it could leave the scene with nothing at all.
        // ponytail: no placeholder art — the honest fix is to re-supply the image.
        $map = static function (&$node) use (&$map, $byId, $disk) {
            if (is_array($node)) {
                foreach ($node as &$v) {
                    $map($v);
                }
                unset($v);
            } elseif (is_string($node) && isset($byId[$node])) {
                $caminho = $disk->path($byId[$node]);
                $node = is_file($caminho) ? $caminho : '';
            }
        };

        foreach ($scenes as &$scene) {
            if (! empty($scene['layers']) && is_array($scene['layers'])) {
                $map($scene['layers']);
            }
        }
        unset($scene);

        return $scenes;
    }

    /** A contrasting backing colour when an image's tone would clash with its scene bg. */
    private function backingFor(string $tone, bool $bgDark, string $bg): ?string
    {
        $dark = 'rgba(26,20,16,0.92)';   // ink-ish
        $light = 'rgba(250,243,224,0.94)'; // vellum-ish

        if ($bg === 'video') {
            // Unknown background — back a light image with dark, a dark image with light.
            return $tone === 'light' ? $dark : ($tone === 'dark' ? $light : null);
        }
        if ($tone === 'light' && ! $bgDark) {
            return $dark; // light image on light bg
        }
        if ($tone === 'dark' && $bgDark) {
            return $light; // dark image on dark bg
        }

        return null;
    }
}
