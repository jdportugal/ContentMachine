<?php

namespace App\Services\RealEstate;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a predimed.pt listing page (predimed.pt/Imovel/<code>; Predimed Prestige's
 * predimedprestige.pt/imovel/<code> runs on the same CRM360 site). The page is
 * plain server HTML: the summary under #descricao-imovel (title, place, price and
 * reference), the description in #descricao, label/value rows in #detalhes and the
 * features in #caracteristicas, then the agent's card. Only the first photos are
 * in the page; the rest come from /Imovel/preview/images?id=<code> (a JSON list).
 * Photos are served watermarked (…/foto_marca_agua/…) and clean (…/foto/…): the
 * video uses the clean ones.
 */
class PredimedListingScraper
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    private const HOSTS = ['predimed.pt', 'predimedprestige.pt'];

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::HOSTS as $h) {
            if ($host === $h || str_ends_with($host, '.'.$h)) {
                return true;
            }
        }

        return false;
    }

    /** The listing's code (predimed.pt/Imovel/wgndg2 → wgndg2), or null when the link is not one listing. */
    public static function codigo(string $url): ?string
    {
        return preg_match('#/imovel/([a-z0-9]{4,})/?$#i', (string) parse_url($url, PHP_URL_PATH), $m) ? $m[1] : null;
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $http = fn () => Http::timeout(30)->withHeaders(['User-Agent' => self::UA]);
        $html = $http()->get($url)->throw()->body();
        $fotos = [];
        if (($codigo = self::codigo($url)) !== null) {
            try {
                $base = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);
                $fotos = (array) $http()->acceptJson()->get($base.'/Imovel/preview/images', ['id' => $codigo])->json();
            } catch (\Throwable) {
                // The page's own photos are enough to make the video.
            }
        }

        return $this->parse($html, $url, $fotos);
    }

    /** @param  list<string>  $galeria  the gallery endpoint's list; the page's own photos when empty */
    public function parse(string $html, string $url, array $galeria = []): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $resumo = $x->query('//*[@id="descricao-imovel"]')->item(0);

        $fotos = self::fotos(array_merge(array_map('strval', array_filter($galeria, 'is_string')), self::fotosDaPagina($html)));
        if (! $resumo instanceof DOMElement || $fotos === []) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single Predimed listing URL?');
        }

        $titulo = rtrim(self::texto($x->query('.//h1', $resumo)->item(0)), " ,");
        $local = '';
        $precoTexto = '';
        foreach ($x->query('.//h3', $resumo) as $h3) {
            $t = self::texto($h3);
            if (str_contains($t, '€') || preg_match('/\bRef\.|consulta/i', $t)) {
                $precoTexto = $t;
            } elseif ($local === '') {
                $local = $t;
            }
        }
        $preco = preg_match('/€\s*([\d .]+)/u', $precoTexto, $m) && ($n = (int) preg_replace('/\D/', '', $m[1])) > 0 ? $n : null;
        $ref = preg_match('/Ref\.?\s*([A-Za-z0-9-]+)/', $precoTexto, $m) ? $m[1] : null;
        $negocio = '';
        foreach ($x->query('.//span', $resumo) as $s) {
            if (preg_match('/^para\s+(comprar|arrendar|trespassar)/i', self::texto($s), $m)) {
                $negocio = strtolower($m[1]);
                break;
            }
        }
        // The three figures under the price ("3 Quartos", "0 WCs", "112,00 m² Área Útil").
        $numeros = [];
        foreach ($x->query('.//span[span]', $resumo) as $s) {
            $rotulo = self::texto($x->query('./span', $s)->item(0));
            $numeros[$rotulo] = trim(str_replace($rotulo, '', self::texto($s)));
        }

        $detalhes = [];
        foreach ($x->query('//*[@id="detalhes"]//div[span]') as $linha) {
            $rotulo = rtrim(self::texto($x->query('./span', $linha)->item(0)), ' :');
            $valor = trim(substr(self::texto($linha), strlen(self::texto($x->query('./span', $linha)->item(0)))));
            if ($rotulo !== '' && $valor !== '' && $valor !== '-') {
                $detalhes[$rotulo] = $valor;
            }
        }
        $equipamentos = [];
        foreach ($x->query('//*[@id="caracteristicas"]//label') as $l) {
            $equipamentos[] = self::texto($l);
        }

        $descricao = self::descricao($x->query('//*[@id="descricao"]')->item(0));
        if ($equipamentos !== []) {
            $descricao = trim($descricao."\n\nCaracterísticas: ".implode(', ', array_unique(array_filter($equipamentos))).'.');
        }

        [$nome, $agencia, $telefone, $fotoAgente] = self::agente($x);
        $ami = preg_match('/AMI\s*(?:n\.?\s*º?\s*)?(\d{3,})/u', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m) ? $m[1] : '';

        return [
            'url' => $url,
            'title' => $titulo,
            'description' => $descricao,
            'price' => $preco,
            'details' => $this->details($detalhes, $numeros, $preco, $local, $ref, $negocio),
            'photos' => $fotos,
            'videos' => [],
            'agent' => ['name' => $nome, 'phone' => $telefone, 'photo' => $fotoAgente],
            // The agent's office ("PREDIMED GOLD"); the network when the card names none.
            'agency' => $agencia !== '' ? $agencia : 'Predimed',
            'ami' => $ami,
        ];
    }

    /** The CRM's photos, clean (…/foto/…) instead of watermarked (…/foto_marca_agua/…), thumbnails dropped, in order, once each. */
    public static function fotos(array $urls): array
    {
        $vistas = [];
        foreach ($urls as $u) {
            $u = str_replace('\\/', '/', trim((string) $u));
            if (! preg_match('#^https?://[^/]+/imovs/[^/]+/(foto_marca_agua|foto)/[^/?]+\.(jpe?g|png|webp)#i', $u)) {
                continue;   // thumbnails (/tn/), the agent's photo, logos
            }
            $u = preg_replace('#/foto_marca_agua/#', '/foto/', $u);
            $vistas[strtok($u, '?')] = true;
        }

        return array_keys($vistas);
    }

    /** @return list<string> the listing photos written in the page (the first few). */
    private static function fotosDaPagina(string $html): array
    {
        preg_match_all('#https?://[a-z0-9.-]+/imovs/[a-z0-9]+/(?:foto_marca_agua|foto)/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)#i', $html, $m);

        return $m[0];
    }

    /** @return array{0: string, 1: string, 2: string, 3: ?string} name, office, phone, photo — from the agent's card. */
    private static function agente(DOMXPath $x): array
    {
        // The card: the agent's photo (…/users/…) and, beside it, name and office in <p>s (the
        // page wraps them in an <h5>, which the parser closes early, so they are looked for in
        // the smallest block around the photo that holds both).
        $img = $x->query('//img[contains(@src, "/users/")]')->item(0);
        $cartao = $img;
        for ($i = 0; $cartao && $i < 5 && $x->query('.//p', $cartao)->length < 2; $i++) {
            $cartao = $cartao->parentNode;
        }
        if (! $cartao || $x->query('.//p', $cartao)->length < 2) {
            return ['', '', '', null];
        }
        $ps = $x->query('.//p', $cartao);
        $telefone = '';
        foreach ($x->query('.//span', $cartao) as $s) {
            $t = preg_replace('/[^\d+]/', '', self::texto($s));
            if (preg_match('/^(\+351)?[29]\d{8}$/', $t)) {
                $telefone = ExpListingScraper::phone($t);
                break;
            }
        }

        return [self::texto($ps->item(0)), self::texto($ps->item(1)), $telefone, $img instanceof DOMElement ? ($img->getAttribute('src') ?: null) : null];
    }

    private static function texto(?\DOMNode $n): string
    {
        return $n ? trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $n->textContent))) : '';
    }

    /** The description is HTML with <br> line breaks: plain text, lines kept. */
    private static function descricao(?\DOMNode $n): string
    {
        if (! $n) {
            return '';
        }
        $t = Century21ListingScraper::texto((string) $n->ownerDocument->saveHTML($n));
        $linhas = array_map(fn ($l) => trim((string) preg_replace('/[ \t]+/u', ' ', $l)), explode("\n", str_replace("\u{a0}", ' ', $t)));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $linhas)));
    }

    private function details(array $d, array $n, ?int $preco, string $local, ?string $ref, string $negocio): array
    {
        $area = fn (?string $v) => $v !== null && preg_match('/([\d.]+(?:,\d+)?)/', $v, $m) && ($a = (float) str_replace(['.', ','], ['', '.'], $m[1])) > 0
            ? (fmod($a, 1.0) === 0.0 ? (int) $a : $a) : null;
        $inteiro = fn (?string $v) => $v !== null && preg_match('/^\d+$/', trim($v)) ? (int) $v : null;
        $tipologia = $inteiro($d['Tipologia'] ?? null);
        $tipo = trim(implode(' ', array_filter([$d['Tipo'] ?? null, $tipologia !== null && $tipologia > 0 && ! preg_match('/terreno|loja|armaz|escrit|garagem/i', (string) ($d['Tipo'] ?? '')) ? 'T'.$tipologia : null])));
        $wcs = $inteiro($n['WCs'] ?? null);
        $energia = $d['Categoria energética'] ?? null;

        return array_filter([
            'Preço' => $preco !== null ? number_format($preco, 0, ',', ' ').' €' : 'Sob consulta',
            'Negócio' => ['comprar' => 'Venda', 'arrendar' => 'Arrendamento', 'trespassar' => 'Trespasse'][$negocio] ?? null,
            'Tipo' => $tipo,
            'Localização' => trim(implode(', ', array_unique(array_filter([$d['Freguesia'] ?? null, $d['Concelho'] ?? null, $d['Distrito'] ?? null])))) ?: $local,
            'Referência' => $ref,
            'Área Bruta m²' => $area($d['Área Bruta Privativa'] ?? null),
            'Área Útil m²' => $area($d['Área útil'] ?? $d['Área Útil'] ?? $n['Área Útil'] ?? null),
            'Área Total do Lote m²' => $area($d['Área do Terreno'] ?? null),
            'Quartos' => $inteiro($n['Quartos'] ?? null) ?: $tipologia,
            'Casas de Banho' => $wcs ?: null,
            'Ano de Construção' => $inteiro($d['Ano'] ?? null),
            'Certificado Energético' => $energia !== null && ! preg_match('/sem certificado|isento/i', $energia) ? $energia : null,
            'Estado' => $d['Estado'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }
}
