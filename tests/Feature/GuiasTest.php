<?php

namespace Tests\Feature;

use App\Services\Guias\Guias;
use App\Services\Marketing\Canal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The /guias articles, and what search engines read: sitemap, robots, canonical and JSON-LD. */
class GuiasTest extends TestCase
{
    use RefreshDatabase;

    private function guias(): array
    {
        return app(Guias::class)->todos();
    }

    public function test_every_guide_is_complete(): void
    {
        $guias = $this->guias();
        $this->assertGreaterThanOrEqual(40, count($guias));
        foreach ($guias as $slug => $g) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertLessThanOrEqual(160, mb_strlen($g['descricao']), "$slug: description too long");
            $this->assertNotSame('', $g['resumo'], "$slug: no short answer");
            $this->assertNotSame('', $g['palavra'], "$slug: no target keyword");
            $this->assertNotEmpty($g['faq'], "$slug: no FAQ");
            $this->assertStringContainsString('<h2 id="perguntas-frequentes">', $g['html']);
            $this->assertFileExists(public_path("images/og/$slug.png"));
            $this->assertContains($g['tema'], Guias::TEMAS, "$slug: unknown theme");
        }
        // One guide per target search, so the guides don't compete with each other.
        $palavras = array_map(fn ($g) => mb_strtolower($g['palavra']), $guias);
        $this->assertSame(array_unique($palavras), $palavras);
    }

    public function test_links_between_guides_point_to_guides_that_exist(): void
    {
        $guias = $this->guias();
        foreach ($guias as $slug => $g) {
            preg_match_all('#href="/guias/([a-z0-9-]+)"#', $g['html'], $m);
            foreach ($m[1] as $alvo) {
                $this->assertArrayHasKey($alvo, $guias, "$slug links to a missing guide: $alvo");
            }
        }
    }

    public function test_a_guide_page_carries_its_metadata_and_structured_data(): void
    {
        $g = $this->guias()['video-no-anuncio-idealista'];
        $r = $this->get('/guias/video-no-anuncio-idealista?utm_source=x')->assertOk();

        $r->assertSee('<title>'.e($g['titulo']).' · Imocena</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/guias/video-no-anuncio-idealista').'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee(asset('images/og/video-no-anuncio-idealista.png'), false)
            ->assertSee('RESPOSTA CURTA')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('Quantos vídeos posso pôr num anúncio do idealista?')
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertSee('utm_source=guias', false);
    }

    public function test_the_index_lists_every_guide_and_unknown_guides_are_404(): void
    {
        $r = $this->get('/guias')->assertOk()->assertSee('"@type":"CollectionPage"', false);
        foreach ($this->guias() as $g) {
            $r->assertSee($g['titulo']);
        }
        $this->get('/guias/nao-existe')->assertNotFound();
    }

    public function test_sitemap_and_robots(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap->assertSee('<loc>'.url('/guias/tiktok-imobiliario-portugal').'</loc>', false)
            ->assertSee('<loc>'.url('/guias').'</loc>', false)
            ->assertSee('<loc>'.url('/privacidade').'</loc>', false);
        $this->assertNotFalse(simplexml_load_string($sitemap->getContent()));

        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $robots);
        $this->assertStringContainsString('Disallow: /conta', $robots);
        $this->assertStringContainsString('Disallow: /v/', $robots);
        $this->assertStringNotContainsString('Disallow: /guias', $robots);
    }

    public function test_home_has_one_canonical_and_says_who_we_are(): void
    {
        $this->get('/?agencia=remax&utm_source=meta')->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og/imocena.png').'">', false)
            ->assertSee(route('guias'), false);
    }

    public function test_an_agency_page_sends_its_button_to_that_agency(): void
    {
        $this->get('/guias/video-imoveis-remax')->assertOk()
            ->assertSee('agencia=remax&amp;utm_source=guias', false)
            ->assertSee('remax.pt');
        $this->get('/guias')->assertSee('Por agência');
    }

    public function test_visits_from_a_guide_count_as_their_own_channel(): void
    {
        $this->assertSame('guias', Canal::de(['utm_source' => 'guias', 'utm_medium' => 'organico']));
    }
}
