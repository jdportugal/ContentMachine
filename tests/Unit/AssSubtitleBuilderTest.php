<?php

namespace Tests\Unit;

use App\Services\Shorts\AssSubtitleBuilder;
use PHPUnit\Framework\TestCase;

class AssSubtitleBuilderTest extends TestCase
{
    private array $data = [[
        'start' => 0.0,
        'end' => 2.0,
        'text' => 'olá mundo',
        'words' => [
            ['word' => 'olá', 'start' => 0.0, 'end' => 1.0],
            ['word' => 'mundo', 'start' => 1.0, 'end' => 2.0],
        ],
    ]];

    public function test_cabecalho_usa_dimensoes_do_video(): void
    {
        $ass = (new AssSubtitleBuilder)->build($this->data, ['position' => 'center-center'], 'off', 1080, 1920);

        $this->assertStringContainsString('PlayResX: 1080', $ass);
        $this->assertStringContainsString('PlayResY: 1920', $ass);
        $this->assertStringContainsString('[V4+ Styles]', $ass);
        $this->assertStringContainsString('[Events]', $ass);
    }

    public function test_posicao_mapeia_para_alignment_ass(): void
    {
        $builder = new AssSubtitleBuilder;

        // Alignment é o penúltimo-antes-das-margens campo do Style: … ,Alignment,ML,MR,MV,Enc
        $bottom = $builder->build($this->data, ['position' => 'bottom-center'], 'off', 1080, 1920);
        $this->assertMatchesRegularExpression('/Style: Default,.*,1,[\d.]+,0,2,\d+,\d+,\d+,1/', $bottom);

        $center = $builder->build($this->data, ['position' => 'center-center'], 'off', 1080, 1920);
        $this->assertMatchesRegularExpression('/Style: Default,.*,1,[\d.]+,0,5,\d+,\d+,\d+,1/', $center);
    }

    public function test_cor_hex_converte_para_bgr_ass(): void
    {
        // #2dbab4 → BGR: b4 ba 2d
        $ass = (new AssSubtitleBuilder)->build($this->data, ['line-color' => '#2dbab4'], 'off', 1080, 1920);
        $this->assertStringContainsString('&H00B4BA2D', $ass);
    }

    public function test_font_family_mapeia_para_nome_da_fonte(): void
    {
        $ass = (new AssSubtitleBuilder)->build($this->data, ['font-family' => 'Luckiest Guy', 'font-size' => 90], 'off', 1080, 1920);
        $this->assertStringContainsString('Style: Default,Luckiest Guy,90,', $ass);
    }

    public function test_modo_off_uma_dialogue_por_segmento(): void
    {
        $ass = (new AssSubtitleBuilder)->build($this->data, [], 'off', 1080, 1920);
        $this->assertSame(1, substr_count($ass, 'Dialogue:'));
        $this->assertStringContainsString('olá mundo', $ass);
    }

    public function test_modo_karaoke_agrupa_palavras_e_destaca_a_ativa(): void
    {
        // 5 palavras, máx. 4/grupo → 2 grupos ([4]+[1]) → 5 eventos (um por palavra).
        $data = [[
            'start' => 0.0, 'end' => 5.0, 'text' => 'um dois três quatro cinco',
            'words' => [
                ['word' => 'um', 'start' => 0.0, 'end' => 1.0],
                ['word' => 'dois', 'start' => 1.0, 'end' => 2.0],
                ['word' => 'três', 'start' => 2.0, 'end' => 3.0],
                ['word' => 'quatro', 'start' => 3.0, 'end' => 4.0],
                ['word' => 'cinco', 'start' => 4.0, 'end' => 5.0],
            ],
        ]];

        $ass = (new AssSubtitleBuilder)->build($data, ['highlight-color' => '#F5C542'], 'karaoke', 1080, 1920);

        // Um evento por palavra (destaque avança palavra a palavra).
        $this->assertSame(5, substr_count($ass, 'Dialogue:'));

        // Destaque inline a amarelo (#F5C542 → BGR 42C5F5).
        $this->assertStringContainsString('{\1c&H42C5F5&}', $ass);

        // Primeiro grupo mostra as 4 palavras juntas (grupo de até 4).
        $this->assertMatchesRegularExpression('/Dialogue:[^\n]*um[^\n]*dois[^\n]*três[^\n]*quatro/', $ass);

        // Já não usa o varrimento \kf nem o estilo Hi.
        $this->assertStringNotContainsString('\kf', $ass);
        $this->assertStringNotContainsString('Style: Hi,', $ass);
    }

    public function test_modo_popup_uma_dialogue_por_palavra(): void
    {
        $ass = (new AssSubtitleBuilder)->build($this->data, [], 'popup', 1080, 1920);
        $this->assertSame(2, substr_count($ass, 'Dialogue:'));
    }

    public function test_modo_typewriter_acumula_texto(): void
    {
        $ass = (new AssSubtitleBuilder)->build($this->data, [], 'typewriter', 1080, 1920);
        $this->assertSame(2, substr_count($ass, 'Dialogue:'));
        $this->assertStringContainsString('olá mundo', $ass); // acumulado na 2ª palavra
    }

    public function test_uppercase_e_escape_de_chavetas(): void
    {
        $data = [['start' => 0, 'end' => 1, 'text' => 'a {b} c', 'words' => []]];
        $ass = (new AssSubtitleBuilder)->build($data, ['text-transform' => 'uppercase'], 'off', 1080, 1920);

        $this->assertStringContainsString('A (B) C', $ass); // maiúsculas + chavetas neutralizadas
        $this->assertStringNotContainsString('{b}', $ass);
    }
}
