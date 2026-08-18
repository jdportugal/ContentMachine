<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Capture\SiteFootage;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\VfxStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

/**
 * Generates one VFX Lab asset: Claude writes a Remotion component for the
 * requested canvas, the design-system guard rejects anything that hardcodes
 * colours/fonts, and the isolated candidate composition (src/sample.ts →
 * SampleEffect) renders it at the chosen size/duration/transparency.
 *
 * Unlike GenerateEffectJob that render is not a smoke test — it IS the
 * deliverable. Nothing is promoted into remotion/src/effects/index.ts, so a VFX
 * never enters the planner's vocabulary and cannot affect existing clips.
 */
class GenerateVfxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 1800;

    public function __construct(public string $vfxId)
    {
        $this->captureProject();
    }

    /**
     * Share GenerateEffectJob's lock, NOT a lock of our own: both jobs write the
     * single remotion/src/effects/_candidate.tsx slot, so running one of each
     * concurrently would render one job's component into the other's output.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('generate-effect'))->releaseAfter(60)->expireAfter(1800)];
    }

    public function handle(
        EffectGenerator $generator,
        EffectLibrary $library,
        VfxStore $store,
        RemotionRenderer $renderer,
        SiteFootage $footage,
    ): void {
        $this->activateProject();

        $vfx = $store->find($this->vfxId);
        if (! $vfx) {
            return;
        }

        $transparent = (bool) $vfx->get('transparent', false);
        // ProRes with alpha must be a .mov — an .mp4 container would fail the render.
        $ext = $transparent ? 'mov' : 'mp4';
        $canvas = [
            'width' => (int) $vfx->get('width', 1920),
            'height' => (int) $vfx->get('height', 1080),
            'transparent' => $transparent,
        ];
        $tmp = null;

        try {
            // If the prompt is about a real product or site, record it scrolling
            // and hand the footage to the component instead of a mock-up.
            $site = $footage->forPrompt(
                (string) $vfx->prompt,
                $canvas['width'],
                $canvas['height'],
                (float) $vfx->get('duration', 5)
            );

            $data = $generator->generate((string) $vfx->prompt, canvas: $canvas, siteCapture: $site['path']);

            $library->writeCandidate($data['tsx']);

            // Render to a temp file first: a half-written video must never be
            // servable as this record's finished asset.
            $tmp = tempnam(sys_get_temp_dir(), 'vfx_').'.'.$ext;
            $renderer->render(
                $library->candidateSampleProps($data['sample_text'], $data['sample_params'], $canvas + [
                    'duration' => (float) $vfx->get('duration', 5),
                ]),
                $tmp,
                'src/sample.ts',
                'SampleEffect'
            );

            $out = $store->videoPath($vfx->id(), $ext);
            @mkdir(dirname($out), 0775, true);
            if (! @rename($tmp, $out)) {
                copy($tmp, $out);
                @unlink($tmp);
            }
            $tmp = null;

            $vfx->update([
                'status' => EffectRecord::STATUS_ACTIVE,
                'ext' => $ext,
                'display_name' => $data['display_name'],
                'tsx' => $data['tsx'],   // kept so a future re-render needs no second generation
                // Surfaced in the UI: an AI-guessed URL is the likeliest thing to
                // be wrong, so you can see which page it actually filmed.
                'site_url' => $site['url'],
                'site_error' => $site['error'],
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            if ($tmp) {
                @unlink($tmp);
            }
            $vfx->update([
                'status' => EffectRecord::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
        } finally {
            // Always free the shared candidate slot, success or failure.
            $library->resetCandidate();
        }
    }
}
