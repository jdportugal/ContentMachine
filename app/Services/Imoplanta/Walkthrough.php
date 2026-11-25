<?php

namespace App\Services\Imoplanta;

use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The walkthrough video: each finished photo becomes a 5 s AI camera move (Kling image-to-video via kie.ai,
 * started from the photo, so the finishes stay the photos'), in tour order — outside first, then the entrance,
 * living spaces, bedrooms and bathrooms, the drone view last — joined with short crossfades into one 1080p MP4.
 */
class Walkthrough
{
    public const FUNDIDO = 0.6;   // crossfade between clips, seconds

    private const ORDEM = ['exterior' => 0, 'hall' => 1, 'circulacao' => 2, 'sala' => 3, 'cozinha' => 4, 'escritorio' => 5,
        'quarto' => 6, 'wc' => 7, 'lavandaria' => 8, 'arrumos' => 9, 'garagem' => 9, 'outro' => 9, 'aereo' => 10];

    private const DIVISOES = [
        'sala' => 'living room', 'cozinha' => 'kitchen', 'quarto' => 'bedroom', 'wc' => 'bathroom',
        'escritorio' => 'home office', 'lavandaria' => 'laundry room', 'hall' => 'entrance hall',
        'circulacao' => 'hallway', 'garagem' => 'garage', 'arrumos' => 'storage room', 'outro' => 'room',
    ];

    /** What each photo shows: a room use, 'exterior' or 'aereo'. */
    public function tipo(Imoplanta $p, ImoplantaImagem $img): string
    {
        if ((float) ($img->camera['altura'] ?? 1.6) > 8) {
            return 'aereo';
        }

        return ImoplantaCasa::divisaoDa($p->casa ?? [], $img->camera, $p->tipologia === 'apartamento')['uso'] ?? 'exterior';
    }

    /** The finished photos in tour order. */
    public function ordem(Imoplanta $p): array
    {
        return $p->imagens->filter(fn (ImoplantaImagem $i) => $i->foto)
            ->sortBy(fn (ImoplantaImagem $i) => sprintf('%02d-%03d', self::ORDEM[$this->tipo($p, $i)] ?? 9, $i->numero))
            ->values()->all();
    }

    /** The camera move for one photo. */
    public function prompt(string $tipo): string
    {
        $fixo = 'The space, the furniture, the walls, the doors, the windows and every material stay exactly as in the '
            .'image: nothing appears, disappears, bends or changes shape. Every window and door keeps its exact number, size '
            .'and place; no new window, door or opening appears on any wall. Natural daylight, lamps off. No people, no text. '
            .'Photorealistic, smooth and steady like a professional real-estate video on a gimbal. Avoid: blur, warped walls, '
            .'morphing furniture, melting objects, fast or shaky movement.';

        return match ($tipo) {
            'aereo' => 'A slow, smooth drone shot: the camera glides gently sideways around the home, keeping it centred. '.$fixo,
            'exterior' => 'A slow, smooth real-estate video: the camera walks gently forward towards the home at eye level. '.$fixo,
            default => 'A slow, smooth real-estate video inside the '.(self::DIVISOES[$tipo] ?? 'room').': the camera pushes slowly '
                .'straight forward into the room at eye level, with no turn or pan, so nothing outside the photo comes into view. '.$fixo,
        };
    }

    /**
     * Joins the clips (absolute paths, in order) into one 1920x1080 30 fps H.264 MP4 with crossfades and a fade
     * in and out. Throws when ffmpeg fails.
     */
    public function juntar(array $clips, string $destino): void
    {
        $f = self::FUNDIDO;
        $dur = array_map(fn ($c) => $this->duracao($c), $clips);
        $args = ['ffmpeg', '-y'];
        foreach ($clips as $c) {
            array_push($args, '-i', $c);
        }
        $filtros = [];
        foreach ($clips as $i => $c) {
            $filtros[] = "[{$i}:v]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=30,format=yuv420p[v{$i}]";
        }
        $ultimo = 'v0';
        $acumulado = $dur[0];
        for ($i = 1; $i < count($clips); $i++) {
            $offset = round($acumulado - $f, 3);
            $filtros[] = "[{$ultimo}][v{$i}]xfade=transition=fade:duration={$f}:offset={$offset}[x{$i}]";
            $ultimo = "x{$i}";
            $acumulado += $dur[$i] - $f;
        }
        $fim = round(max(0, $acumulado - 0.8), 3);
        $filtros[] = "[{$ultimo}]fade=t=in:st=0:d=0.5,fade=t=out:st={$fim}:d=0.8[out]";
        array_push($args, '-filter_complex', implode(';', $filtros), '-map', '[out]', '-an',
            '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-movflags', '+faststart', $destino);

        $proc = new Process($args);
        $proc->setTimeout(600);
        $proc->run();
        if (! $proc->isSuccessful()) {
            throw new RuntimeException('ffmpeg: '.substr($proc->getErrorOutput(), -400));
        }
    }

    private function duracao(string $clip): float
    {
        $p = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $clip]);
        $p->run();

        return max(1.0, (float) trim($p->getOutput()) ?: 5.0);
    }
}
