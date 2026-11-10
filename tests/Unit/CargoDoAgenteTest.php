<?php

namespace Tests\Unit;

use App\Jobs\Clips\RenderJob;
use PHPUnit\Framework\TestCase;

/** The end card says "Consultora imobiliária" for a woman, not the effect's default "Consultor". */
class CargoDoAgenteTest extends TestCase
{
    public function test_the_agent_card_role_follows_the_agents_gender(): void
    {
        $scenes = [['layers' => [
            ['type' => 'agente-revelacao', 'params' => ['name' => 'Ângela Pinto', 'phone' => '+351 912 345 678']],
            ['type' => 'image-reveal', 'params' => ['src' => 'img_a']],
            ['type' => 'agente-revelacao', 'params' => ['name' => 'Rui', 'role' => 'Diretor comercial']],
        ]]];

        $f = RenderJob::comCargo($scenes, 'f')[0]['layers'];
        $this->assertSame('Consultora imobiliária', $f[0]['params']['role']);
        $this->assertArrayNotHasKey('role', $f[1]['params']);          // not an agent card
        $this->assertSame('Diretor comercial', $f[2]['params']['role']); // its own role is kept

        $this->assertSame('Consultor imobiliário', RenderJob::comCargo($scenes, 'm')[0]['layers'][0]['params']['role']);
        $this->assertSame($scenes, RenderJob::comCargo($scenes, ''));   // unknown: the effect decides
    }
}
