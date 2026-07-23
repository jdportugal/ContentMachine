<?php

namespace Tests\Feature;

use App\Livewire\DesignSystem;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\DesignSystem\DesignTheme;
use App\Services\DesignSystem\DesignThemeExtractor;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-ds-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.design_system.path' => $this->tmp.'/design-system.md']);

        // Nunca invocar o CLI Claude real nos testes: extractor falso e determinístico.
        $this->app->bind(DesignThemeExtractor::class, fn () => new class extends DesignThemeExtractor
        {
            public function extract(string $markdown): array
            {
                return DesignTheme::sanitize([
                    'colors' => ['bg' => '#0a1230', 'textOnBg' => '#f5f7ff', 'accent' => '#f4b942'],
                    'fonts' => ['display' => 'Anton', 'body' => 'Inter'],
                    'texture' => ['kind' => 'starfield'],
                ]);
            }
        });
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/*') ?: []);
        @rmdir($this->tmp);
        parent::tearDown();
    }

    public function test_editor_semeia_com_modelo_quando_vazio(): void
    {
        Livewire::test(DesignSystem::class)
            ->assertSet('conteudo', fn ($v) => str_contains($v, 'Sistema de Design'));
    }

    public function test_guardar_escreve_no_vault_e_le_de_volta(): void
    {
        Livewire::test(DesignSystem::class)
            ->set('conteudo', "# A minha marca\n\nVoz: directa.")
            ->call('guardar')
            ->assertSet('guardado', fn ($v) => $v !== null);

        $repo = app(DesignSystemRepository::class);
        $this->assertTrue($repo->exists());
        $this->assertStringContainsString('A minha marca', $repo->read());

        // Guardar extrai e persiste os tokens de tema para o renderizador.
        $tokens = $repo->readTokens();
        $this->assertIsArray($tokens);
        $this->assertSame('#0a1230', $tokens['colors']['bg']);
        $this->assertSame('Anton', $tokens['fonts']['display']);
        $this->assertSame('starfield', $tokens['texture']['kind']);
    }

    public function test_sanitize_preenche_defaults_e_valida(): void
    {
        $t = DesignTheme::sanitize([
            'colors' => ['bg' => '#123456', 'accent' => 'não-é-cor'],
            'fonts' => ['display' => '"Playfair Display"'],
            'texture' => ['kind' => 'inválida'],
        ]);

        $this->assertSame('#123456', $t['colors']['bg']);            // válida → mantém
        $this->assertSame('#FFB347', $t['colors']['accent']);        // inválida → default (Nebula)
        $this->assertSame('Playfair Display', $t['fonts']['display']); // aspas removidas
        $this->assertSame('Space Grotesk', $t['fonts']['body']);     // em falta → default (Nebula)
        $this->assertSame('starfield', $t['texture']['kind']);       // inválida → default (Nebula)
    }

    public function test_tokens_round_trip(): void
    {
        $repo = app(DesignSystemRepository::class);
        $this->assertNull($repo->readTokens());

        $repo->writeTokens(DesignTheme::defaults());
        $this->assertTrue($repo->tokensExist());
        $this->assertSame('#FFB347', $repo->readTokens()['colors']['accent']);
    }

    public function test_carregar_ficheiro_preenche_o_editor_sem_gravar(): void
    {
        $md = UploadedFile::fake()->createWithContent('marca.md', "# Do ficheiro\n\nPaleta: teal.");

        Livewire::test(DesignSystem::class)
            ->set('ficheiro', $md)
            ->call('carregar')
            ->assertSet('conteudo', fn ($v) => str_contains($v, 'Do ficheiro'));

        // Carregar não grava — o ficheiro do vault continua inexistente.
        $this->assertFalse(app(DesignSystemRepository::class)->exists());
    }

    public function test_read_devolve_vazio_quando_inexistente(): void
    {
        $this->assertSame('', app(DesignSystemRepository::class)->read());
    }
}
