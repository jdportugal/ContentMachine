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
            ->assertSee('Manha calma')->assertSee('/conta/musicas/manha-calma.mp3', false)->assertSee('Sem música')
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

    /** A video already made by this customer, showing their agent card and portrait. */
    private function videoAnterior(User $user, \Illuminate\Contracts\Filesystem\Filesystem $disco): void
    {
        $disco->put('clips/uploads/retrato.png', $this->png(0x123456));
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'status' => 'done', 'title' => 'T2',
            'images' => [['id' => 'img_ret', 'path' => 'clips/uploads/retrato.png', 'name' => 'Luis.png', 'description' => 'Retrato do consultor Luis Fraga, da RE/MAX Gold.']],
            'meta' => ['listing' => ['url' => self::ANUNCIO, 'agent' => 'Luis Fraga', 'phone' => '+351912000111', 'email' => 'lf@remax.pt', 'licence' => '4719', 'agency' => 'RE/MAX Gold']]]);
        DB::table('leads')->insert(['anuncio' => self::ANUNCIO, 'user_id' => $user->id, 'clip_id' => $clip->id, 'pago_em' => now(),
            'projeto' => app(\App\Services\Projects\ProjectContext::class)->current()->slug, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_without_a_link_the_page_asks_for_files_description_and_the_agent_prefilled(): void
    {
        $disco = Storage::fake(config('contentmachine.clips.disk'));
        $user = $this->cliente(2);
        $this->videoAnterior($user, $disco);

        $this->actingAs($user)->get('/conta')->assertSee('/conta/novo?modo=ficheiros', false);
        $this->get('/conta/novo?modo=ficheiros')->assertOk()
            ->assertSee('Fotos e vídeos')->assertSee('Arraste as fotos e os vídeos para aqui')
            ->assertSee('Descrição do imóvel')->assertSee('O consultor')
            ->assertSee('Preenchido com os seus dados do último vídeo');

        Livewire::actingAs($user)->test(NovoVideoPagina::class)
            ->call('mudarModo', 'ficheiros')
            ->assertSet('nome', 'Luis Fraga')->assertSet('telefone', '+351912000111')->assertSet('ami', '4719')->assertSet('agencia', 'RE/MAX Gold')
            ->assertSet('fotoAtualCaminho', 'clips/uploads/retrato.png');
    }

    public function test_creating_without_a_link_sends_the_typed_listing_and_keeps_the_agent(): void
    {
        Queue::fake();
        $disco = Storage::fake(config('contentmachine.clips.disk'));
        $user = $this->cliente();
        $this->videoAnterior($user, $disco);
        $descricao = "Moradia T3 em Lajeosa do Dão\nCom 180 m² e jardim de 600 m². Sala com lareira, cozinha equipada, três quartos, garagem para dois carros.";

        $c = Livewire::actingAs($user)->test(NovoVideoPagina::class)->call('mudarModo', 'ficheiros')
            ->call('criar')->assertHasErrors('ficheiros')
            ->set('novas', [UploadedFile::fake()->image('fachada.jpg'), UploadedFile::fake()->image('sala.jpg')])
            ->assertCount('fotos', 2)
            ->set('descricao', 'Curta.')->set('preco', '350 000')->set('nome', '')
            ->call('criar')->assertHasErrors(['descricao', 'nome'])
            ->set('descricao', $descricao)->set('nome', 'Luis Fraga')->set('ami', '4719')
            ->call('criar')->assertHasNoErrors()->assertRedirect('/conta');

        $lead = DB::table('leads')->where('id', '>', 1)->sole();
        $this->assertStringStartsWith('https://imocena.com/carregado/', $lead->anuncio);
        $this->assertNull($lead->consultor);
        $meta = app(ClipStore::class)->find($lead->clip_id)->meta;
        $this->assertSame($descricao, $meta['listing_manual']['description']);
        $this->assertSame(350000, $meta['listing_manual']['price']);
        $this->assertCount(2, $meta['listing_manual']['photos']);
        $this->assertSame(['name' => 'Luis Fraga', 'phone' => '+351912000111', 'email' => 'lf@remax.pt'], $meta['listing_manual']['agent']);
        $this->assertSame('RE/MAX Gold', $meta['listing_manual']['agency']);
        $this->assertSame('4719', $meta['licence']);
        $this->assertArrayNotHasKey('fotos_extra', $meta);
        $this->assertArrayNotHasKey('listing_video', $meta);
        $disco->assertExists($meta['agent_photo']);
        $this->assertNotSame('clips/uploads/retrato.png', $meta['agent_photo']);   // a copy
        $this->assertSame(0, $user->fresh()->creditos());

        // Kept on the account: the next page starts from it, photo included.
        $perfil = $user->fresh()->agente;
        $this->assertSame('Luis Fraga', $perfil['nome']);
        $disco->assertExists($perfil['foto']);
        $this->actingAs($user->fresh())->get('/conta/foto-consultor')->assertOk();
    }

    public function test_the_agency_picks_the_theme(): void
    {
        $this->assertSame('remax', \App\Services\Contas\NovoVideo::portalDaAgencia('RE/MAX Gold'));
        $this->assertSame('remax', \App\Services\Contas\NovoVideo::portalDaAgencia('Remax Vantagem'));
        $this->assertSame('century21', \App\Services\Contas\NovoVideo::portalDaAgencia('Century 21 Lisboa'));
        $this->assertSame('kw', \App\Services\Contas\NovoVideo::portalDaAgencia('KW Prime'));
        $this->assertSame('era', \App\Services\Contas\NovoVideo::portalDaAgencia('ERA Cascais'));
        $this->assertNull(\App\Services\Contas\NovoVideo::portalDaAgencia('Imobiliária Primera'));
        $this->assertNull(\App\Services\Contas\NovoVideo::portalDaAgencia('Casas do Lis'));
    }

    public function test_the_import_uses_the_typed_listing_and_the_uploaded_photos(): void
    {
        Queue::fake();
        $disco = Storage::fake(config('contentmachine.clips.disk'));
        $disco->put('clips/uploads/f1.png', $this->png(0xFF0000));
        $disco->put('clips/uploads/f2.png', $this->png(0x0000FF));
        $disco->put('clips/uploads/eu.png', $this->png(0x00FFFF));
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'status' => 'importing', 'title' => 'x',
            'meta' => ['listing' => ['url' => 'https://imocena.com/carregado/abc'], 'licence' => '4719', 'agent_photo' => 'clips/uploads/eu.png',
                'listing_manual' => ['description' => "Moradia T3 em Lajeosa\nCom jardim.", 'price' => 350000, 'photos' => ['clips/uploads/f1.png', 'clips/uploads/f2.png'],
                    'agent' => ['name' => 'Luis Fraga', 'phone' => '+351912000111', 'email' => 'lf@remax.pt'], 'agency' => 'RE/MAX Gold', 'ami' => '4719']]]);

        $this->mock(ListingScraper::class)->shouldNotReceive('scrape');
        $this->mock(ListingScriptWriter::class)->shouldReceive('write')->withArgs(fn ($l) => $l['title'] === 'Moradia T3 em Lajeosa' && $l['price'] === 350000
            && $l['agency'] === 'RE/MAX Gold' && $l['agent']['name'] === 'Luis Fraga')->andReturn([
                'title' => 'T3', 'flags' => [], 'agent_gender' => 'm', 'voiceover' => 'Texto.', 'hook' => '', 'scenes' => [], 'message' => '',
            ]);
        $this->mock(ElevenLabsVoiceoverService::class)->shouldReceive('synthesizeWith')->once();
        $this->mock(AgencyLogoFinder::class)->shouldReceive('find')->andReturn(null);
        config(['contentmachine.real_estate.voices.m.takes' => 1]);

        app()->call([new ImportListingJob($clip->id, 'https://imocena.com/carregado/abc'), 'handle']);

        $clip = app(ClipStore::class)->find($clip->id);
        $nomes = collect($clip->images)->pluck('name')->all();
        $this->assertSame(['main.png', 'foto-2.png', 'Luis Fraga.png'], array_slice($nomes, 0, 3));
        $this->assertSame('Luis Fraga', $clip->meta['listing']['agent']);
        $this->assertSame('4719', $clip->meta['listing']['licence']);
        $this->assertSame('RE/MAX Gold', $clip->meta['listing']['agency']);
    }
}
