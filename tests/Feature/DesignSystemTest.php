<?php

namespace Tests\Feature;

use App\Livewire\DesignSystem;
use App\Services\DesignSystem\DesignSystemRepository;
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
