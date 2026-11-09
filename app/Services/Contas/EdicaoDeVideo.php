<?php

namespace App\Services\Contas;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Support\Str;

/**
 * What a customer can change in their reel, read from and written back to the clip:
 * the scenes' pictures (by image id), the agent's contact on the cards, and the voiceover.
 */
class EdicaoDeVideo
{
    /** Free re-renders per video; after these, one more video (a credit) buys two more. */
    public const GRATIS = 2;

    public static function permitidas(ClipRecord $clip): int
    {
        return self::GRATIS + (int) ($clip->meta['edicoes_compradas'] ?? 0);
    }

    public static function feitas(ClipRecord $clip): int
    {
        return (int) ($clip->meta['edicoes'] ?? 0);
    }

    /** Ids of the clip's images (photos, agent portrait, logo). */
    public static function imagens(ClipRecord $clip): array
    {
        return collect($clip->images ?? [])->filter(fn ($i) => isset($i['id'], $i['path']))->keyBy('id')->all();
    }

    /**
     * The scenes as the editor shows them: per scene, the pictures it uses (param => image id),
     * a video when it plays footage, and the effect's name. No text.
     *
     * Also whether its effect can be swapped, and its seconds in the rendered video.
     *
     * @return array<int,array{indice:int,slug:string,efeito:string,fotos:array<string,string>,video:bool,trocavel:bool,inicio:float,fim:float}>
     */
    public static function cenas(ClipRecord $clip): array
    {
        $imagens = self::imagens($clip);
        $nomes = self::nomesDosEfeitos();
        $trocaveis = self::efeitosDeFoto();
        $abertura = self::abertura($clip);
        $out = [];
        foreach (($clip->plan['scenes'] ?? []) as $i => $cena) {
            $fotos = [];
            $video = ! empty($cena['plain']);
            foreach (($cena['layers'] ?? []) as $j => $camada) {
                foreach ((array) ($camada['params'] ?? []) as $chave => $valor) {
                    if (is_string($valor) && isset($imagens[$valor])) {
                        $fotos["$j.$chave"] = $valor;
                    }
                }
            }
            $tipo = (string) ($cena['layers'][0]['type'] ?? $cena['layers'][0]['effect'] ?? '');
            $out[] = [
                'indice' => (int) $i, 'slug' => $tipo, 'efeito' => $nomes[$tipo] ?? Str::of($tipo)->replace('-', ' ')->ucfirst()->toString(),
                'fotos' => $fotos, 'video' => $video, 'trocavel' => isset($trocaveis[$tipo]),
                'inicio' => round((float) ($cena['start'] ?? 0) + $abertura, 2), 'fim' => round((float) ($cena['end'] ?? 0) + $abertura, 2),
            ];
        }

        return $out;
    }

    /** Put picture $imagem where scene $cena's layer.param ($onde, "j.param") was. */
    public static function trocarFoto(array $plan, int $cena, string $onde, string $imagem): array
    {
        [$j, $chave] = explode('.', $onde, 2) + [1 => ''];
        if (isset($plan['scenes'][$cena]['layers'][(int) $j]['params'][$chave])) {
            $plan['scenes'][$cena]['layers'][(int) $j]['params'][$chave] = $imagem;
        }

        return $plan;
    }

    /**
     * The agent's contact, everywhere it is printed on the cards: the name (name/agent keys),
     * the phone (wherever the old one appears), and the AMI licence on the legal line.
     *
     * @param  array{name:string,phone:string,licence:string}  $antes
     * @param  array{name:string,phone:string,licence:string}  $depois
     */
    public static function aplicarAgente(array $plan, array $antes, array $depois): array
    {
        foreach (($plan['scenes'] ?? []) as $i => $cena) {
            foreach (($cena['layers'] ?? []) as $j => $camada) {
                foreach ((array) ($camada['params'] ?? []) as $chave => $valor) {
                    if (! is_string($valor) || ! is_string($chave)) {
                        continue;
                    }
                    $novo = $valor;
                    $k = strtolower($chave);
                    if (in_array($k, ['name', 'agent', 'agente', 'nome'], true) && $depois['name'] !== '') {
                        $novo = $depois['name'];
                    }
                    if ($antes['phone'] !== '' && $depois['phone'] !== '' && str_contains($novo, $antes['phone'])) {
                        $novo = str_replace($antes['phone'], $depois['phone'], $novo);
                    }
                    if (in_array($k, ['phone', 'telefone'], true) && $depois['phone'] !== '') {
                        $novo = $depois['phone'];
                    }
                    if (in_array($k, ['legal', 'licence', 'license', 'ami'], true) && $depois['licence'] !== $antes['licence']) {
                        $novo = self::comLicenca($novo, $antes['licence'], $depois['licence'], $k);
                    }
                    $plan['scenes'][$i]['layers'][$j]['params'][$chave] = $novo;
                }
            }
        }

        return $plan;
    }

    private static function comLicenca(string $valor, string $antes, string $depois, string $chave): string
    {
        if ($chave !== 'legal') {
            return $depois;
        }
        if (preg_match('/AMI[:\s]*\d+/i', $valor)) {
            return $depois === '' ? trim(preg_replace('/\s*[·|]?\s*AMI[:\s]*\d+/i', '', $valor)) : preg_replace('/AMI[:\s]*\d+/i', 'AMI '.$depois, $valor);
        }
        if ($antes !== '' && str_contains($valor, $antes)) {
            return trim(str_replace($antes, $depois, $valor));
        }

        return $depois === '' ? $valor : trim($valor.' · AMI '.$depois, ' ·');
    }

    /** Caption params a picture effect may carry; anything else (name, price, facts…) makes it text-led. */
    private const LEGENDAS = ['eyebrow', 'label', 'move'];

    /**
     * The effects a customer can swap a scene's effect for: enabled effects of the clip's
     * theme that show pictures with at most a short caption. Text-led cards (the agent,
     * the price, the opening, the key facts) stay as planned.
     *
     * @return array<string,array{nome:string,imagens:array<int,string>}> slug => name and its picture params, in order
     */
    public static function efeitosDeFoto(): array
    {
        try {
            $efeitos = app(EffectStore::class)->enabled();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($efeitos as $e) {
            $esquema = (string) $e->get('param_schema', '');
            preg_match_all('/"(\w+)"(\??)\s*:\s*(\w+)/', $esquema, $m, PREG_SET_ORDER);
            $imagens = [];
            $outros = [];
            foreach ($m as [, $chave, $opcional, $tipo]) {
                if ($tipo === 'image' && $opcional === '') {
                    $imagens[] = $chave;
                } elseif ($tipo !== 'image' || $opcional === '') {
                    $outros[] = $chave;
                }
            }
            if ($imagens !== [] && array_diff($outros, self::LEGENDAS) === []) {
                $out[(string) $e->slug] = ['nome' => (string) ($e->display_name ?: $e->slug), 'imagens' => $imagens];
            }
        }

        return $out;
    }

    /**
     * Scene $cena with effect $slug instead: its picture params filled from $fotos
     * (in order, then $extra pictures), the caption carried over, the timing untouched.
     *
     * @param  array<int,string>  $fotos  the scene's picture ids, in order
     * @param  array<int,string>  $extra  more picture ids to use when the effect needs more
     * @return array{0:array,1:array<string,string>} [plan, the scene's new "0.param" => image id]
     */
    public static function trocarEfeito(array $plan, int $cena, string $slug, array $fotos, array $extra = []): array
    {
        $efeito = self::efeitosDeFoto()[$slug] ?? null;
        $camada = $plan['scenes'][$cena]['layers'][0] ?? null;
        if (! $efeito || ! is_array($camada) || ! isset(self::efeitosDeFoto()[(string) ($camada['type'] ?? '')])) {
            return [$plan, []];
        }
        $antigos = (array) ($camada['params'] ?? []);
        $legenda = $antigos['eyebrow'] ?? $antigos['label'] ?? null;
        $fila = array_values(array_unique(array_merge(array_values($fotos), $extra)));
        $params = [];
        $novas = [];
        foreach ($efeito['imagens'] as $n => $chave) {
            $params[$chave] = $fila[$n] ?? $fila[0] ?? '';
            $novas["0.$chave"] = $params[$chave];
        }
        $esquema = (string) (app(EffectStore::class)->enabled()->firstWhere('slug', $slug)?->get('param_schema', '') ?? '');
        $campo = str_contains($esquema, '"label"') ? 'label' : (str_contains($esquema, '"eyebrow"') ? 'eyebrow' : null);
        if ($campo && is_string($legenda) && $legenda !== '') {
            $params[$campo] = $legenda;
        }
        if (isset($antigos['move']) && str_contains($esquema, '"move"')) {
            $params['move'] = $antigos['move'];
        }
        $camada['type'] = $slug;
        $camada['params'] = $params;
        $plan['scenes'][$cena]['layers'][0] = $camada;

        return [$plan, $novas];
    }

    /** Seconds of music-only opening before the first scene in the rendered video. */
    public static function abertura(ClipRecord $clip): float
    {
        if (isset($clip->meta['abertura'])) {
            return (float) $clip->meta['abertura'];
        }
        if (empty($clip->meta['beat_opening']) || ! $clip->output_path || ! is_file($clip->output_path)) {
            return 0.0;
        }
        // Rendered before the length was recorded: the video's length past the plan's.
        $video = (float) rescue(fn () => \App\Services\RealEstate\PropertyVideoAnalyzer::probe($clip->output_path)['duration'] ?? 0, 0.0, false);

        return max(0.0, round($video - (float) ($clip->plan['duration'] ?? $video), 3));
    }

    /** @return array<string,string> effect slug => display name */
    private static function nomesDosEfeitos(): array
    {
        try {
            return app(EffectStore::class)->records()->mapWithKeys(fn ($e) => [(string) $e->slug => (string) ($e->display_name ?: $e->slug)])->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
