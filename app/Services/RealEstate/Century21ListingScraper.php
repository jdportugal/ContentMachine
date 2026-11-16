<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a century21.pt listing page (…/comprar/C0242-02837). Like eXp and KW, the
 * listing is in the page's Next.js flight payload: the `property` prop holds price,
 * areas, photos, agent and agency; its description (per language) points at a text
 * row (`"$25"` → `25:T<hex byte length>,<html>`).
 */
class Century21ListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    private const TIPOS = ['house' => 'Moradia', 'apartment' => 'Apartamento', 'land' => 'Terreno', 'shop' => 'Loja', 'office' => 'Escritório', 'warehouse' => 'Armazém', 'farm' => 'Quinta', 'garage' => 'Garagem', 'building' => 'Prédio'];

    private const ESTADOS = ['new' => 'Novo', 'used' => 'Usado', 'renovated' => 'Renovado', 'under_construction' => 'Em construção', 'to_renovate' => 'Para recuperar'];

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'century21.pt' || str_ends_with($host, '.century21.pt');
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $html = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body();

        return $this->parse($html, $url);
    }

    public function parse(string $html, string $url): array
    {
        $flight = ExpListingScraper::flight($html);
        $l = ExpListingScraper::jsonAfter($flight, '"property":');
        $fotos = array_values(array_unique(array_filter(array_map('strval', is_array($l) ? (array) ($l['images'] ?? []) : []))));
        if (! is_array($l) || $fotos === []) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single Century 21 listing URL?');
        }
        $pt = fn ($v) => is_array($v) ? ($v['pt'] ?? reset($v) ?: '') : (string) $v;
        $price = ! empty($l['price_hidden']) || ! is_numeric($l['price'] ?? null) || (int) $l['price'] <= 0 ? null : (int) $l['price'];
        $agent = (array) ($l['agent'] ?? []);
        $agency = (array) ($l['agency'] ?? []);

        return [
            'url' => $url,
            // In capitals as on the site ("MORADIA T3 COM … | VALE DE MILHAÇOS"): the script writer titles the video itself.
            'title' => trim($pt($l['title'] ?? '')),
            'description' => self::texto(ExpListingScraper::text($flight, $pt($l['description'] ?? ''))),
            'price' => $price,
            'details' => $this->details($l, $price),
            'photos' => $fotos,
            'videos' => array_values(array_filter([(string) ($l['video_url'] ?? '')])),
            'agent' => [
                'name' => trim((string) ($agent['name'] ?? '')),
                'phone' => ExpListingScraper::phone((string) (($agent['phone'] ?? '') ?: ($l['agency_phone'] ?? ''))),
                'photo' => ($agent['image'] ?? null) ?: null,
            ],
            'agency' => trim((string) ($agency['name'] ?? '')) ?: 'Century 21 Portugal',
            'ami' => trim((string) (($agency['license_number'] ?? '') ?: ($l['agency_license_number'] ?? ''))),
        ];
    }

    /** The description is HTML (<br>, <p>, &amp;): plain text, paragraphs kept. */
    public static function texto(string $html): string
    {
        $t = preg_replace('#<\s*br\s*/?>|</p>#i', "\n", $html);
        $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]+\n/", "\n", str_replace(["\r\n", "\r"], "\n", $t));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", (string) $t));
    }

    private function details(array $l, ?int $price): array
    {
        $tipo = self::TIPOS[$l['asset_type'] ?? ''] ?? null;
        $tipologia = isset($l['number_of_rooms']) && in_array($l['asset_type'] ?? '', ['house', 'apartment'], true) ? 'T'.(int) $l['number_of_rooms'] : null;

        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Tipo' => trim(implode(' ', array_filter([$tipo, $tipologia]))),
            'Localização' => trim(implode(', ', array_unique(array_filter([$l['ad_3'] ?? null, $l['ad_2'] ?? null, $l['ad_1'] ?? null])))),
            'Referência' => $l['reference'] ?? null,
            'Área Bruta m²' => $l['gross_area'] ?? null,
            'Área Útil m²' => $l['useful_area'] ?? null,
            'Área Total do Lote m²' => $l['terrain_area'] ?? null,
            'Quartos' => $l['number_of_rooms'] ?? null,
            'Casas de Banho' => $l['number_of_wcs'] ?? null,
            'Lugares de Estacionamento' => $l['number_of_parking_spots'] ?? null,
            'Ano de Construção' => $l['building_year'] ?? null,
            'Certificado Energético' => $l['energy_efficiency'] ?? null,
            'Estado' => self::ESTADOS[$l['condition'] ?? ''] ?? null,
        ], fn ($x) => $x !== null && $x !== '' && $x !== 0 && $x !== '0');
    }
}
