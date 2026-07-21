<?php

namespace Tests\Feature;

use App\Services\Shorts\ShortsClient;
use App\Services\Shorts\ShortsException;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShortsClientTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-shorts-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    public function test_split_video_devolve_job_id(): void
    {
        Http::fake([
            '*/split-video' => Http::response(['job_id' => 'split1', 'status' => 'pending']),
        ]);

        $client = new ShortsClient('http://fake.test');

        $this->assertSame('split1', $client->splitVideo([
            'url' => 'http://x/v.mp4', 'start_time' => '00:00:05.000', 'end_time' => '00:00:20.000',
        ]));

        Http::assertSent(fn ($r) => $r->url() === 'http://fake.test/split-video'
            && $r['start_time'] === '00:00:05.000');
    }

    public function test_wait_for_job_devolve_quando_completo(): void
    {
        Http::fake(['*/job-status/*' => Http::response(['status' => 'completed', 'progress' => 100])]);

        $client = new ShortsClient('http://fake.test');
        $this->assertSame('completed', $client->waitForJob('abc')['status']);
    }

    public function test_wait_for_job_lanca_em_falha(): void
    {
        Http::fake(['*/job-status/*' => Http::response(['status' => 'failed', 'error' => 'boom'])]);

        $client = new ShortsClient('http://fake.test');

        $this->expectException(ShortsException::class);
        $client->waitForJob('abc');
    }

    public function test_download_grava_ficheiro(): void
    {
        Http::fake(['*/download/*' => Http::response('BINARIO', 200)]);

        $client = new ShortsClient('http://fake.test');
        $dest = $this->tmp.'/out.mp4';

        $bytes = $client->download('job1', $dest);

        $this->assertSame(7, $bytes);
        $this->assertSame('BINARIO', file_get_contents($dest));
    }

    public function test_pipeline_gravar_legendas_corta_grava_e_descarrega(): void
    {
        Http::fake([
            '*/split-video' => Http::response(['job_id' => 'split1', 'status' => 'pending']),
            '*/add-subtitles' => Http::response(['job_id' => 'burn1', 'status' => 'pending']),
            '*/job-status/*' => Http::response(['status' => 'completed', 'progress' => 100]),
            '*/download/*' => Http::response('VIDEO-BYTES', 200),
        ]);

        $vault = $this->app->make(VaultContract::class);
        $pipeline = new ShortsPipeline(new ShortsClient('http://fake.test'), $vault);

        $fonte = $pipeline->criarFonte('http://x/v.mp4', 'Fonte teste');
        $clip = $pipeline->criarClip($fonte->path, 'Clip 1', 5, 20);

        // Coloca subtitle_data editado.
        $pipeline->guardarLegendas($clip->path, [
            ['start' => 0.0, 'end' => 2.0, 'text' => 'ola', 'words' => []],
        ], ShortsPipeline::estiloPorDefeito(), 'karaoke');

        $resultado = $pipeline->gravarLegendas($clip->path);

        $this->assertSame('pronto', $resultado->get('estado'));
        $this->assertSame('split1', $resultado->get('split_job_id'));
        $this->assertSame('burn1', $resultado->get('output_job_id'));
        $this->assertFileExists($resultado->get('output_path'));

        // add-subtitles recebeu o job de corte e o subtitle_data com palavras alinhadas.
        Http::assertSent(function ($r) {
            if (! str_ends_with($r->url(), '/add-subtitles')) {
                return false;
            }
            $body = $r->data();

            return $body['job_id'] === 'split1'
                && $body['subtitle_data'][0]['text'] === 'ola'
                && $body['subtitle_data'][0]['words'][0]['word'] === 'ola'; // alignWords criou palavras
        });
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
            $p = $dir.'/'.$f;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }
}
