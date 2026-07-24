<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    use RefreshDatabase;

    public static function routes(): array
    {
        return [
            'dashboard' => ['/', 'Dashboard'],
            'monitoring' => ['/monitorizacao', 'Monitoring'],
            'clips' => ['/clips', 'Clip Generator'],
            'animated-clips' => ['/clips-animados', 'Animated Clips'],
            'posts' => ['/publicacoes', 'Posts'],
            'workshop-post' => ['/publicacoes/post', 'Single-page posts'],
            'workshop-quote' => ['/publicacoes/citacao', 'Quotes'],
            'workshop-tip' => ['/publicacoes/dica', 'Quick tips'],
            'workshop-carousel' => ['/publicacoes/carrossel', 'Carousels'],
            'workshop-list' => ['/publicacoes/lista', 'Numbered lists'],
            'workshop-summary' => ['/publicacoes/resumo-semana', 'Week in review'],
            'drafts' => ['/rascunhos', 'Drafts'],
            'news' => ['/noticias', 'News Aggregator'],
            'design-system' => ['/design-system', 'Design System'],
            'settings' => ['/definicoes', 'Settings'],
        ];
    }

    #[DataProvider('routes')]
    public function test_page_responds_200_and_shows_title(string $url, string $text): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($text);
    }

    public function test_monitoring_shows_latest_per_type(): void
    {
        $this->get('/monitorizacao?rede=youtube')
            ->assertOk()
            ->assertSee('Latest of each genre')
            ->assertSee('Top performers');
    }

    public function test_dashboard_reads_drafts_from_vault(): void
    {
        // The seeded vault has examples → the dashboard shows the platforms section.
        $this->get('/')
            ->assertOk()
            ->assertSee('recent performance', false);
    }
}
