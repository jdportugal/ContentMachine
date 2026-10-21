<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Projects\ProjectResources;
use App\Services\Publicacoes\Rendering\KieClient;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\RealEstate\PropertyVideoEditor;
use App\Services\RealEstate\RoomStager;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Approved rooms → Kling first/last-frame clips (all submitted first, then
 * polled), then the reel is cut around them: approach, transformation,
 * zoom-through, speed-ramped walk to the next room, music.
 */
class AnimatePropertyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 3600;

    private const NEGATIVE = 'blur, distortion, warped walls, morphing furniture, melting objects, people, text, watermark, low quality';

    public function __construct(public string $clipId)
    {
        $this->captureProject();
    }

    public function handle(PropertyVideoEditor $editor, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $work = $store->storageDir($p->id).'/edit';
        $flags = [];   // this run's notes only: old ones would describe clips that no longer exist

        try {
            $p->update(['status' => ClipRecord::STATUS_ANIMATING]);
            $prop = $p->meta['property'];
            unset($prop['notice']);
            $kie = app(KieClient::class);
            $kie->passo = 'clips_imagens';

            $stager = app(RoomStager::class);
            $todo = [];
            foreach ($prop['rooms'] as $i => $room) {
                $anim = $room['anim'] ?? null;
                // Skipped, unstaged, or already animated from this same still (a re-cut: don't pay twice).
                if ($anim && ! empty($anim['enabled']) && ! empty($anim['staged']) && ! (! empty($anim['video']) && $disk->exists($anim['video']))) {
                    $todo[$i] = [];   // room => the mistakes the next attempt must avoid
                }
            }

            // Up to two rounds: every clip is checked by Claude against the real first
            // frame and the still; one with problems is made again, told what to avoid,
            // and the clip with fewer problems is kept.
            $broke = null;   // kie.ai out of credits: nothing more can start
            for ($round = 1; $round <= 2 && $todo !== [] && ! $broke; $round++) {
                $tasks = [];
                foreach ($todo as $i => $avoid) {
                    if ($broke) {
                        break;
                    }
                    $anim = $prop['rooms'][$i]['anim'];
                    try {
                        [$first, $last] = $this->klingFrames($prop, $anim, $editor, $work);
                        $start = $kie->upload((string) file_get_contents($first), 'start.jpg');
                        $end = $kie->upload((string) file_get_contents($last), 'end.png');
                        // The ramp lands at the reel's pace: the clip covers the section's camera
                        // move at that pace so the motion carries straight on.
                        $seconds = (int) max(3, min(10, round(($anim['end'] - $anim['start']) / PropertyVideoEditor::PACE)));
                        $prompt = PropertyVideoAnalyzer::videoPrompt($anim)
                            .($avoid !== [] ? ' A previous attempt went wrong — avoid exactly this: '.implode('; ', $avoid).'.' : '');
                        $tasks[$i] = $kie->submitFramesVideo($prompt, $start, $end, self::NEGATIVE, $seconds);
                    } catch (\Throwable $e) {
                        if (stripos($e->getMessage(), 'credits insufficient') !== false) {
                            $broke = 'kie.ai is out of credits';
                        } else {
                            $flags[] = "{$prop['rooms'][$i]['room']}: the animation could not start (".Str::limit($e->getMessage(), 120).')';
                        }
                    }
                }

                $this->step($store, 'Kling is animating '.count($tasks).' room'.(count($tasks) === 1 ? '' : 's').($round > 1 ? ' again (fixing what the check found)' : '').' — a few minutes');
                $todo = [];
                foreach ($tasks as $i => $taskId) {
                    $anim = &$prop['rooms'][$i]['anim'];
                    try {
                        $rel = 'clips/uploads/'.Str::random(40).'.mp4';
                        $disk->put($rel, Http::timeout(300)->get($kie->pollVideo($taskId))->throw()->body());
                    } catch (\Throwable $e) {
                        $flags[] = "{$prop['rooms'][$i]['room']}: the animation failed (".Str::limit($e->getMessage(), 120).')';
                        unset($anim);

                        continue;
                    }
                    $this->step($store, "Checking the {$prop['rooms'][$i]['room']} animation for doors and openings");
                    [$first, $last] = $this->klingFrames($prop, $anim, $editor, $work);
                    $problems = $stager->verifyClip($first, $last, $disk->path($rel), $anim['keep'] ?? []);
                    $better = empty($anim['video']) || ! $disk->exists($anim['video']) || count($problems) < count($anim['clip_problems'] ?? []);
                    if ($better) {
                        if (! empty($anim['video'])) {
                            $disk->delete($anim['video']);
                        }
                        $anim['video'] = $rel;
                        $anim['clip_problems'] = $problems;
                        unset($anim['onset']);   // measured again for the new clip
                    } else {
                        $disk->delete($rel);
                    }
                    if ($anim['clip_problems'] !== []) {
                        $todo[$i] = $anim['clip_problems'];
                    }
                    unset($anim);
                }
            }
            foreach ($prop['rooms'] as $room) {
                if (! empty($room['anim']['clip_problems'])) {
                    $flags[] = "{$room['room']}: the animation still has problems — ".implode('; ', $room['anim']['clip_problems']);
                }
            }

            // A room meant to be animated that has no clip yet (out of credits, kie.ai down):
            // no reel without it — back to the review with what's made kept, so animating
            // again makes only the missing ones.
            $missing = collect($prop['rooms'])->filter(fn ($r) => ! empty($r['anim']['enabled']) && ! empty($r['anim']['staged'])
                && ! (! empty($r['anim']['video']) && $disk->exists($r['anim']['video'])))->pluck('room')->all();
            if ($missing !== []) {
                $made = collect($prop['rooms'])->filter(fn ($r) => ! empty($r['anim']['video']))->count();
                $prop['notice'] = (count($missing).' room'.(count($missing) === 1 ? '' : 's').' could not be animated ('.implode(', ', $missing).')'
                    .($broke ? " — {$broke}: top up at kie.ai" : '')
                    .'. The reel was not cut. The '.$made.' clip'.($made === 1 ? '' : 's').' already made '.($made === 1 ? 'is' : 'are').' kept — press ▶ Animate & cut the reel again to make only the missing ones.');
                $p = $store->findOrFail($this->clipId);
                $p->update([
                    'status' => ClipRecord::STATUS_REVIEWING_ROOMS,
                    'meta' => array_merge($p->meta, [
                        'property' => $prop,
                        'listing' => array_merge($p->meta['listing'] ?? [], ['flags' => $flags]),
                        'step' => null,
                    ]),
                ]);

                return;
            }

            // The reel is only the clean transformations: a skipped section, one whose
            // clip failed, or one Claude still found problems in after both attempts is
            // left out (and named in the flags), never shown as is.
            $rooms = [];
            foreach ($prop['rooms'] as $i => $room) {
                $anim = $room['anim'] ?? [];
                $clean = ! empty($anim['enabled']) && ! empty($anim['video']) && $disk->exists($anim['video']) && empty($anim['clip_problems']);
                if ($clean && ! isset($anim['onset'])) {
                    // When the room starts changing, so the editor can hurry the unchanged start.
                    $this->step($store, "Finding where the {$room['room']} starts changing");
                    $anim['onset'] = $prop['rooms'][$i]['anim']['onset'] = $stager->changeStart($disk->path($anim['video']));
                }
                if ($clean) {
                    $room['anim'] = $anim + ['clip' => $disk->path($anim['video'])];
                    $rooms[] = $room;
                } elseif (! empty($anim['enabled'])) {
                    $flags[] = "{$room['room']}: left out of the reel — its animation isn't clean.";
                }
            }
            if ($rooms === []) {
                throw new \RuntimeException('No section has a clean animation to cut a reel from.');
            }

            // For now the reel is the deliverable: footage, transformations and music —
            // no narration or on-screen text (those come back through the render pipeline later).
            $this->step($store, 'Cutting the reel');
            $out = $store->storageDir($p->id).'/clip.mp4';
            // The final format, and the size the intro and outro cards are drawn at.
            $aspect = (string) ($prop['aspect'] ?? 'source');
            $size = PropertyVideoEditor::targetSize($aspect, (int) $prop['width'], (int) $prop['height']);

            // The music chosen for the clip (none if the user picked none), else the project's, else the listing default.
            $choice = trim((string) ($p->meta['musica'] ?? '')) ?: app(ProjectResources::class)->music() ?: (string) config('contentmachine.real_estate.music');
            $music = $choice === 'nenhuma' ? null : app(MusicLibrary::class)->pathFor($choice);
            $volume = (float) ($p->meta['musica_volume'] ?? 0) > 0 ? (float) $p->meta['musica_volume'] : null;
            $voice = ! empty($prop['voice']) && $disk->exists($prop['voice']) ? $disk->path($prop['voice']) : null;
            $editor->assemble($disk->path($prop['proxy']), $rooms, $music, $out, $work, $voice, $volume, $this->outro($p, $work, $size), $this->intro($p, $work, $size), $aspect, $this->afterIntro($p, $voice));

            $length = PropertyVideoAnalyzer::probe($out)['duration'];
            if ($length > 59.5) {
                $flags[] = 'The reel is '.round($length).' s — over a minute. Shorten some sections (3–4 s each fits ten) or remove a few.';
            }
            $p = $store->findOrFail($this->clipId);
            $p->update([
                'status' => ClipRecord::STATUS_DONE,
                'output_path' => $out,
                'error' => null,
                'meta' => array_merge($p->meta, [
                    'property' => $prop,
                    'listing' => array_merge($p->meta['listing'] ?? [], ['flags' => $flags]),
                    'step' => null,
                ]),
            ]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * The project's agent outro card (its outro effect — the Abílio template's
     * "agente-revelacao"), rendered on its own from the listing: the agent's
     * photo, name and phone, the agency and its logo. Null without a listing
     * agent, or when the project has no outro effect.
     */
    /** @param array{0:int,1:int} $size */
    private function outro(ClipRecord $p, string $work, array $size): ?string
    {
        $listing = $p->meta['listing'] ?? [];
        $library = app(EffectLibrary::class);
        $slug = $library->outroSlugs()[0] ?? null;
        if (empty($listing['agent']) || ! $slug) {
            return null;
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $image = fn (callable $is) => collect($p->images ?? [])->first(fn ($img) => $is($img) && ! empty($img['path']) && $disk->exists($img['path']));
        $photo = $image(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato'));
        $logo = $image(fn ($img) => str_starts_with(strtolower((string) ($img['name'] ?? '')), 'logo.'));
        $role = (($listing['gender'] ?? 'm') === 'f' ? 'Consultora imobiliária' : 'Consultor imobiliário').' · '.($listing['agency'] ?? '');

        $this->step(app(ClipStore::class), 'Rendering the agent outro');
        try {
            $out = "{$work}/outro-render.mp4";
            @mkdir($work, 0775, true);
            $plan = $library->samplePlan($slug, $listing['agent'], array_filter([
                'src' => $photo ? $disk->path($photo['path']) : null,
                'logo' => $logo ? $disk->path($logo['path']) : null,
                'name' => $listing['agent'],
                'role' => $role,
                'phone' => $listing['phone'] ?? null,
                'email' => $listing['email'] ?? null,
                'ami' => $listing['licence'] ?? null,
                'buttons' => trim(($listing['phone'] ?? '').' | Marque a sua visita', ' |'),
            ]), $size[0] > $size[1] ? EffectLibrary::FORMAT_LANDSCAPE : EffectLibrary::FORMAT_PORTRAIT, 4.0);
            [$plan['width'], $plan['height']] = $size;   // drawn at the reel's own format
            app(RemotionRenderer::class)->render($plan, $out);

            return is_file($out) ? $out : null;
        } catch (\Throwable $e) {
            Log::warning('Property outro failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * The project's intro card (its intro effect — the Abílio template's
     * "imovel-revelacao"): title, location and facts, price, the agent and the
     * agency, rendered with transparency and a pure-green photo panel that the
     * editor keys out, so the first section plays through it. Null without an agent.
     */
    /** @param array{0:int,1:int} $size */
    private function intro(ClipRecord $p, string $work, array $size): ?string
    {
        $listing = $p->meta['listing'] ?? [];
        $library = app(EffectLibrary::class);
        $slug = $library->introSlugs()[0] ?? null;
        if (empty($listing['agent']) || ! $slug) {
            return null;
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $image = fn (callable $is) => collect($p->images ?? [])->first(fn ($img) => $is($img) && ! empty($img['path']) && $disk->exists($img['path']));
        $photo = $image(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato'));
        $logo = $image(fn ($img) => str_starts_with(strtolower((string) ($img['name'] ?? '')), 'logo.'));
        $details = (array) ($listing['details'] ?? []);
        $fact = fn (string $needle) => collect($details)->first(fn ($v, $k) => stripos((string) $k, $needle) !== false);
        $price = ! empty($listing['price']) && is_numeric($listing['price'])
            ? number_format((float) $listing['price'], 0, ',', '.').' €'
            : str_replace(' ', '.', trim((string) ($fact('Preço') ?? '')));
        $area = $fact('Área Útil') ?? $fact('Área');
        $rooms = $fact('Quartos');
        $location = implode(' · ', array_filter([
            $fact('Localização') ? trim(explode(',', (string) $fact('Localização'))[0]) : null,
            $area ? preg_replace('/\s*m²?$/u', '', (string) $area).' m²' : null,
            $rooms ? "{$rooms} quartos" : null,
        ]));
        $title = trim((string) ($listing['title'] ?? '')) ?: 'Imóvel à venda';

        $this->step(app(ClipStore::class), 'Rendering the intro');
        try {
            @mkdir($work, 0775, true);
            $green = "{$work}/intro-green.png";
            (new Process(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'color=c=0x00FF00:s=1600x1200', '-frames:v', '1', $green]))->run();
            $out = "{$work}/intro-render.mov";
            // No price or facts: '' hides them (left out, the card would show its sample values).
            $plan = $library->samplePlan($slug, $title, array_filter([
                'src' => $green,
                'avatar' => $photo ? $disk->path($photo['path']) : null,
                'logo' => $logo ? $disk->path($logo['path']) : null,
                'price' => (string) $price,
                'eyebrow' => 'Novo imóvel',
                'title' => Str::limit($title, 40, '…'),
                'location' => $location,
                'agentName' => $listing['agent'],
                'phone' => (string) ($listing['phone'] ?? ''),
                'legal' => (string) ($listing['agency'] ?? ''),
            ], fn ($v) => $v !== null), $size[0] > $size[1] ? EffectLibrary::FORMAT_LANDSCAPE : EffectLibrary::FORMAT_PORTRAIT, 3.5);
            [$plan['width'], $plan['height']] = $size;   // drawn at the reel's own format
            $plan['transparent'] = true;
            app(RemotionRenderer::class)->render($plan, $out);

            return is_file($out) ? $out : null;
        } catch (\Throwable $e) {
            Log::warning('Property intro failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * The after-intro video (this clip's, else the project's) and what's needed to
     * start it on its line: the narration's words. Null when there is none.
     *
     * @return array{path:string,words:list<array>,line:string}|null
     */
    private function afterIntro(ClipRecord $p, ?string $voice): ?array
    {
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $effects = app(EffectStore::class);
        $path = ($p->meta['after_intro'] ?? null) ?: $effects->afterIntroPath();
        if (! $path || ! $disk->exists($path)) {
            return null;
        }
        $words = [];
        if ($voice) {
            $this->step(app(ClipStore::class), 'Finding the after-intro line in the voiceover');
            try {
                $words = (array) (app(TranscriptionService::class)->transcribe($voice)['words'] ?? []);
            } catch (\Throwable $e) {
                Log::warning('After-intro: the voiceover could not be transcribed: '.$e->getMessage());   // it then pauses near the first cut
            }
        }

        return ['path' => $disk->path($path), 'words' => $words, 'line' => $effects->afterIntroLineFor($p->meta ?? [])];
    }

    /** What the job is doing right now, for the clip card. */
    /**
     * The first and last frames Kling animates between: the real frame and the modern
     * still. A photo has no camera move of its own, so one end is moved in — the end
     * when the camera goes forward into the room, the start when it pulls back.
     *
     * @return array{0:string,1:string}
     */
    private function klingFrames(array $prop, array $anim, PropertyVideoEditor $editor, string $work): array
    {
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        [$first, $last] = [$disk->path($anim['start_frame']), $disk->path($anim['staged'])];
        if (empty($prop['photos'])) {
            return [$first, $last];
        }
        $out = "{$work}/push-".md5($anim['start_frame'].$anim['staged'].$anim['motion']).'.jpg';
        if (! is_file($out)) {
            $editor->push($anim['motion'] === 'backward' ? $first : $last, $out);
        }

        return $anim['motion'] === 'backward' ? [$out, $last] : [$first, $out];
    }

    private function step(ClipStore $store, ?string $text): void
    {
        $p = $store->find($this->clipId);
        $p?->update(['meta' => array_merge($p->meta ?? [], ['step' => $text])]);
    }
}
