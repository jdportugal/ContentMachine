<?php

namespace Tests\Unit;

use App\Services\RealEstate\IadListingScraper;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use PHPUnit\Framework\TestCase;

class IadListingScraperTest extends TestCase
{
    public function test_it_reads_the_listing_from_the_nuxt_payload(): void
    {
        // Devalue: element 0 is the root, every integer inside points at another element.
        $flat = [
            ['state' => 1],
            ['$svue-query-state' => 2],
            ['queries' => 3],
            [4],
            ['queryKey' => 5, 'state' => 7],
            [6, 10], 'properties',
            ['data' => 8],
            ['Reactive', 9],
            [
                'propertyListingRef' => 10, 'prices' => 11, 'description' => 13, 'media' => 14, 'agent' => 17,
                'surfaceList' => 24, 'bedroomsCount' => 28, 'seo' => 29, 'location' => 31,
            ],
            '131752',
            ['formattedMain' => 12], '890 000 €',
            "Moradia com vista.\r\nPerto de Almancil.",
            ['photos' => 15], [16], 'https://images.playiad.com/a.jpeg',
            ['fullName' => 18, 'avatar' => 19, 'directContact' => 21],
            'Marisa Neves', ['src' => 20], 'https://images.playiad.com/p.png',
            ['hashedPhone' => 22], 'KzM1MTkxNzIzNTYwMQ==', null,
            [25], ['type' => 26, 'value' => 27], 'plot-area', 550,
            3, ['metaTitle' => 30], 'Casa/Moradia 3 quartos de 128 m² em Almancil',
            ['place' => 32], 'Almancil',
        ];
        $html = '<script type="application/json" id="__NUXT_DATA__">'.json_encode($flat).'</script>';

        $l = (new IadListingScraper)->parse($html, 'https://www.iadportugal.pt/anuncio/x/r131752');

        $this->assertSame(890000, $l['price']);
        $this->assertSame('Casa/Moradia 3 quartos de 128 m² em Almancil', $l['title']);
        $this->assertSame("Moradia com vista.\nPerto de Almancil.", $l['description']);
        $this->assertSame(['https://images.playiad.com/a.jpeg'], $l['photos']);
        $this->assertSame(['name' => 'Marisa Neves', 'phone' => '+351917235601', 'photo' => 'https://images.playiad.com/p.png'], $l['agent']);
        $this->assertSame('IAD Portugal', $l['agency']);
        $this->assertSame('550', $l['details']['Área Total do Lote m²']);
        $this->assertSame(3, $l['details']['Quartos']);
    }

    /** Lowercase "iad" is voiced "IA de Portugal": the spoken text always says "IAD Portugal". */
    public function test_iad_is_spoken_as_iad_portugal(): void
    {
        foreach (['da iad Portugal.', 'da IAD Portugal.', 'da iad de Portugal.', 'da iad.'] as $in) {
            $this->assertSame('da IAD Portugal.', ListingScriptWriter::iadFalado($in), $in);
        }
        $this->assertSame('a piscina iadiante', ListingScriptWriter::iadFalado('a piscina iadiante'));
    }

    public function test_urls_go_to_the_right_site(): void
    {
        $this->assertTrue(ListingScraper::supports('https://www.iadportugal.pt/anuncio/x/r131752'));
        $this->assertTrue(ListingScraper::supports('https://www.remax.pt/pt/imoveis/x/124861009-293'));
        $this->assertFalse(ListingScraper::supports('https://www.imovirtual.com/pt/anuncio/x-ID1'));
    }
}
