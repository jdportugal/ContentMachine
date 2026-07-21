<?php

namespace App\Jobs;

use App\Services\Aggregation\NewsAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Corre a agregação multi-plataforma em segundo plano. Pode ser enfileirado
 * (produção, com worker) ou despachado sincronamente (dispatchSync) quando não
 * há fila disponível — é o caso do fluxo interactivo da página de Notícias.
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
        public readonly ?array $plataformas = null,
        public readonly ?int $limite = null,
    ) {}

    public function handle(NewsAggregator $aggregator): void
    {
        $aggregator->aggregate($this->plataformas, $this->limite);
    }
}
