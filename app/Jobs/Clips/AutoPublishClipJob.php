<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Publishing\BlotatoClient;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Schedules a finished auto clip on Blotato's NEXT FREE SLOT, on every platform
 * with a configured account — the queue-side twin of Rascunhos::publicar()'s
 * "slot" path. Publish failures land on the clip's error field; the clip itself
 * stays done, so the user can always post it by hand from Drafts.
 */
class AutoPublishClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 600;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, SettingsRepository $settings, BlotatoClient $blotato): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);
        if ($p->get('publish_state') || ! $p->output_path || ! is_file($p->output_path)) {
            return; // already handled, or nothing rendered to post
        }

        $accounts = array_filter(array_map('trim', (array) $settings->get('blotato', [])));
        if ($accounts === []) {
            $p->update(['error' => 'Auto-publish: no Blotato accounts configured in Settings.']);

            return;
        }

        $url = $blotato->uploadMedia($p->output_path);
        $texto = $this->legenda($p);

        $ids = [];
        $erros = [];
        foreach ($accounts as $plataforma => $conta) {
            try {
                $r = $blotato->publish($conta, (string) $plataforma, $texto, [$url], null, true, (string) $p->title);
                $ids[$plataforma] = $r['id'] ?? ($r['submissionId'] ?? true);
            } catch (\Throwable $e) {
                $erros[] = ucfirst((string) $plataforma).': '.$e->getMessage();
            }
        }

        $p->update([
            'publish_state' => $ids === [] ? null : 'scheduled',
            'scheduled_for' => null, // Blotato picked the slot — exact time unknown
            'plataformas' => array_keys($ids),
            'blotato_ids' => $ids,
            'error' => $erros === [] ? null : 'Auto-publish: '.implode('; ', $erros),
        ]);
    }

    /** Suggested caption + hashtags — the same shape Rascunhos posts by hand. */
    private function legenda(ClipRecord $p): string
    {
        $meta = (array) ($p->meta ?? []);
        $texto = trim((string) ($meta['suggested']['description'] ?? '')) ?: (string) $p->title;
        $tags = collect((array) ($meta['suggested']['tags'] ?? []))
            ->map(fn ($t) => preg_replace('/\s+/u', '', ltrim(trim((string) $t), '#')))
            ->filter()
            ->unique()
            ->map(fn ($t) => '#'.$t)
            ->implode(' ');

        return $tags === '' ? $texto : $texto."\n\n".$tags;
    }
}
