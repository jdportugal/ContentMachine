<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\NovoVideoPagina;
use App\Models\User;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\AgencyLogoFinder;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** "Novo vídeo": a listing plus the customer's own photos, voiceover and music, before it is made. */
class NovoVideoTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/moradia-t3/777';

    private string $musicas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->musicas = sys_get_temp_dir().'/cm-musicas-'.uniqid();
        mkdir($this->musicas, 0777, true);
        file_put_contents($this->musicas.'/manha-calma.mp3', 'MP3');
        $this->app->singleton(MusicLibrary::class, fn () => new MusicLibrary($this->musicas));
    }

    private function cliente(int $videos = 1): User
    {
        $user = User::factory()->cliente()->create();
        if ($videos) {
            DB::table('compras')->insert(['user_id' => $user->id, 'sessao' => 'cs_'.uniqid(), 'total' => 2000, 'videos' => $videos, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user;
    }

    private function png(int $cor = 0x00FF00): string
    {
        $i = imagecreatetruecolor(4, 4);
        imagefill($i, 0, 0, $cor);
        ob_start();
        imagepng($i);

        return (string) ob_get_clean();
    }

    public function test_the_page_shows_the_four_steps_and_the_library_to_listen_to(): void
    {
        $this->get('/conta/novo')->assertRedirect('/login');
        $user = $this->cliente();

        $this->actingAs($user)->get('/conta')->assertSee('/conta/novo', false);
        $this->get('/conta/novo')->assertOk()
            ->assertSee('O anúncio')->assertSee('Arraste as fotos para aqui')->assertSee('Escrevo eu')
            ->assertSee('Manha Calma')->assertSee('/conta/musicas/manha-calma.mp3', false)->assertSee('Sem música')
            ->assertSee('Usa 1 dos seus 1 vídeo');
        $this->get('/conta/musicas/manha-calma.mp3')->assertOk();
        $this->get('/conta/musicas/nao-existe.mp3')->assertNotFound();
    }

    public function test_creating_carries_the_photos_voiceover_and_music_to_the_import(): void
    {
        Queue::fake();
        $disco = Storage::fake(config('contentmachine.clips.disk'));
        $user = $this->cliente();
        $texto = 'Moradia T3 em Lajeosa, com jardim e vista para a serra. Ligue-me para a visitar.';

        Livewire::actingAs($user)->test(NovoVideoPagina::class)
            ->set('anuncio', self::ANUNCIO)
            ->set('novas', [UploadedFile::fake()->image('piscina.jpg'), UploadedFile::fake()->image('garagem.jpg')])
            ->assertCount('fotos', 2)
            ->set('novas', [UploadedFile::fake()->image('sotao.jpg')])
            ->assertCount('fotos', 3)
            ->call('tirarFoto', 1)->assertCount('fotos', 2)
            ->set('narrador', 'eu')->set('narracao', $texto)
            ->set('musica', 'manha-calma.mp3')
            ->call('criar')->assertHasNoErrors()->assertRedirect('/conta');

        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === self::ANUNCIO);
        $lead = DB::table('leads')->sole();
        $this->assertSame($user->id, (int) $lead->user_id);
        $this->assertNotNull($lead->pago_em);
        $meta = app(ClipStore::class)->find($lead->clip_id)->meta;
        $this->assertCount(2, $meta['fotos_extra']);
        foreach ($meta['fotos_extra'] as $f) {
            $disco->assertExists($f);
        }
        $this->assertSame($texto, $meta['narracao_propria']);
        $this->assertSame('manha-calma.mp3', $meta['musica']);
        $this->assertArrayHasKey('theme', $meta);
        $this->assertSame(0, $user->creditos());
    }

    public function test_own_music_needs_a_file_and_own_voiceover_needs_text(): void
    {
        Queue::fake();
        Storage::fake(config('contentmachine.clips.disk'));
        $user = $this->cliente();

        Livewire::actingAs($user)->test(NovoVideoPagina::class)
            ->set('anuncio', self::ANUNCIO)->set('narrador', 'eu')->set('narracao', 'Curto.')->set('musica', 'propria')
            ->call('criar')->assertHasErrors(['narracao', 'ficheiroMusica'])
            ->set('narracao', str_repeat('Uma casa com luz. ', 4))
            ->set('ficheiroMusica', UploadedFile::fake()->create('minha.mp3', 300, 'audio/mpeg'))
            ->assertSet('musica', 'propria')
            ->call('criar')->assertHasNoErrors();
        $meta = app(ClipStore::class)->find(DB::table('leads')->value('clip_id'))->meta;
        $this->assertSame('propria', $meta['musica']);
        $this->assertFileExists($meta['musica_ficheiro']);

        // A made-up track name is refused; no credits left → the buy button.
        Livewire::actingAs($user)->test(NovoVideoPagina::class)->set('anuncio', self::ANUNCIO.'1')->set('musica', 'outra.mp3')
            ->call('criar')->assertHasErrors('musica')->assertSee('Comprar vídeos para continuar');
    }

    public function test_the_import_says_their_text_and_adds_their_photos(): void
    {
        Queue::fake();
        $disco = Storage::fake(config('contentmachine.clips.disk'));
        $disco->put('clips/uploads/extra-1.png', $this->png(0xFF0000));
        $disco->put('clips/uploads/extra-2.png', $this->png(0x0000FF));
        $texto = 'O meu texto, palavra por palavra, para esta moradia com piscina.';
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'status' => 'importing', 'title' => 'x',
            'meta' => ['listing' => ['url' => 'u'], 'fotos_extra' => ['clips/uploads/extra-1.png', 'clips/uploads/extra-2.png', 'clips/uploads/sumiu.png'],
                'narracao_propria' => $texto, 'musica' => 'manha-calma.mp3']]);

        Http::fake(['*' => Http::response($this->png())]);
        $this->mock(ListingScraper::class)->shouldReceive('scrape')->andReturn([
            'title' => 'T3', 'description' => 'd', 'url' => 'u', 'price' => 1, 'details' => [], 'photos' => ['https://x/casa.png'],
            'agent' => ['name' => 'Ana', 'phone' => '1', 'photo' => null], 'agency' => 'RE/MAX',
        ]);
        $this->mock(ListingScriptWriter::class)->shouldReceive('write')->andReturn([
            'title' => 'T3', 'flags' => [], 'agent_gender' => 'f', 'voiceover' => 'O texto que nós escreveríamos.', 'hook' => '', 'scenes' => [], 'message' => '',
        ]);
        $this->mock(ElevenLabsVoiceoverService::class)->shouldReceive('synthesizeWith')->once()->withArgs(fn ($t) => $t === $texto);
        $this->mock(AgencyLogoFinder::class)->shouldReceive('find')->andReturn(null);
        config(['contentmachine.real_estate.voices.f.takes' => 1]);

        app()->call([new ImportListingJob($clip->id, 'u'), 'handle']);

        $clip = app(ClipStore::class)->find($clip->id);
        $adicionais = collect($clip->images)->filter(fn ($i) => str_starts_with($i['description'], 'Foto adicional'));
        $this->assertCount(2, $adicionais);                       // the vanished one is skipped
        $this->assertSame($texto, $clip->meta['listing']['voiceover']);
        $this->assertSame('manha-calma.mp3', $clip->meta['musica']);
        $this->assertStringContainsString('adicional-1.png', $clip->meta['instructions']);
    }
}
