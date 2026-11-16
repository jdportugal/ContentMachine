<?php

namespace Tests\Unit;

use App\Services\RealEstate\Century21ListingScraper;
use App\Services\RealEstate\ListingScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Century21ListingScraperTest extends TestCase
{
    private const URL = 'https://century21.pt/comprar/C0242-02837';

    /** The page as Century 21 serves it: the listing in the Next.js stream, split across push() calls. */
    private function pagina(array $property, string $descricao): string
    {
        $stream = '9:["$","$L21",null,{"property":'.json_encode($property).'}]'
            ."\n25:T".dechex(strlen($descricao)).','.$descricao.'26:["$","section"]';
        $metade = intdiv(strlen($stream), 2);
        $html = '';
        foreach ([substr($stream, 0, $metade), substr($stream, $metade)] as $pedaco) {
            $html .= '<script>self.__next_f.push([1,'.json_encode($pedaco).'])</script>';
        }

        return $html;
    }

    public function test_it_reads_the_listing_from_the_next_flight_payload(): void
    {
        $descricao = 'Apresentamos uma oportunidade em Vale de Milhaços!<br><br>Moradia de 3 pisos &amp; garagem.<br><br><br><br>Contacte-nos.';
        $property = [
            'asset_type' => 'house', 'ad_type' => 'sell', 'price' => 580000, 'price_hidden' => false,
            'title' => ['en' => '3-BEDROOM HOUSE', 'pt' => 'MORADIA T3 COM ESPAÇO COMERCIAL E GARAGEM | VALE DE MILHAÇOS'],
            'description' => ['en' => '$22', 'pt' => '$25'], 'reference' => 'C0242-02837', 'condition' => 'used',
            'energy_efficiency' => 'E', 'building_year' => 1980, 'gross_area' => 345, 'useful_area' => 310, 'terrain_area' => 353,
            'number_of_rooms' => 3, 'number_of_wcs' => 5, 'number_of_parking_spots' => 2,
            'ad_1' => 'Setúbal', 'ad_2' => 'Seixal', 'ad_3' => 'Corroios',
            'images' => ['https://images.century21.pt/a/c21-58.jpg', 'https://images.century21.pt/b/c21-35.jpg', 'https://images.century21.pt/a/c21-58.jpg'],
            'agency_phone' => '+351 212553597', 'agency_license_number' => '2424', 'video_url' => null,
            'agent' => ['name' => 'Marlon Silva', 'phone' => '+351916057121', 'image' => 'https://x.supabase.co/agente.jpg', 'email' => 'marlonsilva@century21.pt'],
            'agency' => ['name' => 'CENTURY 21 West Consulting', 'license_number' => '2424'],
        ];

        $l = (new Century21ListingScraper)->parse($this->pagina($property, $descricao), self::URL);

        $this->assertSame(580000, $l['price']);
        $this->assertSame('MORADIA T3 COM ESPAÇO COMERCIAL E GARAGEM | VALE DE MILHAÇOS', $l['title']);
        $this->assertSame("Apresentamos uma oportunidade em Vale de Milhaços!\n\nMoradia de 3 pisos & garagem.\n\nContacte-nos.", $l['description']);
        $this->assertSame(['https://images.century21.pt/a/c21-58.jpg', 'https://images.century21.pt/b/c21-35.jpg'], $l['photos']);
        $this->assertSame([], $l['videos']);
        $this->assertSame(['name' => 'Marlon Silva', 'phone' => '+351916057121', 'photo' => 'https://x.supabase.co/agente.jpg'], $l['agent']);
        $this->assertSame('CENTURY 21 West Consulting', $l['agency']);
        $this->assertSame('2424', $l['ami']);
        $this->assertSame('580 000 €', $l['details']['Preço']);
        $this->assertSame('Moradia T3', $l['details']['Tipo']);
        $this->assertSame('Corroios, Seixal, Setúbal', $l['details']['Localização']);
        $this->assertSame(310, $l['details']['Área Útil m²']);
        $this->assertSame('E', $l['details']['Certificado Energético']);
        $this->assertSame('Usado', $l['details']['Estado']);
    }

    public function test_a_hidden_price_is_on_request_and_the_agency_phone_stands_in(): void
    {
        $l = (new Century21ListingScraper)->parse($this->pagina([
            'asset_type' => 'apartment', 'price' => 250000, 'price_hidden' => true, 'title' => ['pt' => 'T2'], 'description' => ['pt' => ''],
            'number_of_rooms' => 2, 'images' => ['https://images.century21.pt/x.jpg'], 'agency_phone' => '212553597', 'agent' => ['name' => 'Ana'], 'agency' => [],
        ], ''), self::URL);

        $this->assertNull($l['price']);
        $this->assertSame('Sob consulta', $l['details']['Preço']);
        $this->assertSame('Apartamento T2', $l['details']['Tipo']);
        $this->assertSame('+351212553597', $l['agent']['phone']);
        $this->assertSame('Century 21 Portugal', $l['agency']);
    }

    public function test_century21_links_are_recognised_and_a_page_without_a_listing_is_refused(): void
    {
        $this->assertTrue(Century21ListingScraper::supports(self::URL));
        $this->assertTrue(Century21ListingScraper::supports('https://www.century21.pt/comprar/0739-04699'));
        $this->assertSame('century21', ListingScraper::portal(self::URL));
        $this->assertFalse(Century21ListingScraper::supports('https://www.century21.com/real-estate/1'));

        $this->expectException(RuntimeException::class);
        (new Century21ListingScraper)->parse('<html><body>CENTURY 21</body></html>', 'https://century21.pt/comprar');
    }
}
