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
