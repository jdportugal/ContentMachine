<?php

namespace App\Services\Alertas;

use App\Services\RealEstate\ListingScraper;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * A cheap demo of a new listing's video, for the new-listing alert emails: the
 * listing's photos panned one after the other, under ONE recorded voice that says
 * it is a demonstration (config alertas.demo_voz, recorded once, so no AI script
 * and no voiceover per video), the default music, a "DEMONSTRAÇÃO" mark and an end
 * card pointing to imocena.com. Only ffmpeg runs per video.
 *
 * Saved on the public disk under demos/{slug}.mp4 (+ .jpg poster, + .json with the
 * listing), where /demo-video/{slug} plays it.
 */
class DemoDoImovel
{
    public const DIR = 'demos';

    public const MAX_FOTOS = 8;

    private const W = 1080;

    private const H = 1920;

    /** Fade between two photos, seconds. */
    private const FADE = 0.6;

    /** Silence before the voice starts, and the end card after it, seconds. */
    private const ANTES = 1.0;

    private const FIM = 3.2;

    public function __construct(private ListingScraper $scraper) {}

    /** The recorded voice, or null when there is none (then no demos are made). */
    public static function voz(): ?string
    {
        $p = (string) config('contentmachine.alertas.demo_voz', '');

        return $p !== '' && is_file($p) ? $p : null;
    }

    public static function slug(string $url): string
    {
        return substr(sha1(trim($url)), 0, 16);
    }

    /**
     * The demo of this listing (made now, or the one made before).
     *
     * @return array{slug:string,url:string,titulo:string,video:string,poster:string,pagina:string}|null
     */
    public function gerar(string $url): ?array
    {
        if (! config('contentmachine.alertas.demo', true) || ! ($voz = self::voz())) {
            return null;
        }
        $slug = self::slug($url);
        if ($feito = self::info($slug)) {
            return $feito;
        }

        $tmp = storage_path('app/tmp/demo-'.$slug.'-'.bin2hex(random_bytes(3)));
        @mkdir($tmp, 0777, true);
        try {
            $anuncio = $this->scraper->scrape($url);
            $fotos = $this->descarregar(array_slice((array) ($anuncio['photos'] ?? []), 0, self::MAX_FOTOS), $tmp);
            if (count($fotos) < 3) {
                return null;   // too few photos for a video worth sending
            }

            $disco = Storage::disk('public');
            $disco->makeDirectory(self::DIR);
            $mp4 = $disco->path(self::DIR."/{$slug}.mp4");
            $musica = app(MusicLibrary::class)->pathFor((string) config('contentmachine.real_estate.music')) ?? app(MusicLibrary::class)->randomPath();

            $p = new Process(self::comando($fotos, $voz, $musica, "$tmp/demo.mp4"));
            $p->setTimeout(600)->mustRun();
            (new Process(['ffmpeg', '-nostdin', '-y', '-v', 'error', '-ss', '1.5', '-i', "$tmp/demo.mp4", '-frames:v', '1', '-vf', 'scale=540:-2', '-q:v', '4', "$tmp/poster.jpg"]))->run();

            self::mover("$tmp/demo.mp4", $mp4);
            if (is_file("$tmp/poster.jpg")) {
                self::mover("$tmp/poster.jpg", $disco->path(self::DIR."/{$slug}.jpg"));
            }
            $disco->put(self::DIR."/{$slug}.json", json_encode(['url' => $url, 'titulo' => (string) ($anuncio['title'] ?? ''), 'criado' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::info($slug);
        } catch (\Throwable $e) {
            Log::warning('Demo do imóvel não feita', ['url' => $url, 'erro' => mb_substr($e->getMessage(), 0, 300)]);

            return null;
        } finally {
            foreach (glob("$tmp/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    /** @return array{slug:string,url:string,titulo:string,video:string,poster:string,pagina:string}|null */
    public static function info(string $slug): ?array
    {
        if (! preg_match('/^[a-f0-9]{16}$/', $slug)) {
            return null;
        }
        $disco = Storage::disk('public');
        if (! $disco->exists(self::DIR."/{$slug}.mp4") || ! $disco->exists(self::DIR."/{$slug}.json")) {
            return null;
        }
        $meta = (array) json_decode((string) $disco->get(self::DIR."/{$slug}.json"), true);

        return [
            'slug' => $slug,
            'url' => (string) ($meta['url'] ?? ''),
            'titulo' => (string) ($meta['titulo'] ?? ''),
            'video' => asset('storage/'.self::DIR."/{$slug}.mp4"),
            'poster' => $disco->exists(self::DIR."/{$slug}.jpg") ? asset('storage/'.self::DIR."/{$slug}.jpg") : '',
            'pagina' => route('demo.imovel', $slug),
        ];
    }

    /** rename(), or copy when the two paths are on different volumes. */
    private static function mover(string $de, string $para): void
    {
        if (! @rename($de, $para)) {
            copy($de, $para);
            @unlink($de);
        }
    }

    /** @return list<string> local paths of the photos that downloaded and are images */
    private function descarregar(array $urls, string $dir): array
    {
        $fotos = [];
        foreach ($urls as $i => $u) {
            try {
                $r = Http::timeout(20)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Imocena)'])->get((string) $u);
                $info = $r->successful() ? @getimagesizefromstring($r->body()) : false;
                if ($info && $info[0] >= 400) {
                    file_put_contents($f = "$dir/foto{$i}.".(image_type_to_extension($info[2], false) ?: 'jpg'), $r->body());
                    $fotos[] = $f;
                }
            } catch (\Throwable) {
                // one photo missing is fine
            }
        }

        return $fotos;
    }

    public static function duracao(string $ficheiro): float
    {
        $p = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $ficheiro]);
        $p->run();

        return max(0.0, (float) trim($p->getOutput()));
    }

    /**
     * The ffmpeg command: each photo panned slowly (alternating direction), faded
     * into the next, the mark over them, the end card last; the voice after a
     * second's pause, the music low under everything.
     *
     * @param  list<string>  $fotos
     * @return list<string>
     */
    public static function comando(array $fotos, string $voz, ?string $musica, string $saida): array
    {
        $n = count($fotos);
        $fotosAte = self::ANTES + self::duracao($voz) + 0.6;       // the photos run until just after the voice
        $total = round($fotosAte + self::FIM, 2);
        $d = round(($fotosAte + ($n - 1) * self::FADE) / $n, 3);   // each photo, overlaps included
        $W = self::W;
        $H = self::H;
        $bw = (int) round($W * 1.12);
        $bh = (int) round($H * 1.12);

        $args = ['ffmpeg', '-nostdin', '-y', '-v', 'error'];
        foreach ($fotos as $f) {
            array_push($args, '-loop', '1', '-framerate', '30', '-t', (string) $d, '-i', $f);
        }
        $iFim = $n;
        $iMarca = $n + 1;
        $iVoz = $n + 2;
        array_push($args, '-loop', '1', '-framerate', '30', '-t', (string) (self::FIM + self::FADE), '-i', resource_path('demo/fim.png'));
        array_push($args, '-loop', '1', '-framerate', '30', '-t', (string) $total, '-i', resource_path('demo/marca.png'));
        array_push($args, '-i', $voz);
        if ($musica) {
            array_push($args, '-stream_loop', '-1', '-i', $musica);
        }

        $fc = [];
        foreach ($fotos as $i => $f) {
            // Cover a frame 12% larger than the video, then move the crop across it.
            [$x, $y] = match ($i % 4) {
                0 => ["(iw-$W)*t/$d", '(ih-'.$H.')/2'],
                1 => ["(iw-$W)*(1-t/$d)", '(ih-'.$H.')/2'],
                2 => ['(iw-'.$W.')/2', "(ih-$H)*t/$d"],
                default => ['(iw-'.$W.')/2', "(ih-$H)*(1-t/$d)"],
            };
            $fc[] = "[$i:v]scale=$bw:$bh:force_original_aspect_ratio=increase,crop=$bw:$bh,crop=$W:$H:x='$x':y='$y',setsar=1,fps=30,format=yuv420p[f$i]";
        }
        $fc[] = "[$iFim:v]scale=$W:$H,setsar=1,fps=30,format=yuv420p[fim]";

        $atual = 'f0';
        for ($i = 1; $i < $n; $i++) {
            $off = round($i * ($d - self::FADE), 3);
            $fc[] = "[$atual][f$i]xfade=transition=fade:duration=".self::FADE.":offset={$off}[x$i]";
            $atual = "x$i";
        }
        $fc[] = "[$iMarca:v]format=rgba[marca]";
        $fc[] = "[$atual][marca]overlay=0:0:shortest=1[comMarca]";
        $offFim = round($n * ($d - self::FADE), 3);
        $fc[] = '[comMarca][fim]xfade=transition=fade:duration='.self::FADE.":offset=$offFim,trim=duration={$total}[v]";

        $ms = (int) round(self::ANTES * 1000);
        $fc[] = "[$iVoz:a]aresample=44100,aformat=channel_layouts=stereo,adelay=$ms|$ms,apad[voz]";
        if ($musica) {
            $fc[] = '['.($iVoz + 1).':a]aresample=44100,aformat=channel_layouts=stereo,volume=0.13,afade=t=in:d=0.6,afade=t=out:st='.round($total - 1.6, 2).':d=1.6[mus]';
            $fc[] = '[voz][mus]amix=inputs=2:normalize=0:duration=first,atrim=duration='.$total.'[a]';
        } else {
            $fc[] = "[voz]atrim=duration={$total}[a]";
        }

        return [...$args, '-filter_complex', implode(';', $fc), '-map', '[v]', '-map', '[a]', '-t', (string) $total,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $saida];
    }
}
