<?php

namespace Tests\Unit;

use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\PredimedListingScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PredimedListingScraperTest extends TestCase
{
    private const URL = 'https://predimed.pt/Imovel/jrlxoe';

    private const MEDIA = 'https://predimed-media.crm360.pt/imovs/jrlxoe';

    /** The page as predimed.pt serves it (classes dropped), cut to what the scraper reads. */
    private function pagina(string $preco = '€ 275 000'): string
    {
        $m = self::MEDIA;

        return <<<HTML
        <html><head><meta property="og:image" content="{$m}/tn/a1.jpg"></head><body>
        <a href="{$m}/foto_marca_agua/a1.jpg"><img src="{$m}/foto_marca_agua/a1.jpg"></a>
        <a href="{$m}/foto_marca_agua/b2.jpg"><img src="{$m}/foto_marca_agua/b2.jpg"></a>
        <div id="imoveis-caracteristicas"> <div> <div id="descricao-imovel"> <div> <h1> Moradia T2 em Pia do Urso, São Mamede, Batalha, </h1>
        <span>para comprar <div></div></span> <h3>São Mamede, Batalha, Leiria</h3> <ul> <li>Destaque</li> </ul> </div>
        <h3> {$preco} <span>Ref. 064781</span> </h3>
        <div> <span>2 <br /><span>Quartos</span></span> <span>1 <br /><span>WCs</span></span> <span>82,60 m² <br /><span>Área Útil</span></span> </div>
        </div> </div>
        <div id="descriptionTab"> <div id="descricao"> <div> <div> Moradia T2 com jardim&nbsp;em Pia do Urso.<br /> Cozinha &amp; sala em open space.<br /><br /><br /> Marque já a sua visita.<br /> </div> </div> </div>
        <div id="detalhes"> <div> <div> <span>Distrito:</span> Leiria </div> <div> <span>Concelho:</span> Batalha </div> <div> <span>Freguesia:</span> São Mamede </div>
        <div> <span>Área Bruta Privativa:</span> 97,20 m² </div> <div> <span>Área útil:</span> 82,60 m² </div> <div> <span>Área do Terreno:</span> 700,00 m² </div>
        <div> <span>Ano:</span> 2008 </div> <div> <span>Estado:</span> Usado </div> <div> <span>Categoria energética:</span> D </div>
        <div> <span>Tipo:</span> Moradia </div> <div> <span>Tipologia:</span> 2 </div> </div> <div id="divisons-list"></div> </div>
        <div id="caracteristicas"> <div> <h3> Equipamentos</h3> <div> <div> <label for="ar_condiconado">Lareira</label> </div> <div> <label for="ar_condiconado">Roupeiros</label> </div> </div>
        <h3> Infraestruturas</h3> <div> <div> <label for="ar_condiconado">Jardim</label> </div> </div> </div> </div> </div> </div>
        <div> <div> <a href="https://predimed.pt/goncalo.cerejo"> <img src="https://predimed-media.crm360.pt/users/3xv2xj/imagens/foto.png"> </a>
        <h5> <p>Gonçalo Cerejo </p> <p>PREDIMED LEIRIA</p> <span> <span>goncalo.cerejo@predimed.pt</span> </span> <span> <span>912 345 678</span> </span> <small>Chamada para a rede móvel nacional</small> </h5> </div> </div>
        <footer><p>Predimed Imobiliária, Mediação Imobiliária Lda.</p><p>AMI nº 22503</p></footer>
        </body></html>
        HTML;
    }

    public function test_it_reads_the_listing_and_takes_the_clean_photos_from_the_gallery(): void
    {
        $galeria = [self::MEDIA.'/foto_marca_agua/a1.jpg', self::MEDIA.'/foto_marca_agua/b2.jpg', self::MEDIA.'/foto_marca_agua/c3.jpg'];

        $l = (new PredimedListingScraper)->parse($this->pagina(), self::URL, $galeria);

        $this->assertSame('Moradia T2 em Pia do Urso, São Mamede, Batalha', $l['title']);
        $this->assertSame(275000, $l['price']);
        $this->assertSame([self::MEDIA.'/foto/a1.jpg', self::MEDIA.'/foto/b2.jpg', self::MEDIA.'/foto/c3.jpg'], $l['photos']);
        $this->assertSame("Moradia T2 com jardim em Pia do Urso.\nCozinha & sala em open space.\n\nMarque já a sua visita.\n\nCaracterísticas: Lareira, Roupeiros, Jardim.", $l['description']);
        $this->assertSame(['name' => 'Gonçalo Cerejo', 'phone' => '+351912345678', 'photo' => 'https://predimed-media.crm360.pt/users/3xv2xj/imagens/foto.png'], $l['agent']);
        $this->assertSame('PREDIMED LEIRIA', $l['agency']);
        $this->assertSame('22503', $l['ami']);
        $this->assertSame([], $l['videos']);

        $d = $l['details'];
        $this->assertSame('275 000 €', $d['Preço']);
        $this->assertSame('Venda', $d['Negócio']);
        $this->assertSame('Moradia T2', $d['Tipo']);
        $this->assertSame('São Mamede, Batalha, Leiria', $d['Localização']);
        $this->assertSame('064781', $d['Referência']);
        $this->assertSame(97.2, $d['Área Bruta m²']);
        $this->assertSame(82.6, $d['Área Útil m²']);
        $this->assertSame(700, $d['Área Total do Lote m²']);
        $this->assertSame(2, $d['Quartos']);
        $this->assertSame(1, $d['Casas de Banho']);
        $this->assertSame(2008, $d['Ano de Construção']);
        $this->assertSame('D', $d['Certificado Energético']);
        $this->assertSame('Usado', $d['Estado']);
    }

    public function test_without_the_gallery_it_uses_the_page_photos_and_a_hidden_price_is_on_request(): void
    {
        $l = (new PredimedListingScraper)->parse($this->pagina('Preço sob consulta'), self::URL);

        $this->assertSame([self::MEDIA.'/foto/a1.jpg', self::MEDIA.'/foto/b2.jpg'], $l['photos']);
        $this->assertNull($l['price']);
        $this->assertSame('Sob consulta', $l['details']['Preço']);
    }

    public function test_a_page_without_a_listing_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        (new PredimedListingScraper)->parse('<html><body><h1>Imóveis</h1></body></html>', 'https://predimed.pt/imoveis');
    }

    public function test_predimed_links_are_read_and_its_other_pages_are_not_listings(): void
    {
        foreach (['https://predimed.pt/Imovel/wgndg2', 'https://www.predimed.pt/en/Imovel/jrlxoe', 'https://predimedprestige.pt/imovel/j9yk95'] as $url) {
            $this->assertSame('predimed', ListingScraper::portal($url), $url);
            $this->assertFalse(ListingScraper::naoEImovel($url), $url);
        }
        foreach (['https://predimed.pt/', 'https://predimed.pt/imoveis', 'https://predimed.pt/equipa', 'https://predimed.pt/liliana.santos', 'https://predimed.pt/agencias'] as $url) {
            $this->assertTrue(ListingScraper::naoEImovel($url), $url);
        }
        $this->assertSame('wgndg2', PredimedListingScraper::codigo('https://predimed.pt/Imovel/wgndg2'));
        $this->assertNull(PredimedListingScraper::codigo('https://predimed.pt/imoveis'));
    }
}
