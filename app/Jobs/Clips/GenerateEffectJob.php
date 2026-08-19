<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Capture\SiteFootage;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

/**
 * Generates a custom SFX end-to-end: Claude writes the component, the guard
 * enforces design-system compliance, and an ISOLATED sample render proves it
 * compiles + runs before it is promoted into the production bundle. A failure at
 * any step leaves the effect `failed` and never touches real clip renders.
 */
class GenerateEffectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 900;

    public function __construct(public string $effectId, public bool $isEdit = false)
    {
        $this->captureProject();
    }

    /** Serialize generations — they share the single _candidate.tsx slot. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('generate-effect'))->releaseAfter(60)->expireAfter(900)];
    }

    public function handle(
        EffectGenerator $generator,
        EffectLibrary $library,
        EffectStore $store,
        RemotionRenderer $renderer,
        SiteFootage $footage,
    ): void {
        $this->activateProject();

        $effect = $store->find($this->effectId);
        if (! $effect) {
            return;
        }

        // Keep the effect's own slug unless it's a fresh placeholder — so custom
        // edits AND built-in overrides (slug = the built-in) regenerate in place,
        // while a brand-new custom effect gets a slug from the model.
        $keepSlug = ! str_starts_with((string) $effect->slug, 'pending-') ? $effect->slug : null;
        // A failed EDIT of a live effect must not break it. "Live" = there is
        // already a committed component to fall back on — NOT status === active,
        // because every caller flips the status to `updating` before dispatching,
        // which made a failed refine mark a perfectly good effect as failed.
        $wasActive = $this->isEdit || trim((string) $effect->tsx) !== '';
        $tmp = null;

        try {
            // An SFX is a clip layer, so it is captured at the project's clip size.
            $c = config('contentmachine.clips');
            $site = $footage->forPrompt((string) $effect->prompt, (int) $c['width'], (int) $c['height'], 2.5);

            $data = $generator->generate($effect->prompt, $keepSlug, siteCapture: $site['path']);

            // Isolated test-render (src/sample.ts → SampleEffect) to a TEMP file, so
            // an edit that fails never overwrites the live version or its preview.
            $library->writeCandidate($data['tsx']);
            $tmp = tempnam(sys_get_temp_dir(), 'sfx_preview_').'.mp4';
            $renderer->render(
                $library->candidateSampleProps($data['sample_text'], $data['sample_params']),
                $tmp,
                'src/sample.ts',
                'SampleEffect'
            );

            // Passed — commit the new version and publish its preview.
            $preview = $library->previewPath($data['slug']);
            @mkdir(dirname($preview), 0777, true);
            if (! @rename($tmp, $preview)) {
                @copy($tmp, $preview);
                @unlink($tmp);
            }
            $tmp = null;

            $effect->update($data + [
                'preview_path' => $preview,
                'site_url' => $site['url'],     // which page it filmed, if any
                'site_error' => $site['error'],
                'error' => null,
            ]);
            $library->promote($effect); // writes <slug>.tsx, marks active, rebuilds index.ts
        } catch (\Throwable $e) {
            $library->resetCandidate();
            if ($tmp) {
                @unlink($tmp);
            }
            // A failed EDIT of a live effect keeps it active + unchanged; a failed
            // CREATE (or override of a not-yet-live effect) is marked failed.
            $effect->update($wasActive
                ? ['status' => EffectRecord::STATUS_ACTIVE, 'error' => Str::limit('Edit failed: '.$e->getMessage(), 500)]
                : ['status' => EffectRecord::STATUS_FAILED, 'error' => Str::limit($e->getMessage(), 500)]
            );
        }
    }
}
