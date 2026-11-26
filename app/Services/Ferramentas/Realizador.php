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
     * Per tool: `analise` (what makes a photo a good start; Analista), `inicio` / `fim` (Nano Banana Pro, for each
     * frame the AI makes), `video` + `som` (Kling 3.0; `som` null: a silent clip). Wording from the research (2026-11-26): Google's Nano Banana guide (say what changes
     * and what stays exactly the same, photographic terms like "aerial view", positive framing, no extreme angles) and
     * Kling camera guides (the move in the first words, one move, "slow, steady", easing to rest: the faster the
     * camera, the more Kling invents). kie's Kling 3.0 takes no negative prompt, so everything is said positively.
     */
    public const PROMPTS = [
        'drone' => [
            'analise' => 'You check a real-estate photo before it is turned into an AI drone video: from this photo the AI '
                .'draws the house from the air, about 10 metres up, then the drone circles around it. '
                .'A GOOD start: an exterior photo of a house or building taken from the ground (street, pavement, garden), '
                .'the building whole or nearly whole in the frame, some sky above the roof, daylight, sharp. '
                .'PROBLEMS to look for: an interior; a floor plan, drawing or 3D render screenshot of a plan; a photo already '
                .'taken from the air; a close-up where the house is cut off at the sides or the roof; the house mostly hidden '
                .'by cars, trees, walls, scaffolding or people; night or very dark; blurry, heavily filtered, a collage, or '
                .'text and logos over the picture; a very wide panorama; a vertical phone photo where the house is small. '
                .'Because the drone circles the house, it works best for a detached or end-of-row house with some space or '
                .'garden around it; a house in a continuous row of attached houses on a narrow street is NOT ideal (say so: '
                .'the drone can only show the front and the roofs).',
            // the start: the house from the air, from the side the photo was taken, centred (the orbit turns around it)
            'inicio' => 'Using this photo as the reference, show the same house as an aerial photograph taken by a drone '
                .'about 10 metres up, on the same side of the house as the original camera and facing it, gently tilted down '
                .'(about 30 degrees), with the main house of the photo in the centre of the frame and a little of its plot '
                .'and the neighbouring buildings around it. Keep exactly the same: the architecture, facade, windows, doors, '
                .'balconies, roof shape, colours, materials and proportions of the house, and the street, neighbouring '
                .'buildings, trees, plants and parked cars where they are. What only the higher view reveals (the roof, the '
                .'yard behind walls) is simple, tidy and consistent with the photo. Preserve the same time of day, light '
                .'direction, shadows and weather. A clean, photoreal real-estate drone photograph, with the picture free of '
                .'text, logos and watermarks.',
            // the end: the same drone view after orbiting ~45° around the house
            'fim' => 'The first image is a drone photograph of a house; the second is the original ground photo of it, for '
                .'reference. Show the same house from the same drone height, distance and downward tilt, with the drone moved '
                .'about 45 degrees around the house to the right (counter-clockwise seen from above), still pointing at the '
                .'house so it stays in the centre of the frame. The house is exactly the same building: the same facade, '
                .'windows, doors, roof shape, colours, materials and proportions, now seen a little more from its side, so '
                .'more of its side wall and of the plot beside it shows. The street, neighbouring buildings, trees and parked '
                .'cars are the same ones, shifted as the new angle requires. The sun is in the same place in the sky, so the '
                .'shadows fall the same way on the ground. A clean, photoreal real-estate drone photograph, with the picture '
                .'free of text, logos and watermarks.',
            'video' => 'Slow drone orbit around the house: the camera circles smoothly to the right at a constant height, '
                .'always pointing at the house so it stays in the centre, from the view of the first frame to the view of the '
                .'last frame, easing to rest. One continuous, slow, steady arc with no cuts. The house and every building '
                .'stay solid and identical, with straight rooflines and unchanged facades; parked cars, trees and the street '
                .'stay still; the same daylight from start to end.',
            'som' => null,   // silent (the user's call, 2026-11-26): also the cheaper Kling price
        ],
        // The side move: the photo is the only frame and Kling makes the slide (tested 2026-11-26: two crops of the
        // photo made a flat 2D pan; an AI end frame moved objects around; Kling alone had the real gimbal depth but
        // went too far and invented things, so the move is kept to about a metre). %s = right | left.
        'lateral' => [
            'analise' => 'You check a real-estate photo before it is turned into an AI "gimbal slide" video: the camera glides '
                .'steadily about one metre sideways from this exact view, like a gimbal slider shot in slow motion. It '
                .'works for interiors and exteriors. A GOOD start: a sharp, well-lit, level photo with depth, some objects or '
                .'furniture in front of a background (the parallax is what makes it beautiful). PROBLEMS to look for: a floor '
                .'plan, drawing or screenshot; a close-up of a flat wall or of one object, with nothing behind it; people or '
                .'animals in the picture (they freeze or warp); blurry, very dark or heavily filtered; strongly tilted; a '
                .'collage, or text and logos over the picture; a very wide panorama.',
            // "half a metre, very slow, eases to rest" sometimes barely moved (user, 2026-11-26): about a metre, a
            // constant pace that is clearly visible from the first frame to the last
            'video' => 'Truck %1$s: a smooth gimbal slider shot in slow motion. From the very first frame the camera glides '
                .'sideways to the %1$s at a steady, constant, clearly visible pace, about one metre over the whole shot, and '
                .'keeps moving until the last frame. It keeps the same height and faces the same direction, with natural '
                .'parallax between nearby objects and the background. Every object stays exactly as it is and where it is; '
                .'the room or scene is the same one throughout, with the same light. One continuous move with no cuts, no '
                .'zoom and no rotation.',
            'som' => null,
        ],
    ];

    public const PROMPT_ALTERAR = 'Change this image as asked, and nothing else: «%s». Keep everything else exactly as it is: '
        .'the framing, the house, its surroundings, the light and the colours. The other images are the other frame of the video and '
        .'its original photograph, for reference: the house must stay true to them. Photoreal, no text, no watermark.';

    public function __construct(private KieClient $kie) {}

    /**
     * One AI frame ($qual: inicio|fim), new or with the customer's change ($pedido) to its current version. The
     * references: the current frame (a change), the other frame when the AI made it too (so the two stay one house),
     * then the customer's photo. Returns its path.
     */
    public function enquadrar(FerramentaIa $f, string $qual, ?string $pedido = null): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $this->configurado();
        $original = $disk->get($f->original);
        $tem = fn (?string $c) => $c && $c !== $f->original && $disk->exists($c);
        $outro = $qual === 'inicio' ? 'fim' : 'inicio';
        $refs = [];
        if ($pedido !== null && $tem($f->{$qual})) {
            $prompt = sprintf(self::PROMPT_ALTERAR, $pedido);
            $refs[] = $this->kie->upload($disk->get($f->{$qual}), 'atual.png');
        } else {
            $prompt = self::PROMPTS[$f->ferramenta][$qual];
        }
        if ($tem($f->{$outro})) {
            $refs[] = $this->kie->upload($disk->get($f->{$outro}), 'outro.png');
        }
        $refs[] = $this->kie->upload($original, 'foto.jpg');
        $proporcao = Fotografo::proporcao($original);
        $url = Fotografo::comAlternativa(
            fn () => $this->kie->submitResolucao($prompt, $proporcao, $refs, '2K'),
            fn () => $this->kie->submitAlternativo($prompt, $proporcao, $refs),
            fn ($t) => $this->kie->poll($t),
        );
        [$w, $h] = getimagesizefromstring($original) ?: [0, 0];
        $caminho = $f->pasta().'/'.$qual.'-'.Str::random(8).'.png';
        $disk->put($caminho, self::ajustar(Http::timeout(120)->retry(2, 2000)->get($url)->throw()->body(), $w, $h));

        return $caminho;
    }

    /** The clip from the approved frames, or from the photo alone ($nota: the customer's wish for a new version). Returns its path. */
    public function animar(FerramentaIa $f, ?string $nota = null): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $this->configurado();
        $p = self::PROMPTS[$f->ferramenta];
        $segundos = $f->tipo()['segundos'];
        $inicio = $this->kie->upload($disk->get($f->inicio ?: $f->original), 'inicio.png');
        $fim = $f->fim ? $this->kie->upload($disk->get($f->fim), 'fim.png') : null;   // none: Kling makes the move from the start
        $som = ($p['som'] ?? null) !== null;
        $video = ($f->tipo()['direcao'] ?? false) ? sprintf($p['video'], $f->direcao() === 'esquerda' ? 'left' : 'right') : $p['video'];
        $prompt = $video.($nota ? ' The client also asks: '.$nota.'.' : '').($p['som'] ?? '');
        $erro = null;
        for ($i = 1; $i <= self::TENTATIVAS_VIDEO; $i++) {
            try {
                $tarefa = $this->kie->submitFramesVideo($prompt, $inicio, $fim, '', $segundos, $som, self::MODELO_VIDEO);
                $url = $this->kie->pollVideo($tarefa, $this->kie->precoKling($segundos, $som));
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
