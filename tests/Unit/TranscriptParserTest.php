<?php

namespace Tests\Unit;

use App\Services\Aggregation\TranscriptParser;
use PHPUnit\Framework\TestCase;

class TranscriptParserTest extends TestCase
{
    private function fixture(string $nome): string
    {
        return file_get_contents(__DIR__.'/../Fixtures/'.$nome);
    }

    public function test_converte_vtt_do_youtube_em_texto_limpo(): void
    {
        $texto = (new TranscriptParser)->vttToText($this->fixture('captions.en.vtt'));

        // Sem cabeçalhos, tempos nem marcações inline.
        $this->assertStringNotContainsString('WEBVTT', $texto);
        $this->assertStringNotContainsString('-->', $texto);
        $this->assertStringNotContainsString('<c>', $texto);
        $this->assertStringNotContainsString('align:', $texto);

        // Contém a fala real.
        $this->assertStringContainsString('the way the most productive people', $texto);
        $this->assertStringContainsString('on Earth work today', $texto);
    }

    public function test_deduplica_linhas_em_rolo(): void
    {
        $texto = (new TranscriptParser)->vttToText($this->fixture('captions.en.vtt'));
        $linhas = explode("\n", $texto);

        // A auto-legenda repete cada linha; após dedup não há duplicados consecutivos.
        for ($i = 1; $i < count($linhas); $i++) {
            $this->assertNotSame($linhas[$i - 1], $linhas[$i], 'Linha duplicada consecutiva não deduplicada.');
        }
    }

    public function test_vtt_vazio_devolve_vazio(): void
    {
        $this->assertSame('', (new TranscriptParser)->vttToText("WEBVTT\n\n"));
    }
}
