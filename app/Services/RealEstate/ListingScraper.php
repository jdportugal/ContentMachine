<?php

namespace App\Services\RealEstate;

use RuntimeException;

/** Picks the scraper for a listing URL: RE/MAX (remax.pt), iad (iadportugal.pt), idealista (idealista.pt) or Imocena's own demo listings (/demo/<slug>). */
class ListingScraper
{
    public const UNSUPPORTED = 'Only RE/MAX (remax.pt), iad (iadportugal.pt) and idealista (idealista.pt) listings are supported.';

    public function __construct(private RemaxListingScraper $remax, private IadListingScraper $iad, private IdealistaListingScraper $idealista, private DemoListingScraper $demo) {}

    public static function supports(string $url): bool
    {
        return RemaxListingScraper::supports($url) || IadListingScraper::supports($url) || IdealistaListingScraper::supports($url) || DemoListingScraper::supports($url);
    }

    /** @return array See RemaxListingScraper::scrape(). */
    /** The portal a listing URL belongs to: 'remax', 'iad', 'idealista', 'demo' or null. */
    public static function portal(string $url): ?string
    {
        return match (true) {
            RemaxListingScraper::supports($url) => 'remax',
            IadListingScraper::supports($url) => 'iad',
            IdealistaListingScraper::supports($url) => 'idealista',
            DemoListingScraper::supports($url) => 'demo',
            default => null,
        };
    }

    public function scrape(string $url): array
    {
        return match (true) {
            RemaxListingScraper::supports($url) => $this->remax->scrape($url),
            IadListingScraper::supports($url) => $this->iad->scrape($url),
            IdealistaListingScraper::supports($url) => $this->idealista->scrape($url),
            DemoListingScraper::supports($url) => $this->demo->scrape($url),
            default => throw new RuntimeException(self::UNSUPPORTED),
        };
    }
}
