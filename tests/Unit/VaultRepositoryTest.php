<?php

namespace Tests\Unit;

use App\Services\Vault\VaultRepository;
use PHPUnit\Framework\TestCase;

class VaultRepositoryTest extends TestCase
{
    private string $tmp;

    private VaultRepository $vault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-vault-'.uniqid();
        mkdir($this->tmp, 0775, true);
        $this->vault = new VaultRepository($this->tmp);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    public function test_escreve_e_le_frontmatter_em_ida_e_volta(): void
    {
        $this->vault->put('rascunhos/nota.md', [
            'titulo' => 'Olá mundo',
            'tipo' => 'post',
            'tags' => ['a', 'b'],
        ], "# Corpo\n\nTexto em **markdown**.");

        $nota = $this->vault->get('rascunhos/nota.md');

        $this->assertNotNull($nota);
        $this->assertSame('Olá mundo', $nota->get('titulo'));
        $this->assertSame('post', $nota->get('tipo'));
        $this->assertSame(['a', 'b'], $nota->get('tags'));
        $this->assertStringContainsString('# Corpo', $nota->body);
        $this->assertStringContainsString('<strong>markdown</strong>', $nota->html());
    }

    public function test_create_gera_slug_e_data(): void
    {
        $nota = $this->vault->create('rascunhos', ['titulo' => 'Cinco Termos'], 'corpo');

        $this->assertStringStartsWith('rascunhos/cinco-termos-', $nota->path);
        $this->assertNotNull($nota->get('data'));
        $this->assertFileExists($this->tmp.'/'.$nota->path);
    }

    public function test_update_frontmatter_preserva_corpo(): void
    {
        $this->vault->put('rascunhos/x.md', ['estado' => 'rascunho'], 'corpo original');

        $this->vault->updateFrontmatter('rascunhos/x.md', [
            'estado' => 'agendado',
            'agendado_para' => '2026-08-01',
        ]);

        $nota = $this->vault->get('rascunhos/x.md');
        $this->assertSame('agendado', $nota->get('estado'));
        $this->assertSame('2026-08-01', $nota->get('agendado_para'));
        $this->assertStringContainsString('corpo original', $nota->body);
    }

    public function test_all_ordena_por_data_desc(): void
    {
        $this->vault->put('r/antiga.md', ['data' => '2026-01-01'], 'a');
        $this->vault->put('r/nova.md', ['data' => '2026-06-01'], 'b');

        $ordenadas = $this->vault->all('r')->map->slug()->all();

        $this->assertSame(['nova', 'antiga'], $ordenadas);
    }

    public function test_delete_remove_ficheiro(): void
    {
        $this->vault->put('r/y.md', [], 'x');
        $this->assertTrue($this->vault->delete('r/y.md'));
        $this->assertNull($this->vault->get('r/y.md'));
    }

    public function test_impede_path_traversal(): void
    {
        $this->vault->put('../fora.md', ['titulo' => 'nope'], 'x');

        // Deve ter sido escrito dentro do vault, não acima dele.
        $this->assertFileDoesNotExist(dirname($this->tmp).'/fora.md');
        $this->assertFileExists($this->tmp.'/fora.md');
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir.'/'.$f;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
