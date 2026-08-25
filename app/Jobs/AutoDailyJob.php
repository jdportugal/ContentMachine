<?php

namespace App\Jobs;

use App\Jobs\Clips\TranscribeJob;
use App\Jobs\Concerns\RunsInProject;
use App\Services\Aggregation\NewsAggregator;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Vault\VaultContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The daily "auto" pipeline (per project, opt-in in Settings → General):
 * gather today's news, write the day report, take its top 3 bits and turn
 * them into ONE animated clip (text input → ElevenLabs voiceover). The clip
 * carries meta.auto, which makes the rest of the chain hands-free: planning
 * skips the image-review pause (PlanAnimationsJob) and the finished render is
 * scheduled on Blotato's next free slot (RenderJob → AutoPublishClipJob).
 *
 * Fired once a day per project by the auto:tick scheduler command.
 */
class AutoDailyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    // Aggregation (yt-dlp fleet) + report writing (Claude CLI) are each slow.
    public int $timeout = 3600;

    public function __construct()
    {
        $this->captureProject();
    }

    public function handle(NewsAggregator $aggregator, VaultContract $vault, ClipStore $clips): void
    {
        $this->activateProject();

        // 1. Gather today's news from every configured source.
        $aggregator->aggregate();

        // 2. Write (and archive) the day report — the same job the News page uses.
        $token = 'auto-'.Str::random(8);
        GerarRelatorioJob::dispatchSync('dia', now()->toDateString(), $token);
        $path = Cache::get(GerarRelatorioJob::key($token))['path'] ?? null;
        $dados = $path ? json_decode((string) ($vault->get($path)?->get('dados', '') ?? ''), true) : null;

        // 3. Top 3 bits. The report writer already filters, verifies and orders
        //    the day's news, so the first three bits ARE the top stories.
        $top = array_slice(self::bits((string) ($dados['redacao'] ?? '')), 0, 3);
        if ($top === []) {
            return; // quiet day — nothing worth a clip, try again tomorrow
        }

        // 4. One animated clip from the three scripts (text → ElevenLabs VO).
        $clip = $clips->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'title' => $top[0]['title'],
            'source_text' => implode("\n\n", array_column($top, 'script')),
            'meta' => ['auto' => true, 'background' => 'auto'],
        ]);

        TranscribeJob::dispatch($clip->id);
    }

    /**
     * The report's redacao is news-bit blocks separated by `---` lines: a
     * `**title**` line, a `> lead` line, then the spoken script. Prose blocks
     * (preamble, skipped-items notes) carry no bold title and drop out. The
     * lead is on-screen text, not speech, so it stays out of the script.
     *
     * @return array<int,array{title:string,script:string}>
     */
    public static function bits(string $redacao): array
    {
        $out = [];
        foreach (preg_split('/^\s*---\s*$/m', $redacao) ?: [] as $bloco) {
            $linhas = array_values(array_filter(array_map('trim', explode("\n", trim($bloco))), fn ($l) => $l !== ''));
            if ($linhas === [] || ! preg_match('/^\*\*(.+)\*\*$/', $linhas[0], $m)) {
                continue;
            }
            $script = implode("\n", array_filter(array_slice($linhas, 1), fn ($l) => ! str_starts_with($l, '>')));
            if (trim($script) !== '') {
                $out[] = ['title' => trim($m[1]), 'script' => trim($script)];
            }
        }

        return $out;
    }
}
