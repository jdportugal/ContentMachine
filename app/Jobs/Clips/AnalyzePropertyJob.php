<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Projects\ProjectResources;
use App\Services\RealEstate\AgencyLogoFinder;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\RealEstate\PropertyVideoEditor;
use App\Services\RealEstate\RoomStager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Walkthrough → a stabilized, colour-corrected working copy and a filmstrip to
 * mark sections on (prepare), then — once the user has marked them — each
 * section's real first frame and a checked modern version of its last (stage). Stops on "reviewing_rooms" so the user approves the stills
 * before any (paid) video is generated.
 */
class AnalyzePropertyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 3600;

    /** false: clean the footage and wait for the sections; true: stage the sections. */
    public function __construct(public string $clipId, public bool $stage = false)
    {
        $this->captureProject();
    }

    public function handle(PropertyVideoAnalyzer $analyzer, PropertyVideoEditor $editor, RoomStager $stager, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $work = $store->storageDir($p->id).'/analysis';

        try {
            $p->update(['status' => ClipRecord::STATUS_ANALYZING]);
            $prop = $p->meta['property'];
            // Constant-frame-rate working copy: phone footage is VFR and its
            // timestamps need not start at 0; every step below reads this.
            if (empty($prop['proxy']) || ! $disk->exists($prop['proxy'])) {
                $prop['proxy'] = 'clips/uploads/'.Str::random(40).'.mp4';
                if (! empty($prop['photos'])) {
                    // Photos instead of a walkthrough: a still copy, one photo after the other.
                    $this->step($store, 'Colour-correcting the photos');
                    $editor->slideshow(array_map(fn ($f) => $disk->path($f), $prop['photos']), $disk->path($prop['proxy']));
                } else {
                    $this->step($store, 'Stabilizing and colour-correcting the footage');
                    $editor->proxy($disk->path($prop['video']), $disk->path($prop['proxy']));
                }
            }
            $video = $disk->path($prop['proxy']);
            $prop = array_merge($prop, PropertyVideoAnalyzer::probe($video));

            if (! $this->stage && ! empty($prop['photos'])) {
                // Photos: each one is a section (no picker), the camera moving into the
                // room on one and back out of it on the next.
                $this->agencyLogo($p, $store);
                $prop['sections'] = array_map(fn ($i) => [
                    'start' => $i * PropertyVideoEditor::PHOTO_SECONDS + 0.2,
                    'end' => ($i + 1) * PropertyVideoEditor::PHOTO_SECONDS - 0.2,
                    'note' => '',
                    'motion' => $i % 2 ? 'backward' : 'forward',
                ], array_keys($prop['photos']));
            } elseif (! $this->stage) {
                // The agent typed in by hand (no listing): find the agency's logo for the outro.
                $this->agencyLogo($p, $store);

                // Prepare: the filmstrip the user marks the sections on, then wait for them.
                if (empty($prop['preview']) || ! $disk->exists($prop['preview'])) {
                    $prop['preview'] = 'clips/uploads/'.Str::random(40).'.mp4';
                    $editor->preview($video, $disk->path($prop['preview']));
                }
                if (empty($prop['strip']) || ! $disk->exists($prop['strip'])) {
                    $prop['strip'] = 'clips/uploads/'.Str::random(40).'.jpg';
                    $editor->filmstrip($video, $disk->path($prop['strip']));
                }
                $p->update([
                    'status' => ClipRecord::STATUS_CHOOSING_SECTIONS,
                    'meta' => array_merge($store->find($p->id)->meta, ['property' => $prop, 'step' => null]),
                ]);

                return;
            }

            // Stage: each section the user marked becomes a room to modernise —
            // its real first frame, and a checked modern version of its last. A
            // section left exactly as it was keeps its still and its animation.
            $key = fn ($start, $end, $note = '') => round((float) $start, 2).'|'.round((float) $end, 2).'|'.trim((string) $note);
            $previous = [];
            foreach ($prop['rooms'] ?? [] as $room) {
                if (! empty($room['anim'])) {
                    $previous[$key($room['anim']['start'], $room['anim']['end'], $room['note'] ?? '')] = $room;
                }
            }
            $rooms = [];
            foreach (array_values($prop['sections'] ?? []) as $n => $section) {
                $k = $key($section['start'], $section['end'], $section['note'] ?? '');
                if (isset($previous[$k])) {
                    $rooms[] = $previous[$k];
                    unset($previous[$k]);

                    continue;
                }
                $this->step($store, 'Reading section '.($n + 1).' of '.count($prop['sections']));
                $anim = ['start' => (float) $section['start'], 'end' => (float) $section['end'], 'motion' => ($section['motion'] ?? '') === 'backward' ? 'backward' : 'forward', 'mode' => 'modernise', 'enabled' => true];
                foreach (['start', 'end'] as $edge) {
                    $rel = 'clips/uploads/'.Str::random(40).'.jpg';
                    $editor->frame($video, $anim[$edge], $disk->path($rel));
                    $anim["{$edge}_frame"] = $rel;
                }
                $brief = $analyzer->modernise($disk->path($anim['end_frame']), (string) ($section['note'] ?? ''));
                $anim['prompt'] = $brief['prompt'];
                $anim['cleanup'] = $brief['cleanup'];
                $rooms[] = ['room' => $brief['room'], 'start' => $anim['start'], 'end' => $anim['end'], 'note' => (string) ($section['note'] ?? ''), 'anim' => $anim];
            }
            // Sections the user removed or moved: their frames, stills and clips go.
            foreach ($previous as $gone) {
                $disk->delete(array_values(array_filter(array_merge(array_intersect_key($gone['anim'], array_flip(['start_frame', 'end_frame', 'staged', 'video'])), array_column($gone['anim']['candidates'] ?? [], 'path')))));
            }
            $prop['rooms'] = $rooms;
            $p->update(['meta' => array_merge($store->find($p->id)->meta, ['property' => $prop])]);

            foreach ($prop['rooms'] as $i => $room) {
                if (! empty($room['anim']['staged'])) {
                    continue;   // kept from before
                }
                $this->step($store, 'Modernising section '.($i + 1).' of '.count($rooms).": {$room['room']}");
                try {
                    $r = $stager->stage($prop, $room['anim']);
                    $prop['rooms'][$i]['anim'] = array_merge($prop['rooms'][$i]['anim'], ['staged' => $r['path'], 'alignment' => $r['score'], 'keep' => $r['keep'], 'problems' => $r['problems'], 'candidates' => $r['candidates']]);
                } catch (\Throwable $e) {
                    $prop['rooms'][$i]['anim']['error'] = Str::limit($e->getMessage(), 200);
                }
            }

            $p->update([
                'status' => ClipRecord::STATUS_REVIEWING_ROOMS,
                'meta' => array_merge($store->find($p->id)->meta, ['property' => $prop, 'step' => null]),
            ]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * The agency logo for the outro when the agent was typed in (a listing import
     * finds its own): the shared cache (clips/logos), else Google Images with
     * Claude picking — the same as ImportListingJob — then cached.
     */
    private function agencyLogo(ClipRecord $p, ClipStore $store): void
    {
        $agency = trim((string) ($p->meta['listing']['agency'] ?? ''));
        $images = $p->images ?? [];
        $has = collect($images)->contains(fn ($img) => str_starts_with(strtolower((string) ($img['name'] ?? '')), 'logo.'));
        if ($agency === '' || empty($p->meta['listing']['agent']) || $has) {
            return;
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        // The project's own logo replaces the found one.
        if ($own = app(ProjectResources::class)->logoPath()) {
            $copy = 'clips/uploads/'.Str::random(40).'.'.($ext = pathinfo($own, PATHINFO_EXTENSION));
            $disk->put($copy, (string) file_get_contents($own));
            $p->update(['images' => [...$images, ['id' => 'img_'.substr(md5($copy), 0, 8), 'path' => $copy, 'name' => "logo.{$ext}", 'description' => "Logótipo da {$agency}."]]]);

            return;
        }
        $base = 'clips/logos/'.Str::slug($agency);
        $cached = collect(['png', 'jpg', 'webp', 'gif'])->map(fn ($e) => "{$base}.{$e}")->first(fn ($f) => $disk->exists($f));
        if (! $cached) {
            $this->step($store, "Finding the {$agency} logo");
            $bytes = app(AgencyLogoFinder::class)->find($agency);
            $info = $bytes ? @getimagesizefromstring($bytes) : false;
            if (! is_array($info)) {
                return;
            }
            $ext = image_type_to_extension($info[2], false) ?: 'png';
            $cached = $base.'.'.($ext === 'jpeg' ? 'jpg' : $ext);
            $disk->put($cached, $bytes);
        }
        $ext = pathinfo($cached, PATHINFO_EXTENSION);
        $copy = 'clips/uploads/'.Str::random(40).'.'.$ext;
        $disk->copy($cached, $copy);
        $images[] = ['id' => 'img_'.substr(md5($copy), 0, 8), 'path' => $copy, 'name' => "logo.{$ext}", 'description' => "Logótipo da {$agency}."];
        $p->update(['images' => $images]);
    }

    /** What the job is doing right now, for the clip card. */
    private function step(ClipStore $store, ?string $text): void
    {
        $p = $store->find($this->clipId);
        $p?->update(['meta' => array_merge($p->meta ?? [], ['step' => $text])]);
    }
}
