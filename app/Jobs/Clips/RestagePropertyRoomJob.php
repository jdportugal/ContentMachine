<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\RoomStager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** One more take of a room's staged still (the user didn't like the last one, or edited its prompt). */
class RestagePropertyRoomJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $clipId, public int $room)
    {
        $this->captureProject();
    }

    public function handle(RoomStager $stager, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);
        $prop = $p->meta['property'];
        $anim = $prop['rooms'][$this->room]['anim'] ?? null;
        if (! $anim) {
            return;
        }

        try {
            $r = $stager->stage($prop, $anim);
            [$staged, $alignment, $extra, $error] = [$r['path'], $r['score'], ['keep' => $r['keep'], 'problems' => $r['problems'], 'candidates' => $r['candidates']], null];
        } catch (\Throwable $e) {
            [$staged, $alignment, $extra] = [$anim['staged'] ?? null, $anim['alignment'] ?? null, []];
            $error = Str::limit($e->getMessage(), 200);
        }

        // Re-read: the user may have edited other rooms while this one was staging.
        $p = $store->findOrFail($this->clipId);
        $prop = $p->meta['property'];
        $current = $prop['rooms'][$this->room]['anim'];
        $old = $current['staged'] ?? null;
        // This take's images join the earlier ones (newest first): any of them can still be chosen.
        $earlier = $current['candidates'] ?? [];
        if ($old && ! in_array($old, array_column($earlier, 'path'), true)) {
            $earlier[] = ['path' => $old, 'score' => (float) ($current['alignment'] ?? 0), 'problems' => (array) ($current['problems'] ?? [])];
        }
        if (isset($extra['candidates'])) {
            $extra['candidates'] = array_merge($extra['candidates'], $earlier);
        }
        if ($old && $old !== $staged) {
            // A new still: its old Kling clip animated the old one.
            Storage::disk(config('contentmachine.clips.disk'))->delete(array_filter([$current['video'] ?? null]));
            unset($current['video'], $current['clip_problems'], $current['onset']);
        }
        $prop['rooms'][$this->room]['anim'] = array_merge($current, $extra, ['staged' => $staged, 'alignment' => $alignment, 'staging' => false, 'error' => $error]);
        $p->update(['meta' => array_merge($p->meta, ['property' => $prop])]);
    }
}
