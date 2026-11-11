<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads an iadportugal.pt listing page. The listing (description, price, photos,
 * agent) is in the page's Nuxt payload (`__NUXT_DATA__`): a flat JSON array
 * where objects point at their values by index, revived here.
 */
class IadListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    /** Area types in the payload's surfaceList → the details labels the script writer knows. */
    private const SURFACES = [
        'gross-area' => 'Área Bruta m²',
        'living-area' => 'Área Útil m²',
        'plot-area' => 'Área Total do Lote m²',
    ];

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'iadportugal.pt' || str_ends_with($host, '.iadportugal.pt');
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $html = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body();

        return $this->parse($html, $url);
    }

    /** The listing in the page's Nuxt payload, or null when the page has none. */
    private static function dados(string $html): ?array
    {
        if (! preg_match('#<script[^>]*id="__NUXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
            return null;
        }
        $root = self::revive(json_decode($m[1], true) ?: []);
        // The query cache moved (Nov 2026) from state["$svue-query-state"] to data["vue-query-state"]: read either.
        $l = collect($root['data']['vue-query-state']['queries'] ?? $root['state']['$svue-query-state']['queries'] ?? [])
            ->first(fn ($q) => ($q['queryKey'][0] ?? null) === 'properties')['state']['data'] ?? null;

        return is_array($l) ? $l : null;
    }

    /** The listing agent's user name on iad (their page is /consultor-imobiliario/<user name>), or null. */
    public static function consultor(string $html): ?string
    {
        $nome = trim((string) (self::dados($html)['agent']['userName'] ?? ''));

        return preg_match('/^[a-z0-9._-]+$/i', $nome) ? $nome : null;
    }

    public function parse(string $html, string $url): array
    {
        if (! str_contains($html, '__NUXT_DATA__')) {
            throw new RuntimeException('That page has no listing data — is it a single iad listing URL?');
        }
        $l = self::dados($html);
        if (! is_array($l) || empty($l['media']['photos'])) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page).');
        }

        $digits = preg_replace('/\D/', '', (string) ($l['prices']['formattedMain'] ?? ''));
        $price = $digits !== '' ? (int) $digits : null;
        $agent = $l['agent'] ?? [];

        return [
            'url' => $url,
            'title' => trim((string) ($l['seo']['metaTitle'] ?? $l['title'] ?? '')),
            'description' => trim(str_replace("\r", '', (string) ($l['description'] ?? ''))),
            'price' => $price,
            'details' => $this->details($l, $price),
            'photos' => array_values(array_map('strval', $l['media']['photos'])),
            'agent' => [
                'name' => trim((string) ($agent['fullName'] ?? '')),
                // The page hides the number behind a click; it is only base64-encoded.
                'phone' => trim((string) base64_decode((string) ($agent['directContact']['hashedPhone'] ?? ''))),
                'photo' => $agent['avatar']['src'] ?? null,
            ],
            // iad agents are independent: no office, the network is the agency.
            // Capitals: on screen it's "IAD", and lowercase "iad" is voiced "IA de Portugal".
            'agency' => 'IAD Portugal',
            'ami' => '',
        ];
    }

    private function details(array $l, ?int $price): array
    {
        $surfaces = collect($l['surfaceList'] ?? [])
            ->filter(fn ($s) => isset(self::SURFACES[$s['type'] ?? '']))
            ->mapWithKeys(fn ($s) => [self::SURFACES[$s['type']] => (string) $s['value']])->all();
        $features = collect(array_merge($l['mainFeatures'] ?? [], $l['secondaryFeatures'] ?? []))
            ->pluck('text')->filter()->implode(', ');

        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Localização' => trim(implode(', ', array_filter([$l['location']['place'] ?? null, $l['location']['postcode'] ?? null]))),
            'Referência' => $l['propertyListingRef'] ?? null,
            ...$surfaces,
            'Quartos' => $l['bedroomsCount'] ?? null,
            'Ano de Construção' => $l['constructionYear'] ?? null,
            'Certificado Energético' => $l['epcGes']['class'] ?? null,
            'Características' => $features,
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }

    /**
     * Nuxt's payload (devalue): element 0 is the root; inside objects and arrays
     * every integer is the index of the real value. ["Reactive", i] and the like
     * wrap a value; ["Date", s] is a date.
     */
    public static function revive(array $flat): mixed
    {
        $memo = [];
        $get = function (int $i) use (&$get, &$memo, $flat) {
            if (array_key_exists($i, $memo)) {
                return $memo[$i];
            }
            $v = $flat[$i] ?? null;
            if (is_array($v) && array_is_list($v) && is_string($v[0] ?? null)
                && in_array($v[0], ['Reactive', 'ShallowReactive', 'Ref', 'ShallowRef', 'EmptyRef', 'Date', 'Set', 'Map', 'null'], true)) {
                return $memo[$i] = $v[0] === 'Date' ? $v[1] : (isset($v[1]) ? $get($v[1]) : null);
            }
            if (is_array($v)) {
                $memo[$i] = null; // a cycle reads as null instead of looping
                $out = [];
                foreach ($v as $k => $x) {
                    $out[$k] = is_int($x) ? ($x < 0 ? null : $get($x)) : $x;
                }

                return $memo[$i] = $out;
            }

            return $memo[$i] = $v;
        };

        return $flat === [] ? null : $get(0);
    }
}
