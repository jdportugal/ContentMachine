<?php

namespace App\Services\Marketing;

/**
 * Where a visitor first came from (first touch, kept in their session): the UTM tags,
 * the Facebook / Google click ids, or the site that linked to us. Saved on the lead and
 * on the purchase, so each customer can be traced to the channel, campaign and ad.
 */
class Origem
{
    public const CHAVES = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid'];

    /** The first touch of this session, or null (came straight in, or nothing recorded). */
    public static function atual(): ?array
    {
        $o = session('origem');

        return is_array($o) && $o !== [] ? $o : null;
    }

    public static function json(): ?string
    {
        return ($o = self::atual()) ? json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /** From this request, when it carries campaign tags or comes from another site. */
    public static function daPedido(\Illuminate\Http\Request $r): ?array
    {
        $o = [];
        foreach (self::CHAVES as $k) {
            $v = trim((string) $r->query($k, ''));
            if ($v !== '') {
                $o[$k] = mb_substr($v, 0, 200);
            }
        }
        $referer = (string) parse_url((string) $r->headers->get('referer', ''), PHP_URL_HOST);
        $proprio = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $externo = $referer !== '' && ! str_ends_with($referer, $proprio) && ! str_ends_with($referer, 'stripe.com');
        if ($o === [] && ! $externo) {
            return null;
        }
        if ($externo) {
            $o['referer'] = $referer;
        }
        if (isset($o['fbclid'])) {
            // The click id in Meta's _fbc format, for the Conversions API.
            $o['fbc'] = 'fb.1.'.((int) floor(microtime(true) * 1000)).'.'.$o['fbclid'];
        }
        $o['pagina'] = '/'.ltrim($r->path(), '/');
        $o['em'] = now()->toIso8601String();

        return $o;
    }

    /** One line for people: "facebook · cpc · lancamento · anuncio-a", "google (anúncio)", "instagram.com". */
    public static function resumo(array|string|null $o): string
    {
        if (is_string($o)) {
            $o = json_decode($o, true);
        }
        if (! is_array($o) || $o === []) {
            return 'direto';
        }
        $partes = array_values(array_filter([$o['utm_source'] ?? null, $o['utm_medium'] ?? null, $o['utm_campaign'] ?? null, $o['utm_content'] ?? null]));
        if ($partes !== []) {
            return implode(' · ', $partes);
        }
        if (! empty($o['fbclid'])) {
            return 'facebook / instagram (clique)';
        }
        if (! empty($o['gclid'])) {
            return 'google (anúncio)';
        }

        return (string) ($o['referer'] ?? 'direto');
    }
}
