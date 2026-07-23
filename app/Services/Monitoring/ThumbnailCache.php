<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Descarrega miniaturas de publicações para public/ e serve-as localmente. Os
 * CDN do Instagram/TikTok bloqueiam o hotlink a partir do browser (403/CORS) e
 * os URLs expiram — por isso guardamos uma cópia local ao recolher.
 */
class ThumbnailCache
{
    /**
     * Descarrega a miniatura e devolve o caminho web (relativo a public/), ou o
     * próprio URL se já for local, ou '' em falha.
     */
    public function localizar(string $plataforma, string $id, string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return $url; // vazio ou já local
        }

        $rel = 'media/monitoring/'.preg_replace('/[^a-z0-9]/i', '', $plataforma);
        $dir = public_path($rel);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $nome = preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?: md5($url);
        $path = $rel.'/'.$nome.'.jpg';

        try {
            $bytes = Http::timeout(20)->get($url)->body();
            if ($bytes === '') {
                return '';
            }
            file_put_contents(public_path($path), $bytes);

            return $path;
        } catch (Throwable) {
            return '';
        }
    }
}
