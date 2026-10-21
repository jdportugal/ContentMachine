<?php

namespace App\Services\RealEstate;

use RuntimeException;

/** Picks the scraper for a listing URL: RE/MAX (remax.pt), iad (iadportugal.pt) or idealista (idealista.pt). */
class ListingScraper
{
    public const UNSUPPORTED = 'Only RE/MAX (remax.pt), iad (iadportugal.pt) and idealista (idealista.pt) listings are supported.';

    public function __construct(private RemaxListingScraper $remax, private IadListingScraper $iad, private IdealistaListingScraper $idealista) {}

    public static function supports(string $url): bool
    {
        return RemaxListingScraper::supports($url) || IadListingScraper::supports($url) || IdealistaListingScraper::supports($url);
    }

    /** @return array See RemaxListingScraper::scrape(). */
    public function scrape(string $url): array
    {
        return match (true) {
            RemaxListingScraper::supports($url) => $this->remax->scrape($url),
            IadListingScraper::supports($url) => $this->iad->scrape($url),
            IdealistaListingScraper::supports($url) => $this->idealista->scrape($url),
            default => throw new RuntimeException(self::UNSUPPORTED),
        };
    }
}
