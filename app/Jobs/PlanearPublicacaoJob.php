<?php

namespace App\Jobs;

use App\Services\Publicacoes\PublicacaoPlanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Writes a post with AI outside the web request cycle.
 *
 * Why a queue: the writing uses the Claude CLI (the subscription, without an
 * API key). That CLI only authenticates in a process that inherits the session — the web server
 * («php artisan serve») does not inherit it, but a WORKER («php artisan queue:work»)
 * does. It is the same pattern as the news aggregator and the reference AdsMaker.
 *
 * The result is cached; the workshop reads it by polling (wire:poll).
 */
class PlanearPublicacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    /**
     * @param array<int,array{indice:int,descricao:string}|string> $referencias
     *   pool of reference images (index + description) for the AI to assign
     */
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
                fn ($s) => ['titulo' => $s->titulo, 'texto' => $s->texto, 'referencias' => $s->referencias],
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
