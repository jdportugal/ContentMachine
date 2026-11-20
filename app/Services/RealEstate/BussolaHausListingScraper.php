<?php

namespace App\Services\RealEstate;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a bussolatchaus.com listing (bussolatchaus.com/property/<slug>/). The site is
 * WordPress with the Essential Real Estate plugin, served as plain HTML: the title and
 * price in the header, the figures (reference, area, rooms, bathrooms) under it, the
 * gallery (each photo's full size in its zoom link), the description, the place
 * (País, Distrito, Concelho, Freguesia) and a "Visão Geral" list of label/value rows.
 * The agency is small and lists no agent per listing: its own name and phone are used.
 */
class BussolaHausListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public const NOME = 'Bússola Haus';

    public const TELEFONE = '+351927131993';

    public const AMI = '26749';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'bussolatchaus.com' || str_ends_with($host, '.bussolatchaus.com');
    }

    /** A listing is /property/<slug>/; the rest of the site (home, searches, pages) is not. */
    public static function eImovel(string $url): bool
    {
        return (bool) preg_match('#^/(?:[a-z]{2}/)?property/[^/]+/?$#i', (string) parse_url($url, PHP_URL_PATH));
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        return $this->parse(Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body(), $url);
    }

    public function parse(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $cls = fn (string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' {$c} ')";

        $titulo = self::texto($x->query("//*[{$cls('ere__single-property-title')}]")->item(0));
        $fotos = [];
        foreach ($x->query("//*[{$cls('property-gallery-item')}]//a[{$cls('zoomGallery')}]/@href") as $a) {
            $fotos[strtok(trim($a->nodeValue), '?')] = true;
        }
        $fotos = array_keys($fotos);
        if ($fotos === []) {
            // No zoom links: the gallery's own images, without WordPress's size suffix (-870x420).
            foreach ($x->query("//*[{$cls('property-gallery-item')}]//img/@src") as $s) {
                $fotos[preg_replace('/-\d+x\d+(\.\w+)$/', '$1', strtok(trim($s->nodeValue), '?'))] = true;
            }
            $fotos = array_keys($fotos);
        }
        if ($titulo === '' || $fotos === []) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single Bússola Haus listing URL?');
        }

        // "Visão Geral" rows and the place: <strong>label</strong> <span>value</span>.
        $d = [];
        foreach ($x->query('//li[strong]|//div[contains(@class, "property-locatio")][strong]') as $li) {
            $rotulo = self::texto($x->query('./strong', $li)->item(0));
            $valor = self::texto($x->query('./span', $li)->item(0));
            if ($rotulo !== '' && $valor !== '' && ! isset($d[$rotulo])) {
                $d[$rotulo] = $valor;
            }
        }
        // The figures under the title ("60 m²" Área Útil, "2" Quartos…).
        foreach ($x->query("//*[{$cls('ere__lpi-content')}]") as $c) {
            $rotulo = self::texto($x->query(".//*[{$cls('ere__lpi-label')}]", $c)->item(0));
            if ($rotulo !== '' && ! isset($d[$rotulo])) {
                $d[$rotulo] = self::texto($x->query(".//*[{$cls('ere__lpi-value')}]", $c)->item(0));
            }
        }

        $precoTexto = $d['Preço'] ?? self::texto($x->query("//*[{$cls('property-price')}]")->item(0));
        $preco = preg_match('/([\d.]+)(?:,\d{2})?/', str_replace(' ', '', $precoTexto), $m) && ($n = (int) str_replace('.', '', $m[1])) > 0 ? $n : null;

        $descricao = '';
        foreach ($x->query('//h2[normalize-space()="Descrição"]/../following-sibling::*[1]') as $bloco) {
            $descricao = self::descricao($bloco);
        }

        return [
            'url' => $url,
            'title' => $titulo,
            'description' => $descricao,
            'price' => $preco,
            'details' => $this->details($d, $preco),
            'photos' => $fotos,
            'videos' => [],
            'agent' => ['name' => self::NOME, 'phone' => self::TELEFONE, 'photo' => null],
            'agency' => self::NOME,
            'ami' => preg_match('/AMI\s*(?:n\.?\s*º?\s*)?(\d{3,})/u', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m) ? $m[1] : self::AMI,
        ];
    }

    private static function texto(?\DOMNode $n): string
    {
        return $n ? trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $n->textContent))) : '';
    }

    /** Paragraphs with <br> line breaks: plain text, lines kept. The site often repeats the text in English after the Portuguese; that part is dropped. */
    private static function descricao(\DOMNode $n): string
    {
        $t = Century21ListingScraper::texto((string) $n->ownerDocument->saveHTML($n));
        $linhas = array_map(fn ($l) => trim((string) preg_replace('/[ \t]+/u', ' ', $l)), explode("\n", str_replace("\u{a0}", ' ', $t)));
        $texto = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $linhas)));
        if (preg_match('/\n(?:PRICE:|\d-Bedroom|[A-Z][a-z]+ (?:Apartment|House|Villa)\b)/u', $texto, $m, PREG_OFFSET_CAPTURE)) {
            $texto = trim(substr($texto, 0, $m[0][1]));
        }

        return $texto;
    }

    private function details(array $d, ?int $preco): array
    {
        $area = fn (?string $v) => $v !== null && preg_match('/([\d.]+(?:,\d+)?)/', $v, $m) && ($a = (float) str_replace(['.', ','], ['', '.'], $m[1])) > 0
            ? (fmod($a, 1.0) === 0.0 ? (int) $a : $a) : null;
        $inteiro = fn (?string $v) => $v !== null && preg_match('/^(\d+)/', trim($v), $m) ? (int) $m[1] : null;
        $quartos = $inteiro($d['Quartos'] ?? $d['Quarto'] ?? null);
        $tipo = $d['Tipo de Propriedade'] ?? null;
        $situacao = strtolower((string) ($d['Situação da Propriedade'] ?? ''));

        return array_filter([
            'Preço' => $preco !== null ? number_format($preco, 0, ',', ' ').' €' : 'Sob consulta',
            'Negócio' => str_contains($situacao, 'arrend') ? 'Arrendamento' : (str_contains($situacao, 'compr') || str_contains($situacao, 'vend') ? 'Venda' : null),
            'Tipo' => trim(implode(' ', array_filter([$tipo, $tipo !== null && $quartos && ! preg_match('/terreno|loja|armaz|escrit|garagem/i', $tipo) ? 'T'.$quartos : null]))),
            'Localização' => implode(', ', array_unique(array_filter([$d['Freguesia'] ?? null, $d['Concelho'] ?? null, $d['Distrito'] ?? null]))),
            'Referência' => $d['Ref.ª do Imóvel'] ?? null,
            'Área Útil m²' => $area($d['Área Útil'] ?? null),
            'Área Bruta m²' => $area($d['Área Bruta'] ?? null),
            'Área Total do Lote m²' => $area($d['Área do Terreno'] ?? $d['Terreno'] ?? null),
            'Quartos' => $quartos,
            'Casas de Banho' => $inteiro($d['Casas de banho'] ?? $d['Casa de banho'] ?? null),
            'Ano de Construção' => $inteiro($d['Ano de Construção'] ?? null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }
}
