<?php

namespace App\Jobs;

use App\Services\Aggregation\NewsAggregator;
use App\Services\Aggregation\RelatorioBuilder;
use App\Services\Vault\VaultContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Gera o relatório de notícias FORA do ciclo do pedido web.
 *
 * Porquê uma fila: a redação usa o CLI do Claude (com pesquisa web) e pode
 * demorar minutos. Corria antes de forma síncrona no pedido web e estourava o
 * max_execution_time (300s). Agora corre num WORKER («php artisan queue:work»),
 * escreve o relatório no vault e deixa o caminho em cache; a página lê-o por
 * sondagem (wire:poll) — o mesmo padrão da oficina de publicações.
 */
class GerarRelatorioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Folga acima do timeout do CLI Claude (até 600s com pesquisa web).
    public int $timeout = 900;

    public function __construct(
        public string $modo,
        public string $data,
        public bool $recolher,
        public string $token,
    ) {}

    public function handle(RelatorioBuilder $builder, VaultContract $vault, NewsAggregator $aggregator): void
    {
        // Recolhe primeiro conteúdo novo dos canais (apanha os vídeos de hoje).
        if ($this->recolher) {
            $aggregator->aggregate();
        }

        $ref = Carbon::parse($this->data !== '' ? $this->data : now()->toDateString());

        // 'semana' = janela dos últimos 7 dias até à data escolhida.
        [$inicio, $fim] = $this->modo === 'semana'
            ? [$ref->copy()->subDays(6)->startOfDay(), $ref->copy()->endOfDay()]
            : [$ref->copy()->startOfDay(), $ref->copy()->startOfDay()];

        $relatorio = $builder->gerar($inicio, $fim, $this->modo);

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
