<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The protected progress report (/interno/*): token, the day's numbers, the runway. */
class RelatorioInternoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'tok-0123456789abcdef0123456789abcdef';

    private const ADS = ['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_campaign' => 'Lancamento', 'utm_content' => 'remax-preco-10-euros', 'utm_term' => '111'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        @unlink(storage_path('app/relatorio_token'));
        config([
            'contentmachine.relatorio.token' => self::TOKEN,
            'contentmachine.relatorio.inicio' => now('Europe/Lisbon')->subDays(4)->toDateString(),
            'contentmachine.relatorio.orcamento_diario' => 20.0,
            'contentmachine.relatorio.taxa_stripe' => 0.0,
            'contentmachine.relatorio.usd_eur' => 1.0,
            'contentmachine.meta_ads.conta' => 'act_3244', 'contentmachine.meta_ads.token' => 'tok-ads', 'contentmachine.meta_capi.version' => 'v23.0',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [
            ['campaign_name' => 'Lançamento', 'ad_id' => '111', 'ad_name' => 'remax-preco-10-euros', 'impressions' => '4000', 'reach' => '3100', 'inline_link_clicks' => '80', 'spend' => '40.00'],
        ]])]);
    }

    private function get_(string $url, ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token ? ['Authorization' => 'Bearer '.$token] : [])->getJson($url);
    }

    private function hoje(): void
    {
        $agora = now();
        DB::table('visitas')->insert([
            ['canal' => 'meta-ads', 'pagina' => '/video-gratis', 'origem' => json_encode(self::ADS), 'created_at' => $agora],
            ['canal' => 'meta-ads', 'pagina' => '/video-gratis', 'origem' => json_encode(self::ADS), 'created_at' => $agora],
            ['canal' => 'google', 'pagina' => '/', 'origem' => json_encode(['referer' => 'www.google.com']), 'created_at' => $agora],
            ['canal' => 'interno', 'pagina' => '/', 'origem' => json_encode(['utm_source' => 'teste-interno']), 'created_at' => $agora],
        ]);
        $lead = DB::table('leads')->insertGetId(['anuncio' => 'https://www.remax.pt/123', 'projeto' => 'default', 'consentimento_em' => $agora,
            'email' => 'agente@remax.pt', 'origem' => json_encode(self::ADS), 'pronto_em' => $agora, 'created_at' => $agora, 'updated_at' => $agora]);
        DB::table('compras')->insert(['sessao' => 'cs_1', 'email' => 'agente@remax.pt', 'nome' => 'Ana Agente', 'nif' => '123456789', 'total' => 10000,
            'videos' => 10, 'lead_id' => $lead, 'origem' => json_encode(self::ADS), 'created_at' => $agora, 'updated_at' => $agora,
            'dados' => json_encode(['payment_intent' => 'pi_1', 'total_details' => ['amount_tax' => 1870, 'amount_discount' => 0],
                'customer_details' => ['business_name' => 'Agente, Lda', 'address' => ['country' => 'PT']]])]);
        DB::table('landing_eventos')->insert(['tipo' => 'escolha', 'agencia' => 'remax', 'created_at' => $agora]);
        DB::table('custos')->insert(['projeto' => 'default', 'tipo' => 'clip', 'peca' => 'c1', 'servico' => 'elevenlabs', 'custo' => 0.5, 'created_at' => $agora]);
    }

    public function test_without_a_token_configured_the_endpoints_do_not_exist(): void
    {
        config(['contentmachine.relatorio.token' => '']);
        $this->get_('/interno/resumo')->assertNotFound();
        config(['contentmachine.relatorio.token' => 'curto']);
        $this->get_('/interno/resumo', 'curto')->assertNotFound();
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        $this->get_('/interno/resumo', null)->assertUnauthorized();
        $this->get_('/interno/resumo', 'errado')->assertUnauthorized();
        $this->get_('/interno/dias', 'errado')->assertUnauthorized();
    }

    public function test_the_token_can_live_in_a_file(): void
    {
        config(['contentmachine.relatorio.token' => '']);
        file_put_contents(storage_path('app/relatorio_token'), self::TOKEN."\n");
        try {
            $this->get_('/interno/resumo')->assertOk();
        } finally {
            @unlink(storage_path('app/relatorio_token'));
        }
    }

    public function test_the_day_in_numbers(): void
    {
        $this->hoje();
        $r = $this->get_('/interno/resumo')->assertOk()->json();

        $this->assertSame(3, $r['visitas']['total']);                 // our own test visit is left out
        $this->assertSame(['meta-ads' => 2, 'google' => 1], $r['visitas']['por_canal']);
        $this->assertSame(['remax-preco-10-euros' => 2], $r['visitas']['por_anuncio']);
        $this->assertSame(1, $r['pedidos']['total']);
        $this->assertSame(1, $r['pedidos']['com_email']);
        $this->assertSame('meta-ads', $r['pedidos']['lista'][0]['canal']);
        $this->assertSame('www.remax.pt', $r['pedidos']['lista'][0]['imovel']);
        $this->assertSame(1, $r['compras']['total']);
        $this->assertEquals(100, $r['compras']['receita']);
        $this->assertEquals(18.7, $r['compras']['iva']);
        $this->assertSame('Agente, Lda', $r['compras']['lista'][0]['empresa']);
        $this->assertSame('123456789', $r['compras']['lista'][0]['nif']);
        $this->assertSame(['escolha' => 1], $r['eventos']);
        $this->assertEquals(['elevenlabs' => 0.5], $r['custos_usd']);
        $this->assertEquals(40, $r['funil']['gasto']);
    }

    public function test_the_runway_counts_revenue_without_vat_against_ads_and_services(): void
    {
        $this->hoje();
        $f = $this->get_('/interno/resumo')->assertOk()->json('financas');

        $this->assertEquals(81.3, $f['receita_sem_iva']);      // 100 − 18.70 VAT
        $this->assertEquals(40, $f['anuncios']);
        $this->assertEquals(0.5, $f['servicos']);
        $this->assertEquals(40.8, $f['folga']);                 // 81.30 − 40 − 0.50
        $this->assertTrue($f['anuncios_ok']);
        // 0.50 of services over the 5 days since the start = 0.10 a day: 40.80 / 20.10
        $this->assertEquals(2.0, $f['dias_de_folga']);
    }

    public function test_dates_are_checked_and_days_come_newest_first(): void
    {
        $this->get_('/interno/resumo?de=ontem')->assertStatus(422);
        $this->get_('/interno/resumo?de=2026-11-20&ate=2026-11-19')->assertStatus(422);
        $this->get_('/interno/resumo?de=2026-11-19&ate=2026-11-20')->assertOk();

        $this->hoje();
        $dias = $this->get_('/interno/dias?n=3')->assertOk()->json('dias');
        $this->assertCount(3, $dias);
        $this->assertSame(2, $dias[0]['visitas']);   // today, the Meta ads channel
    }

    public function test_a_call_is_not_counted_as_a_visit(): void
    {
        $this->get_('/interno/resumo')->assertOk();
        $this->assertSame(0, DB::table('visitas')->count());
    }
}
