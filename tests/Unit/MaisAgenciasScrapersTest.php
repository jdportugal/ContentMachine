<?php

namespace Tests\Unit;

use App\Services\RealEstate\BussolaHausListingScraper;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\MyBestHousesListingScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Bússola Haus (WordPress, plain HTML) and My Best Houses (an app, read once rendered). */
class MaisAgenciasScrapersTest extends TestCase
{
    public function test_a_bussola_haus_listing_is_read(): void
    {
        $up = 'https://bussolatchaus.com/wp-content/uploads/2026/09';
        $html = <<<HTML
        <html><body><main><div id="property-4415" class="ere-property-wrap single-property-area">
        <div class="ere__single-property-header-info"> <h2 class="ere__single-property-title">Apartamento T2 à venda na Rua dos Quartéis, 58</h2>
        <span class="property-price ere__loop-property-price"> €365.000,00 </span> </div>
        <div class="ere__loop-property-info property-info"> <div class="ere__loop-property-info-item"> <div class="ere__lpi-content"> <span class="ere__lpi-value">4415</span> <span class="ere__lpi-label">Ref.ª do Imóvel</span> </div> </div>
        <div class="ere__loop-property-info-item"> <div class="ere__lpi-content"> <span class="ere__lpi-value">60 m<sup>2</sup></span> <span class="ere__lpi-label">Área Útil</span> </div> </div>
        <div class="ere__loop-property-info-item"> <div class="ere__lpi-content"> <span class="ere__lpi-value">2</span> <span class="ere__lpi-label">Quartos</span> </div> </div>
        <div class="ere__loop-property-info-item"> <div class="ere__lpi-content"> <span class="ere__lpi-value">1</span> <span class="ere__lpi-label">Casa de banho</span> </div> </div> </div>
        <div class="single-property-image-main owl-carousel"> <div class="property-gallery-item ere-light-gallery"> <img src="{$up}/001-2-870x420.jpg"> <a href="{$up}/001-2.jpg" class="zoomGallery"></a> </div>
        <div class="property-gallery-item ere-light-gallery"> <img src="{$up}/002-2-870x420.jpg"> <a href="{$up}/002-2.jpg" class="zoomGallery"></a> </div> </div>
        <div class="property-gallery-thumb"> <div class="property-gallery-item"> <img src="{$up}/001-2-250x130.jpg"> </div> </div>
        <div class="single-property-element"> <div class="ere-heading-style2"> <h2>Descrição</h2> </div> <div class="ere-property-element">
        <p>Apartamento T2 na Ajuda Lisboa<br /> Totalmente remodelado.</p> <p>Marque a sua visita</p> <p>2-Bedroom Apartment (T2) in Ajuda, Lisbon<br /> Fully renovated.</p> </div> </div>
        <div class="single-property-element"> <h2>Localização</h2> <ul> <li class="state"> <div class="d-flex ere__property-location-item"> <strong>Distrito</strong> <span><a>Lisboa</a></span> </div> </li>
        <li class="city"> <div class="d-flex ere__property-location-item"> <strong>Concelho</strong> <span><a>Lisboa</a></span> </div> </li>
        <li class="neighborhood"> <div class="d-flex ere__property-location-item"> <strong>Freguesia</strong> <span><a>Ajuda</a></span> </div> </li> </ul> </div>
        <ul class="ere__list-2-col"> <li> <strong>Ref.ª do Imóvel</strong> <span>4415</span> </li> <li> <strong>Preço</strong> <span class="property-price"> €365.000,00 </span> </li>
        <li> <strong>Tipo de Propriedade</strong> <span><a>Apartamento</a></span> </li> <li> <strong>Situação da Propriedade</strong> <span><a>Comprar</a></span> </li> </ul>
        </div></main><footer>Bússola Haus · AMI 26749</footer></body></html>
        HTML;
        $url = 'https://bussolatchaus.com/property/apartamento-t2-a-venda-na-rua-dos-quarteis-58/';

        $l = (new BussolaHausListingScraper)->parse($html, $url);

        $this->assertSame('Apartamento T2 à venda na Rua dos Quartéis, 58', $l['title']);
        $this->assertSame(365000, $l['price']);
        $this->assertSame(["{$up}/001-2.jpg", "{$up}/002-2.jpg"], $l['photos']);
        $this->assertSame("Apartamento T2 na Ajuda Lisboa\nTotalmente remodelado.\nMarque a sua visita", $l['description']);
        $this->assertSame(['name' => 'Bússola Haus', 'phone' => '+351927131993', 'photo' => null], $l['agent']);
        $this->assertSame('26749', $l['ami']);
        $d = $l['details'];
        $this->assertSame(['365 000 €', 'Venda', 'Apartamento T2', 'Ajuda, Lisboa', '4415', 60, 2, 1], [$d['Preço'], $d['Negócio'], $d['Tipo'], $d['Localização'], $d['Referência'], $d['Área Útil m²'], $d['Quartos'], $d['Casas de Banho']]);

        $this->assertSame('bussolahaus', ListingScraper::portal($url));
        $this->assertFalse(ListingScraper::naoEImovel($url));
        $this->assertTrue(ListingScraper::naoEImovel('https://bussolatchaus.com/real-estate/'));
        $this->expectException(RuntimeException::class);
        (new BussolaHausListingScraper)->parse('<html><body><h1>Imóveis</h1></body></html>', 'https://bussolatchaus.com/real-estate/');
    }

    public function test_a_my_best_houses_listing_is_read_from_its_printable_page(): void
    {
        $f = 'https://static.portadafrente.com/PF31803/l';
        $html = <<<HTML
        <html><body><main><div class="inline-flex items-center rounded-full">Venda</div><h1>Apartamento T5 em Miraflores, Oeiras</h1><div class="text-center"><div class="relative w-32 h-32"><img src="/images/claudia-ribeiro.jpeg"></div><h3>Claudia David Ribeiro</h3><p>Co-founder</p></div>
        <img src="{$f}/11_Sala_02.jpg"><img src="{$f}/13_Suite_05.jpg"></main>
        <div id="print-root"><div class="print-brochure bg-white"><div class="flex"><img src="/assets/logo.png" class="h-12 invert"><div class="text-right text-sm"><p class="font-semibold">My Best Houses</p><p>+351 938 731 925</p></div></div>
        <div class="mb-6"><img src="{$f}/11_Sala_02.jpg" class="w-full"></div>
        <div class="mb-6"><h1 class="text-2xl">Apartamento T5 em Miraflores, Oeiras</h1><div class="flex items-center gap-2"><span>Miraflores</span></div><div class="flex items-baseline"><span class="text-3xl">1&nbsp;450&nbsp;000</span></div></div>
        <div class="grid grid-cols-5 gap-4"><div class="text-center"><p>Área</p><p>189m²</p></div><div class="text-center"><p>Quartos</p><p>5</p></div><div class="text-center"><p>Banheiros</p><p>2</p></div><div class="text-center"><p>Vagas</p><p>3</p></div><div class="text-center"><p>Tipo</p><p>Apartamento</p></div></div>
        <div class="mb-6"><h2>Descrição</h2><div class="text-sm"><p class="mb-3">O Vista Mirear situa-se em Miraflores.</p><p class="mb-3"></p><p class="mb-3">- Sala/cozinha com 53m2</p></div></div>
        <div class="mb-6"><h2>Comodidades</h2><div class="flex flex-wrap gap-2"><span>piscina</span><span>elevador</span></div></div>
        <div class="border-t-2"><div class="flex"><div class="flex items-center gap-4"><img src="/images/claudia-ribeiro-founder.png" class="w-16 h-16 rounded-full"><div><p class="font-bold">Claudia David Ribeiro</p><p class="text-sm">Consultora Imobiliária</p></div></div>
        <div class="text-right text-sm"><p class="flex">+351 938 731 925</p><p class="flex">claudia.ribeiro@mybesthouses.com</p></div></div></div></div></div></body></html>
        HTML;
        $url = 'https://mybesthouses.com/property/48597880-5f36-4895-9c61-659df5508b97';

        $l = (new MyBestHousesListingScraper)->parse($html, $url);

        $this->assertSame('Apartamento T5 em Miraflores, Oeiras', $l['title']);
        $this->assertSame(1450000, $l['price']);
        $this->assertSame(["{$f}/11_Sala_02.jpg", "{$f}/13_Suite_05.jpg"], $l['photos']);
        $this->assertSame("O Vista Mirear situa-se em Miraflores.\n\n- Sala/cozinha com 53m2\n\nComodidades: piscina, elevador.", $l['description']);
        $this->assertSame(['name' => 'Claudia David Ribeiro', 'phone' => '+351938731925', 'photo' => 'https://mybesthouses.com/images/claudia-ribeiro.jpeg'], $l['agent']);
        $this->assertSame('My Best Houses', $l['agency']);
        $d = $l['details'];
        $this->assertSame(['1 450 000 €', 'Venda', 'Apartamento T5', 'Miraflores', 189, 5, 2], [$d['Preço'], $d['Negócio'], $d['Tipo'], $d['Localização'], $d['Área Útil m²'], $d['Quartos'], $d['Casas de Banho']]);

        $this->assertSame('mybesthouses', ListingScraper::portal($url));
        $this->assertFalse(ListingScraper::naoEImovel($url));
        $this->assertTrue(ListingScraper::naoEImovel('https://mybesthouses.com/venda?transacao=venda'));
    }
}
