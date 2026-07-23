<?php

namespace App\Jobs;

use App\Services\Aggregation\NewsAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Corre a agregação (yt-dlp) FORA do pedido web: são dezenas de chamadas de
 * subprocesso que estouram o max_execution_time se corressem no pedido. O
 * resumo fica em cache; a página de Notícias lê-o por sondagem (wire:poll).
 */
class AgregarConteudoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    /**
     * @param  array<int,string>|null  $plataformas
     */
    public function __construct(
        public readonly string $token,
        public readonly ?array $plataformas = null,
        public readonly ?int $limite = null,
    ) {}

    public function handle(NewsAggregator $aggregator): void
    {
        $resumo = $aggregator->aggregate($this->plataformas, $this->limite);

        Cache::put(self::key($this->token), $resumo, now()->addMinutes(30));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true], now()->addMinutes(30));
    }

    public static function key(string $token): string
    {
        return 'noticias.agregacao.'.$token;
    }
}
