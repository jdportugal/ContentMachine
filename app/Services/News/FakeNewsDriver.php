<?php

namespace App\Services\News;

use Illuminate\Support\Carbon;

/**
 * Simulated driver — curated report, no external calls.
 */
class FakeNewsDriver implements NewsDriver
{
    public function relatorio(array $fontes): array
    {
        $destaques = collect([
            ['fonte' => 'youtube', 'titulo' => 'Nova geração de modelos abertos supera fechados em raciocínio', 'url' => '#', 'angulo' => 'O que muda para quem começa agora', 'relevancia' => 92],
            ['fonte' => 'reddit', 'titulo' => 'Discussão: prompts longos vs. conversas curtas', 'url' => '#', 'angulo' => 'Mito do "prompt perfeito" desmontado', 'relevancia' => 84],
            ['fonte' => 'twitter', 'titulo' => 'Fio viral explica alucinações com uma metáfora de biblioteca', 'url' => '#', 'angulo' => 'Metáfora reutilizável para vídeo', 'relevancia' => 88],
            ['fonte' => 'tiktok', 'titulo' => 'Criador mostra IA a planear uma semana de refeições', 'url' => '#', 'angulo' => 'Caso prático do dia-a-dia', 'relevancia' => 79],
            ['fonte' => 'reddit', 'titulo' => 'Guia comunitário: erros comuns de principiantes', 'url' => '#', 'angulo' => 'Base para carrossel "3 erros"', 'relevancia' => 81],
        ])->filter(fn ($d) => in_array($d['fonte'], $fontes, true))
            ->sortByDesc('relevancia')
            ->values()
            ->all();

        return [
            'titulo' => 'Relatório de notícias — '.Carbon::today()->translatedFormat('d \d\e F \d\e Y'),
            'gerado_em' => Carbon::now()->toIso8601String(),
            'resumo' => 'Semana marcada pela aproximação entre modelos abertos e fechados e por uma vaga de conteúdo prático (planeamento, refeições, estudo). O ângulo «biblioteca» continua a gerar boa retenção — vale a pena capitalizar em vídeo curto.',
            'destaques' => $destaques,
            'ideias_guiao' => [
                'Vídeo curto: «A IA não pensa — consulta uma biblioteca». Usar a metáfora viral.',
                'Carrossel: «3 erros de principiante» a partir do guia comunitário do Reddit.',
                'Vídeo longo: comparar um modelo aberto e um fechado na mesma tarefa simples.',
            ],
        ];
    }
}
