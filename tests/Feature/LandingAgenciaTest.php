<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Landing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** The landing page asks the agency first, then is that agency's page: its name, its template's video, the form. */
class LandingAgenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_asks_the_agency_first_and_then_shows_that_agencys_page(): void
    {
        Livewire::test(Landing::class)
            ->assertSee('Em que agência trabalha?')->assertSee('RE/MAX')->assertSee('KW Portugal')->assertSee('Century 21')->assertSee('Outra')
            ->assertDontSee('Criar vídeo grátis')
            ->call('escolher', 'kw')
            ->assertSet('agencia', 'kw')->assertDispatched('agencia-escolhida', agencia: 'kw')
            ->assertSee('O vídeo do seu imóvel KW Portugal')->assertSee('kwportugal.pt')->assertSee('Criar vídeo grátis')
            ->assertSee('Vídeo com a imagem da KW')->assertSee('Não é da KW? Trocar de agência')
            ->call('trocar')->assertSet('agencia', '')->assertSee('Em que agência trabalha?');

        $this->assertSame(['escolha', 'kw'], array_values((array) DB::table('landing_eventos')->select('tipo', 'agencia')->sole()));
        // An unknown key changes nothing.
        Livewire::test(Landing::class)->call('escolher', 'idealista')->assertSet('agencia', '');
    }

    public function test_the_answer_is_remembered_and_can_come_in_the_link(): void
    {
        Livewire::test(Landing::class)->call('escolher', 'iad');
        $this->assertSame('iad', session('agencia'));
        $this->get('/video-gratis')->assertOk()->assertSee('O vídeo do seu imóvel iad Portugal');

        session()->forget('agencia');
        $this->get('/video-gratis?agencia=exp')->assertOk()->assertSee('O vídeo do seu imóvel eXp Portugal');
        $this->get('/video-gratis?agencia=nope')->assertOk()->assertSee('Em que agência trabalha?');
    }

    public function test_century21_agents_get_the_form_and_their_link_is_read(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->call('escolher', 'century21')
            ->assertSee('O vídeo do seu imóvel Century 21')->assertSee('century21.pt')->assertSee('Criar vídeo grátis')
            ->set('anuncio', 'https://century21.pt/comprar/C0242-02837')->call('pedir')->assertHasNoErrors();
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === 'https://century21.pt/comprar/C0242-02837');
        $this->assertSame('century21', DB::table('leads')->sole()->agencia);
    }

    public function test_predimed_agents_get_the_form_and_their_link_is_read(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->assertSee('Predimed')->call('escolher', 'predimed')
            ->assertSee('O vídeo do seu imóvel Predimed')->assertSee('predimed.pt')->assertSee('Criar vídeo grátis')
            ->set('anuncio', 'https://predimed.pt/Imovel/wgndg2')->call('pedir')->assertHasNoErrors();
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === 'https://predimed.pt/Imovel/wgndg2');
        $this->assertSame('predimed', DB::table('leads')->sole()->agencia);
    }

    public function test_more_agencies_are_on_a_second_screen_and_the_home_page_always_asks_again(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->assertSee('Mais agências')->assertSee('Bússola Haus')->assertSee('My Best Houses')->assertSee('Homewize')->assertSee('Lisrenda')
            ->assertDontSee('abcdefgh')
            ->call('escolher', 'bussolahaus')->assertSee('O vídeo do seu imóvel Bússola Haus')->assertSee('Criar vídeo grátis')
            ->set('anuncio', 'https://bussolatchaus.com/property/apartamento-t2-a-venda-na-rua-dos-quarteis-58/')->call('pedir')->assertHasNoErrors();
        $this->assertSame('bussolahaus', DB::table('leads')->sole()->agencia);
        session()->forget('lead_id');
        Livewire::test(Landing::class)->call('escolher', 'homewize')->assertSee('Ainda não lemos anúncios da Homewize.');

        // The home page's "vídeo grátis" links ask again, even after an agency was picked and a video asked for.
        $this->get('/')->assertSee('/video-gratis?escolher=1', false);
        $this->get('/video-gratis?escolher=1')->assertOk()->assertSee('Em que agência trabalha?');
        $this->assertNull(session('agencia'));
        $this->assertNull(session('lead_id'));
    }

    public function test_an_agency_we_do_not_read_yet_leaves_an_email_to_be_told(): void
    {
        Livewire::test(Landing::class)->call('escolher', 'era')
            ->assertSee('Ainda não lemos anúncios da ERA.')->assertSee('Avisem-me quando estiver pronto')->assertDontSee('Criar vídeo grátis')
            ->call('lembrar')->assertHasErrors(['contacto' => 'required'])
            ->set('contacto', 'nao-e-email')->call('lembrar')->assertHasErrors(['contacto' => 'email'])
            ->set('contacto', 'ana@example.com')->call('lembrar')->assertHasNoErrors()
            ->assertSet('enviado', 'aviso')->assertSee('Fica combinado.');

        $e = DB::table('landing_eventos')->where('tipo', 'aviso-agencia')->sole();
        $this->assertSame(['era', 'ana@example.com'], [$e->agencia, $e->email]);
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());   // nothing to send yet

        // "Outra": the agency's name, as typed.
        Livewire::test(Landing::class)->call('escolher', 'outra')->assertSee('Diga-nos qual é a sua agência.')
            ->set(['contacto' => 'rui@example.com', 'nomeAgencia' => 'Decisões e Soluções'])->call('lembrar')->assertHasNoErrors();
        $this->assertSame('Decisões e Soluções', DB::table('landing_eventos')->where('agencia', 'outra')->where('tipo', 'aviso-agencia')->value('nota'));
    }

    public function test_someone_without_the_link_at_hand_gets_the_page_by_email_once(): void
    {
        Livewire::test(Landing::class)->call('escolher', 'remax')
            ->assertSee('Não tem o link do anúncio à mão?')
            ->set('contacto', 'ana@example.com')->call('lembrar')->assertHasNoErrors()
            ->assertSet('enviado', 'lembrete')->assertSee('Enviámos-lhe o link.');

        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $email = $enviados[0]->getOriginalMessage();
        $this->assertSame('ana@example.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('/video-gratis?agencia=remax&amp;utm_source=imocena&amp;utm_medium=email&amp;utm_campaign=lembrete#pedido', $email->getHtmlBody());
        $this->assertStringContainsString('RE/MAX', $email->getHtmlBody());
        $this->assertSame('ana@example.com', DB::table('landing_eventos')->where('tipo', 'lembrete')->value('email'));

        // A bot filling the hidden field is told it worked; nothing is kept or sent.
        Livewire::test(Landing::class)->call('escolher', 'remax')->set(['site' => 'x', 'contacto' => 'bot@example.com'])->call('lembrar')->assertSet('enviado', 'lembrete');
        $this->assertSame(1, DB::table('landing_eventos')->where('tipo', 'lembrete')->count());
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_a_link_we_cannot_read_is_kept_and_the_lead_carries_its_agency(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->call('escolher', 'remax')
            ->set('anuncio', 'https://www.era.pt/imovel/apartamento-t3-lisboa-108260389')->call('pedir')->assertHasErrors('anuncio');
        $e = DB::table('landing_eventos')->where('tipo', 'link-recusado')->sole();
        $this->assertSame(['remax', 'https://www.era.pt/imovel/apartamento-t3-lisboa-108260389', null], [$e->agencia, $e->anuncio, $e->email]);
        Queue::assertNothingPushed();

        // A link pasted without answering the question answers it.
        session()->forget('agencia');
        Livewire::test(Landing::class)->set('anuncio', 'https://www.iadportugal.pt/anuncio/t2-faro/r555001')->call('pedir')->assertHasNoErrors()->assertSet('agencia', 'iad');
        Queue::assertPushed(ImportListingJob::class, 1);
        $this->assertSame('iad', DB::table('leads')->sole()->agencia);
    }

    public function test_the_video_is_the_agencys_own_when_one_was_rendered(): void
    {
        // Every agency whose listings we read has a template of its own, shipped with the image.
        foreach (array_keys(array_filter(Landing::AGENCIAS, fn ($a) => $a['lemos'])) as $agencia) {
            $this->assertFileExists(base_path("vault/themes/{$agencia}-premium/theme.json"));
        }
        $this->assertSame(['url' => null, 'proprio' => false], Landing::demoReel('remax', 'sem-esta-pasta'));

        $pasta = public_path('media/demo/teste-'.uniqid());
        mkdir($pasta, 0775, true);
        try {
            $slug = basename($pasta);
            file_put_contents($pasta.'/reel.mp4', 'mp4');
            $this->assertSame(['url' => asset("media/demo/{$slug}/reel.mp4"), 'proprio' => false], Landing::demoReel('remax', $slug));
            file_put_contents($pasta.'/reel-remax.mp4', 'mp4');
            $this->assertSame(['url' => asset("media/demo/{$slug}/reel-remax.mp4"), 'proprio' => true], Landing::demoReel('remax', $slug));
            $this->assertSame(['url' => asset("media/demo/{$slug}/reel.mp4"), 'proprio' => false], Landing::demoReel('', $slug));
        } finally {
            array_map('unlink', glob($pasta.'/*'));
            rmdir($pasta);
        }
    }
}
