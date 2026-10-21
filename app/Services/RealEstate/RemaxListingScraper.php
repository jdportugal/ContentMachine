<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a remax.pt listing page. Everything the video needs — description,
 * price, details, photos, agent and office — is in the page's Next.js payload
 * (`listingEncoded`, base64 JSON), so no HTML scraping is involved.
 */
class RemaxListingScraper
{
    /**
     * Image CDN behind every relative picture path in the payload, at the size
     * the site's slideshow opens (W1300×H800, "l-view"). The page itself only
     * embeds 400px thumbnails ("ds-l"). "bb" is the unwatermarked original, but
     * smaller (≈1000px).
     */
    public const CDN = 'https://i.maxwork.pt/l-view/';

    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'remax.pt' || str_ends_with($host, '.remax.pt');
    }

    /**
     * @return array{url:string,title:string,description:string,price:?int,details:array<string,string>,
     *               photos:list<string>,agent:array{name:string,phone:string,photo:?string},agency:string,ami:string}
     */
    public function scrape(string $url): array
    {
        if (! self::supports($url)) {
            throw new RuntimeException('Only RE/MAX Portugal (remax.pt) listings are supported for now.');
        }

        $html = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body();
        if (! preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
            throw new RuntimeException('That page has no listing data — is it a single listing URL?');
        }
        $props = json_decode($m[1], true)['props']['pageProps'] ?? [];
        $l = json_decode((string) base64_decode((string) ($props['listingEncoded'] ?? '')), true);
        if (! is_array($l) || empty($l['listingPictures'])) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page).');
        }
        $seo = json_decode((string) base64_decode((string) ($props['seoMetadataEncoded'] ?? '')), true) ?: [];

        $description = collect($l['descriptions'] ?? [])->firstWhere('languageCode', 'PT')['description']
            ?? ($l['descriptions'][0]['description'] ?? '');
        $price = ! empty($l['privatePrice']) || empty($l['listingPrice']) ? null : (int) $l['listingPrice'];

        return [
            'url' => $url,
            'title' => trim(preg_replace('/\s*\|\s*RE\/MAX.*$/i', '', (string) ($seo['title'] ?? ''))),
            'description' => trim((string) $description),
            'price' => $price,
            'details' => $this->details($l, $price),
            'photos' => array_map(fn ($p) => self::CDN.ltrim((string) $p, '/'), array_values($l['listingPictures'])),
            'agent' => [
                'name' => trim((string) ($l['userName'] ?? '')),
                'phone' => trim((string) ($l['userCellPhone'] ?? $l['userAgencyPhoneNumber'] ?? '')),
                'photo' => ! empty($l['userPictureURL']) ? self::CDN.ltrim($l['userPictureURL'], '/') : null,
            ],
            'agency' => trim((string) ($l['officeName'] ?? '')),
            'ami' => trim((string) (($l['userAgencyLicenseNumber'] ?? '') ?: ($l['licenseNumberInfo'] ?? ''))),
        ];
    }

    /** The listing's details block, as label => value, skipping what the listing leaves blank. */
    private function details(array $l, ?int $price): array
    {
        $simNao = fn ($v) => $v === null ? null : ($v ? 'Sim' : 'Não');
        $local = implode(', ', array_filter([$l['regionName3'] ?? null, $l['regionName2'] ?? null, $l['regionName1'] ?? null]));

        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Localização' => $local,
            'Referência' => $l['listingTitle'] ?? null,
            'Área Bruta m²' => trim((string) ($l['builtAreaDisplay'] ?? '')),
            'Área Útil m²' => trim((string) ($l['livingAreaDisplay'] ?? '')),
            'Área Total do Lote m²' => trim((string) ($l['lotSizeDisplay'] ?? '')),
            'Quartos' => $l['numberOfBedrooms'] ?? null,
            'WC/Casas de banho' => $l['numberOfBathrooms'] ?? null,
            'Ano de Construção' => $l['constructionYear'] ?? null,
            'Estacionamento' => $simNao($l['parking'] ?? null),
            'Garagem' => $simNao($l['garage'] ?? null),
            'Elevador' => $simNao($l['elevator'] ?? null),
            'Carregamento de Carros Elétricos' => $simNao($l['electricCarsCharging'] ?? null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }
}
