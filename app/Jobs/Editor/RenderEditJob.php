<?php

namespace App\Jobs\Editor;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Editor\CutPlan;
use App\Services\Editor\EditorStore;
use App\Services\Editor\MultiCutEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * Applies the approved cuts. Both tracks get the SAME keep-ranges, computed
 * once — that is what keeps them in sync, so the plan is deliberately built
 * outside the per-file loop rather than recomputed for each.
 */
class RenderEditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 7200;

    public function __construct(public string $editId)
    {
        $this->captureProject();
    }

    public function handle(EditorStore $store, CutPlan $plan, MultiCutEngine $engine): void
    {
        $this->activateProject();

        $edit = $store->find($this->editId);
        if (! $edit) {
            return;
        }

        $edit->update(['status' => EditorStore::RENDERING, 'error' => null]);

        try {
            $ranges = $plan->keepRanges($edit->removals(), (float) $edit->get('duration', 0));

            $saidas = [];
            foreach (['camera', 'screen'] as $role) {
                $origem = (string) $edit->get("{$role}_path");
                if ($origem === '' || ! is_file($origem)) {
                    continue; // the screen feed is optional
                }

                // Render to a temp name first: a half-written file must never be
                // servable as the finished cut.
                $destino = $store->filePath($edit->id(), "{$role}-edited");
                $tmp = $destino.'.part';
                $engine->cut($origem, $ranges, $tmp);
                @rename($tmp, $destino);
                $saidas[$role] = $destino;
            }

            if ($saidas === []) {
                throw new \RuntimeException('No source files to cut.');
            }

            $edit->update([
                'status' => EditorStore::DONE,
                'outputs' => $saidas,
                'kept_duration' => round($plan->keptDuration($edit->removals(), (float) $edit->get('duration', 0)), 2),
            ]);
        } catch (\Throwable $e) {
            $edit->update([
                'status' => EditorStore::FAILED,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
        }
    }
}
