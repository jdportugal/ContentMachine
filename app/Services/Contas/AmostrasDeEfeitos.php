<?php

namespace App\Services\Contas;

use App\Jobs\Clips\RenderAmostraJob;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectStore;
use App\Services\Themes\ThemeContext;
use Illuminate\Support\Facades\Cache;

/**
 * A short sample of each picture effect, so a customer sees how it looks before
 * choosing it. Rendered once per theme (its colours and fonts) with Imocena's own
 * demo house photos, and again only when the effect's code or the design changes.
 */
class AmostrasDeEfeitos
{
    public const SEGUNDOS = 3.0;

    public const LEGENDA = 'Sala de estar';

    /** Where the sample of $slug for the current theme lives (whether or not it exists yet). */
    public static function caminho(string $slug): string
    {
        $tema = app(ThemeContext::class)->current()->slug;
        $tsx = (string) (app(EffectStore::class)->enabled()->firstWhere('slug', $slug)?->get('tsx', '') ?? '');
        $versao = substr(md5($tsx.'|'.app(EffectLibrary::class)->designHash().'|'.implode(',', array_map('basename', self::fotos()))), 0, 10);

        return rtrim((string) config('contentmachine.amostras.pasta', storage_path('app/amostras')), '/')."/$tema/$slug-$versao.mp4";
    }

    public static function existe(string $slug): bool
    {
        return is_file(self::caminho($slug));
    }

    /** The demo photos the samples show (first three of the demo listing). @return array<int,string> */
    public static function fotos(): array
    {
        $pasta = (string) config('contentmachine.amostras.fotos', public_path('media/demo/moradia-lajeosa'));
        $fotos = glob(rtrim($pasta, '/').'/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
        sort($fotos);

        return array_slice($fotos, 0, 3);
    }

    /** Queue the sample of $slug for the current theme, once, unless it exists. */
    public static function pedir(string $slug): void
    {
        if (self::fotos() === [] || self::existe($slug)) {
            return;
        }
        if (Cache::add('amostra:'.self::caminho($slug), 1, now()->addMinutes(30))) {
            RenderAmostraJob::dispatch($slug);
        }
    }

    /** The one-effect plan: the demo photos in the effect's picture params, a caption if it takes one. */
    public static function plano(string $slug): ?array
    {
        $efeito = EdicaoDeVideo::efeitosDeFoto()[$slug] ?? null;
        $fotos = self::fotos();
        if (! $efeito || $fotos === []) {
            return null;
        }
        $params = [];
        foreach ($efeito['imagens'] as $n => $chave) {
            $params[$chave] = $fotos[$n % count($fotos)];
        }
        $esquema = (string) app(EffectStore::class)->enabled()->firstWhere('slug', $slug)?->get('param_schema', '');
        foreach (['label', 'eyebrow'] as $campo) {
            if (str_contains($esquema, '"'.$campo.'"')) {
                $params[$campo] = self::LEGENDA;
                break;
            }
        }

        return app(EffectLibrary::class)->samplePlan($slug, '', $params, EffectLibrary::FORMAT_PORTRAIT, self::SEGUNDOS);
    }
}
