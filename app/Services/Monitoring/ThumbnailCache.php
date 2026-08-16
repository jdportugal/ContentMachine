<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads post thumbnails to public/ and serves them locally. The
 * Instagram/TikTok CDNs block hotlinking from the browser (403/CORS) and
 * the URLs expire — so we keep a local copy at collection time.
 */
class ThumbnailCache
{
    /**
     * Downloads the thumbnail and returns the web path (relative to public/), or the
     * URL itself if already local, or '' on failure.
     */
    public function localizar(string $plataforma, string $id, string $url): string
    {
        $url = trim($url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return $url; // empty or already local
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
