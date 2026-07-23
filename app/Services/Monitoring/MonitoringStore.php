<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Guarda os itens de monitorização já recolhidos (por plataforma), para que o
 * driver leia depressa e a recolha lenta via yt-dlp aconteça só quando o
 * utilizador «Atualiza». Persistido na cache até nova recolha.
 */
class MonitoringStore
{
    private function key(string $plataforma): string
    {
        return "monitoring.ytdlp.{$plataforma}";
    }

    /** @return array<int,array<string,mixed>> */
    public function itens(string $plataforma): array
    {
        $dados = Cache::get($this->key($plataforma));

        return is_array($dados['itens'] ?? null) ? $dados['itens'] : [];
    }

    /**
     * Estatísticas de canal (subscritores, nº total de publicações, nome).
     *
     * @return array<string,mixed>
     */
    public function canal(string $plataforma): array
    {
        $dados = Cache::get($this->key($plataforma));

        return is_array($dados['canal'] ?? null) ? $dados['canal'] : [];
    }

    /** Última recolha (ex.: «22/07 14:05»), ou null se nunca recolhido. */
    public function atualizadoEm(string $plataforma): ?string
    {
        $em = Cache::get($this->key($plataforma))['em'] ?? null;

        return $em ? Carbon::parse($em)->timezone(config('app.timezone'))->translatedFormat('d/m H:i') : null;
    }

    public function recolhido(string $plataforma): bool
    {
        return Cache::has($this->key($plataforma));
    }

    /**
     * @param  array<int,array<string,mixed>>  $itens
     * @param  array<string,mixed>  $canal
     */
    public function guardar(string $plataforma, array $itens, array $canal = []): void
    {
        Cache::forever($this->key($plataforma), [
            'itens' => array_values($itens),
            'canal' => $canal,
            'em' => now()->toIso8601String(),
        ]);
    }
}
