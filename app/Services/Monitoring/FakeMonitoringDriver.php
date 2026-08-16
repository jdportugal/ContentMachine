<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;
use Illuminate\Support\Carbon;

/**
 * Simulated driver — curated data per platform, without any external call.
 * Used to develop the interface before wiring up the real drivers (Apify/TubeLab).
 */
class FakeMonitoringDriver implements MonitoringDriver
{
    public function __construct(
        private readonly string $plataforma,
        private readonly EngagementScorer $scorer,
    ) {}

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    public function resumo(): array
    {
        return match ($this->plataforma) {
            'youtube' => [
                ['label' => 'Subscribers', 'value' => '48.2k', 'delta' => 3.1, 'unit' => 'total'],
                ['label' => 'Views (28d)', 'value' => '612k', 'delta' => 12.4, 'unit' => '28 days'],
                ['label' => 'Watch time', 'value' => '19.4k h', 'delta' => 8.7, 'unit' => '28 days'],
                ['label' => 'New subscribers', 'value' => '+1,480', 'delta' => -4.2, 'unit' => '28 days'],
            ],
            'instagram' => [
                ['label' => 'Followers', 'value' => '31.7k', 'delta' => 2.2, 'unit' => 'total'],
                ['label' => 'Reach (28d)', 'value' => '284k', 'delta' => 18.9, 'unit' => '28 days'],
                ['label' => 'Interactions', 'value' => '41.3k', 'delta' => 6.5, 'unit' => '28 days'],
                ['label' => 'Saves', 'value' => '5,902', 'delta' => 22.0, 'unit' => '28 days'],
            ],
            'tiktok' => [
                ['label' => 'Followers', 'value' => '76.9k', 'delta' => 9.4, 'unit' => 'total'],
                ['label' => 'Views (28d)', 'value' => '2.1M', 'delta' => 34.2, 'unit' => '28 days'],
                ['label' => 'Likes', 'value' => '188k', 'delta' => 27.8, 'unit' => '28 days'],
                ['label' => 'Shares', 'value' => '12.4k', 'delta' => 41.0, 'unit' => '28 days'],
            ],
            'linkedin' => [
                ['label' => 'Followers', 'value' => '9,340', 'delta' => 4.7, 'unit' => 'total'],
                ['label' => 'Impressions (28d)', 'value' => '58.6k', 'delta' => 11.2, 'unit' => '28 days'],
                ['label' => 'Interactions', 'value' => '3,210', 'delta' => 5.3, 'unit' => '28 days'],
                ['label' => 'Click-through rate', 'value' => '2.8', 'delta' => -1.1, 'unit' => '%'],
            ],
            default => [],
        };
    }

    public function conteudosRecentes(int $limite = 12): array
    {
        $itens = $this->seed();
        $mediana = $this->scorer->mediana(array_column($itens, 'views'));

        $itens = array_map(function (array $item) use ($mediana) {
            $s = $this->scorer->score($this->plataforma, $item, $mediana);

            return array_merge($item, $s);
        }, $itens);

        usort($itens, fn ($a, $b) => strcmp($b['publicado_em'], $a['publicado_em']));

        return array_slice($itens, 0, $limite);
    }

    public function ultimoPorTipo(): array
    {
        $porTipo = [];

        foreach ($this->conteudosRecentes(100) as $item) {
            $tipo = $item['tipo'];
            if (! isset($porTipo[$tipo])) {
                $porTipo[$tipo] = $item; // already sorted by date desc → the first is the most recent
            }
        }

        return array_values($porTipo);
    }

    public function melhores(int $limite = 5): array
    {
        $itens = $this->conteudosRecentes(100);
        usort($itens, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($itens, 0, $limite);
    }

    /**
     * Curated content per platform.
     *
     * @return array<int, array<string,mixed>>
     */
    private function seed(): array
    {
        $d = fn (int $dias) => Carbon::today()->subDays($dias)->toDateString();

        return match ($this->plataforma) {
            'youtube' => [
                ['id' => 'yt1', 'plataforma' => 'youtube', 'tipo' => 'vídeo', 'titulo' => 'O que é um modelo de linguagem, afinal?', 'url' => '#', 'publicado_em' => $d(2), 'duracao_seg' => 742, 'views' => 41200, 'likes' => 2870, 'comentarios' => 412, 'partilhas' => 690, 'guardados' => 980],
                ['id' => 'yt2', 'plataforma' => 'youtube', 'tipo' => 'short', 'titulo' => 'Truque: pedir fontes ao modelo', 'url' => '#', 'publicado_em' => $d(4), 'duracao_seg' => 48, 'views' => 98600, 'likes' => 7120, 'comentarios' => 233, 'partilhas' => 1810, 'guardados' => 2450],
                ['id' => 'yt3', 'plataforma' => 'youtube', 'tipo' => 'vídeo', 'titulo' => 'Como falar com uma máquina — capítulo I', 'url' => '#', 'publicado_em' => $d(9), 'duracao_seg' => 1120, 'views' => 28400, 'likes' => 1640, 'comentarios' => 288, 'partilhas' => 402, 'guardados' => 720],
                ['id' => 'yt4', 'plataforma' => 'youtube', 'tipo' => 'short', 'titulo' => 'Três erros de principiante', 'url' => '#', 'publicado_em' => $d(14), 'duracao_seg' => 55, 'views' => 54300, 'likes' => 3980, 'comentarios' => 121, 'partilhas' => 990, 'guardados' => 1310],
            ],
            'instagram' => [
                ['id' => 'ig1', 'plataforma' => 'instagram', 'tipo' => 'reel', 'titulo' => 'Sabia que… a IA não "pensa"?', 'url' => '#', 'publicado_em' => $d(1), 'duracao_seg' => 32, 'views' => 63400, 'likes' => 4120, 'comentarios' => 318, 'partilhas' => 1420, 'guardados' => 2180],
                ['id' => 'ig2', 'plataforma' => 'instagram', 'tipo' => 'carrossel', 'titulo' => '5 termos para começar', 'url' => '#', 'publicado_em' => $d(3), 'views' => 21800, 'likes' => 2610, 'comentarios' => 142, 'partilhas' => 540, 'guardados' => 3120],
                ['id' => 'ig3', 'plataforma' => 'instagram', 'tipo' => 'post', 'titulo' => 'Citação — «com vagar»', 'url' => '#', 'publicado_em' => $d(6), 'views' => 12400, 'likes' => 1480, 'comentarios' => 63, 'partilhas' => 120, 'guardados' => 410],
                ['id' => 'ig4', 'plataforma' => 'instagram', 'tipo' => 'reel', 'titulo' => 'Prompt vs. conversa', 'url' => '#', 'publicado_em' => $d(11), 'duracao_seg' => 41, 'views' => 48900, 'likes' => 3320, 'comentarios' => 209, 'partilhas' => 980, 'guardados' => 1560],
            ],
            'tiktok' => [
                ['id' => 'tt1', 'plataforma' => 'tiktok', 'tipo' => 'vídeo', 'titulo' => 'A IA explicada em 30 segundos', 'url' => '#', 'publicado_em' => $d(1), 'duracao_seg' => 29, 'views' => 412000, 'likes' => 38400, 'comentarios' => 1890, 'partilhas' => 9200, 'guardados' => 7100],
                ['id' => 'tt2', 'plataforma' => 'tiktok', 'tipo' => 'vídeo', 'titulo' => 'Perguntei o mesmo a 3 modelos', 'url' => '#', 'publicado_em' => $d(5), 'duracao_seg' => 58, 'views' => 189000, 'likes' => 16800, 'comentarios' => 940, 'partilhas' => 3100, 'guardados' => 2600],
                ['id' => 'tt3', 'plataforma' => 'tiktok', 'tipo' => 'vídeo', 'titulo' => 'Não faças isto com a IA', 'url' => '#', 'publicado_em' => $d(10), 'duracao_seg' => 44, 'views' => 96500, 'likes' => 8100, 'comentarios' => 512, 'partilhas' => 1400, 'guardados' => 1180],
            ],
            'linkedin' => [
                ['id' => 'li1', 'plataforma' => 'linkedin', 'tipo' => 'post', 'titulo' => 'IA para quem nunca viu um modelo', 'url' => '#', 'publicado_em' => $d(2), 'views' => 18400, 'likes' => 640, 'comentarios' => 88, 'partilhas' => 74, 'guardados' => 51],
                ['id' => 'li2', 'plataforma' => 'linkedin', 'tipo' => 'carrossel', 'titulo' => 'Glossário essencial (PDF)', 'url' => '#', 'publicado_em' => $d(7), 'views' => 12900, 'likes' => 520, 'comentarios' => 61, 'partilhas' => 132, 'guardados' => 210],
                ['id' => 'li3', 'plataforma' => 'linkedin', 'tipo' => 'artigo', 'titulo' => 'O ofício de conversar com máquinas', 'url' => '#', 'publicado_em' => $d(15), 'views' => 8600, 'likes' => 312, 'comentarios' => 47, 'partilhas' => 38, 'guardados' => 44],
            ],
            default => [],
        };
    }
}
