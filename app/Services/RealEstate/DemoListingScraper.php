<?php

namespace App\Services\RealEstate;

use RuntimeException;

/**
 * Imocena's own demo listings: fictional properties with generated photos
 * (public/media/demo/<slug>/), used for the landing page and the ads. The
 * "listing URL" is <app url>/demo/<slug>; nothing is fetched — the data is here.
 * The photos are illustrative and the agent is Imocena itself: never a real client.
 */
class DemoListingScraper
{
    public const PATH = 'demo';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // Unit tests build scrapers without the framework: no config there, and no demo either.
        $app = app()->bound('config') ? strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)) : '';
        $slug = self::slug($url);

        return $slug !== null && in_array($host, array_filter([$app, 'imocena.com', 'www.imocena.com', 'localhost', '127.0.0.1']), true)
            && isset(self::listings()[$slug]);
    }

    /** The demo slug in a URL (…/demo/<slug>), or null. */
    public static function slug(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return preg_match('#^'.self::PATH.'/([a-z0-9-]+)$#', $path, $m) ? $m[1] : null;
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $slug = self::slug($url);
        $l = self::listings()[$slug] ?? null;
        if (! $l) {
            throw new RuntimeException('Unknown demo listing.');
        }
        $base = rtrim((string) config('app.url'), '/')."/media/demo/{$slug}/";

        return [
            'url' => $url,
            'title' => $l['title'],
            'description' => $l['description'],
            'price' => $l['price'],
            'details' => ['Preço' => number_format($l['price'], 0, ',', ' ').' €'] + $l['details'],
            'photos' => array_map(fn ($f) => $base.$f, $l['photos']),
            'agent' => ['name' => 'Imocena', 'phone' => '+351 232 000 000', 'photo' => rtrim((string) config('app.url'), '/').'/images/imocena-demo-agente.png', 'email' => 'info@imocena.com', 'licence' => ''],
            'agency' => 'Imocena',
            'ami' => '',
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public static function listings(): array
    {
        return [
            'moradia-lajeosa' => [
                'title' => 'Moradia T3 com jardim e garagem · Lajeosa, Viseu',
                'price' => 295000,
                'details' => [
                    'Localização' => 'Lajeosa, Viseu',
                    'Tipologia' => 'T3',
                    'Área Bruta m²' => '185',
                    'Área Útil m²' => '142',
                    'Área Total do Lote m²' => '620',
                    'Quartos' => '3',
                    'Casas de Banho' => '2',
                    'Ano de Construção' => '2019',
                    'Certificado Energético' => 'B',
                    'Características' => 'Garagem, jardim, terraço coberto, recuperador de calor, painéis solares, vista desafogada',
                ],
                'description' => "Moradia isolada de 2019, em Lajeosa, a dez minutos do centro de Viseu. Sala ampla com recuperador de calor e acesso direto ao terraço coberto e ao jardim; cozinha equipada aberta à zona de refeições, com muita luz natural. Três quartos no piso superior, dois deles com vista para a serra, e duas casas de banho completas. Garagem para um carro, estacionamento exterior para mais dois e painéis solares para águas quentes. Lote de 620 m² totalmente vedado, com relvado e zona de refeições ao ar livre.\n\nImóvel de demonstração da Imocena: imagens ilustrativas.",
                'photos' => ['01-fachada.png', '03-sala.png', '05-sala-jantar.png', '06-cozinha.png', '08-cozinha-detalhe.png', '09-quarto.png', '11-wc.png', '13-jardim.png', '14-jardim-terraco.png', '02-fachada-lateral.png', '04-sala-2.png', '07-cozinha-2.png', '10-quarto-2.png', '12-wc-2.png'],
            ],
        ];
    }
}
