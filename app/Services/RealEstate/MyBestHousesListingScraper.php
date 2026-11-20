<?php

namespace App\Services\RealEstate;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Reads a mybesthouses.com listing (mybesthouses.com/property/<uuid>). The site is a
 * single-page app: the server sends an empty shell and the browser fetches the data,
 * so the page is rendered in headless Chrome first (remotion/scripts/render-html.mjs).
 * Once rendered, the page carries a printable brochure (#print-root) with everything in
 * a plain layout: title, place, price, a row of figures (Área, Quartos, Banheiros, Vagas,
 * Tipo), the description, the amenities and the agent. The photos are the gallery's
 * (static.portadafrente.com, the CRM the agency lists from).
 */
class MyBestHousesListingScraper
{
    public const NOME = 'My Best Houses';

    public static function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'mybesthouses.com' || str_ends_with($host, '.mybesthouses.com');
    }

    /** A listing is /property/<id>; searches (/venda?transacao=venda) and pages are not. */
    public static function eImovel(string $url): bool
    {
        return (bool) preg_match('#^/property/[a-z0-9-]{6,}/?$#i', (string) parse_url($url, PHP_URL_PATH));
    }

    /** @return array Same shape as RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $dir = rtrim((string) config('contentmachine.clips.remotion_path'), '/');
        $p = new Process(['node', $dir.'/scripts/render-html.mjs', '--url', $url, '--wait', '#print-root h1', '--timeout', '45000'], $dir, timeout: 120);
        $p->run();
        if (! $p->isSuccessful() || trim($p->getOutput()) === '') {
            throw new RuntimeException('Could not open the My Best Houses page: '.(trim($p->getErrorOutput()) ?: 'no output'));
        }

        return $this->parse($p->getOutput(), $url);
    }

    public function parse(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $folha = $x->query('//*[@id="print-root"]')->item(0);

        $fotos = [];
        preg_match_all('#https://static\.portadafrente\.com/[^"\'\s)<>]+?\.(?:jpe?g|png|webp)#i', $html, $m);
        foreach ($m[0] as $u) {
            $fotos[html_entity_decode($u)] = true;
        }
        $fotos = array_keys($fotos);
        $titulo = $folha ? self::texto($x->query('.//h1', $folha)->item(0)) : '';
        if (! $folha instanceof DOMElement || $titulo === '' || $fotos === []) {
            throw new RuntimeException('Could not read the listing (no data or no photos on that page) — is it a single My Best Houses listing URL?');
        }

        // The row of figures: <p>label</p><p>value</p> in each cell.
        $numeros = [];
        foreach ($x->query('.//div[count(p)=2]', $folha) as $c) {
            $numeros[self::texto($x->query('./p', $c)->item(0))] = self::texto($x->query('./p', $c)->item(1));
        }
        $h1 = $x->query('.//h1', $folha)->item(0);
        $local = self::texto($x->query('following-sibling::div[1]', $h1)->item(0));
        $precoTexto = self::texto($x->query('following-sibling::div[2]', $h1)->item(0));
        $preco = ($n = (int) preg_replace('/\D/', '', $precoTexto)) > 0 ? $n : null;

        $paragrafos = [];
        foreach ($x->query('.//h2[normalize-space()="Descrição"]/following-sibling::div[1]/p', $folha) as $p) {
            $paragrafos[] = self::texto($p);
        }
        $descricao = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $paragrafos)));
        $comodidades = [];
        foreach ($x->query('.//h2[normalize-space()="Comodidades"]/following-sibling::div[1]/span', $folha) as $s) {
            $comodidades[] = self::texto($s);
        }
        if ($comodidades !== []) {
            $descricao = trim($descricao."\n\nComodidades: ".implode(', ', array_filter($comodidades)).'.');
        }

        // The agent: the brochure's footer (photo, name, role, phone).
        $nome = '';
        $telefone = '';
        $fotoAgente = null;
        $img = $x->query('.//img[contains(@class, "rounded-full")]', $folha)->item(0);
        if ($img instanceof DOMElement) {
            $nome = self::texto($x->query('following-sibling::div[1]/p[1]', $img)->item(0));
            $fotoAgente = self::absoluto($img->getAttribute('src'), $url);
        }
        // The brochure's picture can be a banner of both founders; the page's own contact card has the agent's headshot.
        if ($nome !== '' && ($retrato = $x->query('//h3[normalize-space()="'.str_replace('"', '', $nome).'"]/preceding-sibling::div[1]//img')->item(0)) instanceof DOMElement) {
            $fotoAgente = self::absoluto($retrato->getAttribute('src'), $url);
        }
        foreach ($x->query('.//p', $folha) as $p) {
            $t = preg_replace('/[^\d+]/', '', self::texto($p));
            if (preg_match('/^(\+351)?[29]\d{8}$/', $t)) {
                $telefone = ExpListingScraper::phone($t);
                break;
            }
        }
        // The page's own badge: "Venda" or "Arrendamento".
        $negocio = '';
        foreach ($x->query('//main//div[contains(@class, "inline-flex")]') as $b) {
            if (preg_match('/^(Venda|Arrendamento)$/u', self::texto($b), $mm)) {
                $negocio = $mm[1];
                break;
            }
        }

        return [
            'url' => $url,
            'title' => $titulo,
            'description' => $descricao,
            'price' => $preco,
            'details' => $this->details($numeros, $preco, $local, $negocio, $titulo),
            'photos' => $fotos,
            'videos' => [],
            'agent' => ['name' => $nome, 'phone' => $telefone, 'photo' => $fotoAgente],
            'agency' => self::NOME,
            'ami' => '',
        ];
    }

    private static function absoluto(string $src, string $url): ?string
    {
        if ($src === '') {
            return null;
        }

        return preg_match('#^https?://#', $src) ? $src : parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/'.ltrim($src, '/');
    }

    private static function texto(?\DOMNode $n): string
    {
        return $n ? trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $n->textContent))) : '';
    }

    private function details(array $n, ?int $preco, string $local, string $negocio, string $titulo): array
    {
        $inteiro = fn (?string $v) => $v !== null && preg_match('/^(\d+)/', trim($v), $m) ? (int) $m[1] : null;
        $area = $n['Área'] ?? null;
        $area = $area !== null && preg_match('/([\d.]+(?:,\d+)?)/', $area, $m) && ($a = (float) str_replace(['.', ','], ['', '.'], $m[1])) > 0 ? (fmod($a, 1.0) === 0.0 ? (int) $a : $a) : null;
        $quartos = $inteiro($n['Quartos'] ?? null);
        $tipo = $n['Tipo'] ?? null;

        return array_filter([
            'Preço' => $preco !== null ? number_format($preco, 0, ',', ' ').' €' : 'Sob consulta',
            'Negócio' => $negocio ?: null,
            'Tipo' => preg_match('/^(\S+ T\d+)/u', $titulo, $m) ? $m[1] : trim(implode(' ', array_filter([$tipo, $quartos ? 'T'.$quartos : null]))),
            'Localização' => $local,
            'Área Útil m²' => $area,
            'Quartos' => $quartos,
            'Casas de Banho' => $inteiro($n['Banheiros'] ?? $n['Casas de banho'] ?? null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }
}
