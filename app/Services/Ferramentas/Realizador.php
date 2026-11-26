<?php

namespace App\Services\Ferramentas;

use App\Models\FerramentaIa;
use App\Services\Imoplanta\Fotografo;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Makes a Ferramentas IA video's pictures: the AI frame (Nano Banana Pro at 2K from the customer's photo, with the
 * fallback model, as Imoplanta's photos) and the clip (Kling 3.0 pro, start frame → end frame, with sound). Each
 * tool is its PROMPTS entry: `frame` makes the AI frame from the photo, `video` and `som` the clip.
 */
class Realizador
{
    /** Kling is paid per run and we don't yet know whether kie charges failed runs: two attempts, not four. */
    public const TENTATIVAS_VIDEO = 2;

    public const MODELO_VIDEO = 'kling-3.0/video';

    public const PROMPTS = [
        'drone' => [
            'frame' => 'Turn this real-estate photograph into the same property seen from a low drone. Raise the camera to '
                .'about 6 to 8 metres above the ground, on the same side and looking in the same direction, tilted slightly '
                .'down so the roof, the plot and the immediate surroundings show. Keep the house exactly as it is: the same '
                .'architecture, walls, windows, doors, roof, colours and materials. Keep the same neighbouring houses, street, '
                .'trees and plants in their places. What the higher view reveals (garden, terrace, pool, roofs, the next plots) '
                .'must be plain and consistent with the photo; invent nothing remarkable. Same time of day, same light and '
                .'weather. A photoreal aerial photograph: no people added, no text, no watermark.',
            'video' => 'A smooth real-estate drone shot. The camera starts exactly at the eye-level view of the first frame '
                .'and rises slowly and steadily, drifting slightly forward and tilting down, to end exactly on the high view '
                .'of the last frame. One continuous move, no cuts. The house, the street and everything around stay solid and '
                .'unchanged; nothing appears, disappears or changes shape.',
            'som' => ' Sound: a soft outdoor ambience, a light breeze and distant birds. No music, no voices.',
            'negativo' => 'cuts, scene change, morphing buildings, house changing shape, extra buildings, people, text, watermark, flicker, fast motion, shaking',
        ],
    ];

    public const PROMPT_ALTERAR = 'Change this image as asked, and nothing else: «%s». Keep everything else exactly as it is: '
        .'the framing, the house, its surroundings, the light and the colours. The second image is the original photograph '
        .'of the property, for reference: the house must stay true to it. Photoreal, no text, no watermark.';

    public function __construct(private KieClient $kie) {}

    /** The AI frame, new from the photo or with the customer's change ($pedido) to the current one. Returns its path. */
    public function enquadrar(FerramentaIa $f, ?string $pedido = null): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $this->configurado();
        $original = $disk->get($f->original);
        $urlOriginal = $this->kie->upload($original, 'foto.jpg');
        $atual = $f->{$f->quadroIa()};
        if ($pedido !== null && $atual && $disk->exists($atual)) {
            $prompt = sprintf(self::PROMPT_ALTERAR, $pedido);
            $refs = [$this->kie->upload($disk->get($atual), 'atual.png'), $urlOriginal];
        } else {
            $prompt = self::PROMPTS[$f->ferramenta]['frame'];
            $refs = [$urlOriginal];
        }
        $proporcao = Fotografo::proporcao($original);
        $url = Fotografo::comAlternativa(
            fn () => $this->kie->submitResolucao($prompt, $proporcao, $refs, '2K'),
            fn () => $this->kie->submitAlternativo($prompt, $proporcao, $refs),
            fn ($t) => $this->kie->poll($t),
        );
        [$w, $h] = getimagesizefromstring($original) ?: [0, 0];
        $caminho = $f->pasta().'/quadro-'.Str::random(8).'.png';
        $disk->put($caminho, self::ajustar(Http::timeout(120)->retry(2, 2000)->get($url)->throw()->body(), $w, $h));

        return $caminho;
    }

    /** The clip from the approved frames ($nota: the customer's wish for a new version). Returns its path. */
    public function animar(FerramentaIa $f, ?string $nota = null): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $this->configurado();
        $p = self::PROMPTS[$f->ferramenta];
        $segundos = $f->tipo()['segundos'];
        $inicio = $this->kie->upload($disk->get($f->inicio), 'inicio.png');
        $fim = $this->kie->upload($disk->get($f->fim), 'fim.png');
        $prompt = $p['video'].($nota ? ' The client also asks: '.$nota.'.' : '').$p['som'];
        $erro = null;
        for ($i = 1; $i <= self::TENTATIVAS_VIDEO; $i++) {
            try {
                $tarefa = $this->kie->submitFramesVideo($prompt, $inicio, $fim, $p['negativo'], $segundos, true, self::MODELO_VIDEO);
                $url = $this->kie->pollVideo($tarefa, $this->kie->precoKling($segundos, true));
                $caminho = $f->pasta().'/video-'.Str::random(8).'.mp4';
                $disk->put($caminho, Http::timeout(300)->retry(2, 2000)->get($url)->throw()->body());

                return $caminho;
            } catch (\Throwable $e) {
                $erro = $e;
                Log::warning('Ferramentas IA: Kling attempt '.$i.' failed for video '.$f->id.': '.$e->getMessage());
            }
        }

        throw $erro;
    }

    /**
     * The AI frame cut to the photo's exact size (scaled to cover, centred): the image AI only has set ratios, and
     * Kling's two frames must match or the clip jumps at the end.
     */
    public static function ajustar(string $imagem, int $w, int $h): string
    {
        $im = @imagecreatefromstring($imagem);
        if (! $im || $w < 1 || $h < 1) {
            return $imagem;
        }
        [$iw, $ih] = [imagesx($im), imagesy($im)];
        $escala = max($w / $iw, $h / $ih);
        [$cw, $ch] = [(int) round($w / $escala), (int) round($h / $escala)];
        $novo = imagecreatetruecolor($w, $h);
        imagecopyresampled($novo, $im, 0, 0, (int) (($iw - $cw) / 2), (int) (($ih - $ch) / 2), $w, $h, $cw, $ch);
        ob_start();
        imagepng($novo);

        return (string) ob_get_clean();
    }

    private function configurado(): void
    {
        $this->kie->passo = 'imoplanta_imagens';
        if (! $this->kie->configurado()) {
            throw new RuntimeException('Falta a chave kie.ai (Definições → Passos → Imoplanta · imagens).');
        }
    }
}
