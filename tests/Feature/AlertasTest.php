<?php

namespace Tests\Feature;

use App\Livewire\Landing;
use App\Services\Alertas\PaginaDoConsultor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** New-listing alerts: agents who ticked the box are watched on their portal and emailed when they publish. */
class AlertasTest extends TestCase
{
    use RefreshDatabase;

    private const REMAX = 'https://remax.pt/pt/imoveis/venda-apartamento-t2-gondomar-fanzeres/125411383-12';

    private const PAGINA_REMAX = 'https://remax.pt/pt/agente/consultor/125411383';

    /** What each URL answers; a missing URL is a 500. */
    private array $paginas = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['contentmachine.alertas.pausa_ms' => 0, 'app.url' => 'https://imocena.com']);
        URL::forceRootUrl('https://imocena.com');
        Http::fake(fn ($request) => isset($this->paginas[$request->url()]) ? Http::response($this->paginas[$request->url()]) : Http::response('', 500));
    }

    private function lead(string $anuncio, ?string $email = 'carla@remax.pt', array $mais = []): int
    {
        return DB::table('leads')->insertGetId($mais + ['anuncio' => $anuncio, 'nome' => 'Carla Silva', 'projeto' => 'default', 'consentimento_em' => now(),
            'email' => $email, 'marketing_em' => $email ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function remax(array $numeros, ?string $email = null): string
    {
        $resultados = array_map(fn ($n) => ['listingTitle' => '125411383-'.$n, 'descriptionTags' => 'venda-apartamento-t2-gondomar-fanzeres',
            'regionName2' => 'Gondomar', 'regionName3' => 'Fânzeres'], $numeros);

        return '<html><script id="__NEXT_DATA__" type="application/json">'.json_encode(['props' => ['pageProps' => [
            'agentEncoded' => base64_encode(json_encode(['name' => 'Carla Silva', 'email' => $email, 'email2' => null])),
            'relatedListingsEncoded' => base64_encode(json_encode(['activeListings' => ['results' => $resultados]]))]]]).'</script></html>';
    }

    private function enviados(): array
    {
        return array_map(fn ($m) => [
            'para' => $m->getEnvelope()->getRecipients()[0]->getAddress(),
            'assunto' => $m->getOriginalMessage()->getSubject(),
            'html' => $m->getOriginalMessage()->getHtmlBody(),
            'cancelar' => $m->getOriginalMessage()->getHeaders()->get('List-Unsubscribe')?->getBodyAsString(),
        ], iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    public function test_the_first_reading_only_records_and_a_listing_published_later_is_emailed_once(): void
    {
        $lead = $this->lead(self::REMAX);
        $this->paginas[self::PAGINA_REMAX] = $this->remax([12, 11]);

        $this->artisan('alertas:imoveis')->expectsOutputToContain('Consultores novos: 1')->assertSuccessful();
        $c = DB::table('consultores_vigiados')->sole();
        $this->assertSame(['ativo', 'remax', 'remax:125411383', self::PAGINA_REMAX, 'carla@remax.pt'], [$c->estado, $c->portal, $c->chave, $c->pagina, $c->email]);
        $this->assertSame(['125411383-12', '125411383-11'], json_decode($c->conhecidos, true));
        $this->assertSame([], $this->enviados());   // what was already there is not news

        $this->paginas[self::PAGINA_REMAX] = $this->remax([13, 12, 11]);
        $this->artisan('alertas:imoveis')->expectsOutputToContain('avisos enviados: 1')->assertSuccessful();

        $mail = collect($this->enviados())->sole();
        $this->assertSame('carla@remax.pt', $mail['para']);
        $this->assertSame('Publicou um imóvel novo. Quer o vídeo dele?', $mail['assunto']);
        $this->assertStringContainsString('Olá Carla,', $mail['html']);
        $this->assertStringContainsString('Apartamento T2 em Fânzeres', $mail['html']);
        $this->assertStringContainsString('anuncio='.urlencode('https://remax.pt/pt/imoveis/venda-apartamento-t2-gondomar-fanzeres/125411383-13'), $mail['html']);
        $this->assertStringContainsString('utm_campaign=imovel-novo', $mail['html']);
        $this->assertStringContainsString('/emails/cancelar/'.$lead.'?signature=', $mail['cancelar']);
        $this->assertStringContainsString('Deixar de receber', $mail['html']);
        $this->assertStringContainsString('a partir de 8,75 €', $mail['html']);   // it suggests a video, it does not promise a free one
        $this->assertSame('125411383-13', DB::table('avisos_imoveis')->sole()->anuncio_id);

        // One email a day at most: the next listing waits, then goes out.
        $this->paginas[self::PAGINA_REMAX] = $this->remax([14, 13, 12, 11]);
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertCount(1, $this->enviados());
        $this->travel(21)->hours();
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertCount(2, $this->enviados());
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->travel(21)->hours();
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertCount(2, $this->enviados());   // nothing new: nothing sent
    }

    public function test_only_people_who_ticked_the_box_are_watched_and_unsubscribing_stops_it(): void
    {
        $this->lead('https://remax.pt/pt/imoveis/venda-moradia-t3-porto/999990001-3', null);   // no box ticked
        $a = $this->lead(self::REMAX);
        $b = $this->lead('https://remax.pt/pt/imoveis/venda-loja-porto/125411383-9');           // same agent, same email
        $this->paginas[self::PAGINA_REMAX] = $this->remax([12, 9]);
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertSame([$a => 'ativo', $b => 'duplicado'], DB::table('consultores_vigiados')->pluck('estado', 'lead_id')->all());

        // The link in the email: GET asks, POST cancels every alert of that email.
        $url = URL::signedRoute('lead.emails.cancelar', ['lead' => $a]);
        $this->get($url)->assertOk();
        $this->assertNull(DB::table('leads')->where('id', $a)->value('marketing_cancelado_em'));
        $this->post($url)->assertOk();
        $this->assertSame(2, DB::table('leads')->whereNotNull('marketing_cancelado_em')->count());

        $this->paginas[self::PAGINA_REMAX] = $this->remax([13, 12, 9]);
        $this->artisan('alertas:imoveis')->expectsOutputToContain('páginas lidas: 0')->assertSuccessful();
        $this->assertSame([], $this->enviados());
    }

    public function test_a_listing_that_already_has_its_video_or_a_page_that_changed_is_not_emailed(): void
    {
        $this->lead(self::REMAX);
        $this->paginas[self::PAGINA_REMAX] = $this->remax([12]);
        $this->artisan('alertas:imoveis')->assertSuccessful();

        // They made the video of the new one themselves before we looked.
        $this->lead('https://remax.pt/pt/imoveis/venda-apartamento-t2-gondomar-fanzeres/125411383-13', null);
        $this->paginas[self::PAGINA_REMAX] = $this->remax([13, 12]);
        $this->artisan('alertas:imoveis')->assertSuccessful();

        // Six "new" at once is the portal changing, not the agent publishing.
        $this->paginas[self::PAGINA_REMAX] = $this->remax([20, 19, 18, 17, 16, 15, 13, 12]);
        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertSame([], $this->enviados());
        $this->assertCount(8, json_decode(DB::table('consultores_vigiados')->value('conhecidos'), true));

        // The portal is down: nothing is forgotten, the failure is counted.
        unset($this->paginas[self::PAGINA_REMAX]);
        $this->artisan('alertas:imoveis')->expectsOutputToContain('falhas: 1')->assertSuccessful();
        $c = DB::table('consultores_vigiados')->sole();
        $this->assertSame(1, (int) $c->falhas);
        $this->assertCount(8, json_decode($c->conhecidos, true));
    }

    public function test_iad_and_exp_agents_are_found_from_their_listing(): void
    {
        // iad: the agent's user name is in the listing's Nuxt payload (devalue: integers point at other entries).
        $nuxt = [['data' => 1], ['vue-query-state' => 2], ['queries' => 3], [4], ['queryKey' => 5, 'state' => 7], [6], 'properties', ['data' => 8], ['agent' => 9], ['userName' => 10], 'elisabete.carvalho'];
        $this->paginas['https://www.iadportugal.pt/anuncio/casa-venda-porches/r1076604'] = '<script type="application/json" id="__NUXT_DATA__">'.json_encode($nuxt).'</script>';
        $this->paginas['https://www.iadportugal.pt/consultor-imobiliario/elisabete.carvalho'] = '<a href="/anuncio/casa-venda-porches/r1076604">x</a><a href="/anuncio/casa-venda-porches/r1076604?foto=2">x</a>';
        $this->lead('https://www.iadportugal.pt/anuncio/casa-venda-porches/r1076604', 'elisabete@iad.pt');

        // eXp: the agent's own site, with its listings in the Next flight payload (no links).
        $li = fn (string $id, string $titulo) => '["$","li","'.$id.'",{"children":["$","article",null,{"children":[["$","h3",null,{"children":'.json_encode($titulo).'}],["$","p",null,{"children":"Penafiel"}]]}]}]';
        $exp = fn (array $itens) => '<script>self.__next_f.push([1,'.json_encode('4:[["$","ul",null,{"children":['.implode(',', $itens).']}]]').'])</script>';
        $um = $li('00bae96e-d1b3-4cb7-8cd2-30c3cd494ade', 'Moradia T6 em Guilhufe');
        $this->paginas['https://brunocouto.expportugal.com/'] = $exp([$um]);
        $this->lead('https://brunocouto.expportugal.com/imoveis/pacos-de-ferreira/apartamento/freamunde-8', 'bruno@exp.pt');
        $this->lead('https://expportugal.com/imoveis/lisboa/apartamento/x-1', 'outra@exp.pt');   // the main site: no agent page to watch

        $this->artisan('alertas:imoveis')->assertSuccessful();
        $this->assertSame(['elisabete@iad.pt' => 'iad:elisabete.carvalho', 'bruno@exp.pt' => 'exp:brunocouto', 'outra@exp.pt' => null],
            DB::table('consultores_vigiados')->pluck('chave', 'email')->all());
        $this->assertSame('sem-pagina', DB::table('consultores_vigiados')->where('email', 'outra@exp.pt')->value('estado'));

        $this->paginas['https://www.iadportugal.pt/consultor-imobiliario/elisabete.carvalho'] .= '<a href="/anuncio/terreno-venda-olhao-360m2/r1070740">y</a>';
        $this->paginas['https://brunocouto.expportugal.com/'] = $exp([$li('01044832-2313-4c84-b550-862996c630d6', 'Apartamento T2 "de luxo"'), $um]);
        $this->artisan('alertas:imoveis')->expectsOutputToContain('avisos enviados: 2')->assertSuccessful();

        $mails = collect($this->enviados())->keyBy('para');
        $this->assertStringContainsString('Terreno venda olhao 360m2', $mails['elisabete@iad.pt']['html']);
        $this->assertStringContainsString('anuncio='.urlencode('https://www.iadportugal.pt/anuncio/terreno-venda-olhao-360m2/r1070740'), $mails['elisabete@iad.pt']['html']);
        $this->assertStringContainsString('Apartamento T2 &quot;de luxo&quot;', $mails['bruno@exp.pt']['html']);
        $this->assertStringNotContainsString('anuncio=', $mails['bruno@exp.pt']['html']);   // eXp gives no link: they paste it
    }

    public function test_the_agent_page_parsers_ignore_what_is_not_a_listing(): void
    {
        $this->assertSame([], PaginaDoConsultor::remax('<html>nada</html>'));
        $this->assertSame([], PaginaDoConsultor::iad('<a href="/consultor-imobiliario/x">x</a>'));
        $this->assertSame([], PaginaDoConsultor::exp('<html></html>'));
        $this->assertNull(app(PaginaDoConsultor::class)->de('https://remax.pt/pt/imoveis/sem-referencia'));
    }

    public function test_ticking_the_box_takes_the_email_from_the_listing_and_only_asks_when_there_is_none(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->paginas[self::PAGINA_REMAX] = $this->remax([12], 'Carla.Silva@remax.pt');

        Livewire::test(Landing::class)->set(['anuncio' => self::REMAX, 'marketing' => true])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        $this->assertSame(['carla.silva@remax.pt', 'remax:125411383'], [$lead->email, $lead->consultor]);
        $this->assertNotNull($lead->marketing_em);

        // Without the box nothing is read from the agent's page and no email is kept.
        session()->forget('lead_id');
        Livewire::test(Landing::class)->set(['anuncio' => 'https://brunocouto.expportugal.com/imoveis/penafiel/moradia/penafiel-3'])->call('pedir')->assertHasNoErrors();
        $this->assertNull(DB::table('leads')->where('consultor', 'exp:brunocouto')->sole()->email);

        // iad does not publish the email: the form asks for it.
        session()->forget('lead_id');
        $nuxt = [['data' => 1], ['vue-query-state' => 2], ['queries' => 3], [4], ['queryKey' => 5, 'state' => 7], [6], 'properties', ['data' => 8], ['agent' => 9], ['userName' => 10], 'ana.sousa'];
        $this->paginas['https://www.iadportugal.pt/anuncio/t2-faro/r555001'] = '<script type="application/json" id="__NUXT_DATA__">'.json_encode($nuxt).'</script>';
        Livewire::test(Landing::class)->set(['anuncio' => 'https://www.iadportugal.pt/anuncio/t2-faro/r555001', 'marketing' => true])->call('pedir')
            ->assertHasErrors(['email' => 'required'])->assertSee('Não encontrámos o seu email no anúncio')
            ->set('email', 'ana@sousa.pt')->call('pedir')->assertHasNoErrors();
        $this->assertSame('ana@sousa.pt', DB::table('leads')->where('consultor', 'iad:ana.sousa')->sole()->email);

        $this->assertSame('bruno@expportugal.com', PaginaDoConsultor::emailExp('<script>self.__next_f.push([1,'.json_encode('5:{"agent":{"name":"Bruno","email":"x@expportugal.com"},"contactFormEmail":"bruno@expportugal.com"}').'])</script>'));
        $this->assertNull(PaginaDoConsultor::emailRemax('<html></html>'));
    }

    public function test_the_free_video_is_one_per_agent_and_the_next_listing_is_offered_a_paid_one(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Livewire::test(Landing::class)->set('anuncio', self::REMAX)->call('pedir')->assertHasNoErrors();
        $this->assertSame(1, DB::table('leads')->count());

        // The same agent's next listing, typed in the form: no second free video.
        session()->forget('lead_id');
        $outro = 'https://remax.pt/pt/imoveis/venda-moradia-t3-gondomar/125411383-13';
        Livewire::test(Landing::class)->set('anuncio', $outro)->call('pedir')->assertHasNoErrors()
            ->assertSet('jaTeve', true)->assertSee('JÁ FEZ O SEU VÍDEO GRÁTIS')->assertSee('/conta/novo?anuncio='.urlencode($outro), false)->assertDontSee('Criar vídeo grátis')
            ->call('outro')->assertSet('jaTeve', false)->assertSee('Criar vídeo grátis');
        $this->assertSame(1, DB::table('leads')->count());
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Clips\ImportListingJob::class, 1);

        // The link in the alert email opens the same suggestion, not the free form.
        Livewire::withQueryParams(['anuncio' => $outro])->test(Landing::class)->assertSet('anuncio', $outro)->assertSet('jaTeve', true)->assertSee('O vídeo grátis é um por consultor');
        // An agent we do not know still gets the form, with the link filled in; a link from elsewhere is ignored.
        $novo = 'https://remax.pt/pt/imoveis/venda-t1-porto/999990001-2';
        Livewire::withQueryParams(['anuncio' => $novo])->test(Landing::class)->assertSet('anuncio', $novo)->assertSet('jaTeve', false)->assertSee('Criar vídeo grátis');
        Livewire::withQueryParams(['anuncio' => 'https://evil.example/x'])->test(Landing::class)->assertSet('anuncio', '');
    }
}
