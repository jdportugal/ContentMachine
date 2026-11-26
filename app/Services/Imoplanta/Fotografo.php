<?php

namespace App\Services\Imoplanta;

use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a three.js render (made in the customer's browser) into a photoreal
 * architectural photograph with Google's Nano Banana edit (via kie.ai — it
 * keeps the render's framing), and
 * applies a customer's change to one. The prompt is the prototype's, which
 * worked: geometry locked, everything supported, clean freshly painted walls
 * (no "weathering": it made them dirty), daylight only with every lamp off and
 * interiors darker than outside (glowing windows looked fake), real-estate
 * camera, an explicit AVOID list. Each call is stateless, so every image gets
 * the full prompt (the chat prototype could say "same as before").
 */
class Fotografo
{
    public function __construct(private KieClient $kie, private Acabamentos $acabamentos, private ImoplantaCustos $custos) {}

    /** @return string the new image's path on the Imoplanta disk */
    public function fotografar(Imoplanta $p, ImoplantaImagem $img): string
    {
        // the references, each made once per property: the finishes board, then the realistic aerial photo
        $quadro = $this->acabamentos->garantir($p, 'image_generate');
        $aereo = $this->aereo($p, $quadro, 'image_generate', $this->nivelDaFoto($p, $img));
        $extras = self::extras($p);
        $prompt = $this->prompt($p->casa ?? [], $img->camera, $p->tipologia, $this->acabamentos->texto($p), (bool) $quadro, (bool) $aereo, count($extras));

        return $this->editar($img, $img->render, $prompt, $quadro, $aereo, $extras);
    }

    /**
     * The top-view reference: the browser's straight-down render of the main storey with the roof off (the plan
     * in 3D) made real with the finishes board — a photorealistic 3D floor plan — once per property (under a
     * lock: the photo jobs run in parallel), then sent with every photo so each room's floor, furniture and
     * finishes match in all of them. Null when it can't be made (no top render yet, no key, kie failure): the
     * photos then go out without it.
     */
    public function aereo(Imoplanta $p, ?string $quadro, string $passo, ?int $nivel = null): ?string
    {
        $nivel ??= ImoplantaCasa::nivelPrincipal($p->casa ?? []);
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $pronto = fn () => ($f = $p->refresh()->aereo($nivel)) && $disk->exists($f) ? $f : null;
        if ($f = $pronto()) {
            return $f;
        }
        $render = $p->aereo($nivel, 'render');
        if (! $render || ! $disk->exists($render) || ! $this->kie->configurado()) {
            return null;
        }

        return Cache::lock('imoplanta-aereo-'.$p->id.'-'.$nivel, 900)->block(900, function () use ($p, $quadro, $passo, $pronto, $nivel, $render) {
            if ($f = $pronto()) {
                return $f;
            }
            try {
                $this->custos->contexto($p->id, 'references');
                $divisoes = ($p->casa['niveis'][$nivel] ?? [])['divisoes'] ?? [];
                $salas = json_decode((string) $p->aereo($nivel, 'salas'), true);
                $prompt = $this->promptPlanta($p->tipologia, $this->acabamentos->texto($p), (bool) $quadro, $divisoes, is_array($salas) ? $salas : []);
                $f = $this->gerar($render, $prompt, $quadro, null, $p->pasta().'/aereo-'.$nivel.'-'.Str::random(6).'.png', [],
                    self::proporcao(Storage::disk(config('contentmachine.imoplanta.disk'))->get($render)));
                $p->guardarAereo($nivel, 'img', $f);

                return $f;
            } catch (\Throwable $e) {
                report($e);

                return null;
            } finally {
                $this->custos->contexto($p->id, $passo);
            }
        });
    }

    /** The finishes board with the owner's change (an edit of the board itself); returns the new board's path. */
    public function alterarQuadro(Imoplanta $p, string $pedido): string
    {
        $prompt = 'Edit this interior-design finishes board (a material sample board photographed from above): '.$pedido.' '
            .'Change only the samples the request is about, into real materials with real texture. Keep the layout, every other sample, '
            .'the background and the light exactly as they are. No text, no labels.';

        return $this->gerar($p->acabamentos_img, $prompt, null, null, $p->pasta().'/acabamentos-'.Str::random(6).'.png', [],
            self::proporcao(Storage::disk(config('contentmachine.imoplanta.disk'))->get($p->acabamentos_img)));
    }

    /** One storey's realistic 3D floor plan with the owner's change (an edit of it); returns the new image's path. */
    public function alterarAereo(Imoplanta $p, int $nivel, string $pedido): string
    {
        $origem = $p->aereo($nivel);
        $prompt = 'Edit this photorealistic top-view 3D floor plan of a home: '.$pedido.' Change only that. Keep exactly as they are: '
            .'the framing, the outline, every wall, door, window and room with its size, and everything the request does not mention. '
            .'Straight down, no perspective.'.($p->acabamentos_img ? ' The SECOND image is the home\'s finishes board: keep using those materials.' : '')
            .' No text, no labels.';

        return $this->gerar($origem, $prompt, $p->acabamentos_img, null, $p->pasta().'/aereo-'.$nivel.'-'.Str::random(6).'.png', [],
            self::proporcao(Storage::disk(config('contentmachine.imoplanta.disk'))->get($origem)));
    }

    /**
     * The plan video add-on, for the whole home: per storey ONE continuous clip — from a bare plot (its
     * footprint, empty) the floors and walls go up, then the furniture pops in — the storeys one after the
     * other, ground floor first. A storey's clip is a locked-off Kling clip from the plot (drawn here from the
     * browser's top render) to its realistic 3D floor plan. (Two clips meeting at an AI-«emptied» plan played
     * the floor twice when the edit left the furniture in.) What a storey already has (aereos[n]: lote,
     * construcao) is reused, so a retry only pays for what is missing. Returns the video's path.
     */
    public function plantaVideo(Imoplanta $p): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        if (! $this->kie->configurado()) {
            throw new RuntimeException('Falta a chave kie.ai (Definições → Passos → Imoplanta · imagens).');
        }
        $partes = [];
        foreach ($p->niveisComPlanta() as $nivel) {
            $partes[] = $disk->path($this->clipPiso($p->refresh(), $nivel));
        }
        if ($partes === []) {
            throw new RuntimeException('Falta a planta 3D dos pisos.');
        }
        $video = $p->pasta().'/planta-video-'.Str::random(6).'.mp4';
        self::juntar($partes, $disk->path($video));

        return $video;
    }

    /** One storey's clip (plot → built and furnished), made when missing or silent (from before sound was on). */
    private function clipPiso(Imoplanta $p, int $nivel): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $tem = fn (string $qual) => ($f = $p->refresh()->aereo($nivel, $qual)) && $disk->exists($f) ? $f : null;
        $cheio = $tem('img') ?? throw new RuntimeException('Falta a planta 3D de '.($p->casa['niveis'][$nivel]['nome'] ?? 'um piso').'.');
        if (($f = $tem('construcao')) && self::temSom($disk->path($f))) {
            return $f;
        }
        // a floor already finished the earlier way (walls clip + furniture clip): joined into its clip, no new AI
        if (! $tem('construcao') && $tem('obra') && $tem('mobilia')) {
            $f = $p->pasta().'/aereo-'.$nivel.'-construcao-'.Str::random(6).'.mp4';
            self::juntar([$disk->path($tem('obra')), $disk->path($tem('mobilia'))], $disk->path($f));
            $p->guardarAereo($nivel, 'construcao', $f);

            return $f;
        }
        if (! $tem('lote')) {
            $f = $p->pasta().'/aereo-'.$nivel.'-lote-'.Str::random(6).'.png';
            $disk->put($f, self::lote($disk->get($cheio), ($r = $p->aereo($nivel, 'render')) && $disk->exists($r) ? $disk->get($r) : null));
            $p->guardarAereo($nivel, 'lote', $f);
        }
        $this->kie->passo = 'imoplanta_imagens';
        $urlLote = $this->kie->upload($disk->get($tem('lote')), 'lote.png');
        $urlCheio = $this->kie->upload($disk->get($cheio), 'planta.png');
        $tentativa = fn () => $this->kie->submitFramesVideo(self::PROMPT_CONSTRUCAO.self::SOM_CONSTRUCAO, $urlLote, $urlCheio, self::NEGATIVO_MOBILIA, 10, true);
        $url = self::comAlternativa($tentativa, $tentativa, fn ($t) => $this->kie->pollVideo($t));
        $f = $p->pasta().'/aereo-'.$nivel.'-construcao-'.Str::random(6).'.mp4';
        $disk->put($f, Http::timeout(300)->retry(2, 2000)->get($url)->throw()->body());
        $p->guardarAereo($nivel, 'construcao', $f);

        return $f;
    }

    /**
     * The bare plot a storey's video starts from: the floor plan's frame in white with the home's footprint as
     * one flat, empty rectangle. The footprint is the bounding box of what isn't white in the browser's top
     * render (the house on white, same framing), scaled to the plan's size; without it, a centred 80 % box.
     */
    public static function lote(string $planta, ?string $render): string
    {
        [$w, $h] = getimagesizefromstring($planta) ?: [1600, 900];
        [$x0, $y0, $x1, $y1] = [0.1, 0.1, 0.9, 0.9];
        if ($render && ($r = @imagecreatefromstring($render))) {
            [$rw, $rh] = [imagesx($r), imagesy($r)];
            [$a, $b, $c, $d] = [$rw, $rh, -1, -1];
            $fundo = imagecolorat($r, 0, 0);   // the render's background (white, or grey around a flat): the corner
            $longe = fn (int $cor) => max(abs((($cor >> 16) & 255) - (($fundo >> 16) & 255)), abs((($cor >> 8) & 255) - (($fundo >> 8) & 255)), abs(($cor & 255) - ($fundo & 255))) > 18;
            for ($y = 0; $y < $rh; $y += 2) {   // ponytail: every 2nd pixel; precise enough for a footprint
                for ($x = 0; $x < $rw; $x += 2) {
                    if ($longe(imagecolorat($r, $x, $y))) {
                        [$a, $b, $c, $d] = [min($a, $x), min($b, $y), max($c, $x), max($d, $y)];
                    }
                }
            }
            if ($c > $a && $d > $b) {
                [$x0, $y0, $x1, $y1] = [$a / $rw, $b / $rh, $c / $rw, $d / $rh];
            }
        }
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, (int) ($x0 * $w), (int) ($y0 * $h), (int) ($x1 * $w), (int) ($y1 * $h), imagecolorallocate($im, 226, 222, 213));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** Joins clips end to end, each fitted (letterboxed in white) to the first one's size. */
    public static function juntar(array $partes, string $saida): void
    {
        $info = \App\Services\RealEstate\PropertyVideoAnalyzer::probe($partes[0]);
        [$w, $h] = [$info['width'] - $info['width'] % 2, $info['height'] - $info['height'] % 2];
        $entradas = [];
        $filtros = '';
        foreach ($partes as $i => $f) {
            array_push($entradas, '-i', $f);
            $filtros .= "[{$i}:v]scale={$w}:{$h}:force_original_aspect_ratio=decrease,pad={$w}:{$h}:(ow-iw)/2:(oh-ih)/2:white,setsar=1,fps=30,format=yuv420p[v{$i}];";
            // each clip's own sound; silence for one without (so the sound stays in step with the picture)
            $dur = \App\Services\RealEstate\PropertyVideoAnalyzer::probe($f)['duration'];
            $filtros .= self::temSom($f) ? "[{$i}:a]aresample=44100,aformat=channel_layouts=stereo,apad,atrim=0:{$dur}[a{$i}];"
                : "anullsrc=r=44100:cl=stereo,atrim=0:{$dur}[a{$i}];";
        }
        $filtros .= implode('', array_map(fn ($i) => "[v{$i}][a{$i}]", array_keys($partes))).'concat=n='.count($partes).':v=1:a=1[out][som]';
        (new \Symfony\Component\Process\Process(['ffmpeg', '-y', '-v', 'error', ...$entradas, '-filter_complex', $filtros, '-map', '[out]', '-map', '[som]',
            '-c:v', 'libx264', '-crf', '18', '-preset', 'veryfast', '-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart', $saida]))->setTimeout(600)->mustRun();
    }

    public static function temSom(string $video): bool
    {
        $p = new \Symfony\Component\Process\Process(['ffprobe', '-v', 'quiet', '-select_streams', 'a', '-show_entries', 'stream=index', '-of', 'csv=p=0', $video]);
        $p->run();

        return trim($p->getOutput()) !== '';
    }

    // Kling makes the sound with the picture: what to hear, in step with what appears. No music or voices.
    public const SOM_CONSTRUCAO = ' Sound, in step with the picture: tiles and planks set down, trowels, a few soft hammer taps and drills '
        .'as the walls rise, a door clicking into place; then a soft whoosh and a light, satisfying thud as each piece of furniture lands, '
        .'and a gentle chime when the last piece settles. Light and pleasant, not loud. No music, no voices.';



    public const PROMPT_CONSTRUCAO = 'A locked-off overhead shot of a 3D floor plan, seen from directly above. The camera does not move, zoom '
        .'or rotate at all. It starts as a bare, empty rectangle of ground. FIRST HALF, the home is built: the floors are laid room by '
        .'room, then the walls rise straight up along their lines to full height, then the doors, windows, kitchen units and bathroom '
        .'fittings appear — still no furniture. SECOND HALF, the furniture appears piece by piece and settles into place: beds, sofas, '
        .'tables and chairs, wardrobes, rugs and plants each pop in with a soft, quick scale-up, until every room is exactly as in the '
        .'final frame. Once built, walls, doors and windows never move; no extra wall, window or door appears.';

    public const NEGATIVO_MOBILIA = 'camera movement, zoom, rotation, perspective change, walls moving, walls changing, floor changing colour, '
        .'rooms changing shape, people, text, watermark, flicker, morphing walls';

    /** The storey whose top view goes with a photo: the one its camera stands on; the main one for outside shots. */
    private function nivelDaFoto(Imoplanta $p, ImoplantaImagem $img): int
    {
        return ImoplantaCasa::nivelDa($p->casa ?? [], $img->camera ?? [], $p->tipologia === 'apartamento')
            ?? ImoplantaCasa::nivelPrincipal($p->casa ?? []);
    }

    /** The customer's own references sent with each photo (kie takes up to 8 images; render + board + top view use 3). */
    public const MAX_EXTRAS = 4;

    /** @return list<string> */
    public static function extras(Imoplanta $p): array
    {
        return array_slice($p->referencias ?? [], 0, self::MAX_EXTRAS);   // ponytail: first 4 only; pick per shot if customers upload more
    }

    public function alterar(ImoplantaImagem $img, string $pedido): string
    {
        $p = $img->imoplanta;
        $quadro = $this->acabamentos->garantir($p, 'image_redo');
        $aereo = $this->aereo($p, $quadro, 'image_redo', $this->nivelDaFoto($p, $img));
        $prompt = 'Edit this photograph: '.$pedido.' Change only that. Keep the camera, the walls, '
            .'every opening and everything else exactly as it is, photorealistic, daylight only with lamps off. '
            .'Keep this home\'s finishes'.($quadro ? ' (the second image is its finishes board)' : '').': '.$this->acabamentos->texto($p).' '
            .'AVOID: dirt, weathering, lit windows, warped lines, floating objects, text or watermarks.';

        return $this->editar($img, $img->foto, $prompt, $quadro, $aereo, self::extras($p));
    }

    /**
     * The top-view reference: the plan in 3D, made photoreal. $salas (from the browser's render: each room's place
     * in the picture and its pieces) makes it an inventory: each room gets exactly its pieces, made real, and
     * nothing else (the AI used to invent rooms, e.g. a bathroom in an entrance hall). Without it (renders from
     * before), the room counts.
     */
    public function promptPlanta(string $tipologia, string $acabamentos, bool $quadro, array $divisoes = [], array $salas = []): string
    {
        $casa = $tipologia === 'apartamento' ? 'apartment' : 'house';
        $ref = $quadro ? ' The SECOND image is the finishes board: use exactly those materials, colours, door and frame styles.' : '';
        if ($salas) {
            $linhas = array_map(function ($s) {
                $nome = self::DIVISOES[$s['uso'] ?? 'outro'] ?? 'room';
                $pecas = array_count_values($s['itens'] ?? []);
                $lista = $pecas ? implode(', ', array_map(fn ($i, $n) => $n > 1 ? "{$n} × {$i}" : $i, array_keys($pecas), $pecas)) : 'nothing (bare floor)';

                return "        - {$nome}, from {$s['x'][0]}% to {$s['x'][1]}% across and {$s['y'][0]}% to {$s['y'][1]}% down the image: {$lista}.";
            }, $salas);
            $lista = "\n\n        WHAT IS IN EACH ROOM (positions in the image, from its top-left corner); each room is exactly this, nothing more:\n"
                .implode("\n", $linhas)."\n"
                .'        A room keeps its use: never draw bathroom fittings, kitchen units, beds or sofas in a room whose list does not have them. '
                .'Hallways and halls stay as open floor with only what their list says.';
        } else {
            // what each room is, so a rug in a hallway doesn't become a sofa or a dining table
            $nomes = array_map(fn ($d) => self::DIVISOES[$d['uso'] ?? 'outro'] ?? 'room', $divisoes);
            $lista = $nomes === [] ? '' : "\n\n        THE ROOMS on this floor: ".implode(', ', array_map(fn ($n, $k) => $n.' ×'.$k, array_keys(array_count_values($nomes)), array_count_values($nomes))).'. '
                .'Furnish each room only as what it is. Hallways, landings and stair halls stay EMPTY: at most a runner rug on the floor, never a sofa, a table, chairs or kitchen units. '
                .'Bedrooms have one bed and their wardrobe, no sofa. Only the kitchen has kitchen units, only the living room has sofas and a dining table.';
        }

        return <<<PROMPT
        Turn this 3D render into a photorealistic 3D FLOOR PLAN of a newly built {$casa} in Portugal: the SAME floor plan, seen from directly above, the roof and ceilings removed, every room open to view, like a high-end real-estate 3D floor plan.

        The render is EXACT and must be traced, not reinterpreted. The result must overlay the render pixel for pixel:
        - the same outline of the {$casa}, the same proportions and the same framing on the same white background; do not make it wider, longer or a different shape, and do not add rooms, terraces, decks or garden around it;
        - every wall in the same place and thickness, every door opening and window in the same place and width, every room the same size and shape, with the same number of rooms;
        - every piece of furniture in the same place, size and orientation; do not add, remove or rearrange furniture.
        Straight down, no perspective, no tilt.{$lista}

        REPLACE EVERY PLACEHOLDER with the real thing seen from above, keeping its footprint: grey and brown boxes are real furniture (beds with bedding and pillows, sofas with cushions, tables and chairs, wardrobes, kitchen units with a worktop, hob and sink, bathroom fittings); green circles on dark pots are real plants; flat light rectangles are woven rugs. Make every piece look real and detailed (fabric folds, wood grain, stitching, soft shadows), but ADD NOTHING: no extra furniture, décor, plants, lamps, appliances or fittings where the render has no placeholder, and an empty floor area stays empty floor. Real floors with real texture in every room.{$ref} Finishes: {$acabamentos}

        Clean white walls with crisp tops, soft daylight from above with gentle shadows, true colours, sharp. No text, no labels, no dimensions, no people.
        PROMPT;
    }

    /** Room use → what to call it in the prompt. */
    private const DIVISOES = [
        'sala' => 'living room', 'cozinha' => 'kitchen', 'quarto' => 'bedroom', 'wc' => 'bathroom',
        'escritorio' => 'home office', 'lavandaria' => 'laundry room', 'hall' => 'entrance hall',
        'circulacao' => 'hallway', 'garagem' => 'garage', 'arrumos' => 'storage room', 'outro' => 'room',
    ];

    /**
     * The edit instruction for one shot. The render already holds everything that is in the picture, so the
     * prompt only says what kind of picture it is and how to make it real — it never lists things that might
     * not be in this shot (an earlier version mentioned the pool, the lawn and the plan's description in every
     * prompt, and the model turned interior renders into garden views with a pool).
     * A camera inside a room gets the interior prompt; outside, the exterior one; above 8 m, an aerial.
     */
    public function prompt(array $casa, array $camera, string $tipologia = 'isolada', string $acabamentos = '', bool $quadro = false, bool $aereo = false, int $extras = 0): string
    {
        // the same finishes in every photo of the home: the finish list, and the board image when there is one
        $acab = $acabamentos === '' ? '' : ' Use exactly this home\'s finishes, the same in every photo of it'
            .($quadro ? ' — the SECOND image is its finishes board: copy those exact materials, colours and door and frame styles' : '')
            .': '.$acabamentos;
        if ($aereo) {
            $acab .= ' The '.($quadro ? 'THIRD' : 'SECOND').' image is a realistic top view of this same home with the roof '
                .'removed: every room\'s floor, furniture, rugs and finishes in this photo must match it exactly.';
        }
        if ($extras > 0) {
            $acab .= ' The last '.($extras === 1 ? 'image is' : $extras.' images are').' the owner\'s own drawings or photos of this '
                .'property (elevations, sections, photos of the house or site, material samples): where they show what this '
                .'view shows — façade, windows, roof, materials, surroundings — follow them; ignore what this view does not show.';
        }
        $apto = $tipologia === 'apartamento';
        $divisao = ImoplantaCasa::divisaoDa($casa, $camera, $apto);
        $fov = (int) round((float) ($camera['fov'] ?? 70));
        $comum = 'The render is a LAYOUT GUIDE, not a style reference: keep exactly the same camera position, angle, '
            .'lens and framing, and every wall, corner, door, window and opening in the same place and size (do not add, '
            .'remove or move any of them, nor change the room\'s shape) — but nothing in the result may look like a 3D '
            .'render.';
        $real = 'Real materials with real texture: wood grain, woven fabric, matte painted plaster, stone; soft natural '
            .'shadows, contact shadows under every object, gentle light falloff, the subtle imperfections of a real photo.';
        $luz = 'Daylight only, every lamp OFF. Natural exposure, as a professional real-estate photographer on a tripod with '
            .'a full-frame camera, straight verticals. A real photograph — not CGI, not a 3D render, not an illustration.';
        $evitar = 'AVOID: anything blocky, flat or plastic-looking, untextured surfaces, glossy or reflective ceilings, dirt, '
            .'stains, glowing lamps, orange/teal colour grading, HDR halo, warped or bent lines, floating objects, people, '
            .'text or watermarks.';

        if ($divisao) {
            $sala = self::DIVISOES[$divisao['uso'] ?? 'outro'] ?? 'room';
            $proprio = match ($divisao['uso'] ?? '') {
                'cozinha' => ' It is a KITCHEN: the white units along the walls are a real fitted kitchen — make them real, with doors and drawers, a worktop, a sink with a tap, an induction hob with an extractor hood above it, a built-in oven and a tall fridge column. Keep every unit where it is.',
                'wc' => ' It is a BATHROOM: the white blocks are real sanitary ware — a toilet, a washbasin with a tap on a wooden vanity, a mirror, a glass shower with a shower head (or a bath with a tap), tiled as the finishes say.',
                default => '',
            };
            $onde = $apto ? 'an apartment in Portugal' : 'a newly built house in Portugal';

            return <<<PROMPT
            Turn this 3D render into a real photograph: a photorealistic INTERIOR photograph of the {$sala} of {$onde}, taken from inside the room. It stays an interior view of this same room: do not turn it into an outside view of the building.{$proprio}

            {$comum}

            REPLACE EVERY PLACEHOLDER with the real thing, same size, place and orientation: grey and brown boxes are real furniture (a sofa with cushions and a throw, beds with bedding, wooden cabinets and tables, upholstered chairs, wardrobes, kitchen units with handles and a worktop, bathroom fittings); green ovals on dark cylinders are real potted plants with leaves; flat rectangles on the walls are framed artworks or mirrors; pale panels beside the windows are linen curtains; flat light rectangles on the floor are woven rugs. None of them may stay as a simple block. Tidy and lived-in, with a few tasteful details (books, a vase, a lamp switched off); nothing cluttered.

            FINISHES. Smooth matte white walls and a matte white ceiling.{$acab}

            LIGHT. Soft daylight coming in through the windows, brighter near them; through the glass, a real garden or street, softly overexposed. {$real} {$luz}

            {$evitar}
            PROMPT;
        }

        $tipo = match ($tipologia) {
            'geminada' => ' It is a semi-detached house; the attached neighbouring house is a separate home with its own finishes.',
            'banda' => ' It is a terraced house in a row; the attached neighbouring houses are separate homes with their own finishes.',
            'apartamento' => ' The home is a flat in this residential building; the other flats share the same façade.',
            default => '',
        };
        $vista = (float) ($camera['altura'] ?? 1.6) > 8 ? 'aerial drone photograph' : "eye-level exterior photograph ({$fov}° lens)";

        return <<<PROMPT
        Turn this 3D render into a photorealistic {$vista} of a newly built home in Portugal.{$tipo}

        {$comum} Keep the roof shape and the number of storeys. Keep the neighbouring buildings' shape.

        REPLACE EVERY PLACEHOLDER with the real thing, same size and place: green spheres and ovals are real trees and shrubs with leaves and branches; flat green areas are real lawn with natural variation; grey slabs are real stone paving; boxes are real outdoor furniture. Only make real what the render shows: do not add a pool, extra trees, extra buildings or new garden areas. You may add small real details: drainpipes, outdoor wall lights (switched off), a house number, low plants along the walls.

        FINISHES. Clean, freshly painted render with a fine plaster texture, real stone and wood where the render has them, clean light stone paving.{$acab}

        LIGHT. A clear late afternoon: warm sunlight on the lit walls, cool soft shade. Windows show reflections of the sky; interiors are darker than outside. {$real} {$luz}

        {$evitar}
        PROMPT;
    }

    private function editar(ImoplantaImagem $img, ?string $origem, string $prompt, ?string $quadro = null, ?string $aereo = null, array $extras = []): string
    {
        return $this->gerar($origem, $prompt, $quadro, $aereo, $img->imoplanta->pasta().'/fotos/'.$img->numero.'-'.Str::random(8).'.png', $extras);
    }

    /** The image AI's ratio nearest to a picture's own ("4:3"), so it doesn't re-frame it (as cena.js proporcao()). */
    public static function proporcao(string $imagem): string
    {
        $d = @getimagesizefromstring($imagem);
        $r = $d ? $d[0] / max(1, $d[1]) : 16 / 9;
        $melhor = '16:9';
        foreach (['1:1', '5:4', '4:5', '4:3', '3:4', '3:2', '2:3', '16:9', '9:16', '21:9'] as $p) {
            [$a, $b] = explode(':', $p);
            [$x, $y] = explode(':', $melhor);
            if (abs(log($a / $b / $r)) < abs(log($x / $y / $r))) {
                $melhor = $p;
            }
        }

        return $melhor;
    }

    /** One Nano Banana image from a starting image, with the references after it; stored at $destino. */
    private function gerar(?string $origem, string $prompt, ?string $quadro, ?string $aereo, string $destino, array $extras = [], string $proporcao = '16:9'): string
    {
        $this->kie->passo = 'imoplanta_imagens';
        if (! $this->kie->configurado()) {
            throw new RuntimeException('Falta a chave kie.ai (Definições → Passos → Imoplanta · imagens).');
        }
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        if (! $origem || ! $disk->exists($origem)) {
            throw new RuntimeException('Falta a imagem de partida.');
        }
        $url = $this->kie->upload($disk->get($origem), 'imoplanta.png');
        $refs = [$url];
        $lista = [[$quadro, 'acabamentos.png'], [$aereo, 'aereo.png'], ...array_map(fn ($e) => [$e, basename($e)], $extras)];
        foreach ($lista as [$ref, $nome]) {   // then the board, the top view, the owner's references
            if ($ref && $disk->exists($ref)) {
                $refs[] = $this->kie->upload($disk->get($ref), $nome);
            }
        }
        $principal = config('contentmachine.imoplanta.modelo_foto') === 'edit'
            ? fn () => $this->kie->submitExactEdit($prompt, $url, $proporcao)              // takes one image: the finish list alone
            : fn () => $this->kie->submitResolucao($prompt, $proporcao, $refs, '2K');   // Nano Banana Pro
        $resultado = self::comAlternativa($principal, fn () => $this->kie->submitAlternativo($prompt, $proporcao, $refs), fn ($t) => $this->kie->poll($t));
        $disk->put($destino, Http::timeout(120)->retry(2, 2000)->get($resultado)->throw()->body());

        return $destino;
    }

    /**
     * kie fails now and then for no reason of ours (busy, a refused prompt, a timeout): the main model twice,
     * then the other model twice, before giving up. Each failure is logged, not shown. Returns the image URL.
     *
     * @param  callable(): string  $principal  submits to the main model, returns a task id
     * @param  callable(): string  $alternativo  submits to the fallback model
     * @param  callable(string): string  $esperar  polls a task id to its image URL
     */
    public static function comAlternativa(callable $principal, callable $alternativo, callable $esperar): string
    {
        $erro = null;
        foreach ([$principal, $principal, $alternativo, $alternativo] as $i => $submeter) {
            try {
                return $esperar($submeter());
            } catch (\Throwable $e) {
                $erro = $e;
                Log::warning('Imoplanta: kie attempt '.($i + 1).' of 4 failed: '.$e->getMessage());
            }
        }

        throw $erro;
    }
}
