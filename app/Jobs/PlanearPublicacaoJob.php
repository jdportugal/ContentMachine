<?php

namespace App\Jobs;

use App\Services\Publicacoes\PublicacaoPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Redige uma publicação com IA fora do ciclo do pedido web.
 *
 * Porquê uma fila: a redação usa o CLI do Claude (a subscrição, sem chave de
 * API). Esse CLI só autentica num processo que herda a sessão — o servidor web
 * («php artisan serve») não a herda, mas um WORKER («php artisan queue:work»)
 * sim. É o mesmo padrão do agregador de notícias e do AdsMaker de referência.
 *
 * O resultado fica em cache; a oficina lê-o por sondagem (wire:poll).
 */
class PlanearPublicacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    /** @param array<int,string> $referencias descrições das imagens de referência */
    public function __construct(
        public string $tipo,
        public string $brief,
        public string $plataforma,
        public string $token,
        public array $referencias = [],
    ) {}

    public function handle(PublicacaoPlanner $planner): void
    {
        $plano = $planner->planear($this->tipo, $this->brief, $this->plataforma, $this->referencias);

        Cache::put(self::key($this->token), [
            'fonte' => $planner->fonte,
            'fornecedor' => $planner->fornecedor,
            'titulo' => $plano->titulo,
            'legenda' => $plano->legenda,
            'slides' => array_map(
                fn ($s) => ['titulo' => $s->titulo, 'texto' => $s->texto],
                $plano->slides,
            ),
        ], now()->addMinutes(15));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true], now()->addMinutes(15));
    }

    public static function key(string $token): string
    {
        return 'publicacao.plano.'.$token;
    }
}
