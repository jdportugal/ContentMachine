<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class RenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(RemotionRenderer $renderer, MusicLibrary $music, ClipStore $store, EffectLibrary $effects, BackgroundLibrary $backgrounds, BackgroundStore $backgroundStore): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_RENDERING]);

            // The remotion effects + backgrounds folders are global — sync the active
            // project's custom SFX and code backgrounds before rendering.
            $effects->syncFilesystem();
            $backgrounds->syncFilesystem();

            $dir = $store->storageDir($p->id);
            @mkdir($dir, 0777, true);
            $plan = $p->plan;

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

            // The news LEAD, pinned on screen for the whole clip (opt-in per clip).
            if (($lead = trim((string) ($p->meta['lead'] ?? ''))) !== '') {
                $plan['banner'] = $lead;
            }

            // Background music (mixed as a looping <Audio> track by Remotion).
            if ($track = $this->resolveMusic($p, $music)) {
                $plan['musicSrc'] = $track;
                $plan['musicVolume'] = (float) ($p->meta['musica_volume'] ?? 0.1);
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

            $out = $renderer->render($plan, "$dir/clip.mp4");
            $p->update(['output_path' => $out, 'status' => ClipRecord::STATUS_DONE]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
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
        $map = static function (&$node) use (&$map, $byId, $disk) {
            if (is_array($node)) {
                foreach ($node as &$v) {
                    $map($v);
                }
                unset($v);
            } elseif (is_string($node) && isset($byId[$node])) {
                $node = $disk->path($byId[$node]);
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
