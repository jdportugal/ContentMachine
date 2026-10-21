<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Every route requires a session now (see Authenticate in bootstrap/app.php).
        $this->comSessaoIniciada();
    }

    public static function routes(): array
    {
        return [
            'portal' => ['/imoveis', 'Os meus vídeos'],
            'animated-clips' => ['/clips-animados', 'Animated Clips'],
            'assets' => ['/ativos', 'Assets'],
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
}
