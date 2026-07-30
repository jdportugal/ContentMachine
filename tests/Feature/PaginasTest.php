<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    use RefreshDatabase;

    public static function rotas(): array
    {
        return [
            'painel' => ['/', 'Painel'],
            'monitorizacao' => ['/monitorizacao', 'Monitorização'],
            'clips' => ['/clips', 'Gerador de Clips'],
            'clips-animados' => ['/clips-animados', 'Clips Animados'],
            'publicacoes' => ['/publicacoes', 'Publicações'],
            'oficina-post' => ['/publicacoes/post', 'Posts de página única'],
            'oficina-citacao' => ['/publicacoes/citacao', 'Citações'],
            'oficina-dica' => ['/publicacoes/dica', 'Dicas rápidas'],
            'oficina-carrossel' => ['/publicacoes/carrossel', 'Carrosséis'],
            'oficina-lista' => ['/publicacoes/lista', 'Listas numeradas'],
            'oficina-resumo' => ['/publicacoes/resumo-semana', 'Resumo da semana'],
            'rascunhos' => ['/rascunhos', 'Rascunhos'],
            'noticias' => ['/noticias', 'Agregador de Notícias'],
            'design-system' => ['/design-system', 'Sistema de Design'],
            'definicoes' => ['/definicoes', 'Definições'],
        ];
    }

    #[DataProvider('rotas')]
    public function test_pagina_responde_200_e_mostra_titulo(string $url, string $texto): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($texto);
    }

    public function test_monitorizacao_mostra_ultimo_por_tipo(): void
    {
        $this->get('/monitorizacao?rede=youtube')
            ->assertOk()
            ->assertSee('Último de cada género')
            ->assertSee('Melhores desempenhos');
    }

    public function test_painel_le_rascunhos_do_vault(): void
    {
        // O vault semeado tem exemplos → o painel mostra a secção de plataformas.
        $this->get('/')
            ->assertOk()
            ->assertSee('desempenho recente', false);
    }

    /**
     * O Painel é a página de entrada (/). Com os drivers reais ('api') ainda
     * por configurar — ou quando o endpoint externo falha — os fetches ao vivo
     * lançam. A página tem de degradar com elegância (sem dados) em vez de
     * devolver 500. Regressão do bug «/ não abre, só as subpáginas».
     */
    public function test_painel_nao_rebenta_com_drivers_api_por_configurar(): void
    {
        config([
            'contentmachine.monitoring.driver' => 'api',
            'contentmachine.news.driver' => 'api',
        ]);

        \Livewire\Livewire::test(\App\Livewire\Painel::class)
            ->assertOk()
            ->assertSee('desempenho recente', false)
            ->assertSee('Sem destaques ainda', false);
    }
}
