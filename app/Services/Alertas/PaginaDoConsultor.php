<?php

namespace App\Services\Alertas;

use App\Services\RealEstate\ExpListingScraper;
use App\Services\RealEstate\IadListingScraper;
use App\Services\RealEstate\ListingScraper;
use Illuminate\Support\Facades\Http;

/**
 * An agent's public page on their portal, and the listings on it. Found from one of
 * their listings: RE/MAX puts the agent's number in the listing's reference, eXp agents
 * have a site of their own, and iad names the agent in the listing's page.
 */
class PaginaDoConsultor
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    /** @return array{portal: string, chave: string, url: string}|null */
    public function de(string $anuncio, int $timeout = 30): ?array
    {
        $host = strtolower((string) parse_url($anuncio, PHP_URL_HOST));
        $caminho = (string) parse_url($anuncio, PHP_URL_PATH);

        switch (ListingScraper::portal($anuncio)) {
            case 'remax':   // …/imoveis/<slug>/125411383-12: agent 125411383, their 12th listing
                return preg_match('#/(\d{5,})-\d+/?$#', $caminho, $m)
                    ? ['portal' => 'remax', 'chave' => 'remax:'.$m[1], 'url' => 'https://remax.pt/pt/agente/consultor/'.$m[1]]
                    : null;
            case 'exp':     // brunocouto.expportugal.com: the agent's own site, listings on its first page
                $sub = substr($host, 0, -strlen('.expportugal.com'));

                return str_ends_with($host, '.expportugal.com') && $sub !== 'www' && preg_match('/^[a-z0-9-]+$/', $sub)
                    ? ['portal' => 'exp', 'chave' => 'exp:'.$sub, 'url' => 'https://'.$host.'/']
                    : null;
            case 'iad':
                $nome = IadListingScraper::consultor($this->ler($anuncio, $timeout));

                return $nome ? ['portal' => 'iad', 'chave' => 'iad:'.strtolower($nome), 'url' => 'https://www.iadportugal.pt/consultor-imobiliario/'.$nome] : null;
        }

        return null;
    }

    /**
     * The listings on the agent's page, newest first as the portal shows them.
     *
     * @return list<array{id: string, titulo: string, url: ?string}>
     */
    public function anuncios(string $portal, string $url): array
    {
        $html = $this->ler($url);

        return match ($portal) {
            'remax' => self::remax($html),
            'iad' => self::iad($html),
            'exp' => self::exp($html),
            default => [],
        };
    }

    /** The email the agent publishes on their page: RE/MAX and eXp show it, iad does not. */
    public function email(array $pagina, int $timeout = 30): ?string
    {
        $email = match ($pagina['portal']) {
            'remax' => self::emailRemax($this->ler($pagina['url'], $timeout)),
            'exp' => self::emailExp($this->ler($pagina['url'], $timeout)),
            default => null,
        };

        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : null;
    }

    public static function emailRemax(string $html): ?string
    {
        if (! preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
            return null;
        }
        $agente = json_decode((string) base64_decode((string) (json_decode($m[1], true)['props']['pageProps']['agentEncoded'] ?? '')), true);

        return trim((string) ($agente['email'] ?? '')) ?: null;
    }

    public static function emailExp(string $html): ?string
    {
        $flight = ExpListingScraper::flight($html);

        return preg_match('/"contactFormEmail":"([^"\s]+@[^"\s]+)"/', $flight, $m) || preg_match('/"email":"([^"\s]+@[^"\s]+)"/', $flight, $m) ? trim($m[1]) : null;
    }

    private function ler(string $url, int $timeout = 30): string
    {
        return Http::timeout($timeout)->withHeaders(['User-Agent' => self::UA])->get($url)->throw()->body();
    }

    /** RE/MAX: the agent's active listings are in the page's Next.js data (first 10, newest first). */
    public static function remax(string $html): array
    {
        if (! preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
            return [];
        }
        $props = json_decode($m[1], true)['props']['pageProps'] ?? [];
        $lista = json_decode((string) base64_decode((string) ($props['relatedListingsEncoded'] ?? '')), true)['activeListings']['results'] ?? [];
        $out = [];
        foreach ((array) $lista as $l) {
            $ref = trim((string) ($l['listingTitle'] ?? ''));
            $slug = trim((string) ($l['descriptionTags'] ?? ''));
            if (! preg_match('/^\d+-\d+$/', $ref)) {
                continue;
            }
            // "venda-apartamento-t2-gondomar-…" → "Apartamento T2"
            $p = explode('-', $slug);
            $tipo = trim(ucfirst($p[1] ?? 'Imóvel').(preg_match('/^t\d/', $p[2] ?? '') ? ' '.strtoupper($p[2]) : ''));
            $local = trim((string) ($l['regionName3'] ?? '')) ?: trim((string) ($l['regionName2'] ?? ''));
            $out[] = ['id' => $ref, 'titulo' => $tipo.($local !== '' ? ' em '.$local : ''), 'url' => $slug !== '' ? 'https://remax.pt/pt/imoveis/'.$slug.'/'.$ref : null];
        }

        return $out;
    }

    /** iad: the agent's page links each listing as /anuncio/<slug>/r<number>. */
    public static function iad(string $html): array
    {
        preg_match_all('#href="(/anuncio/([a-z0-9-]+)/(r\d+))["?]#', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $out[$x[3]] ??= ['id' => $x[3], 'titulo' => ucfirst(str_replace('-', ' ', $x[2])), 'url' => 'https://www.iadportugal.pt'.$x[1]];
        }

        return array_values($out);
    }

    /** eXp: the agent's site lists each listing (id, title, place) for search engines; the link is not in the page. */
    public static function exp(string $html): array
    {
        $texto = '"((?:[^"\\\\]|\\\\.)*)"';
        preg_match_all('/\["\$","li","([0-9a-f-]{36})",\{"children":\["\$","article",null,\{"children":\[\["\$","h3",null,\{"children":'.$texto.'\}\](?:,\["\$","p",null,\{"children":'.$texto.'\}\])?/',
            ExpListingScraper::flight($html), $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $titulo = trim((string) json_decode('"'.$x[2].'"'));
            $local = trim((string) json_decode('"'.($x[3] ?? '').'"'));
            $out[$x[1]] ??= ['id' => $x[1], 'titulo' => $titulo !== '' ? $titulo : $local, 'url' => null];
        }

        return array_values($out);
    }
}
