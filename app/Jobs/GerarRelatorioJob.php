<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Aggregation\RelatorioBuilder;
use App\Services\Projects\ProjectLanguage;
use App\Services\Vault\VaultContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Generates the news report OUTSIDE the web request cycle.
 *
 * Why a queue: the writing uses the Claude CLI (with web search) and can
 * take minutes. It used to run synchronously in the web request and blew the
 * max_execution_time (300s). Now it runs in a WORKER («php artisan queue:work»),
 * writes the report to the vault and leaves the path in cache; the page reads it by
 * polling (wire:poll) — the same pattern as the posts workshop.
 */
class GerarRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    // Headroom above the Claude CLI timeout (up to 600s with web search).
    public int $timeout = 900;

    /** @param string $idioma Output language; '' follows the project's own. */
    public function __construct(
        public string $modo,
        public string $data,
        public string $token,
        public string $idioma = '',
    ) {
        $this->captureProject();
    }

    public function handle(RelatorioBuilder $builder, VaultContract $vault): void
    {
        // The worker has no session: activate the project so the report is written
        // in ITS language and archived in ITS vault.
        $this->activateProject();
        $idioma = $this->idioma !== '' ? $this->idioma : ProjectLanguage::name();

        // Report is built only from already-scraped items (no channel scan here).
        $ref = Carbon::parse($this->data !== '' ? $this->data : now()->toDateString());

        // 'semana' = window of the last 7 days up to the chosen date.
        [$inicio, $fim] = $this->modo === 'semana'
            ? [$ref->copy()->subDays(6)->startOfDay(), $ref->copy()->endOfDay()]
            : [$ref->copy()->startOfDay(), $ref->copy()->startOfDay()];

        $relatorio = $builder->gerar($inicio, $fim, $this->modo, $idioma);

        $slug = $this->modo === 'semana'
            ? 'semana-'.$inicio->toDateString()
            : 'dia-'.$inicio->toDateString();

        $nota = $vault->put("noticias/relatorios/{$slug}.md", [
            'titulo' => $relatorio['titulo'],
            'tipo' => 'relatorio',
            'modo' => $relatorio['modo'],
            'inicio' => $relatorio['inicio'],
            'fim' => $relatorio['fim'],
            'total' => $relatorio['total'],
            'gerado_em' => $relatorio['gerado_em'],
            'estado' => 'arquivado',
            'tags' => ['noticias', 'relatorio', $relatorio['modo']],
            'dados' => json_encode($relatorio, JSON_UNESCAPED_UNICODE),
        ], $builder->corpoMarkdown($relatorio));

        Cache::put(self::key($this->token), ['path' => $nota->path], now()->addMinutes(30));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true], now()->addMinutes(30));
    }

    public static function key(string $token): string
    {
        return 'noticias.relatorio.'.$token;
    }
}
