<?php

namespace App\Services\Monitoring;

/**
 * Agrega as estatísticas de canal recolhidas (via yt-dlp) em totais para o
 * Painel: subscritores, publicações e desempenho recente somados pelas redes
 * monitorizadas. Só conta redes já recolhidas — sem números inventados.
 */
class MonitoringStats
{
    public function __construct(private readonly MonitoringStore $store) {}

    /**
     * @param  array<int,string>  $plataformas
     * @return array{subscritores:int,publicacoes:int,visualizacoes:int,interacoes:int,redes:int,temDados:bool}
     */
    public function totais(array $plataformas): array
    {
        $subscritores = 0;
        $publicacoes = 0;
        $visualizacoes = 0;
        $interacoes = 0;
        $redes = 0;

        foreach ($plataformas as $p) {
            $canal = $this->store->canal($p);
            $itens = $this->store->itens($p);

            if ($this->store->recolhido($p) && ($canal !== [] || $itens !== [])) {
                $redes++;
            }

            $subscritores += (int) ($canal['subscribers'] ?? 0);
            $publicacoes += (int) ($canal['posts'] ?? 0);

            foreach ($itens as $i) {
                $visualizacoes += (int) ($i['views'] ?? 0);
                $interacoes += (int) ($i['likes'] ?? 0) + (int) ($i['comentarios'] ?? 0);
            }
        }

        return [
            'subscritores' => $subscritores,
            'publicacoes' => $publicacoes,
            'visualizacoes' => $visualizacoes,
            'interacoes' => $interacoes,
            'redes' => $redes,
            'temDados' => $redes > 0,
        ];
    }

    /** Formata números grandes à portuguesa: 1 480 · 41,2 mil · 2,1 M. */
    public static function numero(int $n): string
    {
        if ($n >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1, ',', ' '), '0'), ',').' M';
        }
        if ($n >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1, ',', ' '), '0'), ',').' mil';
        }

        return number_format($n, 0, ',', ' ');
    }
}
