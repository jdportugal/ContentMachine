<?php

namespace Tests\Feature;

use App\Services\Aggregation\TranscriptParser;
use App\Services\Aggregation\YtDlpDriver;
use Tests\Support\FakeYtDlpRunner;
use Tests\TestCase;

class YtDlpDriverTest extends TestCase
{
    /** @return array<string,mixed> */
    private function itemFixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/yt-dlp-item.json'), true);
    }

    private function vttFixture(): string
    {
        return file_get_contents(__DIR__.'/../Fixtures/captions.en.vtt');
    }

    private function driver(): YtDlpDriver
    {
        $meta = $this->itemFixture();
        $url = $meta['webpage_url'];

        $runner = new FakeYtDlpRunner(
            entradas: [['id' => $meta['id'], 'url' => $url]],
            metadados: [$url => $meta],
            vtt: $this->vttFixture(),
        );

        return new YtDlpDriver($runner, new TranscriptParser, 'youtube');
    }

    public function test_normaliza_metadados_do_yt_dlp(): void
    {
        $itens = $this->driver()->collect(['https://www.youtube.com/@nicksaraev'], 3);

        $this->assertCount(1, $itens);
        $item = $itens[0];

        $this->assertSame('youtube', $item->plataforma);
        $this->assertSame('A Practical AI Agent Workflow For Companies In 2027 (Guide)', $item->titulo);
        $this->assertSame('Nick Saraev', $item->canal);
        $this->assertSame('2026-07-14', $item->data);            // upload_date 20260714
        $this->assertStringContainsString('automation', implode(',', $item->tags));
        $this->assertStringContainsString('maxresdefault', $item->thumbnail);
    }

    public function test_extrai_transcricao_das_auto_legendas(): void
    {
        $item = $this->driver()->collect(['canal'], 3)[0];

        $this->assertStringContainsString('the way the most productive people', $item->transcricao);
        $this->assertStringNotContainsString('WEBVTT', $item->transcricao);
    }

    public function test_extrai_fontes_da_descricao(): void
    {
        $item = $this->driver()->collect(['canal'], 3)[0];

        // A descrição do vídeo contém ligações skool.com.
        $this->assertNotEmpty($item->fontes);
        $this->assertTrue(
            collect($item->fontes)->contains(fn ($u) => str_contains($u, 'skool.com')),
            'Esperava uma fonte skool.com extraída da descrição.'
        );
    }

    public function test_organiza_caminho_por_dia(): void
    {
        $item = $this->driver()->collect(['canal'], 3)[0];

        $this->assertSame('2026-07-14', $item->dia());
        $this->assertStringStartsWith('noticias/2026-07-14/youtube-', $item->caminho());
    }

    public function test_plataforma_sem_itens_degrada_sem_erro(): void
    {
        $runner = new FakeYtDlpRunner(entradas: [], metadados: [], vtt: '');
        $driver = new YtDlpDriver($runner, new TranscriptParser, 'instagram');

        $this->assertSame([], $driver->collect(['https://www.instagram.com/x/'], 3));
    }
}
