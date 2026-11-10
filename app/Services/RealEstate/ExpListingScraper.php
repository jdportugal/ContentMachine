<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads an expportugal.com listing page (the agents' microsites too, e.g.
 * andreiaramalho.expportugal.com/imoveis/…). The listing is in the page's
 * Next.js flight payload (`self.__next_f.push([1,"…"])` chunks): the
 * `initialProperty` prop holds price, photos and agent, and its description
 * points at a separate text row (`"$1b"` → `1b:T<hex byte length>,<text>`).
 */
class ExpListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'expportugal.com' || str_ends_with($host, '.expportugal.com');
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $html = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body();

        return $this->parse($html, $url);
    }

    public function parse(string $html, string $url): array
    {
        $flight = self::flight($html);
        $l = self::jsonAfter($flight, '"initialProperty":');
        if (! is_array($l) || empty($l['images'])) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single eXp listing URL?');
        }
        // The microsite's own agent (proper name casing, its portrait) wins over the feed's.
        $override = self::jsonAfter($flight, '"agentOverride":');
        $agent = array_filter(is_array($override) ? $override : []) + array_filter($l['agent'] ?? []);
        $price = is_numeric($l['price'] ?? null) ? (int) $l['price'] : null;

        return [
            'url' => $url,
            'title' => trim((string) ($l['title'] ?? '')),
            'description' => trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", self::text($flight, (string) ($l['description'] ?? ''))))),
            'price' => $price,
            'details' => $this->details($l, $price),
            'photos' => array_values(array_unique(array_map('strval', $l['images']))),
            // Walkthrough videos: direct mp4 (Apimo) or YouTube links.
            'videos' => array_values(array_unique(array_filter(array_map(fn ($v) => is_array($v) ? (string) ($v['url'] ?? '') : (string) $v, (array) ($l['video_urls'] ?? []))))),
            'agent' => [
                'name' => trim((string) ($agent['name'] ?? '')),
                'phone' => self::phone((string) ($l['agent']['phone'] ?? $agent['phone'] ?? '')),
                'photo' => $agent['photo'] ?? null,
            ],
            'agency' => 'eXp Portugal',
            'ami' => trim((string) ($l['agent']['licence_number'] ?? '')),
        ];
    }

    private function details(array $l, ?int $price): array
    {
        $v = fn (string $k) => ($x = $l[$k] ?? null) === '$undefined' ? null : $x;

        return array_filter([
            'Preço' => $price !== null ? number_format($price, 0, ',', ' ').' €' : 'Sob consulta',
            'Tipo' => $v('property_type'),
            'Localização' => trim(implode(', ', array_filter([$v('city'), $v('state')]))),
            'Referência' => $v('source_id'),
            'Área m²' => $v('square_feet'), // despite the name, in m² (area_unit "sqm")
            'Área Útil m²' => $v('net_area'),
            'Área Total do Lote m²' => $v('plot_size_sqm'),
            'Quartos' => $v('bedrooms'),
            'Casas de Banho' => $v('bathrooms'),
            'Ano de Construção' => $v('construction_year') ?? $v('build_year'),
            'Certificado Energético' => $v('energy_efficiency_class'),
            'Características' => collect($v('features') ?? [])->map(fn ($f) => is_array($f) ? ($f['name'] ?? $f['label'] ?? null) : $f)->filter()->implode(', '),
        ], fn ($x) => $x !== null && $x !== '' && $x !== 0 && $x !== '0');
    }

    /** All flight chunks, unescaped and joined: the RSC stream as the browser sees it. */
    public static function flight(string $html): string
    {
        preg_match_all('/self\.__next_f\.push\(\[1,"((?:[^"\\\\]|\\\\.)*)"\]\)/s', $html, $m);

        return implode('', array_map(fn ($s) => (string) json_decode('"'.$s.'"'), $m[1]));
    }

    /** The JSON object right after $marker, cut at its matching brace. */
    public static function jsonAfter(string $s, string $marker): ?array
    {
        $at = strpos($s, $marker);
        if ($at === false || ($start = strpos($s, '{', $at)) === false) {
            return null;
        }
        $depth = 0;
        $in = false;
        for ($i = $start, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            if ($in) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $in = false;
                }
            } elseif ($c === '"') {
                $in = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                return json_decode(substr($s, $start, $i - $start + 1), true);
            }
        }

        return null;
    }

    /** A "$1b"-style reference to a text row (`1b:T<hex bytes>,…`), or the value itself. */
    public static function text(string $s, string $value): string
    {
        if (! preg_match('/^\$([0-9a-f]+)$/', $value, $ref)) {
            return $value;
        }
        if (! preg_match('/(?<![0-9a-z])'.$ref[1].':T([0-9a-f]+),/', $s, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        return substr($s, $m[0][1] + strlen($m[0][0]), hexdec($m[1][0]));
    }

    /** Portuguese numbers come bare ("911982972") on the microsite: give them the country code. */
    public static function phone(string $p): string
    {
        $p = preg_replace('/[^\d+]/', '', $p);

        return preg_match('/^[29]\d{8}$/', $p) ? '+351'.$p : $p;
    }
}
