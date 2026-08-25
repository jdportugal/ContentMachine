<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\Store\ClipStore;
use App\Services\Costs\CostLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * "Generate now" on an image suggestion: makes the AI image DURING review, so the
 * user sees the real picture and only then decides. For the animated-intro
 * suggestion it (re)generates the START IMAGE from its concept; for any other
 * suggestion it fulfils the request the way an upload would. Runs queued (kie is
 * slow); the review page polls via the shared site_collecting flag.
 */
class GenerateRequestImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 300;

    public function __construct(public string $projectId, public string $key)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store): void
    {
        $this->activateProject();
        app(CostLedger::class)->contexto('clip', $this->projectId);
        $p = $store->findOrFail($this->projectId);
        $key = $this->key;

        try {
            $requests = $p->meta['image_requests'] ?? [];
            $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === $key);
            $animado = $i !== false && ! empty($requests[$i]['animado']);
            $prompt = $i === false ? ''
                : (string) ($animado ? ($requests[$i]['imagem_prompt'] ?? '') : ($requests[$i]['prompt'] ?? ''));

            $img = $prompt !== ''
                ? app(ClipImageGenerator::class)->generate($prompt, (string) config('contentmachine.clips.image_style', ''))
                : null;

            // Generation just took up to a minute. Re-read before deciding what to
            // write: `meta` and `images` are whole fields, so composing them from
            // the snapshot taken before the wait would drop whatever the user did
            // meanwhile — another suggestion's upload, a prompt they edited (which
            // is what made a later generation produce the wrong picture), a mode
            // they switched. `$requests`/`$i` above are only read for the prompt.
            $p->refresh();
            $requests = $p->meta['image_requests'] ?? $requests;
            $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === $key);

            $erros = $p->meta['site_errors'] ?? [];
            $uploads = $p->meta['image_uploads'] ?? [];
            $images = $p->images ?? [];
            if ($img !== null && $i !== false) {
                // Replace the previous GENERATED take, wherever it was recorded —
                // never a file the user uploaded themselves.
                $anterior = $animado ? ($requests[$i]['imagem'] ?? null) : ($uploads[$key] ?? null);
                $images = array_values(array_filter($images, fn ($im) => ($im['id'] ?? null) !== $anterior || empty($im['generated'])));
                $images[] = $img;
                if ($animado) {
                    $requests[$i]['imagem'] = $img['id'];
                } else {
                    $uploads[$key] = $img['id'];
                }
                unset($erros[$key]);
            } else {
                $erros[$key] = 'Could not generate the image — try again.';
            }

            $p->update([
                'images' => $images,
                'meta' => array_merge($p->meta ?? [], [
                    'image_requests' => $requests,
                    'image_uploads' => $uploads,
                    'site_errors' => $erros,
                    'site_collecting' => array_diff_key($p->meta['site_collecting'] ?? [], [$key => true]),
                ]),
            ]);
        } catch (\Throwable $e) {
            $p->refresh();  // same reason as above — `meta` is written whole
            $p->update(['meta' => array_merge($p->meta ?? [], [
                'site_errors' => array_merge($p->meta['site_errors'] ?? [], [$key => $e->getMessage()]),
                'site_collecting' => array_diff_key($p->meta['site_collecting'] ?? [], [$key => true]),
            ])]);
        }
    }
}
