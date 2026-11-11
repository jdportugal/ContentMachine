<?php

namespace App\Services\Marketing;

/**
 * The channel an origin (Origem) belongs to, for the campaign funnel. Paid Meta is told
 * apart from organic by the tags our ads carry (utm_medium=paid): Facebook adds a click
 * id to organic links too, so the click id alone does not mean an ad.
 */
class Canal
{
    public const META_ADS = 'meta-ads';

    public const INTERNO = 'interno';

    public const NOMES = [
        'meta-ads' => 'Anúncios Meta',
        'meta' => 'Facebook / Instagram (orgânico)',
        'google-ads' => 'Anúncios Google',
        'google' => 'Google (pesquisa)',
        'outro' => 'Outros sites e campanhas',
        'direto' => 'Direto',
    ];

    /** The URL parameters every Meta ad should carry, so visits can be tied to the ad. */
    public const PARAMETROS_META = 'utm_source=meta&utm_medium=paid&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{ad.id}}';

    private const FONTES_META = ['meta', 'facebook', 'fb', 'instagram', 'ig'];

    private const MEIOS_PAGOS = ['paid', 'cpc', 'ppc', 'ads', 'paid_social', 'paidsocial', 'paid-social'];

    public static function de(array|string|null $o): string
    {
        if (is_string($o)) {
            $o = json_decode($o, true);
        }
        if (! is_array($o) || $o === []) {
            return 'direto';
        }
        $fonte = mb_strtolower((string) ($o['utm_source'] ?? ''));
        $meio = mb_strtolower((string) ($o['utm_medium'] ?? ''));
        $referer = mb_strtolower((string) ($o['referer'] ?? ''));
        if ($fonte === 'teste-interno') {
            return self::INTERNO;
        }
        $pago = in_array($meio, self::MEIOS_PAGOS, true);
        if (in_array($fonte, self::FONTES_META, true)) {
            return $pago ? self::META_ADS : 'meta';
        }
        if (! empty($o['gclid']) || ($fonte === 'google' && $pago)) {
            return 'google-ads';
        }
        if ($fonte === 'google' || ($fonte === '' && str_contains($referer, 'google.'))) {
            return 'google';
        }
        if ($fonte === '' && (! empty($o['fbclid']) || str_contains($referer, 'facebook.com') || str_contains($referer, 'instagram.com'))) {
            return 'meta';
        }

        return 'outro';
    }

    /** Does this origin belong to this Meta ad? By the ad id (utm_term), else by its name (utm_content). */
    public static function doAnuncio(?array $o, string $id, string $nome): bool
    {
        if (! $o) {
            return false;
        }
        if ($id !== '' && (string) ($o['utm_term'] ?? '') === $id) {
            return true;
        }

        return $nome !== '' && mb_strtolower((string) ($o['utm_content'] ?? '')) === mb_strtolower($nome);
    }
}
