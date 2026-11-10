<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** What Portuguese law asks of an online shop: terms, who sells, complaints book, dispute resolution. */
class LegalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_terms_say_who_sells_the_prices_and_the_withdrawal_right(): void
    {
        $this->get('/termos')->assertOk()
            ->assertSee('Termos e condições')
            ->assertSee('Eclipsingredients Unipessoal Lda')->assertSee('NIF 517419122')->assertSee('3500-393 Viseu')
            ->assertSee('100 €')->assertSee('2 edições grátis')
            ->assertSee('Decreto-Lei n.º 24/2014')->assertSee('artigo 17.º')
            ->assertSee('livroreclamacoes.pt')->assertSee('cniacc.pt');
    }

    public function test_the_site_links_the_terms_complaints_book_and_company_everywhere_people_buy(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('/termos', false)->assertDontSee('href="#" class="text-[#AEB5C1] hover:text-ivory">Termos', false)
            ->assertSee('Livro de Reclamações')->assertSee('Resolução de litígios')
            ->assertSee('Eclipsingredients Unipessoal Lda · NIF 517419122')
            ->assertSee('Ao comprar, aceita os');
        $this->get('/privacidade')->assertOk()->assertSee('Eclipsingredients Unipessoal Lda, NIF 517419122');

        $this->actingAs(User::factory()->cliente()->create())->get('/conta')->assertOk()->assertSee('/termos', false);
    }

    public function test_the_free_video_form_has_one_optional_box_and_a_notice(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('Ao pedir, confirma que é o consultor deste anúncio')
            ->assertSee('Avisem-me por email quando eu publicar um imóvel novo')->assertSee('(Opcional)')
            ->getContent();
        $form = substr($html, strpos($html, 'Peça o seu vídeo grátis'), 9000);
        $this->assertSame(1, substr_count($form, 'type="checkbox"'), 'one box only');

        // Nothing to tick to get the video.
        \Illuminate\Support\Facades\Queue::fake();
        \Livewire\Livewire::test(\App\Livewire\Landing::class)->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/515151')->call('pedir')->assertHasNoErrors();
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('leads')->value('consentimento_em'));
    }
}
