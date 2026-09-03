<?php

namespace App\Services\Shorts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The font directory libass burns subtitles with.
 *
 * The animated clips caption in the Design System's body face, which the Remotion
 * renderer pulls from Google Fonts as woff2 — a format libass cannot read, so a
 * burned-in short fell back to a generic sans and the two didn't match. This
 * fetches that family as TTF once and keeps it next to the faces bundled in
 * resources/fonts, then hands ffmpeg the one directory that holds both.
 *
 * The cache lives under storage (a persistent volume in production, and writable
 * where resources/ may not be). Every failure path returns the bundled directory:
 * a font that cannot be fetched must never fail a render, only look plainer.
 */
class FontLibrary
{
    /**
     * Weight to fetch: the REGULAR face, even though KaraokeTrack captions at 600.
     *
     * A non-regular face names its family after itself — the 600 file calls itself
     * "Space Grotesk SemiBold" — and libass, asked for "Space Grotesk", then finds
     * nothing and silently falls back to Helvetica. The regular face is named for
     * the family exactly, so it matches. Right typeface at a slightly lighter
     * weight beats the right weight of the wrong typeface.
     *
     * ponytail: if the weight difference ever matters, resolve the real family
     * name out of the file's `name` table and put THAT in the ASS Fontname.
     */
    private const WEIGHT = 400;

    /** Bare, on purpose: this is the UA Google serves plain TTF to. See download(). */
    private const UA = 'Mozilla/5.0';

    public function __construct(
        private readonly string $bundledDir,
        private readonly string $cacheDir,
    ) {}

    /**
     * Directory to pass to libass as `fontsdir`, with $family available in it when
     * it could be obtained. Falls back to the bundled directory.
     */
    public function dirFor(?string $family): string
    {
        $family = trim((string) $family);

        if ($family === '' || $this->bundled($family)) {
            return $this->bundledDir;
        }

        try {
            $dir = $this->seededCache();
            $ficheiro = $dir.'/'.Str::slug($family).'.ttf';

            if (is_file($ficheiro) && filesize($ficheiro) > 0) {
                return $dir;
            }
            if ($this->download($family, $ficheiro)) {
                return $dir;
            }
        } catch (\Throwable $e) {
            Log::warning('FontLibrary: could not provide "'.$family.'" — '.$e->getMessage());
        }

        return $this->bundledDir;
    }

    /** Is the family already one of the faces shipped with the app? */
    private function bundled(string $family): bool
    {
        foreach (glob($this->bundledDir.'/*.{ttf,otf,TTF,OTF}', GLOB_BRACE) ?: [] as $path) {
            if (Str::slug(pathinfo($path, PATHINFO_FILENAME)) === Str::slug($family)
                || Str::startsWith(Str::slug(pathinfo($path, PATHINFO_FILENAME)), Str::slug($family))) {
                return true;
            }
        }

        return false;
    }

    /** The cache directory, holding a copy of every bundled face plus what we fetch. */
    private function seededCache(): string
    {
        if (! is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }

        // libass gets ONE fontsdir, so the bundled faces have to live here too or
        // choosing a downloaded family would lose Anton and the rest.
        foreach (glob($this->bundledDir.'/*.{ttf,otf,TTF,OTF}', GLOB_BRACE) ?: [] as $path) {
            $destino = $this->cacheDir.'/'.basename($path);
            if (! is_file($destino) || filesize($destino) !== filesize($path)) {
                @copy($path, $destino);
            }
        }

        return $this->cacheDir;
    }

    /**
     * Fetch $family from Google Fonts as TTF.
     *
     * Google picks the format from the User-Agent, and only a bare "Mozilla/5.0"
     * is served font/ttf — a full modern UA gets woff2 and an ancient IE one gets
     * eot, neither of which libass reads. That is why the header is what it is;
     * it is load-bearing, not decoration. The same UA has to go on BOTH requests,
     * the stylesheet and the font it points at.
     *
     * The weight is asked for because the animated clips caption at 600, and the
     * bytes are checked for a real sfnt signature rather than trusting any name.
     */
    private function download(string $family, string $destino): bool
    {
        // css2, not css: the old endpoint hands back a VARIABLE subset whose
        // internal family reads "Space Grotesk Light", which libass then cannot
        // match to "Space Grotesk". css2 serves the static face for one weight,
        // named the way the stylesheet asks for it.
        $url = 'https://fonts.googleapis.com/css2?family='.rawurlencode($family).':wght@'.self::WEIGHT;
        $css = Http::timeout(15)->withHeaders(['User-Agent' => self::UA])->get($url);

        if (! $css->successful() || ! preg_match('#src:\s*url\(([^)]+)\)#i', $css->body(), $m)) {
            Log::warning('FontLibrary: Google Fonts offered no face for "'.$family.'".');

            return false;
        }

        $fonte = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get(trim($m[1], '\'" '));
        if (! $fonte->successful() || ! self::pareceFonte($fonte->body())) {
            Log::warning('FontLibrary: what came back for "'.$family.'" is not a usable font file.');

            return false;
        }

        return (bool) file_put_contents($destino, $fonte->body());
    }

    /** TrueType/OpenType signature — anything else would make libass fall back anyway. */
    private static function pareceFonte(string $bytes): bool
    {
        return strlen($bytes) > 1024
            && in_array(substr($bytes, 0, 4), ["\x00\x01\x00\x00", 'true', 'ttcf', 'OTTO'], true);
    }
}
