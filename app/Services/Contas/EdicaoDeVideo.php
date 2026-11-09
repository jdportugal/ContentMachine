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
     * @return array<int,array{indice:int,efeito:string,fotos:array<string,string>,video:bool}>
     */
    public static function cenas(ClipRecord $clip): array
    {
        $imagens = self::imagens($clip);
        $nomes = self::nomesDosEfeitos();
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
            $out[] = ['indice' => (int) $i, 'efeito' => $nomes[$tipo] ?? Str::of($tipo)->replace('-', ' ')->ucfirst()->toString(), 'fotos' => $fotos, 'video' => $video];
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
        if ($antes !== '' && str_contains($valor, $antes)) {
            return trim(str_replace($antes, $depois, $valor));
        }
        if (preg_match('/AMI[:\s]*\d+/i', $valor)) {
            return $depois === '' ? trim(preg_replace('/\s*[·|]?\s*AMI[:\s]*\d+/i', '', $valor)) : preg_replace('/AMI[:\s]*\d+/i', 'AMI '.$depois, $valor);
        }

        return $depois === '' ? $valor : trim($valor.' · AMI '.$depois, ' ·');
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
