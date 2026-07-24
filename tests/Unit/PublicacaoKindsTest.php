<?php

namespace Tests\Unit;

use App\Services\Publicacoes\PublicacaoKinds;
use Tests\TestCase;

class PublicacaoKindsTest extends TestCase
{
    public function test_all_devolve_pelo_menos_seis_tipos_indexados_por_tipo(): void
    {
        $kinds = new PublicacaoKinds;
        $todos = $kinds->all();

        $this->assertGreaterThanOrEqual(6, count($todos));
        $this->assertArrayHasKey('post', $todos);
        $this->assertArrayHasKey('carrossel', $todos);
    }

    public function test_cada_tipo_tem_os_campos_obrigatorios(): void
    {
        $kinds = new PublicacaoKinds;

        foreach ($kinds->all() as $tipo => $def) {
            foreach (['label', 'glifo', 'formato', 'proporcao', 'gabarito', 'plano_prompt', 'cartoes'] as $campo) {
                $this->assertArrayHasKey($campo, $def, "Tipo {$tipo} sem campo {$campo}");
            }
            $this->assertContains($def['formato'], ['single', 'carousel']);
        }
    }

    public function test_get_e_formato(): void
    {
        $kinds = new PublicacaoKinds;

        $this->assertSame('carousel', $kinds->formato('carrossel'));
        $this->assertSame('single', $kinds->formato('post'));
        $this->assertSame('Carousels', $kinds->get('carrossel')['label']);
        $this->assertNull($kinds->get('inexistente'));
    }

    public function test_exists(): void
    {
        $kinds = new PublicacaoKinds;

        $this->assertTrue($kinds->exists('post'));
        $this->assertFalse($kinds->exists('inexistente'));
    }
}
