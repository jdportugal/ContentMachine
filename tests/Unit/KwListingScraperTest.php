<?php

namespace Tests\Unit;

use App\Services\RealEstate\KwListingScraper;
use App\Services\RealEstate\ListingScraper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KwListingScraperTest extends TestCase
{
    private const URL = 'https://www.kwportugal.pt/pt/Imovel/Venda/Moradia/Setubal/Setubal/Azeitao-(Sao-Lourenco-e-Sao-Simao)/75048/KWPT-039614';

    /** The page as KW serves it: the listing in the Next.js stream, split across push() calls. */
    private function pagina(array $property, string $description): string
    {
        $stream = '6:[["$","$L1a",null,{"agent":{"name":"Outro"},"language":"pt","property":'.json_encode($property).',"lng":"pt"}]]'
            ."\n5a:T".dechex(strlen($description)).','.$description.'5b:["$","section"]';
        $half = intdiv(strlen($stream), 2);
        $html = '';
        foreach ([substr($stream, 0, $half), substr($stream, $half)] as $chunk) {
            $html .= '<script>self.__next_f.push([1,'.json_encode($chunk).'])</script>';
        }

        return $html;
    }

    public function test_it_reads_the_listing_from_the_next_flight_payload(): void
    {
        $description = "Moradia térrea T2 em Azeitão.\r\n\r\n\r\n\r\nLogradouro — com churrasqueira.";
        $property = [
            'reference' => 'KWPT-039614', 'type' => 'Moradia', 'typology' => 'T2', 'state' => 'Usado',
            'region1' => 'Setúbal', 'region2' => 'Setúbal', 'region3' => 'Azeitão (São Lourenço e São Simão)',
            'rooms' => 2, 'suites' => 0, 'bathrooms' => 1, 'parkingPlaces' => 1, 'livingArea' => 80, 'totalArea' => 84, 'landArea' => 209,
            'constructionYear' => 1977, 'energyClass' => 'E', 'name' => 'Encantadora Moradia T2 Unifamiliar com Garagem,',
            'description' => '$5a', 'price' => 295000, 'videoLink' => '$undefined', 'uploadedVideoFile' => '$undefined',
            'images' => [
                ['order' => 2, 'url' => 'https://imgs.soukwportugal.pt/41218/properties/b.jpg'],
                ['order' => 1, 'url' => 'https://imgs.soukwportugal.pt/41218/properties/a.png'],
                ['order' => 3, 'url' => 'https://imgs.soukwportugal.pt/41218/properties/b.jpg'],
            ],
            'features' => [],
            'agentName' => 'Acácio Pereira', 'agentMobile' => '+351964023951', 'agentImage' => 'https://soukwimgsprod.s3.eu-central-1.amazonaws.com/associate/x.jpg',
            'agencyName' => 'KW Select Cascais', 'agencyDesignation' => 'Yellow Select, Mediação Imobiliária, Lda.',
        ];

        $l = (new KwListingScraper)->parse($this->pagina($property, $description), self::URL);

        $this->assertSame(295000, $l['price']);
        $this->assertSame('Encantadora Moradia T2 Unifamiliar com Garagem,', $l['title']);
        $this->assertSame("Moradia térrea T2 em Azeitão.\n\nLogradouro — com churrasqueira.", $l['description']);
        $this->assertSame(['https://imgs.soukwportugal.pt/41218/properties/a.png', 'https://imgs.soukwportugal.pt/41218/properties/b.jpg'], $l['photos']);
        $this->assertSame([], $l['videos']);
        $this->assertSame(['name' => 'Acácio Pereira', 'phone' => '+351964023951', 'photo' => 'https://soukwimgsprod.s3.eu-central-1.amazonaws.com/associate/x.jpg'], $l['agent']);
        $this->assertSame('KW Select Cascais', $l['agency']);
        $this->assertSame('295 000 €', $l['details']['Preço']);
        $this->assertSame('Moradia T2', $l['details']['Tipo']);
        $this->assertSame('Azeitão (São Lourenço e São Simão), Setúbal', $l['details']['Localização']);
        $this->assertSame(80, $l['details']['Área Útil m²']);
        $this->assertSame(209, $l['details']['Área Total do Lote m²']);
        $this->assertSame('E', $l['details']['Certificado Energético']);
        $this->assertArrayNotHasKey('Suites', $l['details']);
        $this->assertArrayNotHasKey('Características', $l['details']);
    }

    public function test_kw_links_are_recognised_and_a_page_without_a_listing_is_refused(): void
    {
        $this->assertTrue(KwListingScraper::supports(self::URL));
        $this->assertSame('kw', ListingScraper::portal(self::URL));
        $this->assertFalse(KwListingScraper::supports('https://www.kw.com/property/1'));

        $this->expectException(RuntimeException::class);
        (new KwListingScraper)->parse('<html><body>KW Portugal</body></html>', 'https://www.kwportugal.pt/pt');
    }
}
