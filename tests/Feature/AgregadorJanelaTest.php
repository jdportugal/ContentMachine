<?php

namespace Tests\Feature;

use App\Services\Aggregation\NewsAggregator;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Two things that made a collection look broken: months-old videos arriving as
 * if they were today's news, and one Apify run per channel making a couple of
 * dozen channels take the better part of an hour.
 */
class AgregadorJanelaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.apify.token' => 'tok-de-teste']);
        config(['contentmachine.monitoring.apify.youtube' => 'ator~youtube']);
        // Só o YouTube nesta bateria — as outras redes não têm canais.
        config(['contentmachine.aggregation.youtube_via_apify' => true]);
    }

    /** @param array<int,array{0:string,1:string,2:string}> $videos [id, canal, data] */
    private function comVideos(array $videos): void
    {
        Http::fake(['*run-sync-get-dataset-items*' => Http::response(
            array_map(fn (array $v) => [
                'id' => $v[0],
                'title' => 'Vídeo '.$v[0],
                'url' => 'https://www.youtube.com/watch?v='.$v[0],
                'channelName' => $v[1],
                'date' => $v[2].'T10:00:00.000Z',
                'text' => 'descrição',
                'thumbnailUrl' => '',
            ], $videos),
        )]);
    }

    private function canais(array $urls): void
    {
        $d = app(SettingsRepository::class);
        $d->save(['canais' => ['youtube' => $urls, 'instagram' => [], 'tiktok' => [], 'linkedin' => []]]);
    }

    public function test_uma_recolha_normal_ignora_videos_antigos(): void
    {
        $this->canais(['https://www.youtube.com/@um']);
        $this->comVideos([
            ['recente', 'Canal Um', now()->subDay()->toDateString()],
            ['antigo', 'Canal Um', now()->subMonths(8)->toDateString()],
        ]);

        $resumo = app(NewsAggregator::class)->aggregate(['youtube']);

        $this->assertSame(1, $resumo['por_plataforma']['youtube'], 'só o vídeo dentro da janela conta');
        $vault = app(VaultContract::class);
        $this->assertNotNull($vault->get('noticias/'.now()->subDay()->toDateString().'/youtube-recente.md'));
        $this->assertNull($vault->get('noticias/'.now()->subMonths(8)->toDateString().'/youtube-antigo.md'));
    }

    public function test_a_janela_pode_ser_desligada(): void
    {
        config(['contentmachine.aggregation.dias_recentes' => 0]);
        $this->canais(['https://www.youtube.com/@um']);
        $this->comVideos([['antigo', 'Canal Um', now()->subMonths(8)->toDateString()]]);

        $resumo = app(NewsAggregator::class)->aggregate(['youtube']);

        $this->assertSame(1, $resumo['por_plataforma']['youtube'], 'sem janela, o vídeo antigo entra');
    }

    /** Uma única corrida do actor para TODOS os canais, não uma por canal. */
    public function test_todos_os_canais_vao_numa_so_corrida_do_actor(): void
    {
        $this->canais([
            'https://www.youtube.com/@um',
            'https://www.youtube.com/@dois',
            'https://www.youtube.com/@tres',
        ]);
        $this->comVideos([['a', 'Canal Um', now()->toDateString()]]);

        app(NewsAggregator::class)->aggregate(['youtube']);

        $corridas = 0;
        $urlsEnviadas = 0;
        foreach (Http::recorded() as [$pedido, $resposta]) {
            if (str_contains($pedido->url(), 'run-sync-get-dataset-items')) {
                $corridas++;
                $urlsEnviadas = count($pedido->data()['startUrls'] ?? []);
            }
        }

        $this->assertSame(1, $corridas, 'três canais devem custar UMA corrida, não três');
        $this->assertSame(3, $urlsEnviadas, 'os três canais seguem no mesmo input');
    }

    /** O tecto por canal é aplicado por nós — um canal ocupado não afoga os outros. */
    public function test_um_canal_ocupado_nao_ultrapassa_o_limite(): void
    {
        $this->canais(['https://www.youtube.com/@um', 'https://www.youtube.com/@dois']);
        $hoje = now()->toDateString();
        $videos = [];
        foreach (range(1, 9) as $i) {
            $videos[] = ["ocupado$i", 'Canal Ocupado', $hoje];
        }
        $videos[] = ['calmo1', 'Canal Calmo', $hoje];
        $this->comVideos($videos);

        $resumo = app(NewsAggregator::class)->aggregate(['youtube'], 5);

        // 5 do canal ocupado (tecto) + 1 do calmo.
        $this->assertSame(6, $resumo['por_plataforma']['youtube']);
    }
}
