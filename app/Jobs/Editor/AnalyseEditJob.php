<?php

namespace App\Jobs\Editor;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Editor\DeadAirDetector;
use App\Services\Editor\DuplicateTakeDetector;
use App\Services\Editor\EditorStore;
use App\Services\Shorts\LocalVideoEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * Transcribes the camera track once, then proposes the cuts: dead air
 * (arithmetic) and retakes (one LLM pass). Leaves the edit in `review` — nothing
 * is rendered until the user approves.
 */
class AnalyseEditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 3600;

    public function __construct(public string $editId)
    {
        $this->captureProject();
    }

    public function handle(
        EditorStore $store,
        LocalVideoEngine $engine,
        DeadAirDetector $deadAir,
        DuplicateTakeDetector $duplicates,
    ): void {
        $this->activateProject();

        $edit = $store->find($this->editId);
        if (! $edit) {
            return;
        }

        $edit->update(['status' => EditorStore::ANALYSING, 'error' => null]);

        try {
            $camera = (string) $edit->get('camera_path');
            if (! is_file($camera)) {
                throw new \RuntimeException('The camera file is missing.');
            }

            // The camera carries the voice, so it drives both the transcript and
            // the cuts; the screen feed is cut to the same ranges.
            $segments = $engine->transcribe($camera, (string) $edit->get('language', 'pt'));
            $duration = (float) ($engine->probe($camera)['duration'] ?? 0);

            $removals = array_merge(
                $deadAir->detect(
                    $segments,
                    (float) $edit->get('silence_threshold', 0.7),
                    (float) $edit->get('silence_padding', 0.15),
                    $duration
                ),
                // A failure here returns [] rather than throwing, so a flaky LLM
                // never costs the transcript or the dead-air pass.
                $duplicates->detect($segments),
            );

            $edit->update([
                'transcript' => $segments,
                'duration' => $duration,
                'status' => EditorStore::REVIEW,
            ]);
            $edit->setRemovals($removals);
        } catch (\Throwable $e) {
            $edit->update([
                'status' => EditorStore::FAILED,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
        }
    }
}
