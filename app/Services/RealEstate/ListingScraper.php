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
        'demo' => DemoListingScraper::class,
    ];

    /** The portals as a customer reads them, in the order they are listed on the site. */
    public const NOMES = ['RE/MAX', 'iad Portugal', 'eXp Portugal', 'KW Portugal', 'Century 21'];

    public const UNSUPPORTED = 'Only RE/MAX, iad, eXp, KW and Century 21 listings are supported.';

    /** What a customer is told when the link is from another site. */
    public const MENSAGEM = 'Por agora lemos anúncios da RE/MAX, da iad Portugal, da eXp Portugal, da KW Portugal e da Century 21. Cole o link de um desses sites.';

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
        // Agent and agency pages, on any portal.
        if (preg_match('#/(agente|agentes|agent|agents|consultor|consultora|consultores|real-estate-agent|agente-imobiliario|officer|agencia|agencias|agency)(/|$)#', $path)) {
            return true;
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
