<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundGenerator;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\EffectRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

/**
 * Generates a custom CODE background end-to-end: Claude writes the backdrop
 * component, the guard enforces design-system compliance, and an ISOLATED sample
 * render (the shared SFX candidate slot → SampleEffect) proves it compiles + runs
 * before it is promoted into the production bundle. A failure at any step leaves
 * the background `failed` and never touches real clip renders.
 */
class GenerateBackgroundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 1800;

    public function __construct(public string $backgroundId, public bool $isEdit = false)
    {
        $this->captureProject();
    }

    /** Serialize with SFX generation — both share the single _candidate.tsx slot. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('generate-effect'))->releaseAfter(60)->expireAfter(1800)];
    }

    public function handle(BackgroundGenerator $generator, BackgroundLibrary $library, EffectLibrary $effects, BackgroundStore $store, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        $background = $store->find($this->backgroundId);
        if (! $background) {
            return;
        }

        $keepSlug = ! str_starts_with((string) $background->slug, 'pending-') ? $background->slug : null;
        $wasActive = $background->isActive(); // a failed EDIT of a live background must not break it
        $tmp = null;

        try {
            $data = $generator->generate($background->prompt, $keepSlug);

            // Isolated test-render (reuses the SFX candidate slot → SampleEffect) to a
            // TEMP file, so an edit that fails never overwrites the live version.
            $effects->writeCandidate($data['tsx']);
            $tmp = tempnam(sys_get_temp_dir(), 'bg_preview_').'.mp4';
            $renderer->render($effects->candidateSampleProps('', []), $tmp, 'src/sample.ts', 'SampleEffect');

            $preview = $library->previewPath($data['slug']);
            @mkdir(dirname($preview), 0777, true);
            if (! @rename($tmp, $preview)) {
                @copy($tmp, $preview);
                @unlink($tmp);
            }
            $tmp = null;

            $background->update($data + ['preview_path' => $preview, 'error' => null]);
            $library->promote($background); // writes backgrounds/<slug>.tsx, marks active, rebuilds index.ts
        } catch (\Throwable $e) {
            $effects->resetCandidate();
            if ($tmp) {
                @unlink($tmp);
            }
            $background->update($wasActive
                ? ['status' => EffectRecord::STATUS_ACTIVE, 'error' => Str::limit('Edit failed: '.$e->getMessage(), 500)]
                : ['status' => EffectRecord::STATUS_FAILED, 'error' => Str::limit($e->getMessage(), 500)]
            );
        } finally {
            $effects->resetCandidate();
        }
    }
}
