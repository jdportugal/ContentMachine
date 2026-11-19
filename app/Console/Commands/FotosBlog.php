<?php

namespace App\Console\Commands;

use App\Services\Guias\Guias;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * The blog's photos: for each guide, a realistic AI photo per image idea in
 * resources/guias/videos/guioes.json (one or two), saved as
 * public/media/guias/fotos/<slug>-<n>.jpg. The first opens the article and stands on
 * the blog's cards; the second goes inside the text (Guias::comFotos). A guide without
 * photos still shows as before.
 */
class FotosBlog extends Command
{
    /** How every photo is described to the model, so the blog looks like one series. */
    public const ESTILO = 'Realistic editorial photograph for a Portuguese real-estate blog, natural daylight, warm and calm, Portugal, wide 16:9 frame, no text, no captions, no logos, no watermarks, no recognisable brands';

    protected $signature = 'imocena:fotos-blog {slugs?*} {--todos : todos os guias sem fotos} {--refazer : refazer mesmo com fotos}';

    protected $description = 'Gera as fotografias de cada guia do blog';

    public function handle(Guias $guias, KieClient $kie): int
    {
        $kie->passo = 'clips_imagens';   // the key the clip images already use
        if (! $kie->configurado()) {
            $this->error('Sem chave de imagens (kie.ai).');

            return self::FAILURE;
        }
        $ideias = json_decode((string) file_get_contents(resource_path('guias/videos/guioes.json')), true);
        $pasta = public_path('media/guias/fotos');
        @mkdir($pasta, 0775, true);

        $lista = (array) $this->argument('slugs');
        if ($lista === [] && $this->option('todos')) {
            $lista = array_keys($guias->todos());
        }
        foreach ($lista as $slug) {
            $prompts = array_slice(array_values((array) ($ideias[$slug]['imagens'] ?? [])), 0, 2);
            if ($guias->encontrar($slug) === null || $prompts === []) {
                $this->warn("{$slug}: sem guia ou sem ideias de imagem");

                continue;
            }
            foreach ($prompts as $i => $prompt) {
                $ficheiro = sprintf('%s/%s-%d.jpg', $pasta, $slug, $i + 1);
                if (is_file($ficheiro) && ! $this->option('refazer')) {
                    continue;
                }
                try {
                    $url = $kie->generate(self::ESTILO.' — '.$prompt, '16:9');
                    self::guardarJpeg(Http::timeout(90)->get($url)->body(), $ficheiro);
                    $this->info("{$slug}: foto ".($i + 1).' pronta');
                } catch (\Throwable $e) {
                    $this->error("{$slug}: foto ".($i + 1).' falhou — '.mb_substr($e->getMessage(), 0, 200));
                }
            }
        }

        return self::SUCCESS;
    }

    /** At most 1600 px wide, as a JPEG the page can load quickly. */
    public static function guardarJpeg(string $bytes, string $ficheiro): void
    {
        $img = $bytes !== '' ? @imagecreatefromstring($bytes) : false;
        if ($img === false) {
            throw new \RuntimeException('imagem inválida');
        }
        if (imagesx($img) > 1600) {
            $img = imagescale($img, 1600);
        }
        imagejpeg($img, $ficheiro, 82);
        imagedestroy($img);
    }
}
