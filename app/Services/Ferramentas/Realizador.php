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

    /**
     * Per tool: `analise` (what makes a photo a good start; Analista), `frame` (Nano Banana Pro, the AI frame),
     * `video` + `som` (Kling 3.0). Wording from the research (2026-11-26): Google's Nano Banana guide (say what changes
     * and what stays exactly the same, photographic terms like "aerial view", positive framing, no extreme angles) and
     * Kling camera guides (the move in the first words, one move, "slow, steady", easing to rest: the faster the
     * camera, the more Kling invents). kie's Kling 3.0 takes no negative prompt, so everything is said positively.
     */
    public const PROMPTS = [
        'drone' => [
            'analise' => 'You check a real-estate photo before it is turned into an AI "drone rise" video: the video starts '
                .'exactly at this photo and the camera rises to a low drone view about 7 metres up, looking at the same house. '
                .'A GOOD start: an exterior photo of a house or building taken from the ground (street, pavement, garden), '
                .'the building whole or nearly whole in the frame, some sky above the roof, daylight, sharp. '
                .'PROBLEMS to look for: an interior; a floor plan, drawing or 3D render screenshot of a plan; a photo already '
                .'taken from the air; a close-up where the house is cut off at the sides or the roof; the house mostly hidden '
                .'by cars, trees, walls, scaffolding or people; night or very dark; blurry, heavily filtered, a collage, or '
                .'text and logos over the picture; a very wide panorama; a vertical phone photo where the house is small.',
            'frame' => 'Using this photo as the reference, show this same house as an aerial drone photograph: the camera '
                .'raised to about 7 metres above the ground, standing on the same side of the house and facing the same '
                .'direction, gently tilted down so the roof, the plot and the immediate surroundings are seen from above. '
                .'Keep exactly the same: the architecture, facade, windows, doors, roof shape, colours, materials and '
                .'proportions of the house, and the neighbouring buildings, street, trees and plants where they are. '
                .'What only the higher view reveals (the roof surface, the garden or yard, the plots next door) is simple, '
                .'tidy and consistent with the photo. Preserve the same time of day, light direction, shadows and weather. '
                .'A natural, moderate drone angle, not top-down. A clean, photoreal real-estate aerial photograph with an '
                .'empty, clear sky area and an image free of people, text and logos.',
            'video' => 'Slow pedestal up, like a drone rising: the camera lifts steadily from the street-level view of the '
                .'first frame, tilting gently down, and eases to rest exactly on the aerial view of the last frame. One '
                .'continuous, slow, smooth move with no cuts. The house stays solid and identical throughout, with straight '
                .'rooflines and an unchanged facade; the street, trees and neighbouring houses stay still; the same daylight '
                .'from start to end.',
            'som' => ' Audio: quiet residential street ambience, a light breeze and distant birds; no music, no voices.',
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
                $tarefa = $this->kie->submitFramesVideo($prompt, $inicio, $fim, '', $segundos, true, self::MODELO_VIDEO);
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
