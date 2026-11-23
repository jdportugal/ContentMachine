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

/** RE/MAX agents can find their listing by their name instead of pasting the link. */
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
}
