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

    /**
     * The animation style manual is per-project and already feeds every clip's
     * prompt — this page is the only way to edit it without opening the vault.
     */
    public function test_o_manual_de_animacao_do_projeto_e_lido_e_guardado(): void
    {
        $caminho = $this->tmp.'/estilo-animacao.md';
        config(['contentmachine.clips.style_md' => $caminho]);
        file_put_contents($caminho, "# Estilo\nSempre escuro.");

        Livewire::test(DesignSystem::class)
            ->assertSet('estilo', "# Estilo\nSempre escuro.")
            ->set('estilo', "# Estilo\nSempre claro, sem gráficos.")
            ->call('guardar');

        $this->assertSame("# Estilo\nSempre claro, sem gráficos.", file_get_contents($caminho));
    }

    /** Changing only the animation instructions must not touch the design system (or its preview hash). */
    public function test_so_instrucoes_de_animacao_nao_reescrevem_o_design(): void
    {
        $caminho = $this->tmp.'/estilo-animacao.md';
        config(['contentmachine.clips.style_md' => $caminho]);
        $repo = app(DesignSystemRepository::class);
        $repo->write("# Marca\nAzul.");
        $repo->writeTokens(DesignTheme::defaults());
        $antes = filemtime($repo->path());
        $chamadas = 0;
        $this->app->bind(DesignThemeExtractor::class, fn () => new class($chamadas) extends DesignThemeExtractor
        {
            public function __construct(private int &$n) {}

            public function extract(string $markdown): array
            {
                $this->n++;

                return DesignTheme::defaults();
            }
        });
        sleep(1);

        Livewire::test(DesignSystem::class)
            ->set('estilo', "# Estilo\nMais rápido.")
            ->call('guardar');

        $this->assertSame(0, $chamadas, 'no theme extraction');
        $this->assertSame($antes, filemtime($repo->path()), 'design system file untouched');
        $this->assertSame("# Estilo\nMais rápido.", file_get_contents($caminho));
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

    /** Project resources: agent details save, photo and extra images upload, and the page shows them. */
    public function test_project_resources_are_saved_from_the_panel(): void
    {
        $png = UploadedFile::fake()->image('photo.png', 8, 8);

        Livewire::test(\App\Livewire\ProjectResourcesPanel::class)
            ->set('agent.name', 'Nuno Narciso')
            ->set('agent.licence', 'AMI 1234')
            ->set('music', 'nenhuma')
            ->call('guardar')
            ->assertHasNoErrors()
            ->set('agentPhoto', $png)
            ->set('newImage', UploadedFile::fake()->image('keys.png', 8, 8))
            ->call('adicionarImagem')
            ->assertHasErrors('newImageDesc')
            ->set('newImageDesc', 'Nuno com as chaves')
            ->call('adicionarImagem')
            ->assertHasNoErrors()
            ->assertSet('extras.0.description', 'Nuno com as chaves');

        $r = app(\App\Services\Projects\ProjectResources::class);
        $this->assertSame('Nuno Narciso', $r->agent()['name']);
        $this->assertSame('nenhuma', $r->music());
        $this->assertNotNull($r->agentPhotoPath());
        $this->assertCount(1, $r->images());
        $this->comSessaoIniciada()->get(route('project-resources.image', $r->images()[0]['file']))->assertOk();

        Livewire::test(\App\Livewire\ProjectResourcesPanel::class)->call('removerImagem', $r->images()[0]['file']);
        $this->assertSame([], $r->images());
    }
}
