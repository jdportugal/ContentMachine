<?php

namespace Tests\Feature;

use App\Livewire\Campanhas;
use App\Models\User;
use App\Services\Marketing\Canal;
use App\Services\Marketing\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin: the campaign funnel, from Meta's impressions and clicks to visits, videos and purchases. */
class CampanhasTest extends TestCase
{
    use RefreshDatabase;

    private const ADS = '?utm_source=meta&utm_medium=paid&utm_campaign=Lancamento&utm_content=preco-10-euros&utm_term=111&fbclid=X1';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['contentmachine.meta_ads.conta' => 'act_3244', 'contentmachine.meta_ads.token' => 'tok-ads', 'contentmachine.meta_capi.version' => 'v23.0']);
    }

    private function metaResponde(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['campaign_name' => 'Lançamento', 'ad_id' => '111', 'ad_name' => 'preco-10-euros', 'impressions' => '4000', 'reach' => '3100', 'inline_link_clicks' => '80', 'spend' => '40.00'],
            ['campaign_name' => 'Lançamento', 'ad_id' => '222', 'ad_name' => 'sem-complicacoes', 'impressions' => '1000', 'reach' => '900', 'inline_link_clicks' => '20', 'spend' => '10.50'],
        ]])]);
    }

    private function lead(string $anuncio, ?array $origem, array $mais = []): int
    {
        return DB::table('leads')->insertGetId($mais + ['anuncio' => $anuncio, 'projeto' => 'default', 'consentimento_em' => now(),
            'origem' => $origem ? json_encode($origem) : null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_only_admins_see_the_page_and_it_is_in_the_menu(): void
    {
        Http::fake();
        $this->get('/campanhas')->assertRedirect('/login');
        $this->actingAs(User::factory()->cliente()->create())->get('/campanhas')->assertRedirect('/conta');
        $this->actingAs(User::factory()->create())->get('/campanhas')->assertOk()
            ->assertSee('Campanhas')->assertSee('FUNIL DOS ANÚNCIOS META')->assertSee('Funil · anúncios');
    }

    public function test_origins_are_sorted_into_channels(): void
    {
        $this->assertSame('meta-ads', Canal::de(['utm_source' => 'Meta', 'utm_medium' => 'paid']));
        $this->assertSame('meta-ads', Canal::de(['utm_source' => 'facebook', 'utm_medium' => 'cpc', 'fbclid' => 'x']));
        $this->assertSame('meta', Canal::de(['fbclid' => 'x']));                       // Facebook tags organic links too
        $this->assertSame('meta', Canal::de(['referer' => 'l.instagram.com']));
        $this->assertSame('google-ads', Canal::de(['gclid' => 'x']));
        $this->assertSame('google', Canal::de(['referer' => 'www.google.pt']));
        $this->assertSame('outro', Canal::de(['utm_source' => 'newsletter']));
        $this->assertSame('direto', Canal::de(null));
        $this->assertSame('interno', Canal::de('{"utm_source":"teste-interno"}'));
    }

    public function test_a_visit_is_counted_once_per_session_and_not_for_robots_or_the_operator(): void
    {
        $this->get('/'.self::ADS)->assertOk();
        $this->get('/termos')->assertOk();                      // same session: still one visit
        $visita = DB::table('visitas')->sole();
        $this->assertSame('meta-ads', $visita->canal);
        $this->assertSame('/', $visita->pagina);
        $this->assertSame('preco-10-euros', json_decode($visita->origem, true)['utm_content']);

        $this->flushSession();
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();
        $this->flushSession();
        $this->get('/nao-existe')->assertNotFound();
        $this->flushSession();
        Http::fake();
        $this->actingAs(User::factory()->create())->get('/campanhas')->assertOk();
        $this->assertSame(1, DB::table('visitas')->count());

        $this->flushSession();
        auth()->logout();
        $this->get('/')->assertOk();
        $this->assertSame('direto', DB::table('visitas')->orderByDesc('id')->value('canal'));
    }

    public function test_the_funnel_joins_meta_results_with_what_happened_on_the_site(): void
    {
        $this->metaResponde();
        $ad1 = ['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_content' => 'preco-10-euros', 'utm_term' => '111'];
        $ad2 = ['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_content' => 'Sem-Complicacoes'];   // by name, no id
        foreach ([$ad1, $ad1, $ad1, $ad2, null, ['referer' => 'www.google.pt'], ['utm_source' => 'teste-interno']] as $o) {
            DB::table('visitas')->insert(['canal' => Canal::de($o), 'origem' => $o ? json_encode($o) : null, 'created_at' => now()]);
        }
        $this->lead('https://www.remax.pt/a/1', $ad1, ['pronto_em' => now()]);
        $this->lead('https://www.remax.pt/a/2', $ad1);
        $this->lead('https://www.remax.pt/a/3', null, ['pronto_em' => now()]);
        $this->lead('https://www.remax.pt/a/4', ['utm_source' => 'teste-interno'], ['pronto_em' => now()]);   // ours: left out
        $this->lead('https://www.remax.pt/a/5', $ad1, ['pago_em' => now(), 'pronto_em' => now()]);          // a paid video from the start: not a free request
        $this->lead('https://www.remax.pt/a/6', $ad1, ['created_at' => now()->subDays(60)]);                 // outside 30 days
        DB::table('compras')->insert(['sessao' => 'cs_1', 'total' => 10000, 'videos' => 10, 'origem' => json_encode($ad1), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('compras')->insert(['sessao' => 'admin-1', 'total' => 0, 'videos' => 3, 'created_at' => now(), 'updated_at' => now()]);   // a gift is not a sale

        $f = app(Funil::class)->calcular(now()->subDays(29)->startOfDay(), now());

        $this->assertSame(['Impressões' => 5000, 'Cliques' => 100, 'Visitas ao site' => 4, 'Vídeos grátis pedidos' => 2, 'Vídeos prontos' => 1, 'Compras' => 1],
            array_column($f['passos'], 'n', 'nome'));
        $this->assertEqualsWithDelta(0.02, $f['passos'][1]['taxa'], 0.0001);    // 100 clicks / 5000 impressions
        $this->assertEqualsWithDelta(0.5, $f['passos'][3]['taxa'], 0.0001);     // 2 requests / 4 visits
        $this->assertSame(50.5, $f['gasto']);
        $this->assertEqualsWithDelta(0.505, $f['custos']['clique'], 0.0001);
        $this->assertEqualsWithDelta(25.25, $f['custos']['pedido'], 0.0001);
        $this->assertEqualsWithDelta(50.5, $f['custos']['compra'], 0.0001);
        $this->assertSame(10000, $f['receita']);
        $this->assertFalse($f['semEtiquetas']);

        $this->assertSame('preco-10-euros', $f['anuncios'][0]['nome']);         // most spend first
        $this->assertSame([3, 2, 1, 10000], [$f['anuncios'][0]['visitas'], $f['anuncios'][0]['pedidos'], $f['anuncios'][0]['compras'], $f['anuncios'][0]['receita']]);
        $this->assertSame([1, 0], [$f['anuncios'][1]['visitas'], $f['anuncios'][1]['pedidos']]);

        $this->assertSame(['meta-ads', 'google', 'direto'], array_keys($f['canais']));
        $this->assertSame(['visitas' => 1, 'pedidos' => 1, 'prontos' => 1, 'compras' => 0, 'receita' => 0], $f['canais']['direto']);
        $this->assertSame(6, $f['total']['visitas']);                           // the internal test is not counted

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v23.0/act_3244/insights') && $r['level'] === 'ad'
            && $r['access_token'] === 'tok-ads' && str_contains($r['time_range'], now()->toDateString()));
    }

    public function test_the_page_shows_the_funnel_the_ads_and_the_channels(): void
    {
        $this->metaResponde();
        $o = ['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_term' => '111'];
        DB::table('visitas')->insert(['canal' => 'meta-ads', 'origem' => json_encode($o), 'created_at' => now()]);
        $this->lead('https://www.remax.pt/a/1', $o);

        Livewire::actingAs(User::factory()->create())->test(Campanhas::class)
            ->assertSee('5 000')->assertSee('50,50 €')->assertSee('preco-10-euros')->assertSee('sem-complicacoes')
            ->assertSee('Anúncios Meta')->assertSee('2,0 %')
            ->assertDontSee('Falta ligar')
            ->set('periodo', 'tudo')->assertSee('5 000')
            ->call('atualizar')->assertSee('5 000')
            ->set('periodo', 'xpto')->assertSet('periodo', '30');
        Http::assertSent(fn ($r) => ($r['date_preset'] ?? null) === 'maximum');
        Http::assertSentCount(3);   // 30 days, everything, and the refresh; the rest came from the cache
    }

    public function test_without_meta_the_site_side_still_shows_and_it_says_what_is_missing(): void
    {
        config(['contentmachine.meta_ads.token' => '', 'contentmachine.meta_capi.token' => '']);
        Http::fake();
        DB::table('visitas')->insert(['canal' => 'direto', 'created_at' => now()]);
        Livewire::actingAs(User::factory()->create())->test(Campanhas::class)
            ->assertSee('Falta ligar a conta de anúncios da Meta')->assertSee('META_ADS_TOKEN')
            ->assertSee('Visitas ao site')->assertDontSee('Impressões', false)->assertSee('Direto');
        Http::assertNothingSent();

        // A token that cannot read ads: Meta's own message is shown, and nothing is cached.
        config(['contentmachine.meta_capi.token' => 'tok-capi']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#200) Ad account owner has NOT grant ads_management or ads_read permission']], 403)]);
        Livewire::actingAs(User::factory()->create())->test(Campanhas::class)
            ->assertSee('A Meta não devolveu os resultados')->assertSee('ads_read permission');
    }

    public function test_clicks_without_tagged_visits_ask_for_the_url_parameters(): void
    {
        $this->metaResponde();
        Livewire::actingAs(User::factory()->create())->test(Campanhas::class)
            ->assertSee('nenhuma visita chegou com as etiquetas')->assertSee('utm_term={{ad.id}}');
    }
}
