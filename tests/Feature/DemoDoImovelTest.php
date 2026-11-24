<?php

namespace Tests\Feature;

use App\Services\Alertas\DemoDoImovel;
use App\Services\RealEstate\ListingScraper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** The cheap demo of a new listing: its photos, one recorded voice, ffmpeg only. */
class DemoDoImovelTest extends TestCase
{
    private const URL = 'https://remax.pt/pt/imoveis/venda-apartamento-t2-gondomar-fanzeres/125411383-13';

    private string $voz;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->voz = sys_get_temp_dir().'/demo-voz-'.uniqid().'.mp3';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', '-ac', '1', $this->voz]))->mustRun();
        config(['contentmachine.alertas.demo' => true, 'contentmachine.alertas.demo_voz' => $this->voz]);

        $jpg = function (int $cor): string {
            $im = imagecreatetruecolor(800, 600);
            imagefill($im, 0, 0, imagecolorallocate($im, $cor, 120, 200 - $cor));
            ob_start();
            imagejpeg($im);

            return (string) ob_get_clean();
        };
        Http::fake([
            'fotos.test/1.jpg' => Http::response($jpg(30)),
            'fotos.test/2.jpg' => Http::response($jpg(90)),
            'fotos.test/3.jpg' => Http::response($jpg(150)),
            '*' => Http::response('', 404),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->voz);
        parent::tearDown();
    }

    private function anuncio(array $fotos): void
    {
        $this->app->instance(ListingScraper::class, new class($fotos) extends ListingScraper
        {
            public function __construct(private array $fotos) {}

            public function scrape(string $url): array
            {
                return ['url' => $url, 'title' => 'Apartamento T2 em Fânzeres', 'photos' => $this->fotos];
            }
        });
    }

    public function test_it_renders_the_demo_from_the_photos_and_the_recorded_voice(): void
    {
        $this->anuncio(['https://fotos.test/1.jpg', 'https://fotos.test/2.jpg', 'https://fotos.test/3.jpg', 'https://fotos.test/falta.jpg']);

        $demo = app(DemoDoImovel::class)->gerar(self::URL);

        $this->assertNotNull($demo);
        $slug = DemoDoImovel::slug(self::URL);
        $this->assertSame($slug, $demo['slug']);
        $this->assertSame('Apartamento T2 em Fânzeres', $demo['titulo']);
        $this->assertStringEndsWith("/demo-video/{$slug}", $demo['pagina']);
        $disco = Storage::disk('public');
        $this->assertTrue($disco->exists("demos/{$slug}.mp4"));
        $this->assertTrue($disco->exists("demos/{$slug}.jpg"));
        // 1 s before the voice + 2 s of voice + 0.6 s + the 3.2 s end card
        $this->assertEqualsWithDelta(6.8, DemoDoImovel::duracao($disco->path("demos/{$slug}.mp4")), 0.3);

        // A second email about the same listing reuses it.
        $this->assertSame($demo, app(DemoDoImovel::class)->gerar(self::URL));
    }

    public function test_no_demo_without_the_voice_or_with_too_few_photos(): void
    {
        $this->anuncio(['https://fotos.test/1.jpg', 'https://fotos.test/2.jpg']);
        $this->assertNull(app(DemoDoImovel::class)->gerar(self::URL));   // two photos only

        $this->anuncio(['https://fotos.test/1.jpg', 'https://fotos.test/2.jpg', 'https://fotos.test/3.jpg']);
        config(['contentmachine.alertas.demo_voz' => '/nao/existe.mp3']);
        $this->assertNull(app(DemoDoImovel::class)->gerar(self::URL));

        config(['contentmachine.alertas.demo_voz' => $this->voz, 'contentmachine.alertas.demo' => false]);
        $this->assertNull(app(DemoDoImovel::class)->gerar(self::URL));
        $this->assertSame([], Storage::disk('public')->allFiles('demos'));
    }

    public function test_the_page_plays_it_and_links_to_the_final_version(): void
    {
        $this->anuncio(['https://fotos.test/1.jpg', 'https://fotos.test/2.jpg', 'https://fotos.test/3.jpg']);
        $demo = app(DemoDoImovel::class)->gerar(self::URL);

        $this->get('/demo-video/'.$demo['slug'])->assertOk()
            ->assertSee($demo['video'], false)
            ->assertSee('noindex', false)
            ->assertSee('Apartamento T2 em Fânzeres')
            ->assertSee('anuncio='.urlencode(self::URL), false)
            ->assertSee('utm_campaign=demo-imovel', false);

        $this->get('/demo-video/0123456789abcdef')->assertNotFound();
        $this->get('/demo-video/nao-e-um-slug')->assertNotFound();
    }

    public function test_the_command_prints_the_page(): void
    {
        $this->anuncio(['https://fotos.test/1.jpg', 'https://fotos.test/2.jpg', 'https://fotos.test/3.jpg']);

        $this->artisan('alertas:demo', ['url' => self::URL])
            ->expectsOutputToContain('/demo-video/'.DemoDoImovel::slug(self::URL))
            ->assertSuccessful();
    }
}
