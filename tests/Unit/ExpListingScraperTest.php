<?php

namespace Tests\Unit;

use App\Services\RealEstate\ExpListingScraper;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use PHPUnit\Framework\TestCase;

class ExpListingScraperTest extends TestCase
{
    public function test_it_reads_the_listing_from_the_next_flight_payload(): void
    {
        $description = "Moradia de pedra com terreno.\r\nPronta a habitar — junto à praia fluvial.\r\n\r\n\r\n\r\nContacte-me!";
        $property = [
            'title' => 'Moradia com 3.000 m² de terreno', 'price' => 159000, 'bedrooms' => 4, 'bathrooms' => 2,
            'square_feet' => 2290, 'plot_size_sqm' => 0, 'property_type' => 'Moradia', 'city' => 'Vila Nova do Ceira',
            'state' => 'Góis', 'construction_year' => '$undefined', 'description' => '$1b', 'source_id' => '87147730',
            'images' => ['https://x.supabase.co/a.jpg', 'https://x.supabase.co/b.jpg', 'https://x.supabase.co/a.jpg'],
            'video_urls' => [['type' => 'Apimo', 'url' => 'https://media.apimo.pro/video/a.mp4'], ['type' => 'http', 'url' => 'https://youtu.be/x']],
            'agent' => ['name' => 'Andreia RAMALHO', 'photo' => 'https://media.apimo.pro/feed.jpg', 'phone' => '+351911982972', 'licence_number' => ''],
        ];
        $override = ['name' => 'Andreia Ramalho', 'photo' => 'https://x.supabase.co/agent.png', 'phone' => '911982972'];
        // The stream is split across push() calls at arbitrary points, and text rows give their length in UTF-8 bytes.
        $stream = '6:[["$","$L1a",null,{"initialProperty":'.json_encode($property).',"agentOverride":'.json_encode($override).'}]]'
            ."\n1b:T".dechex(strlen($description)).','.$description.'1c:["$","section"]';
        $half = intdiv(strlen($stream), 2);
        $html = '';
        foreach ([substr($stream, 0, $half), substr($stream, $half)] as $chunk) {
            $html .= '<script>self.__next_f.push([1,'.json_encode($chunk).'])</script>';
        }

        $l = (new ExpListingScraper)->parse($html, 'https://andreiaramalho.expportugal.com/imoveis/gois/moradia/vila-nova-do-ceira-1');

        $this->assertSame(159000, $l['price']);
        $this->assertSame("Moradia de pedra com terreno.\nPronta a habitar — junto à praia fluvial.\n\nContacte-me!", $l['description']);
        $this->assertSame(['https://x.supabase.co/a.jpg', 'https://x.supabase.co/b.jpg'], $l['photos']);
        $this->assertSame(['name' => 'Andreia Ramalho', 'phone' => '+351911982972', 'photo' => 'https://x.supabase.co/agent.png'], $l['agent']);
        $this->assertSame('eXp Portugal', $l['agency']);
        $this->assertSame(['https://media.apimo.pro/video/a.mp4', 'https://youtu.be/x'], $l['videos']);
        $this->assertSame(4, $l['details']['Quartos']);
        $this->assertSame('Vila Nova do Ceira, Góis', $l['details']['Localização']);
        $this->assertArrayNotHasKey('Ano de Construção', $l['details']);
        $this->assertArrayNotHasKey('Área Total do Lote m²', $l['details']);
    }

    /** Written as one word, eXp is read as a word: the voiceover spells it out. */
    public function test_exp_is_spoken_letter_by_letter(): void
    {
        $this->assertSame('fale com a Andreia da E X P Portugal.', ListingScriptWriter::expFalado('fale com a Andreia da eXp Portugal.'));
        $this->assertSame('E X P', ListingScriptWriter::expFalado('E X P'));
        $this->assertSame('andreia@expportugal.com, uma expansão', ListingScriptWriter::expFalado('andreia@expportugal.com, uma expansão'));
        // The after-intro line is converted before the duplicate check, so it isn't said twice.
        $vo = 'Moradia em Góis. Fale com a Andreia da E X P Portugal. Quatro quartos.';
        $this->assertSame($vo, ListingScriptWriter::withAfterIntroLine($vo, 'Fale com a Andreia da eXp Portugal'));
    }

    /** As written, "T2+1" is voiced "T, dois mais um" and "T1" as "T, um". */
    public function test_tipologia_is_spoken_as_said(): void
    {
        $this->assertSame('Um tê dois mais um mobilado.', ListingScriptWriter::tipologiaFalada('Um T2+1 mobilado.'));
        $this->assertSame('tê um renovado, tê zero e tê quatro mais um', ListingScriptWriter::tipologiaFalada('T1 renovado, T0 e t4 + 1'));
        $this->assertSame('MT2 e T2A', ListingScriptWriter::tipologiaFalada('MT2 e T2A'));
    }

    public function test_hard_words_are_spelled_for_speech(): void
    {
        $this->assertSame('No logradôro, em Montinhos dos Pêgos, REMAX Víjan II.', ListingScriptWriter::pronunciaFalada('No logradouro, em Montinhos dos Pegos, REMAX Vision II.'));
        $this->assertSame('Logradouros e Visionário', ListingScriptWriter::pronunciaFalada('Logradouros e Visionário'));
    }

    public function test_urls_go_to_the_right_site(): void
    {
        $this->assertTrue(ListingScraper::supports('https://andreiaramalho.expportugal.com/imoveis/gois/moradia/vila-nova-do-ceira-1'));
        $this->assertTrue(ListingScraper::supports('https://expportugal.com/imoveis/x'));
        $this->assertFalse(ListingScraper::supports('https://expportugal.com.evil.io/imoveis/x'));
    }
}
