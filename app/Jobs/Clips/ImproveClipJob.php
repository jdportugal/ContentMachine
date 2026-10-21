<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\ClipReviser;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\RealEstate\ListingScriptWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * "Improve with AI" on a clip's card (meta.improve_request): the script is
 * rewritten as asked when the voice is generated (then recorded again), and
 * the request becomes direction for the planner — which photos and footage,
 * timing, effects. Re-planning writes the caption again too. No picture is
 * generated or edited.
 */
class ImproveClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $clipId)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, ClipReviser $reviser): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);

        try {
            $request = trim((string) ($p->meta['improve_request'] ?? ''));
            $script = trim((string) ($p->transcript['text'] ?? ($p->meta['listing']['voiceover'] ?? '')));
            $generated = isset($p->meta['listing']) || $p->input_kind === 'text';
            $l = (array) ($p->meta['listing'] ?? []);
            $context = trim(collect([
                'Property' => $l['title'] ?? null,
                'Details' => collect((array) ($l['details'] ?? []))->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ') ?: null,
                'Notes' => $p->meta['script_notes'] ?? null,
            ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n"));
            $keep = ! empty($p->meta['after_intro']) || app(EffectStore::class)->afterIntroPath()
                ? app(EffectStore::class)->afterIntroLineFor($p->meta ?? []) : '';

            $r = $reviser->revise($script, $request, $context, $generated, $keep);
            if ($r['voiceover'] !== '' && $keep !== '') {
                $r['voiceover'] = ListingScriptWriter::withAfterIntroLine($r['voiceover'], $keep);   // never lose the after-intro line
            }

            // The planner's brief keeps the clip's own instructions; the request's direction joins them (the latest replaces an earlier one).
            $base = trim((string) preg_replace('/\n*THE USER ASKED FOR THIS VERSION:.*$/s', '', (string) ($p->meta['instructions'] ?? '')));
            $p->update(['meta' => array_merge($p->meta, [
                'instructions' => trim($base.($r['direction'] !== '' ? "\n\nTHE USER ASKED FOR THIS VERSION: «{$request}»\nDo it this way: {$r['direction']}" : "\n\nTHE USER ASKED FOR THIS VERSION: «{$request}»")),
            ])]);

            if ($generated && $r['voiceover'] !== '' && $r['voiceover'] !== $script) {
                $p->update(['status' => ClipRecord::STATUS_TRANSCRIBING]);
                RegenerateVoiceoverJob::dispatch($p->id, $r['voiceover'], replan: true);
            } else {
                $p->update(['status' => ClipRecord::STATUS_PLANNING]);
                PlanAnimationsJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => 'Improve with AI: '.$e->getMessage()]);
            throw $e;
        }
    }
}
