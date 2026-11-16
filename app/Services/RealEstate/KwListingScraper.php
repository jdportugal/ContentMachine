<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a kwportugal.pt listing page (…/pt/Imovel/Venda/…/<id>/KWPT-<number>).
 * Like eXp, the listing is in the page's Next.js flight payload: the `property`
 * prop holds price, areas, photos and the agent, and its description points at
 * a separate text row (`"$5a"` → `5a:T<hex byte length>,<text>`).
 */
class KwListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'kwportugal.pt' || str_ends_with($host, '.kwportugal.pt');
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
        $photos = collect(is_array($l) ? ($l['images'] ?? []) : [])
            ->sortBy(fn ($i) => (int) ($i['order'] ?? 0))
            ->map(fn ($i) => (string) ($i['url'] ?? ''))->filter()->unique()->values()->all();
        if (! is_array($l) || $photos === []) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single KW listing URL?');
        }
        $v = fn (string $k) => ($x = $l[$k] ?? null) === '$undefined' ? null : $x;
        $price = is_numeric($v('price')) && (int) $v('price') > 0 ? (int) $v('price') : null;

        return [
            'url' => $url,
            'title' => trim((string) ($v('name') ?? $v('designation') ?? '')),
            'description' => trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", ExpListingScraper::text($flight, (string) ($v('description') ?? ''))))),
            'price' => $price,
            'details' => $this->details($v, $price),
            'photos' => $photos,
            'videos' => array_values(array_unique(array_filter(array_map(fn ($k) => (string) ($v($k) ?? ''), ['uploadedVideoFile', 'videoLink', 'videoLink1', 'videoLink2'])))),
            'agent' => [
                'name' => trim((string) ($v('agentName') ?? '')),
                'phone' => ExpListingScraper::phone((string) ($v('agentMobile') ?? $v('agentPhone') ?? '')),
                'photo' => $v('agentImage') ?: null,
            ],
            // The market centre ("KW Select Cascais"); the network when the page names none.
            'agency' => trim((string) ($v('agencyName') ?? '')) ?: 'KW Portugal',
            'ami' => '',
        ];
    }

    private function details(callable $v, ?int $price): array
    {
        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Tipo' => trim(implode(' ', array_filter([$v('type'), $v('typology')]))),
            'Localização' => trim(implode(', ', array_unique(array_filter([$v('region3'), $v('region2'), $v('region1')])))),
            'Referência' => $v('reference'),
            'Área Bruta m²' => $v('totalArea'),
            'Área Útil m²' => $v('livingArea'),
            'Área Total do Lote m²' => $v('landArea'),
            'Quartos' => $v('rooms'),
            'Suites' => $v('suites'),
            'Casas de Banho' => $v('bathrooms'),
            'Lugares de Estacionamento' => $v('parkingPlaces'),
            'Ano de Construção' => $v('constructionYear'),
            'Certificado Energético' => $v('energyClass'),
            'Estado' => $v('state'),
            'Características' => collect(is_array($v('features')) ? $v('features') : [])
                ->map(fn ($f) => is_array($f) ? ($f['name'] ?? $f['designation'] ?? $f['description'] ?? null) : $f)->filter()->implode(', '),
        ], fn ($x) => $x !== null && $x !== '' && $x !== 0 && $x !== '0');
    }
}
