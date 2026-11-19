<?php

// The blog's social videos, in the ads' design (remotion/src/blog/BlogShort.tsx).
// Each guide's video is a list of scenes in resources/guias/videos/cenas/<slug>.json;
// this records each scene's pt-PT voiceover (the listing videos' voice), lays out the
// photos it names, renders the video and saves it as an unlisted draft (plus a contact
// sheet) in public/media/guias/rascunhos/, for someone to watch before
// `imocena:videos-blog --publicar=<slug>` puts it on the guide's page.
// Run inside the app container:
//   php scripts/blog/videos.php luz-natural-fotografar-casa [outro-slug ...]
//
// A scene's "foto" is one of:
//   "demo:03-sala.png"   a photo of Imocena's demo house (public/media/demo/moradia-lajeosa)
//   "ia:<file>.jpg"      an AI photo made for the videos (public/media/guias/fotos/videos)
//   absent               the navy background: a video without AI images is made of these
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slugs = array_slice($argv, 1) ?: exit("Uso: php scripts/blog/videos.php <slug> [...]\n");
$voz = (array) config('contentmachine.real_estate.voices.m');
$tts = app(App\Services\Clips\Api\ElevenLabsVoiceoverService::class);
$musicas = glob(storage_path('app/shorts/musicas').'/*.mp3') ?: [];
sort($musicas);
$rascunhos = public_path('media/guias/rascunhos');
@mkdir($rascunhos, 0775, true);

foreach ($slugs as $slug) {
    $spec = json_decode((string) @file_get_contents(resource_path("guias/videos/cenas/{$slug}.json")), true);
    if (! is_array($spec) || empty($spec['cenas'])) {
        echo "{$slug}: sem cenas em resources/guias/videos/cenas/{$slug}.json\n";

        continue;
    }
    $dir = "/tmp/blogvid/{$slug}";
    @mkdir($dir, 0775, true);

    // AI photos the scenes need and nobody has made yet: made with kie.ai (9:16), from
    // "fotos": {"<file>.jpg": "prompt"} or {"<file>.jpg": {"prompt": "…", "de": "<other>.jpg"}}
    // ("de": an edit of that other photo, for a before/after of the same room).
    $pastaIa = public_path('media/guias/fotos/videos');
    @mkdir($pastaIa, 0775, true);
    foreach ((array) ($spec['fotos'] ?? []) as $nome => $pedido) {
        $destino = $pastaIa.'/'.basename((string) $nome);
        if (is_file($destino)) {
            continue;
        }
        $kie = app(App\Services\Publicacoes\Rendering\KieClient::class);
        $kie->passo = 'clips_imagens';
        if (! $kie->configurado()) {
            exit("{$slug}: falta a foto {$nome} e não há chave da kie.ai (Definições → Passos → imagens dos clips)\n");
        }
        $prompt = is_array($pedido) ? (string) ($pedido['prompt'] ?? '') : (string) $pedido;
        $de = is_array($pedido) ? (string) ($pedido['de'] ?? '') : '';
        $estilo = 'Realistic editorial photograph, Portugal, natural daylight, warm and calm, vertical 9:16 frame, no text, no captions, no logos, no watermarks';
        // kie sometimes times out: one more try, then this video waits for the next run.
        for ($tentativa = 1; $tentativa <= 2 && ! is_file($destino); $tentativa++) {
            try {
                $url = $de !== '' && is_file($pastaIa.'/'.basename($de))
                    ? $kie->edit($prompt.'. Keep the walls, windows, floor and view exactly the same.', '9:16', (string) file_get_contents($pastaIa.'/'.basename($de)), basename($de))
                    : $kie->generate($estilo.' — '.$prompt, '9:16');
                App\Console\Commands\FotosBlog::guardarJpeg(Illuminate\Support\Facades\Http::timeout(90)->get($url)->body(), $destino);
                echo "{$slug}: foto {$nome} feita\n";
            } catch (Throwable $e) {
                echo "{$slug}: foto {$nome} falhou ({$tentativa}) — ".mb_substr($e->getMessage(), 0, 120)."\n";
            }
        }
        if (! is_file($destino)) {
            echo "{$slug}: fica para a próxima (sem a foto {$nome})\n";

            continue 2;
        }
    }

    $cenas = [];
    foreach ($spec['cenas'] as $i => $c) {
        // The photo, copied next to the props (Remotion's public dir for this render).
        if (! empty($c['foto'])) {
            [$tipo, $nome] = explode(':', (string) $c['foto'], 2) + [1 => ''];
            $origem = match ($tipo) {
                'demo' => public_path('media/demo/moradia-lajeosa/'.basename($nome)),
                'ia' => public_path('media/guias/fotos/videos/'.basename($nome)),
                default => '',
            };
            if (! is_file($origem)) {
                exit("{$slug}: a cena ".($i + 1)." pede {$c['foto']}, que não existe\n");
            }
            copy($origem, "{$dir}/".basename($nome));
            $c['foto'] = basename($nome);
            if (! empty($c['fotoB'])) {
                [$t2, $n2] = explode(':', (string) $c['fotoB'], 2) + [1 => ''];
                $o2 = $t2 === 'demo' ? public_path('media/demo/moradia-lajeosa/'.basename($n2)) : public_path('media/guias/fotos/videos/'.basename($n2));
                copy($o2, "{$dir}/".basename($n2));
                $c['fotoB'] = basename($n2);
            }
        }
        // The voiceover: a line said the same way is recorded once.
        $fala = trim((string) ($c['fala'] ?? ''));
        $dur = 0.0;
        if ($fala !== '') {
            $ficheiro = 'vo-'.substr(md5($voz['voice_id'].$fala), 0, 12).'.mp3';
            if (! is_file("{$dir}/{$ficheiro}")) {
                $tts->synthesizeWith($fala, "{$dir}/{$ficheiro}", $voz['voice_id'], $voz['model'], $voz['stability'] ?? null);
            }
            $dur = (float) trim((string) shell_exec('ffprobe -v quiet -show_entries format=duration -of csv=p=0 '.escapeshellarg("{$dir}/{$ficheiro}")));
            $c['audio'] = $ficheiro;
            $c['legenda'] ??= $fala;
        }
        $ultima = $i === count($spec['cenas']) - 1;
        $c['segundos'] = round(max((float) ($c['segundos'] ?? 0), $dur + ($ultima ? 1.6 : 0.3), $ultima ? 4.0 : 2.0), 2);
        unset($c['fala']);
        $cenas[] = $c;
        echo "{$slug} ".($i + 1)." {$c['tipo']}: {$c['segundos']}s\n";
    }

    // Music: the guide's own pick, else one of the library's tracks, the same for a guide every time.
    $musica = null;
    $faixa = $spec['musica'] ?? ($musicas !== [] ? basename($musicas[crc32($slug) % count($musicas)]) : null);
    if ($faixa && is_file(storage_path('app/shorts/musicas/'.$faixa))) {
        copy(storage_path('app/shorts/musicas/'.$faixa), "{$dir}/{$faixa}");
        $musica = $faixa;
    }
    file_put_contents("{$dir}/props.json", json_encode(['cenas' => $cenas, 'musica' => $musica], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $saida = "{$dir}/video.mp4";
    @unlink($saida);
    $cmd = 'cd '.escapeshellarg(base_path('remotion')).' && npx remotion render src/blog/index.ts BlogShort '.escapeshellarg($saida)
        .' --props='.escapeshellarg("{$dir}/props.json").' --public-dir='.escapeshellarg($dir).' --log=error 2>&1';
    $log = (string) shell_exec($cmd);
    if (! is_file($saida)) {
        echo "{$slug}: a renderização falhou\n".mb_substr($log, -1500)."\n";

        continue;
    }
    $nome = $slug.'-'.substr(md5_file($saida), 0, 8);
    copy($saida, "{$rascunhos}/{$nome}.mp4");
    // A contact sheet, one frame every ~3 s, to look at it without playing it.
    shell_exec('ffmpeg -v error -y -i '.escapeshellarg($saida).' -vf "fps=1/3,scale=270:-1,tile=6x2:padding=6:color=0x222222" -frames:v 1 '.escapeshellarg("{$rascunhos}/folha-{$nome}.jpg"));
    echo "{$slug}: rascunho → ".asset("media/guias/rascunhos/{$nome}.mp4")."\n";
}
echo "PRONTO\n";
