<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\SceneVisualFiller;
use Tests\TestCase;

/**
 * How many images ONE clip may ask to have made.
 *
 * The planner is told to be visually dense and requestImages() then tops up every
 * remaining bare scene, neither of them counting. The only limit was
 * clips.image_max — which caps GENERATION, not requests, and is flat regardless
 * of length, so a short clip asked for seven paid generations.
 */
class SceneVisualFillerImageBudgetTest extends TestCase
{
    private function filler(): SceneVisualFiller
    {
        return app(SceneVisualFiller::class);
    }

    /** @param array<int,array<string,mixed>> $layersPorCena */
    private function plan(float $fim, array $layersPorCena): array
    {
        $n = count($layersPorCena);
        $passo = $fim / max(1, $n);
        $scenes = [];
        foreach ($layersPorCena as $i => $layers) {
            $scenes[] = ['start' => $i * $passo, 'end' => ($i + 1) * $passo, 'layers' => $layers];
        }

        return ['scenes' => $scenes];
    }

    private function pedido(string $prompt = 'Illustrate this moment: x'): array
    {
        return ['type' => 'image-reveal', 'params' => ['generate' => $prompt, 'variant' => 'fullscreen']];
    }

    private function contarPedidos(array $plan): int
    {
        $n = 0;
        foreach ($plan['scenes'] as $s) {
            foreach ($s['layers'] as $l) {
                if (! empty($l['params']['generate'])) {
                    $n++;
                }
            }
        }

        return $n;
    }

    /** The reported case: a short clip that wanted seven pictures. */
    public function test_a_short_clip_does_not_ask_for_seven_images(): void
    {
        config(['contentmachine.clips.image_every_seconds' => 12, 'contentmachine.clips.image_max' => 8]);

        // 30 seconds, nine scenes, every one asking for a generated image.
        $plan = $this->plan(30, array_fill(0, 9, [$this->pedido()]));
        $this->assertSame(9, $this->contarPedidos($plan));

        $filler = $this->filler();
        $orcamento = $filler->imageBudget($plan);
        $cortado = $filler->limitImages($plan, $orcamento);

        $this->assertSame(3, $orcamento, '30s at one image per 12s');
        $this->assertSame(3, $this->contarPedidos($cortado));
    }

    /** A long clip still gets a decent number — the budget scales, not just clamps. */
    public function test_a_long_clip_gets_more_images_than_a_short_one(): void
    {
        config(['contentmachine.clips.image_every_seconds' => 12, 'contentmachine.clips.image_max' => 8]);
        $filler = $this->filler();

        $curto = $this->plan(24, array_fill(0, 8, [$this->pedido()]));
        $longo = $this->plan(120, array_fill(0, 12, [$this->pedido()]));

        $this->assertSame(2, $filler->imageBudget($curto));
        $this->assertSame(8, $filler->imageBudget($longo), 'capped by image_max');
        $this->assertGreaterThan($filler->imageBudget($curto), $filler->imageBudget($longo));
    }

    /** image_max stays the hard ceiling however long the clip runs. */
    public function test_the_hard_ceiling_still_applies(): void
    {
        config(['contentmachine.clips.image_every_seconds' => 1, 'contentmachine.clips.image_max' => 4]);

        $this->assertSame(4, $this->filler()->imageBudget($this->plan(300, [[]])));
    }

    /** Never zero: a clip with no picture at all is worse than one with a single good one. */
    public function test_a_very_short_clip_still_gets_one(): void
    {
        config(['contentmachine.clips.image_every_seconds' => 12, 'contentmachine.clips.image_max' => 8]);

        $this->assertSame(1, $this->filler()->imageBudget($this->plan(4, [[]])));
    }

    /**
     * The user's own image and a page to FILM are not "asked to be made" — they
     * must survive the cut whatever the budget, or trimming would throw away an
     * upload the user chose on purpose.
     */
    public function test_provided_images_and_site_captures_are_never_trimmed(): void
    {
        $plan = $this->plan(30, [
            [['type' => 'image-reveal', 'params' => ['src' => 'img_meu']]],
            [['type' => 'image-reveal', 'params' => ['site' => 'https://example.com']]],
            [$this->pedido('a')],
            [$this->pedido('b')],
            [$this->pedido('c')],
        ]);

        $cortado = $this->filler()->limitImages($plan, 1);

        $this->assertSame(1, $this->contarPedidos($cortado), 'only one generate survives');
        $sobreviventes = collect($cortado['scenes'])->pluck('layers')->flatten(1);
        $this->assertTrue($sobreviventes->contains(fn ($l) => ($l['params']['src'] ?? null) === 'img_meu'));
        $this->assertTrue($sobreviventes->contains(fn ($l) => ! empty($l['params']['site'])));
    }

    /**
     * The keepers are spread across the clip. Taking the first N would put every
     * picture in the opening seconds and leave the rest of the video without one.
     */
    public function test_the_kept_images_are_spread_across_the_clip(): void
    {
        $plan = $this->plan(60, array_map(fn ($i) => [$this->pedido("p{$i}")], range(0, 8)));

        $cortado = $this->filler()->limitImages($plan, 3);

        $comImagem = [];
        foreach ($cortado['scenes'] as $i => $s) {
            foreach ($s['layers'] as $l) {
                if (! empty($l['params']['generate'])) {
                    $comImagem[] = $i;
                }
            }
        }

        $this->assertCount(3, $comImagem);
        $this->assertSame(0, $comImagem[0], 'first scene keeps one');
        $this->assertSame(8, $comImagem[2], 'last scene keeps one');
        $this->assertGreaterThan(2, $comImagem[1] - $comImagem[0], 'not clustered at the front');
    }

    /** Under budget, the plan is returned untouched. */
    public function test_a_plan_within_budget_is_unchanged(): void
    {
        $plan = $this->plan(60, [[$this->pedido('a')], [$this->pedido('b')]]);

        $this->assertSame($plan, $this->filler()->limitImages($plan, 5));
    }
}
