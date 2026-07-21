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
            'posts' => ['/publicacoes/posts', 'Posts de página única'],
            'carrosseis' => ['/publicacoes/carrosseis', 'Carrosséis'],
            'rascunhos' => ['/rascunhos', 'Rascunhos'],
            'noticias' => ['/noticias', 'Agregador de Notícias'],
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
}
