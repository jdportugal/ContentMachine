<?php

namespace Tests\Feature;

use App\Livewire\Clientes;
use App\Livewire\Landing;
use App\Models\User;
use App\Services\Marketing\Origem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Measurement and attribution: cookie consent, first-touch origin, Meta Conversions API, privacy page. */
class MedicaoTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/moradia-t3/424242';

    protected function setUp(): void
    {
        parent::setUp();
        config(['contentmachine.meta_pixel' => '1127337756822112', 'contentmachine.ga4' => 'G-TESTE123', 'contentmachine.meta_capi.token' => 'tok', 'contentmachine.meta_capi.version' => 'v23.0']);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        Queue::fake();
    }

    public function test_tags_wait_for_consent_and_the_privacy_page_explains_them(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="cookies"', false)->assertSee('Aceitar')->assertSee('Rejeitar')
            ->assertSee("includes('imocena_cookies=sim')) imocenaMedicao.carregar()", false)   // loaded only with a yes
            ->assertSee('G-TESTE123')->assertSee('/privacidade', false)->assertSee('Preferências de cookies');
        $this->get('/privacidade')->assertOk()->assertSee('Política de privacidade')->assertSee('_fbp')->assertSee('cnpd.pt');

        config(['contentmachine.meta_pixel' => '', 'contentmachine.ga4' => '']);
        $this->get('/')->assertOk()->assertDontSee('id="cookies"', false);   // nothing to ask about
    }

    public function test_the_first_campaign_a_visitor_came_from_is_kept_on_their_lead(): void
    {
        $this->get('/?utm_source=facebook&utm_medium=paid&utm_campaign=lancamento&utm_content=anuncio-a&fbclid=ABC123')->assertOk();
        $this->get('/?utm_source=google&utm_campaign=outra')->assertOk();   // first touch wins
        $origem = session('origem');
        $this->assertSame('facebook', $origem['utm_source']);
        $this->assertStringStartsWith('fb.1.', $origem['fbc']);
        $this->assertStringEndsWith('.ABC123', $origem['fbc']);

        Livewire::test(Landing::class)->set(['anuncio' => self::ANUNCIO, 'consentimento' => true])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        $this->assertSame('lancamento', json_decode($lead->origem, true)['utm_campaign']);
        $this->assertSame('facebook · paid · lancamento · anuncio-a', Origem::resumo($lead->origem));
        $this->assertSame('google (anúncio)', Origem::resumo(['gclid' => 'x']));
        $this->assertSame('direto', Origem::resumo(null));
    }

    public function test_a_referring_site_is_an_origin_and_our_own_pages_are_not(): void
    {
        config(['app.url' => 'https://imocena.com']);
        $this->get('/', ['referer' => 'https://imocena.com/precos'])->assertOk();
        $this->assertNull(session('origem'));
        $this->get('/', ['referer' => 'https://l.instagram.com/?u=x'])->assertOk();
        $this->assertSame('l.instagram.com', session('origem')['referer']);
    }

    public function test_the_lead_goes_to_meta_from_the_server_only_with_consent(): void
    {
        Livewire::test(Landing::class)->set(['anuncio' => self::ANUNCIO, 'consentimento' => true])->call('pedir')->assertHasNoErrors();
        Http::assertNothingSent();

        Livewire::withCookies(['imocena_cookies' => 'sim', '_fbp' => 'fb.1.1.2'])->test(Landing::class)
            ->set(['anuncio' => self::ANUNCIO.'9', 'consentimento' => true, 'marketing' => true, 'email' => 'Ana@Exemplo.pt'])->call('pedir')->assertHasNoErrors();
        $id = DB::table('leads')->max('id');
        Http::assertSent(function (Request $r) use ($id) {
            $e = $r['data'][0];

            return str_contains($r->url(), 'graph.facebook.com/v23.0/1127337756822112/events')
                && $e['event_name'] === 'Lead' && $e['event_id'] === 'lead-'.$id && $e['action_source'] === 'website'
                && $e['user_data']['em'] === [hash('sha256', 'ana@exemplo.pt')] && $e['user_data']['fbp'] === 'fb.1.1.2'
                && $r['access_token'] === 'tok';
        });
    }

    public function test_the_purchase_is_reported_once_with_its_value_and_origin(): void
    {
        $user = User::factory()->cliente()->create(['email' => 'joana@agencia.pt']);
        $lead = DB::table('leads')->insertGetId(['anuncio' => self::ANUNCIO, 'projeto' => 'default', 'user_id' => $user->id, 'consentimento_em' => now(),
            'origem' => json_encode(['utm_source' => 'facebook', 'utm_campaign' => 'lancamento']), 'created_at' => now(), 'updated_at' => now()]);
        $compra = DB::table('compras')->insertGetId(['sessao' => 'cs_live_1', 'user_id' => $user->id, 'lead_id' => $lead, 'email' => 'joana@agencia.pt',
            'total' => 10000, 'moeda' => 'eur', 'videos' => 10, 'login_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->withUnencryptedCookie('imocena_cookies', 'sim')->get('/obrigado?session_id=cs_live_1')->assertOk()
            ->assertSee('imocenaMedicao.compra', false)->assertSee('compra-'.$compra);
        Http::assertSent(fn (Request $r) => $r['data'][0]['event_name'] === 'Purchase' && $r['data'][0]['event_id'] === 'compra-'.$compra
            && $r['data'][0]['custom_data']['value'] == 100 && $r['data'][0]['custom_data']['currency'] === 'EUR');
        $this->assertSame('lancamento', json_decode(DB::table('compras')->value('origem'), true)['utm_campaign']);   // from the free video
        $this->assertNotNull(DB::table('compras')->value('medido_em'));

        // A reload reports nothing again.
        $this->withUnencryptedCookie('imocena_cookies', 'sim')->get('/obrigado?session_id=cs_live_1')->assertOk()->assertDontSee('imocenaMedicao.compra', false);
        Http::assertSentCount(1);

        // The admin sees where the customer came from.
        Livewire::actingAs(User::factory()->create())->test(Clientes::class)->assertSee('veio de facebook · lancamento');
    }
}
