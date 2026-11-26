<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Landing;
use App\Services\RealEstate\RemaxConsultores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** RE/MAX, iad and Century 21 agents can find their listing by their name instead of pasting the link. */
class ProcurarPeloNomeTest extends TestCase
{
    use RefreshDatabase;

    private function remax(): void
    {
        Cache::flush();
        $lista = [
            ['listingTitle' => '126941022-22', 'descriptionTags' => 'venda-moradia-t3-angra-do-heroismo-se', 'regionName3' => 'Sé', 'listingPictureUrl' => 'listings/1/a.jpg', 'listingPrice' => 250000],
            ['listingTitle' => '126941022-21', 'descriptionTags' => 'venda-apartamento-t2-praia-da-vitoria', 'regionName2' => 'Praia da Vitória', 'listingPrice' => 0],
        ];
        Http::fake([
            'remax.pt/api/Reference/UserAutocompleteSearch*' => Http::response(['results' => [
                ['name' => 'Rita Garcia', 'officeName' => 'RE/MAX Urban', 'agentID' => '124121001', 'userPictureURL' => 'users/r.jpg'],
                ['name' => 'Clélio Garcia', 'officeName' => 'RE/MAX Azor Estate', 'agentID' => '126941022', 'userPictureURL' => 'users/c.jpg'],
                ['name' => 'Nuno Garcia', 'officeName' => 'RE/MAX Yes', 'agentID' => '123441125'],
            ]]),
            'remax.pt/pt/agente/consultor/126941022' => Http::response('<html><script id="__NEXT_DATA__" type="application/json">'.json_encode(['props' => ['pageProps' => [
                'relatedListingsEncoded' => base64_encode(json_encode(['activeListings' => ['results' => $lista]]))]]]).'</script></html>'),
        ]);
    }

    public function test_matches_hold_every_word_typed_and_accents_do_not_matter(): void
    {
        $this->remax();
        $r = app(RemaxConsultores::class)->procurar('clelio garcia');
        $this->assertSame([['id' => '126941022', 'nome' => 'Clélio Garcia', 'agencia' => 'RE/MAX Azor Estate']], $r);
        $this->assertSame([], app(RemaxConsultores::class)->procurar('cl'));
    }

    public function test_an_agent_finds_their_listing_by_name_and_asks_for_its_video(): void
    {
        $this->remax();
        Queue::fake();
        Livewire::test(Landing::class)->call('escolher', 'remax')
            ->assertSee('Procurar pelo meu nome')
            ->call('mudarModo', 'nome')->assertSet('modo', 'nome')
            ->set('nomeConsultor', 'Clélio Garcia')->call('procurarConsultor')
            ->assertSee('Clélio Garcia')->assertSee('RE/MAX Azor Estate')->assertDontSee('Rita Garcia')
            ->call('escolherConsultor', '126941022')
            ->assertSee('Moradia T3 em Sé')->assertSee('250 000 €')->assertSee('Apartamento T2 em Praia da Vitória')
            ->call('escolherImovel', '126941022-22')
            // One tap on the listing asks for its video.
            ->assertHasNoErrors()->assertSet('anuncio', 'https://remax.pt/pt/imoveis/venda-moradia-t3-angra-do-heroismo-se/126941022-22')
            ->assertSee('Estamos a fazer o seu vídeo');

        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === 'https://remax.pt/pt/imoveis/venda-moradia-t3-angra-do-heroismo-se/126941022-22');
        $this->assertSame(['procura-nome', 'escolha-consultor', 'escolha-imovel'], DB::table('landing_eventos')->where('tipo', '!=', 'escolha')->orderBy('id')->pluck('tipo')->all());
        // Only ids from the search can be picked.
        Livewire::test(Landing::class)->call('escolher', 'remax')->call('escolherConsultor', '999999999')->assertSet('consultorEscolhido', '');
    }

    public function test_other_agencies_only_paste_the_link_and_a_failed_search_says_so(): void
    {
        Livewire::test(Landing::class)->call('escolher', 'kw')->assertDontSee('Procurar pelo meu nome')->call('mudarModo', 'nome')->assertSet('modo', 'link');

        Cache::flush();
        Http::fake(['remax.pt/*' => Http::response('', 503)]);
        Livewire::test(Landing::class)->call('escolher', 'remax')->call('mudarModo', 'nome')
            ->set('nomeConsultor', 'Ana Silva')->call('procurarConsultor')
            ->assertSet('avisoProcura', 'erro')->assertSee('Não conseguimos procurar agora');
    }

    public function test_an_iad_agent_finds_their_listing_by_name_even_typing_a_middle_name(): void
    {
        Cache::flush();
        Queue::fake();
        Http::fake([
            // iad matches the whole name: "Sandra Marisa Silva" finds nobody, so it is asked again by the last word
            'www.iadportugal.pt/api/agents?searchTerm=Sandra%20Marisa%20Silva*' => Http::response(['items' => [], 'totalItems' => 0]),
            'www.iadportugal.pt/api/agents?searchTerm=Silva*' => Http::response(['items' => [
                ['userName' => 'sandra.silva', 'fullName' => 'Sandra Silva', 'location' => ['place' => 'Ericeira']],
                ['userName' => 'luis.silva', 'fullName' => 'Luis Silva', 'location' => ['place' => 'Porto']],
                ['userName' => '<script>', 'fullName' => 'Sandra Má'],
            ], 'totalItems' => 3]),
            'www.iadportugal.pt/consultor-imobiliario/sandra.silva' => Http::response(
                '<a href="/anuncio/terreno-venda-alvarinhos-4851m2/r953966">Terreno venda alvarinhos 4851m2</a>'),
        ]);

        Livewire::test(Landing::class)->call('escolher', 'iad')
            ->assertSee('Procurar pelo meu nome')->call('mudarModo', 'nome')->assertSet('modo', 'nome')->assertSee('Como aparece na iad Portugal')
            ->set('nomeConsultor', 'Sandra Marisa Silva')->call('procurarConsultor')
            ->assertSee('Sandra Silva')->assertSee('iad Portugal · Ericeira')
            ->call('escolherConsultor', 'sandra.silva');
        $this->assertNotSame([], app(\App\Services\RealEstate\IadConsultores::class)->imoveis('sandra.silva'));
        $this->assertSame([], app(\App\Services\RealEstate\IadConsultores::class)->imoveis('../etc'));
    }

    public function test_a_century_21_agent_finds_their_listing_by_name_and_asks_for_its_video(): void
    {
        Cache::flush();
        Queue::fake();
        Http::fake([
            'century21.pt/api/agents*' => Http::response(['data' => [
                ['handler' => 'sandra-asilva', 'name' => 'Sandra Silva', 'display_name' => null, 'agency' => ['name' => 'CENTURY 21 Realty Art M&J']],
                ['handler' => 'rui-costa', 'name' => 'Rui Costa', 'agency' => ['name' => 'CENTURY 21 Lis']],
            ], 'total' => 2]),
            'century21.pt/api/properties*' => Http::response(['data' => [
                ['reference' => 'C0223-10424', 'link' => 'https://www.century21.pt/ref/C0223-10424', 'title' => ['pt' => 'Apartamento T4 em Olivais, Lisboa', 'en' => 'Olivais'],
                    'price' => 360000, 'price_hidden' => false, 'images' => ['https://images.century21.pt/a/1.jpg']],
                ['reference' => 'C0223-10500', 'link' => 'https://www.century21.pt/ref/C0223-10500', 'title' => ['pt' => 'Moradia T3'], 'price' => 500000, 'price_hidden' => true, 'images' => []],
            ], 'total' => 2]),
        ]);

        Livewire::test(Landing::class)->call('escolher', 'century21')
            ->call('mudarModo', 'nome')->set('nomeConsultor', 'sandra silva')->call('procurarConsultor')
            ->assertSee('Sandra Silva')->assertSee('CENTURY 21 Realty Art M&amp;J', false)->assertDontSee('Rui Costa')
            ->call('escolherConsultor', 'sandra-asilva')
            ->assertSee('Apartamento T4 em Olivais, Lisboa')->assertSee('360 000 €')->assertSee('Moradia T3')->assertDontSee('500 000 €')   // a hidden price stays hidden
            ->call('escolherImovel', 'C0223-10424')
            ->assertHasNoErrors()->assertSet('anuncio', 'https://www.century21.pt/ref/C0223-10424');

        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === 'https://www.century21.pt/ref/C0223-10424');
    }
}
