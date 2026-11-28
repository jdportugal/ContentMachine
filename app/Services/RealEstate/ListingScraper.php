<?php

namespace App\Services\RealEstate;

use RuntimeException;

/**
 * Picks the scraper for a listing URL. One line per portal in PORTAIS: its key
 * (also its theme, '<key>-premium') and the class that reads it. Imocena's own
 * demo listings (/demo/<slug>) come last. idealista was dropped: it blocks
 * automated reading.
 */
class ListingScraper
{
    /** @var array<string, class-string> portal key => scraper */
    public const PORTAIS = [
        'remax' => RemaxListingScraper::class,
        'iad' => IadListingScraper::class,
        'exp' => ExpListingScraper::class,
        'kw' => KwListingScraper::class,
        'century21' => Century21ListingScraper::class,
        'predimed' => PredimedListingScraper::class,
        'bussolahaus' => BussolaHausListingScraper::class,
        'mybesthouses' => MyBestHousesListingScraper::class,
        'demo' => DemoListingScraper::class,
    ];

    /** The portals as a customer reads them, in the order they are listed on the site. */
    public const NOMES = ['RE/MAX', 'iad Portugal', 'eXp Portugal', 'KW Portugal', 'Century 21', 'Predimed', 'Bússola Haus', 'My Best Houses'];

    public const UNSUPPORTED = 'Only RE/MAX, iad, eXp, KW, Century 21, Predimed, Bússola Haus and My Best Houses listings are supported.';

    /** What a customer is told when the link is from another site. */
    public const MENSAGEM = 'Por agora lemos anúncios da RE/MAX, da iad Portugal, da eXp Portugal, da KW Portugal, da Century 21, da Predimed, da Bússola Haus e da My Best Houses. Cole o link de um desses sites.';

    /** What an agent is told when they paste their own page or a search instead of one property. */
    public const NAO_E_IMOVEL = 'Este link é de uma página de consultor ou de uma pesquisa, não de um imóvel. Abra o imóvel que quer em vídeo e copie o link dessa página.';

    /**
     * A page on a portal we read that is not one property: an agent's or agency's page, or a
     * search (RE/MAX: /comprar/…, /arrendar/…, ?s=…). Agents mix these up with a listing; caught
     * when the link is pasted, so they are told at once instead of getting a failed video.
     */
    public static function naoEImovel(string $url): bool
    {
        $portal = self::portal($url);
        if ($portal === null || $portal === 'demo') {
            return false;
        }
        $path = strtolower(rtrim((string) parse_url($url, PHP_URL_PATH), '/'));
        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($path === '' || preg_match('#^/(pt|en)$#', $path)) {
            return true;   // the portal's home page
        }
        // Predimed: a property is /Imovel/<code>; everything else (searches, /equipa, an agent's /nome.apelido) is not.
        if ($portal === 'predimed') {
            return PredimedListingScraper::codigo($url) === null;
        }
        // Bússola Haus (/property/<slug>/) and My Best Houses (/property/<id>): anything else is a page or a search.
        if ($portal === 'bussolahaus') {
            return ! BussolaHausListingScraper::eImovel($url);
        }
        if ($portal === 'mybesthouses') {
            return ! MyBestHousesListingScraper::eImovel($url);
        }
        // Agent and agency pages, on any portal.
        if (preg_match('#/(agente|agentes|agent|agents|consultor|consultora|consultores|real-estate-agent|agente-imobiliario|officer|agencia|agencias|agency)(/|$)#', $path)) {
            return true;
        }
        // iad: a property ends in /r<number> (/anuncio/<slug>/r148292, /pt/imovel/<slug>/r1234567); anything else is a
        // search or a page — the one that failed twice on 28 Nov was a search, /anuncios/constancia_1408/venda/casa-moradia
        if ($portal === 'iad') {
            return ! preg_match('#/r\d+$#', $path);
        }
        // RE/MAX searches (an agent's "my listings" link is one): /comprar/…, /arrendar/…, or a ?s= filter.
        if ($portal === 'remax' && (preg_match('#/(comprar|arrendar)(/|$)#', $path) || preg_match('/(^|&)s=/', $query))) {
            return true;
        }

        return false;
    }

    /**
     * The pasted link, trimmed, as the listing's own page where a portal also shares it some
     * other way. RE/MAX's "portfolio" share link (remax.pt/portfolio/<code>/<listing id>?a=…)
     * shows the listing inside the agent's portfolio and has no listing data of its own: it
     * becomes www.remax.pt/<listing id>, the listing's page.
     */
    public static function limpar(string $url): string
    {
        $url = trim($url);
        if (self::portal($url) === 'remax'
            && preg_match('#/portfolio/[^/]+/(\d{6,}-\d+)/?$#i', (string) parse_url($url, PHP_URL_PATH), $m)) {
            return 'https://www.remax.pt/'.$m[1];
        }

        return $url;
    }

    /** Short and share links (Google's "Partilhar", bit.ly, Facebook's l.php) lead to the listing. */
    private const PARTILHA = ['share.google', 'g.co', 'goo.gl', 'maps.app.goo.gl', 'bit.ly', 'tinyurl.com', 't.co', 'ow.ly',
        'l.facebook.com', 'lm.facebook.com', 'l.instagram.com', 'shorturl.at', 'cutt.ly'];

    /** The address a share or short link ends up at; the link itself when it is not one or cannot be followed. */
    public static function abrirPartilha(string $url): string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url) && preg_match('#^[\w.-]+\.[a-z]{2,}/#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        if (! in_array($host, self::PARTILHA, true)) {
            return $url;
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (! empty($q['u']) && filter_var($q['u'], FILTER_VALIDATE_URL)) {
            return (string) $q['u'];   // l.facebook.com/l.php?u=…
        }
        try {
            $historico = [];
            \Illuminate\Support\Facades\Http::timeout(8)->withOptions(['allow_redirects' => ['max' => 6, 'track_redirects' => true],
                'on_stats' => function ($s) use (&$historico) { $historico[] = (string) $s->getEffectiveUri(); }])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148'])
                ->get($url);
            $fim = (string) end($historico);
        } catch (\Throwable) {
            return $url;
        }
        // Google sometimes stops at its own page with the target in ?url= or ?q=.
        if (preg_match('#(^|\.)google\.#', (string) parse_url($fim, PHP_URL_HOST))) {
            parse_str((string) parse_url($fim, PHP_URL_QUERY), $g);
            foreach (['url', 'q', 'link'] as $k) {
                if (! empty($g[$k]) && filter_var($g[$k], FILTER_VALIDATE_URL)) {
                    return (string) $g[$k];
                }
            }
        }

        return $fim !== '' && filter_var($fim, FILTER_VALIDATE_URL) ? $fim : $url;
    }

    public static function supports(string $url): bool
    {
        return self::portal($url) !== null;
    }

    /** The portal a listing URL belongs to (a key of PORTAIS), or null. */
    public static function portal(string $url): ?string
    {
        foreach (self::PORTAIS as $portal => $scraper) {
            if ($scraper::supports($url)) {
                return $portal;
            }
        }

        return null;
    }

    /** @return array See RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        $portal = self::portal($url) ?? throw new RuntimeException(self::UNSUPPORTED);

        return app(self::PORTAIS[$portal])->scrape($url);
    }
}
