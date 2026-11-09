<?php

namespace App\Services\Contas;

/**
 * The words on screen in a reel's plan (titles, labels, price, contact…), as a flat
 * list a customer can edit, and back. Only plain text leaves are offered: paths,
 * links, colours, numbers that drive animations and ids are left alone.
 */
class TextosDoVideo
{
    private const CHAVES = '/^(eyebrow|kicker|title|titulo|subtitle|subtitulo|headline|heading|text|texto|label|caption|tagline|line\d*|name|nome|agent|agency|agencia|phone|telefone|price|preco|cta|legal|logotext|location|local|description|descricao|value|suffix|prefix)$/i';

    /** @return array<int,array{caminho:string,cena:int,campo:string,valor:string}> */
    public static function extrair(array $plan): array
    {
        $out = [];
        foreach (($plan['scenes'] ?? []) as $i => $cena) {
            foreach (($cena['layers'] ?? []) as $j => $camada) {
                self::percorrer((array) ($camada['params'] ?? []), "scenes.$i.layers.$j.params", (int) $i, $out);
            }
        }

        return $out;
    }

    /**
     * @param  array<string,string>  $valores  caminho => new text (only paths extrair() offers are applied)
     */
    public static function aplicar(array $plan, array $valores): array
    {
        $permitidos = array_column(self::extrair($plan), 'valor', 'caminho');
        foreach ($valores as $caminho => $texto) {
            if (! array_key_exists($caminho, $permitidos)) {
                continue;
            }
            $texto = trim(preg_replace('/\s+/u', ' ', (string) $texto));
            if ($texto === '' || mb_strlen($texto) > 160) {
                continue;   // an empty or runaway text would break the layout; keep the original
            }
            data_set($plan, $caminho, $texto);
        }

        return $plan;
    }

    private static function percorrer(array $params, string $prefixo, int $cena, array &$out): void
    {
        foreach ($params as $chave => $valor) {
            $caminho = "$prefixo.$chave";
            if (is_array($valor)) {
                self::percorrer($valor, $caminho, $cena, $out);
            } elseif (is_string($valor) && is_string($chave) && preg_match(self::CHAVES, $chave) && self::eTexto($valor)) {
                $out[] = ['caminho' => $caminho, 'cena' => $cena, 'campo' => $chave, 'valor' => $valor];
            }
        }
    }

    private static function eTexto(string $v): bool
    {
        $v = trim($v);

        return $v !== '' && mb_strlen($v) <= 160
            && preg_match('/\p{L}/u', $v)                       // has letters (not a bare number an animation counts to)
            && ! preg_match('#^(/|https?://|data:|\#[0-9a-f]{3,8}$)#i', $v)
            && ! preg_match('/\.(mp4|mov|jpe?g|png|webp|mp3|m4a|wav)$/i', $v);
    }
}
