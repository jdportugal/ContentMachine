<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Stores the already-collected monitoring items (per platform), so the
 * driver reads quickly and the slow yt-dlp collection only happens when the
 * user hits «Refresh». Persisted in cache until the next collection.
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
     * Channel statistics (subscribers, total number of posts, name).
     *
     * @return array<string,mixed>
     */
    public function canal(string $plataforma): array
    {
        $dados = Cache::get($this->key($plataforma));

        return is_array($dados['canal'] ?? null) ? $dados['canal'] : [];
    }

    /** Last collection (e.g. «22/07 14:05»), or null if never collected. */
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
