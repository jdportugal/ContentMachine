<?php

namespace Tests\Unit;

use App\Services\Shorts\SubtitleShifter;
use PHPUnit\Framework\TestCase;

class SubtitleShifterTest extends TestCase
{
    public function test_parse_time_aceita_varios_formatos(): void
    {
        $this->assertSame(5.0, SubtitleShifter::parseTimeToSeconds(5));
        $this->assertSame(5.5, SubtitleShifter::parseTimeToSeconds(5.5));
        $this->assertSame(5.25, SubtitleShifter::parseTimeToSeconds('5.25'));
        $this->assertSame(10.0, SubtitleShifter::parseTimeToSeconds('00:00:10'));
        $this->assertSame(63.25, SubtitleShifter::parseTimeToSeconds('00:01:03,250'));
        $this->assertSame(63.25, SubtitleShifter::parseTimeToSeconds('00:01:03.250'));
        // Separador ':' antes dos ms (formato do n8n) e ljust-a-3 do servidor.
        $this->assertSame(63.25, SubtitleShifter::parseTimeToSeconds('00:01:03:250'));
        $this->assertSame(3.5, SubtitleShifter::parseTimeToSeconds('00:00:03:5'));
    }

    public function test_seconds_to_timestamp(): void
    {
        $this->assertSame('00:00:05.000', SubtitleShifter::secondsToTimestamp(5));
        $this->assertSame('00:01:03.250', SubtitleShifter::secondsToTimestamp(63.25));
        $this->assertSame('01:00:00.000', SubtitleShifter::secondsToTimestamp(3600));
    }

    private function transcricaoFixture(): array
    {
        return [
            // Antes da janela — deve ser excluído.
            ['start' => 5.0, 'end' => 9.0, 'text' => 'antes', 'words' => [
                ['word' => 'antes', 'start' => 5.0, 'end' => 9.0],
            ]],
            // Sobrepõe o início da janela — tempos negativos limitados a 0.
            ['start' => 9.5, 'end' => 11.0, 'text' => 'na fronteira', 'words' => [
                ['word' => 'na', 'start' => 9.5, 'end' => 10.2],
                ['word' => 'fronteira', 'start' => 10.2, 'end' => 11.0],
            ]],
            // Totalmente dentro da janela.
            ['start' => 12.0, 'end' => 14.0, 'text' => 'ola mundo', 'words' => [
                ['word' => 'ola', 'start' => 12.0, 'end' => 13.0],
                ['word' => 'mundo', 'start' => 13.0, 'end' => 14.0],
            ]],
            // Depois da janela — excluído.
            ['start' => 25.0, 'end' => 28.0, 'text' => 'depois', 'words' => [
                ['word' => 'depois', 'start' => 25.0, 'end' => 28.0],
            ]],
        ];
    }

    public function test_shift_recorta_e_desloca_para_zero(): void
    {
        $out = SubtitleShifter::shift($this->transcricaoFixture(), 10, 20);

        // Só 2 segmentos sobrepõem [10,20].
        $this->assertCount(2, $out);

        // Segmento na fronteira: 9.5-10 = -0.5 → limitado a 0.
        $this->assertSame(0.0, $out[0]['start']);
        $this->assertSame(1.0, $out[0]['end']);
        $this->assertSame('na fronteira', $out[0]['text']);
        $this->assertSame(0.0, $out[0]['words'][0]['start']); // 9.5-10 clamped
        $this->assertEqualsWithDelta(0.2, $out[0]['words'][1]['start'], 0.0001); // 10.2-10

        // Segmento interior deslocado por -10.
        $this->assertSame(2.0, $out[1]['start']);
        $this->assertSame(4.0, $out[1]['end']);
        $this->assertSame(2.0, $out[1]['words'][0]['start']);
        $this->assertSame(4.0, $out[1]['words'][1]['end']);
    }

    public function test_shift_aceita_timestamps_como_janela(): void
    {
        $out = SubtitleShifter::shift($this->transcricaoFixture(), '00:00:10.000', '00:00:20.000');

        $this->assertCount(2, $out);
        $this->assertSame(2.0, $out[1]['start']);
    }

    public function test_align_words_mantem_quando_texto_igual(): void
    {
        $words = [
            ['word' => 'ola', 'start' => 0.0, 'end' => 1.0],
            ['word' => 'mundo', 'start' => 1.0, 'end' => 2.0],
        ];

        $result = SubtitleShifter::alignWords('ola mundo', 0.0, 2.0, $words);

        $this->assertSame($words, $result); // tempos originais preservados
    }

    public function test_align_words_redistribui_quando_texto_editado(): void
    {
        $words = [
            ['word' => 'ola', 'start' => 0.0, 'end' => 1.0],
            ['word' => 'mundo', 'start' => 1.0, 'end' => 2.0],
        ];

        $result = SubtitleShifter::alignWords('texto novo editado', 0.0, 3.0, $words);

        $this->assertCount(3, $result);
        $this->assertSame('texto', $result[0]['word']);
        $this->assertSame(0.0, $result[0]['start']);
        $this->assertSame(1.0, $result[0]['end']);
        $this->assertSame(3.0, $result[2]['end']);
    }
}
