<?php

namespace App\Services\Marketing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What Meta reports for our ads (Marketing API insights, one row per ad): impressions,
 * reach, link clicks and spend. Read-only, cached for a few minutes; a failure is
 * returned as a message, never thrown, so the funnel still shows the site's numbers.
 */
class MetaAnuncios
{
    private const CACHE_MINUTOS = 10;

    public function conta(): string
    {
        return preg_replace('/\D/', '', (string) config('contentmachine.meta_ads.conta'));
    }

    private function token(): string
    {
        return (string) (config('contentmachine.meta_ads.token') ?: config('contentmachine.meta_capi.token'));
    }

    public function configurado(): bool
    {
        return $this->conta() !== '' && $this->token() !== '';
    }

    /**
     * @return array{ok: bool, erro: ?string, anuncios: list<array{id: string, nome: string, campanha: string, impressoes: int, alcance: int, cliques: int, gasto: float}>}
     */
    public function resultados(?CarbonInterface $de, CarbonInterface $ate, bool $fresco = false): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'erro' => 'sem-token', 'anuncios' => []];
        }
        $chave = 'meta-anuncios:'.$this->conta().':'.($de?->toDateString() ?? 'tudo').':'.$ate->toDateString();
        if ($fresco) {
            Cache::forget($chave);
        }
        if ($guardado = Cache::get($chave)) {
            return $guardado;
        }
        $resultado = $this->pedir($de, $ate);
        if ($resultado['ok']) {
            Cache::put($chave, $resultado, now()->addMinutes(self::CACHE_MINUTOS));
        }

        return $resultado;
    }

    private function pedir(?CarbonInterface $de, CarbonInterface $ate): array
    {
        $parametros = [
            'level' => 'ad',
            'fields' => 'campaign_name,ad_id,ad_name,impressions,reach,inline_link_clicks,spend',
            'limit' => 200,
            'access_token' => $this->token(),
        ] + ($de
            ? ['time_range' => json_encode(['since' => $de->toDateString(), 'until' => $ate->toDateString()])]
            : ['date_preset' => 'maximum']);
        $url = 'https://graph.facebook.com/'.config('contentmachine.meta_capi.version', 'v23.0').'/act_'.$this->conta().'/insights';

        try {
            $anuncios = [];
            for ($pagina = 0; $url && $pagina < 5; $pagina++) {
                $r = Http::timeout(8)->get($url, $pagina === 0 ? $parametros : []);
                if (! $r->successful()) {
                    return ['ok' => false, 'erro' => (string) ($r->json('error.message') ?: 'HTTP '.$r->status()), 'anuncios' => []];
                }
                foreach ((array) $r->json('data') as $l) {
                    $anuncios[] = [
                        'id' => (string) ($l['ad_id'] ?? ''),
                        'nome' => (string) ($l['ad_name'] ?? ''),
                        'campanha' => (string) ($l['campaign_name'] ?? ''),
                        'impressoes' => (int) ($l['impressions'] ?? 0),
                        'alcance' => (int) ($l['reach'] ?? 0),
                        'cliques' => (int) ($l['inline_link_clicks'] ?? 0),
                        'gasto' => (float) ($l['spend'] ?? 0),
                    ];
                }
                $url = $r->json('paging.next');
            }

            return ['ok' => true, 'erro' => null, 'anuncios' => $anuncios];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => 'Sem resposta da Meta ('.class_basename($e).').', 'anuncios' => []];
        }
    }
}
