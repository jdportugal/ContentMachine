<?php

namespace App\Jobs\Clips;

use App\Models\ClipEffect;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\EffectLibrary;
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
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    public function __construct(public int $effectId, public bool $isEdit = false) {}

    /** Serialize generations — they share the single _candidate.tsx slot. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('generate-effect'))->releaseAfter(60)->expireAfter(900)];
    }

    public function handle(EffectGenerator $generator, EffectLibrary $library, RemotionRenderer $renderer): void
    {
        $effect = ClipEffect::find($this->effectId);
        if (! $effect) {
            return;
        }

        // Editing keeps the effect's own slug (so clips referencing it keep working).
        $keepSlug = ($this->isEdit && ! str_starts_with((string) $effect->slug, 'pending-')) ? $effect->slug : null;
        $tmp = null;

        try {
            $data = $generator->generate($effect->prompt, $keepSlug);

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

            $effect->update($data + ['preview_path' => $preview, 'error' => null]);
            $library->promote($effect); // writes <slug>.tsx, marks active, rebuilds index.ts
        } catch (\Throwable $e) {
            $library->resetCandidate();
            if ($tmp) {
                @unlink($tmp);
            }
            // A failed EDIT of a live effect keeps it active + unchanged; a failed
            // CREATE (or edit of a non-live effect) is marked failed.
            $effect->update($keepSlug
                ? ['status' => ClipEffect::STATUS_ACTIVE, 'error' => Str::limit('Edit failed: '.$e->getMessage(), 500)]
                : ['status' => ClipEffect::STATUS_FAILED, 'error' => Str::limit($e->getMessage(), 500)]
            );
        }
    }
}
