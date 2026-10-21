<?php

namespace Tests\Unit;

use App\Services\RealEstate\IdealistaListingScraper;
use App\Services\RealEstate\ListingScraper;
use PHPUnit\Framework\TestCase;

class IdealistaListingScraperTest extends TestCase
{
    public function test_it_maps_what_the_page_shows(): void
    {
        $l = (new IdealistaListingScraper)->parse([
            'title' => 'Apartamento T2 à venda na Campo de Santa Clara, 140',
            'price' => 550000,
            'description' => 'Apartamento T2 sobre o Tejo.',
            'location' => ['Campo de Santa Clara, 140', 'São Vicente', 'Lisboa'],
            'features' => ['85 m² área bruta, 84 m² úteis', 'T2', '2 casas de banho', 'Varanda'],
            'energy' => 'd',
            'reference' => 'KWPT-007771',
            'advertiser' => 'Ana Mação - KW',
            'advertiserInfo' => ['AMI 12223', 'Oeiras'],
            'photos' => ['https://img4.idealista.pt/a.jpg', 'https://img4.idealista.pt/b.jpg'],
        ], 'https://www.idealista.pt/imovel/33663830/');

        $this->assertSame(550000, $l['price']);
        $this->assertSame(['name' => 'Ana Mação', 'phone' => '', 'photo' => null], $l['agent']);
        $this->assertSame('KW', $l['agency']);
        $this->assertSame('12223', $l['ami']);
        $this->assertCount(2, $l['photos']);
        $this->assertSame('550 000 €', $l['details']['Preço']);
        $this->assertSame('85', $l['details']['Área Bruta m²']);
        $this->assertSame('84', $l['details']['Área Útil m²']);
        $this->assertSame('2', $l['details']['Quartos']);
        $this->assertSame('D', $l['details']['Certificado Energético']);
        $this->assertSame('Campo de Santa Clara, 140, São Vicente, Lisboa', $l['details']['Localização']);
    }

    public function test_an_agency_advertising_itself_is_both_agent_and_agency(): void
    {
        $l = (new IdealistaListingScraper)->parse(['advertiser' => 'Century 21 Alfa', 'photos' => ['x.jpg']], 'https://www.idealista.pt/imovel/1/');

        $this->assertSame('Century 21 Alfa', $l['agent']['name']);
        $this->assertSame('Century 21 Alfa', $l['agency']);
        $this->assertNull($l['price']);
        $this->assertSame('Sob consulta', $l['details']['Preço']);
    }

    public function test_only_single_listing_urls_are_supported(): void
    {
        $this->assertTrue(ListingScraper::supports('https://www.idealista.pt/imovel/33663830/'));
        $this->assertFalse(ListingScraper::supports('https://www.idealista.pt/comprar-casas/lisboa/'));
        $this->assertFalse(ListingScraper::supports('https://www.idealista.com/inmueble/1/'));
    }
}
