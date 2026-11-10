<?php

namespace App\Services\RealEstate;

use RuntimeException;

/** Picks the scraper for a listing URL: RE/MAX (remax.pt), iad (iadportugal.pt), eXp (expportugal.com and its agents' sites) or Imocena's own demo listings (/demo/<slug>). idealista was dropped: it blocks automated reading. */
class ListingScraper
{
    public const UNSUPPORTED = 'Only RE/MAX (remax.pt), iad (iadportugal.pt) and eXp (expportugal.com) listings are supported.';

    /** What a customer is told when the link is from another site. */
    public const MENSAGEM = 'Por agora lemos anúncios da RE/MAX, da iad Portugal e da eXp Portugal. Cole o link de um desses sites.';

    public function __construct(private RemaxListingScraper $remax, private IadListingScraper $iad, private ExpListingScraper $exp, private DemoListingScraper $demo) {}

    public static function supports(string $url): bool
    {
        return RemaxListingScraper::supports($url) || IadListingScraper::supports($url) || ExpListingScraper::supports($url) || DemoListingScraper::supports($url);
    }

    /** @return array See RemaxListingScraper::scrape(). */
    /** The portal a listing URL belongs to: 'remax', 'iad', 'exp', 'demo' or null. */
    public static function portal(string $url): ?string
    {
        return match (true) {
            RemaxListingScraper::supports($url) => 'remax',
            IadListingScraper::supports($url) => 'iad',
            ExpListingScraper::supports($url) => 'exp',
            DemoListingScraper::supports($url) => 'demo',
            default => null,
        };
    }

    public function scrape(string $url): array
    {
        return match (true) {
            RemaxListingScraper::supports($url) => $this->remax->scrape($url),
            IadListingScraper::supports($url) => $this->iad->scrape($url),
            ExpListingScraper::supports($url) => $this->exp->scrape($url),
            DemoListingScraper::supports($url) => $this->demo->scrape($url),
            default => throw new RuntimeException(self::UNSUPPORTED),
        };
    }
}
