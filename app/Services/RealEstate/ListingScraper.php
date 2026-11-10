<?php

namespace App\Services\RealEstate;

use RuntimeException;

/** Picks the scraper for a listing URL: RE/MAX (remax.pt), iad (iadportugal.pt) or Imocena's own demo listings (/demo/<slug>). idealista was dropped: it blocks automated reading. */
class ListingScraper
{
    public const UNSUPPORTED = 'Only RE/MAX (remax.pt) and iad (iadportugal.pt) listings are supported.';

    /** What a customer is told when the link is from another site. */
    public const MENSAGEM = 'Por agora lemos anúncios da RE/MAX e da iad Portugal. Cole o link de um desses sites.';

    public function __construct(private RemaxListingScraper $remax, private IadListingScraper $iad, private DemoListingScraper $demo) {}

    public static function supports(string $url): bool
    {
        return RemaxListingScraper::supports($url) || IadListingScraper::supports($url) || DemoListingScraper::supports($url);
    }

    /** @return array See RemaxListingScraper::scrape(). */
    /** The portal a listing URL belongs to: 'remax', 'iad', 'demo' or null. */
    public static function portal(string $url): ?string
    {
        return match (true) {
            RemaxListingScraper::supports($url) => 'remax',
            IadListingScraper::supports($url) => 'iad',
            DemoListingScraper::supports($url) => 'demo',
            default => null,
        };
    }

    public function scrape(string $url): array
    {
        return match (true) {
            RemaxListingScraper::supports($url) => $this->remax->scrape($url),
            IadListingScraper::supports($url) => $this->iad->scrape($url),
            DemoListingScraper::supports($url) => $this->demo->scrape($url),
            default => throw new RuntimeException(self::UNSUPPORTED),
        };
    }
}
