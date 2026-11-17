<?php

namespace Tests\Unit;

use App\Services\RealEstate\ListingScraper;
use PHPUnit\Framework\TestCase;

/** Links to an agent's page or a search are told apart from links to one property. */
class ListingScraperPaginasTest extends TestCase
{
    public function test_agent_pages_and_searches_are_not_listings(): void
    {
        foreach ([
            // The one that failed on 17 Nov: a RE/MAX agent's "my listings" search.
            'https://remax.pt/pt/comprar/imoveis/h/r/r/r/t?s=%7B%22us%22%3A%22126401222%22%2C%22nm%22%3A%22Margarida%20Lima%22%7D&p=1&o=-PublishDate',
            'https://remax.pt/pt/agente/consultor/125411383',
            'https://www.remax.pt/pt/arrendar/imoveis/h/r/r/r/t',
            'https://www.remax.pt/pt/imoveis/r/r/r/t?s=%7B%7D',
            'https://www.remax.pt/',
            'https://www.remax.pt/pt',
            'https://www.iadportugal.pt/en/real-estate-agent/ricardo.rodrigues',
            'https://realtyart.century21.pt/en/officer/rgamelas/',
            'https://www.kwportugal.pt/pt/agentes/acacio-pereira',
        ] as $url) {
            $this->assertTrue(ListingScraper::naoEImovel($url), $url);
        }
    }

    public function test_single_listings_pass(): void
    {
        foreach ([
            'https://remax.pt/pt/imoveis/venda-moradia-t3-braga-barcelos/126281088-16/t6nqohpolX-3oPlra70FcRI1c9HSOYca',
            'https://www.remax.pt/pt/imoveis/apartamento-t2/123456',
            'https://www.century21.pt/comprar/apartamento/porto/12345',
            'https://www.kwportugal.pt/pt/Imovel/Venda/Moradia/Setubal/Setubal/Azeitao/75048/KWPT-039614',
            'https://www.iadportugal.pt/pt/imovel/venda-apartamento-t2/r1234567',
            'https://www.idealista.pt/imovel/123',   // not a portal we read: MENSAGEM handles it
        ] as $url) {
            $this->assertFalse(ListingScraper::naoEImovel($url), $url);
        }
    }
}
