<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\PlanValidator;
use Tests\TestCase;

class PlanValidatorCustomTest extends TestCase
{
    /** A custom effect is a visual: a punch word in the scene must not get it dropped. */
    public function test_a_custom_effect_survives_a_scene_with_a_punch_word(): void
    {
        $scenes = [['start' => 0, 'end' => 3, 'punchWord' => 'hectares', 'layers' => [
            ['type' => 'photo-fan', 'params' => ['src' => 'img_a', 'src2' => 'img_b']],
        ]]];

        $out = (new PlanValidator)->validateScenes($scenes, 3.0, 'dense', '', ['photo-fan']);

        $this->assertSame('photo-fan', $out[0]['layers'][0]['type']);
        $this->assertNull($out[0]['punchWord'], 'the effect carries its own text — no punch word on top');
    }
}
