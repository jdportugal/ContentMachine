<?php

namespace Tests\Feature\Shorts;

use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Shorts\AssSubtitleBuilder;
use App\Services\Shorts\ShortsPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A burned-in short and an animated clip from the same project must caption the
 * same way. Every expectation here mirrors KaraokeTrack in
 * remotion/src/scenes.tsx — panel in bgAlt, body text on it, accent on the word
 * being spoken, four words a line, 13% up from the bottom.
 */
class CaptionStyleTest extends TestCase
{
    use RefreshDatabase;

    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        // The design system path is NOT project-scoped, so it points at the REAL
        // vault: left alone, this test deletes and overwrites the developer's own
        // design tokens. Point it somewhere disposable for the duration.
        $this->raiz = sys_get_temp_dir().'/cm-ds-'.bin2hex(random_bytes(4));
        File::makeDirectory($this->raiz, 0775, true);
        config(['contentmachine.design_system.path' => $this->raiz.'/design-system.md']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->raiz);
        parent::tearDown();
    }

    private function comTokens(array $tokens): void
    {
        $repo = app(DesignSystemRepository::class);
        @mkdir(dirname($repo->tokensPath()), 0775, true);
        file_put_contents($repo->tokensPath(), json_encode($tokens));
    }

    public function test_the_default_style_comes_from_the_design_system(): void
    {
        $this->comTokens([
            'colors' => ['bgAlt' => '#111827', 'textOnBg' => '#F0F0F0', 'accent' => '#FF0000'],
            'fonts' => ['display' => 'Anton', 'body' => 'Space Grotesk'],
        ]);

        $estilo = ShortsPipeline::estiloPorDefeito();

        $this->assertSame('Space Grotesk', $estilo['font-family']);
        $this->assertSame('#F0F0F0', $estilo['line-color']);      // text on the panel
        $this->assertSame('#FF0000', $estilo['highlight-color']); // the spoken word
        $this->assertSame('#111827', $estilo['box-color']);       // the panel itself
        $this->assertSame('bottom-center', $estilo['position']);
        $this->assertSame(58, $estilo['font-size']);
        $this->assertSame(4, $estilo['max-words-per-line']);
    }

    public function test_without_a_design_system_it_falls_back_to_the_renderer_defaults(): void
    {
        // The Nebula values baked into remotion/src/style-tokens.ts.
        $estilo = ShortsPipeline::estiloPorDefeito();

        $this->assertSame('Space Grotesk', $estilo['font-family']);
        $this->assertSame('#EAF0FF', $estilo['line-color']);
        $this->assertSame('#FFB347', $estilo['highlight-color']);
        $this->assertSame('#0C1225', $estilo['box-color']);
    }

    public function test_a_box_colour_paints_a_panel_instead_of_outlining_the_glyphs(): void
    {
        $ass = (new AssSubtitleBuilder)->build(
            [['start' => 0, 'end' => 2, 'text' => 'hello there', 'words' => []]],
            ShortsPipeline::estiloPorDefeito(),
            'karaoke',
            1080,
            1920,
        );

        // Style fields, 0-indexed once the whole line is split on commas:
        // 0 "Style: Default" 1 Fontname 2 Fontsize 3 Primary 4 Secondary 5 Outline
        // 6 Back 7 Bold … 15 BorderStyle 16 Outline 17 Shadow 18 Alignment
        // 19 MarginL 20 MarginR 21 MarginV 22 Encoding
        $style = collect(explode("\n", $ass))->first(fn ($l) => str_starts_with($l, 'Style: Default'));
        $campos = explode(',', $style);

        $this->assertSame('3', $campos[15], 'BorderStyle must be 3 (opaque panel), not 1 (outline)');
        $this->assertSame('&H0025120C', $campos[6], 'BackColour is the panel #0C1225, ASS-ordered BGR');
        $this->assertSame('2', $campos[18], 'alignment 2 = bottom-centre');
        // 13% of 1920, matching KaraokeTrack's paddingBottom.
        $this->assertSame((string) (int) round(1920 * AssSubtitleBuilder::MARGIN_V_PCT), $campos[21]);
    }

    public function test_an_outlined_style_is_still_available(): void
    {
        $ass = (new AssSubtitleBuilder)->build(
            [['start' => 0, 'end' => 2, 'text' => 'hello', 'words' => []]],
            ['position' => 'center-center', 'line-color' => '#FFFFFF', 'outline-width' => 6],
            'off',
            1080,
            1920,
        );

        $style = collect(explode("\n", $ass))->first(fn ($l) => str_starts_with($l, 'Style: Default'));
        $campos = explode(',', $style);

        $this->assertSame('1', $campos[15], 'no box-color → the original outlined style');
    }
}
