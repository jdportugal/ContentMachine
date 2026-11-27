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
        // The cleanup: a photo tool, no video (user, 2026-11-26): the same view tidied (the AI's frame, approved) is
        // the result. `fim` takes the customer's own list (opcoes.texto, in `campo`) at %s. Nano Banana Pro kept the
        // framing here (measured: 0.6 % scale, 0.3 % shift; Nano Banana Edit drifted more, 2.5 %).
        'limpeza' => [
            'analise' => 'You check a real-estate photo before an AI "cleanup": the AI removes clutter and unwanted '
                .'objects from the photo to show the space clean and tidy, everything else unchanged. Interiors and exteriors both work. A GOOD start: a sharp, well-lit photo of a room or of a house '
                .'exterior with visible clutter or unwanted items (boxes, clothes, bags, toys, cables, bins, rubbish, tools, '
                .'personal items, cars in front of a facade). PROBLEMS to look for: a space that is already clean and tidy '
                .'(say there is little to remove, so the photo will change little); people in the picture; a floor plan, '
                .'drawing or screenshot; very dark, blurry or heavily filtered; so much clutter that walls and floor are '
                .'hidden (the AI would have to invent most of the room). Text, logos and watermarks over the picture are NOT '
                .'a problem: the cleanup removes them too. The result is a photo, never a video: do not mention video.',
            'fim' => 'Using this photo as the reference, show exactly the same space from exactly the same camera position, '
                .'lens and framing, after it has been cleaned and tidied for a property listing. Remove the clutter and '
                .'unwanted items: loose objects on floors, tables, counters and shelves, clothes, bags, boxes, toys, cables, '
                .'rubbish and bins, cleaning products, tools, personal items, papers, magnets and photos on the fridge, '
                .'laundry, debris.%s Keep everything that belongs to the property exactly as it is and where it is: walls, '
                .'floors, ceilings, doors, windows, built-in cupboards and appliances, light fittings, radiators, plants in '
                .'the ground, and the main furniture (sofas, beds, tables, chairs, wardrobes). Where something was removed, '
                .'show the clean floor, wall or surface that was behind it, matching the rest. The same light, colours and '
                .'white balance. A photoreal real-estate photograph, with the picture free of text, logos and watermarks.',
            'campo' => ' The client also asks to remove: %s.',
        ],
        // The side move, a real sideways travel (user, 2026-11-26: "it must always move the camera position"). From one
        // photo every video model (Kling 3.0 with any wording, Kling 2.6, Hailuo 2.3) often TURNS instead of moving: the
        // near and far parts shift alike (measured on tracked points against a depth map). So the end frame is built:
        // a depth map of the photo (`profundidade`), the photo shifted by it (near pixels further than far ones: what
        // a sideways move does), that cleaned by the AI where the warp bent things (`limpar`); the start is the photo
        // cut to the same window. Kling then has to land there: measured as a real slide (near/far shift 1.6-1.7).
        'lateral' => [
            'analise' => 'You check a real-estate photo before it is turned into an AI "gimbal slide" video: the camera travels '
                .'about one metre sideways from this exact view, like a gimbal slider shot in slow motion. It works for '
                .'interiors and exteriors. A GOOD start: a sharp, well-lit, level photo with depth, some objects or furniture '
                .'in front of a background (the parallax is what makes it beautiful). PROBLEMS to look for: a floor plan, '
                .'drawing or screenshot; a close-up of a flat wall or of one object, with nothing behind it; people or '
                .'animals in the picture (they freeze or warp); blurry, very dark or heavily filtered; strongly tilted; a '
                .'collage, or text and logos over the picture; a very wide panorama.',
            'profundidade' => 'Turn this photo into its depth map: a smooth grayscale image with exactly the same framing and '
                .'composition, where the brightness of each pixel is its distance from the camera: the nearest surfaces pure '
                .'white, the farthest pure black, smooth gradients along floors and walls that recede, crisp edges where an '
                .'object stands in front of something further away. No colours, no textures, no shading from light, no text.',
            'limpar' => 'The first image is a photo seen from a slightly moved camera, made by warping the second image (the '
                .'original photo), so it has warping artifacts: some straight lines, door and window frames and glass panes '
                .'are bent or wavy, and some edges are smeared or stretched. Repair those distorted areas so every straight '
                .'line is straight and every object looks exactly like it does in the original photo. Keep the first image\'s '
                .'viewpoint, framing and the positions of everything in it exactly as they are; same colours and light. '
                .'Photoreal, with the picture free of text, logos and watermarks.',
            'video' => 'Slow truck %1$s: a smooth gimbal slider shot in slow motion; the camera itself travels sideways to the '
                .'%1$s at a steady pace from the first frame to the last frame, never turning, with natural parallax: things '
                .'near the camera pass faster than the walls behind. Every object stays exactly as it is; the same light '
                .'throughout. One continuous move, no cuts, no zoom.',
            'som' => null,
        ],
        // The remodel: the photo is the start, the AI draws the same room renovated (the architecture kept, every
        // finish new, the customer's style in `campo`), Kling plays the works between them as a locked-off timelapse.
        'remodelacao' => [
            'analise' => 'You check a real-estate photo before it is turned into an AI "renovation timelapse": from this '
                .'photo the AI draws the same space fully renovated, then a fixed-camera timelapse shows workers turning one '
                .'into the other. Interiors work best; a house facade also works. A GOOD start: a sharp, well-lit photo of a '
                .'room or facade that is dated, worn or in need of works (old tiles, stains, damaged walls, old fittings), '
                .'the walls, floor and windows visible. PROBLEMS to look for: a space that is already new or recently '
                .'renovated (say the change will be small); a floor plan, drawing or screenshot; people in the picture; very '
                .'dark, blurry or heavily filtered; a close-up of one object or one wall, where the room cannot be read; a '
                .'very wide panorama. Text, logos and watermarks over the picture are NOT a problem.',
            // measured 2026-11-26: "renew everything" let the AI redraw the room (window moved, other camera); each
            // thing renewed in its own place, the photo's own outline kept, holds it
            'fim' => 'Edit this photo: the same room, renovated. The camera does not move: exactly the same viewpoint, lens, '
                .'framing and crop, so the walls, corners, ceiling line and floor meet the edges of the picture exactly where '
                .'they do now. Every window and door stays exactly where it is, at the same size and shape (new frames are '
                .'fine). Each fitting is replaced by a new one of the same kind IN THE SAME PLACE and about the same size: '
                .'a bath where the bath is, a toilet or bidet where it is, a sink where the sink is, kitchen units along the '
                .'same walls. New finishes everywhere: smooth freshly painted walls and ceiling, new wall tiles where there '
                .'are tiles, a new floor, new taps and light fittings, everything clean. The new materials are modern and '
                .'high-end: large-format porcelain tiles in warm white or light stone with thin joints, a matching '
                .'large-format floor, sleek modern fittings, slim brushed or matt black taps; plain, calm surfaces in place '
                .'of small patterned or chequered tiles; at most a towel or a plant as decor.%s The same natural daylight '
                .'from the same windows. Only the materials and fittings change: laid over the original photo, the room\'s '
                .'outline, the window, the door and each fitting line up exactly. A photoreal real-estate photograph, with '
                .'the picture free of people, text, logos and watermarks.',
            'campo' => ' The client wants this style: %s.',
            // measured 2026-11-26: with the workers gone before the end and no step for the tiles, Kling faded the
            // last change in on its own; so every change is done by hands, row by row, and the workers stay to the end
            'video' => 'Fast-motion renovation timelapse from a fixed camera on a tripod: the camera stays perfectly still for '
                .'the whole shot. Two or three workers in work clothes are in the room from the first second to the last, '
                .'sped up, and they do every change with their hands: they knock off the old tiles and carry out the old '
                .'fittings, plaster and paint the walls, set the new tiles one row at a time from the bottom up, lay the new '
                .'floor piece by piece, carry in and fit each new fitting, then wipe everything clean. Nothing changes by '
                .'itself: no tile, wall finish, floor or fitting fades, morphs or appears without a worker putting it there. '
                .'Only in the last second do the workers step out of the frame, leaving the room exactly as in the last '
                .'frame. The walls, windows and doors stay in place the whole time; the daylight flickers gently as the days '
                .'pass. One continuous shot, no cuts.',
            'som' => ' Sound, sped up with the picture: scraping, hammering, drills, a saw, tiles and planks set down, '
                .'brushes and rollers, footsteps; it calms down at the end to a quiet, finished room. No music, no speech.',
            // the type of works (opcoes.obra, user 2026-11-27): each laid over the interior prompts above. Outside there
            // is rarely a picture of the result, so the photo itself says where the building goes (the ruin's footprint,
            // a cleared or levelled patch) and the camera, the street and the neighbours are pinned as they are; the
            // 2026-11-26 ruin run drifted the camera and the building faded in, hence the build named step by step
            'obras' => [
                'exterior' => [
                    'analise' => 'You check a real-estate photo before it is turned into an AI "renovation timelapse": from this '
                        .'photo the AI draws the same building with its exterior renovated, then a fixed-camera timelapse shows '
                        .'workers doing it. A GOOD start: an outdoor photo taken from the ground of a house or building that is '
                        .'dated or worn (old render, stains, old windows, a tired roof), the facade whole or nearly whole in the '
                        .'frame, daylight, sharp. PROBLEMS to look for: an interior; a floor plan, drawing or screenshot; a building '
                        .'that is already new; the facade mostly hidden by trees, cars or scaffolding, or cut off; people in the '
                        .'picture; night, very dark or blurry; a very wide panorama. Text, logos and watermarks over the picture '
                        .'are NOT a problem.',
                    'fim' => 'Edit this photo: the same building with its exterior renovated. The camera does not move: exactly '
                        .'the same viewpoint, lens, framing and crop. The building keeps exactly its volume, outline, number of '
                        .'floors and roof shape, and every window and door stays exactly where it is, at the same size; the street, '
                        .'the neighbouring buildings, the sky and everything around stay exactly as they are. Only the finishes '
                        .'are new: smooth fresh render and paint, new window frames and doors, the roof repaired with new tiles, '
                        .'clean gutters, a tidy front. Unless the client says otherwise: modern and high-end, light render, slim '
                        .'dark window frames, a few plants.%s The same daylight and shadows. Laid over the original photo, the '
                        .'building\'s outline, every window and door and the neighbours line up exactly. A photoreal real-estate '
                        .'photograph, with the picture free of people, text, logos and watermarks.',
                    'campo' => ' The client describes the works: %s.',
                    'video' => 'Fast-motion renovation timelapse from a fixed camera on a tripod: the camera stays perfectly still '
                        .'for the whole shot. Workers in work clothes are at the building from the first second to the last, sped '
                        .'up, and they do every change with their hands: they put up scaffolding, chip off the old render, render '
                        .'and paint the walls section by section, take out the old windows and fit the new ones, lay the new roof '
                        .'tiles row by row, then take the scaffolding down. Nothing changes by itself: no wall, window, door or '
                        .'roof fades, morphs or appears without a worker putting it there. Only in the last second do the workers '
                        .'step out of the frame, leaving the building exactly as in the last frame. The street and the neighbouring '
                        .'buildings stay exactly in place the whole time; the daylight and shadows flicker as the days pass. One '
                        .'continuous shot, no cuts.',
                    'som' => ' Sound, sped up with the picture: scaffolding clanking, chisels and hammers, drills, trowels, a cement '
                        .'mixer, footsteps, street sounds; it calms down at the end to a quiet street. No music, no speech.',
                ],
                'ruina' => [
                    'analise' => 'You check a photo before it is turned into an AI "ruin rebuild timelapse": from this photo the AI '
                        .'draws a finished building where the ruin stands, then a fixed-camera timelapse shows workers building it. '
                        .'A GOOD start: an outdoor photo taken from the ground of a ruin or the remains of a building (old stone '
                        .'walls, a roofless shell, rubble) with its plot visible, daylight, sharp. A ruin, rubble, weeds and an '
                        .'empty plot are what is expected: they are NOT problems. PROBLEMS to look for: an interior of a finished '
                        .'house; a floor plan, drawing or screenshot; a photo from the air; the ruin mostly hidden or cut off so '
                        .'its footprint cannot be read; people close to the camera; night, very dark or blurry; a very wide '
                        .'panorama. Text, logos and watermarks over the picture are NOT a problem.',
                    'fim' => 'Edit this photo: the same place after the works, with a finished building where the ruin stands. The '
                        .'camera does not move: exactly the same viewpoint, lens, framing and crop. Everything outside the ruin\'s '
                        .'plot stays exactly as it is: the street and its paving, the neighbouring houses, the towers and roofs '
                        .'behind, the sky, the parked cars, the poles and wires, and whatever stands in the foreground. The ruin '
                        .'shows where the building goes: the new building stands on the ruin\'s footprint, its walls rising where '
                        .'the old walls stand (the sound old stone walls kept and repaired as part of it), and it stays inside the '
                        .'plot, never past the line of the street. The open ground of the plot in front of it becomes a tidy yard '
                        .'or entrance. Unless the client says otherwise: a modern renovation that respects the place, about the '
                        .'height of its neighbours, the old stone repaired, clean light render, large dark-framed windows, a new '
                        .'roof.%s The same daylight and shadows. Laid over the original photo, the street, the neighbours and the '
                        .'foreground line up exactly. A photoreal real-estate photograph, with the picture free of people, text, '
                        .'logos and watermarks.',
                    'campo' => ' The client describes what is built: %s. Follow it (its use, size and style) within the plot.',
                    'video' => 'Fast-motion construction timelapse from a fixed camera on a tripod. The sound old stone walls '
                        .'stay standing the whole time and the new building rises inside and on them. '.self::OBRA_DE_RAIZ,
                    'som' => ' Sound, sped up with the picture: an excavator engine, stones and rubble tipped, a cement mixer, '
                        .'hammering, drills, a saw, trowels on blocks, scaffolding clanking, footsteps; it calms down at the end '
                        .'to a quiet street. No music, no speech.',
                ],
                'terreno' => [
                    'analise' => 'You check a photo before it is turned into an AI "construction timelapse": from this photo of a plot '
                        .'the AI draws a new building on it, then a fixed-camera timelapse shows workers building it. A GOOD start: '
                        .'an outdoor photo taken from the ground of an empty or cleared plot, its ground and edges visible (a road, '
                        .'walls, fences, neighbours), daylight, sharp. Bare earth, weeds, foundations or marking stakes are what is '
                        .'expected: they are NOT problems. PROBLEMS to look for: an interior; a floor plan, drawing or screenshot; '
                        .'a photo from the air; ground so close up or overgrown that the plot cannot be read; people close to the '
                        .'camera; night, very dark or blurry; a very wide panorama. Text, logos and watermarks over the picture are '
                        .'NOT a problem.',
                    'fim' => 'Edit this photo: the same place after the works, with a finished new building on the plot. The camera '
                        .'does not move: exactly the same viewpoint, lens, framing and crop. Everything outside the plot stays '
                        .'exactly as it is: the road, the neighbouring houses, the trees beyond, the sky and the hills. The ground '
                        .'shows where the building goes: on the cleared or levelled patch, on the foundations, slab or marked-out '
                        .'stakes if there are any; otherwise in the middle of the plot, set back from the road like its neighbours, '
                        .'sized to fit with space around it. It stays inside the plot, never past the road or the boundary walls. '
                        .'The rest of the plot becomes a tidy garden, a drive and a path. Unless the client says otherwise: a modern '
                        .'Portuguese house of one or two floors, white render, large dark-framed windows, a flat or low roof.%s The '
                        .'same daylight and shadows. Laid over the original photo, the road, the neighbours and the horizon line '
                        .'up exactly. A photoreal real-estate photograph, with the picture free of people, text, logos and '
                        .'watermarks.',
                    'campo' => ' The client describes what is built: %s. Follow it (its use, size and style) within the plot.',
                    'video' => 'Fast-motion construction timelapse from a fixed camera on a tripod. '.self::OBRA_DE_RAIZ,
                    'som' => ' Sound, sped up with the picture: an excavator engine, a concrete pump and mixer, hammering, drills, '
                        .'a saw, trowels on blocks, scaffolding clanking, footsteps; it calms down at the end to a quiet, '
                        .'finished place. No music, no speech.',
                ],
            ],
        ],
    ];

    /**
     * A building from the ground (ruína, terreno), in the order a real site goes (user, 2026-11-27: "we should always
     * see preparing the ground and building the structure"). Timed beats for 15 s: untimed, Kling skipped the
     * structure, hid it behind scaffolding and showed the finished house all at once.
     */
    public const OBRA_DE_RAIZ = 'The camera stays perfectly still for the whole shot; everything is sped up like a real building '
        .'site filmed over months. A crew of workers in work clothes and hard hats and a small excavator are on the plot from '
        .'the first second to the last, and the building goes up in this order, like a real construction: '
        .'from 0 to 3 seconds, the ground is prepared: the rubble, stones and weeds are cleared, the excavator levels the '
        .'ground and digs the foundation trenches; '
        .'from 3 to 6 seconds, the foundations: steel rebar is laid in the trenches, concrete is poured from a mixer, the '
        .'ground-floor slab is poured and smoothed; '
        .'from 6 to 10 seconds, the bare structure, in full view with no scaffolding in front of it: grey reinforced-concrete '
        .'columns rise in wooden formwork, the beams and the upper slab or roof beams are set on them, then the walls are '
        .'built block by block between the columns, row by row, with the openings for the windows and doors left empty; '
        .'from 10 to 13 seconds, the roof is covered, the windows and doors are fitted, and the walls are rendered and '
        .'painted from scaffolding; '
        .'from 13 to 15 seconds, the scaffolding comes down, the yard is paved, and only in the last second do the workers '
        .'and the machine leave the frame, the place exactly as in the last frame. '
        .'Every step is done by the workers: no wall, slab, roof, window or finish fades, morphs or appears by itself. The '
        .'street, the neighbouring houses and everything outside the plot stay exactly in place the whole time; the daylight '
        .'and shadows flicker as the days pass. One continuous shot, no cuts.';

    public const PROMPT_ALTERAR = 'Change this image as asked, and nothing else: «%s». Keep everything else exactly as it is: '
        .'the framing, the house, its surroundings, the light and the colours. The other images are the other frame of the video and '
        .'its original photograph, for reference: the house must stay true to them. Photoreal, no text, no watermark.';

    /** A tool's prompts, with its type of works' own (the remodel's `obras`: a ruin is not a room) laid over them. */
    public static function prompts(FerramentaIa $f): array
    {
        $p = self::PROMPTS[$f->ferramenta];

        return [...$p, ...($p['obras'][$f->obra()] ?? [])];
    }

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
        if ($f->ferramenta === 'lateral' && $pedido === null) {   // the side move's end frame is built, not drawn
            return $this->lateral($f);
        }
        $original = $disk->get($f->original);
        $tem = fn (?string $c) => $c && $c !== $f->original && $disk->exists($c);
        $outro = $qual === 'inicio' ? 'fim' : 'inicio';
        $refs = [];
        if ($pedido !== null && $tem($f->{$qual})) {
            $prompt = sprintf(self::PROMPT_ALTERAR, $pedido);
            $refs[] = $this->kie->upload($disk->get($f->{$qual}), 'atual.png');
        } else {
            $p = self::prompts($f);
            $prompt = $p[$qual];
            if (isset($p['campo'])) {   // the customer's own text, in the tool's words
                $extra = trim((string) ($f->opcoes['texto'] ?? ''));
                $prompt = sprintf($prompt, $extra !== '' ? sprintf($p['campo'], $extra) : '');
            }
        }
        if ($tem($f->{$outro})) {
            $refs[] = $this->kie->upload($disk->get($f->{$outro}), 'outro.png');
        }
        $refs[] = $this->kie->upload($original, 'foto.jpg');
        [$w, $h] = getimagesizefromstring($original) ?: [0, 0];

        return $this->imagem($prompt, $refs, Fotografo::proporcao($original), $w, $h, $f->pasta().'/'.$qual.'-'.Str::random(8).'.png');
    }

    /** One Nano Banana Pro image (the fallback model if it keeps failing), cut to $w×$h, stored at $destino. */
    private function imagem(string $prompt, array $refs, string $proporcao, int $w, int $h, string $destino): string
    {
        $url = Fotografo::comAlternativa(
            fn () => $this->kie->submitResolucao($prompt, $proporcao, $refs, '2K'),
            fn () => $this->kie->submitAlternativo($prompt, $proporcao, $refs),
            fn ($t) => $this->kie->poll($t),
        );
        Storage::disk(config('contentmachine.imoplanta.disk'))->put($destino, self::ajustar(Http::timeout(120)->retry(2, 2000)->get($url)->throw()->body(), $w, $h));

        return $destino;
    }

    /** Working width of the side move's frames: the displacement map is 8-bit (±127 px), the near shift ~7 % of it. */
    public const LARGURA_LATERAL = 1600;

    /** Share of the width both side-move frames keep: the shift smears the edge it moves away from. */
    public const JANELA_LATERAL = 0.92;

    /**
     * The side move's frames: the start is the photo cut to the window, the end is the same window after the camera
     * has travelled sideways (the photo shifted by its depth map, then cleaned by the AI). Sets `inicio`; returns the end.
     */
    private function lateral(FerramentaIa $f): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $p = self::PROMPTS['lateral'];
        $original = $disk->get($f->original);
        [$w0, $h0] = getimagesizefromstring($original) ?: [1600, 1200];
        $prof = $f->pasta().'/profundidade.png';   // once per video: a new direction reuses it
        if (! $disk->exists($prof)) {
            $h = (int) (round($h0 * self::LARGURA_LATERAL / $w0 / 2) * 2);
            $this->imagem($p['profundidade'], [$this->kie->upload($original, 'foto.jpg')], Fotografo::proporcao($original), self::LARGURA_LATERAL, $h, $prof);
        }
        $dir = sys_get_temp_dir().'/lateral-'.$f->id.'-'.Str::random(6);
        @mkdir($dir);
        try {
            file_put_contents("$dir/foto.jpg", $original);
            [$inicio, $fim] = self::deslocar("$dir/foto.jpg", $disk->path($prof), $f->direcao() === 'esquerda', $dir);
            $caminhoInicio = $f->pasta().'/inicio-'.Str::random(8).'.png';
            $disk->put($caminhoInicio, file_get_contents($inicio));
            $f->update(['inicio' => $caminhoInicio]);
            [$cw, $ch] = getimagesize($fim);
            $deslocada = file_get_contents($fim);

            $refs = [$this->kie->upload($deslocada, 'deslocada.png'), $this->kie->upload($original, 'foto.jpg')];   // the photo: what things really look like

            return $this->imagem($p['limpar'], $refs, Fotografo::proporcao($deslocada), $cw, $ch,
                $f->pasta().'/fim-'.Str::random(8).'.png');
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
        }
    }

    /**
     * The photo as seen after the camera moved sideways, from its depth map (white = near): every pixel shifts the
     * way the camera's move pushes it, near ones (~7.5 % of the width) much further than far ones (~0.8 %). Returns
     * [start, end] PNG paths in $dir, both cut to the same window, away from the smeared edge.
     */
    public static function deslocar(string $foto, string $profundidade, bool $esquerda, string $dir): array
    {
        [$w0, $h0] = getimagesize($foto);
        $w = self::LARGURA_LATERAL;
        $h = (int) (round($h0 * $w / $w0 / 2) * 2);
        $cw = (int) (round($w * self::JANELA_LATERAL / 2) * 2);
        $x = $esquerda ? $w - $cw : 0;   // moving right, the picture slides left: keep the left part (and the reverse)
        // displace takes out(x) = in(x + map - 128): a right move reads from the right (map above 128), a left one from the left
        $mapa = $esquerda ? 'val*-0.42+116' : 'val*0.42+140';   // far 12 px, near 119 px (of 1600)
        $corre = fn (array $args) => (new \Symfony\Component\Process\Process(['ffmpeg', '-y', '-v', 'error', ...$args]))->setTimeout(120)->mustRun();
        $corre(['-i', $foto, '-vf', "scale=$w:$h,crop=$cw:$h:$x:0", "$dir/inicio.png"]);
        $corre(['-i', $foto, '-i', $profundidade, '-filter_complex',
            "[0]scale=$w:$h,format=gbrp[m];[1]scale=$w:$h,format=gray,gblur=sigma=10,lut=c0='$mapa',format=gbrp[x];"
            ."color=c=0x808080:s={$w}x{$h},format=gbrp[y];[m][x][y]displace=edge=smear,crop=$cw:$h:$x:0", '-frames:v', '1', "$dir/fim.png"]);

        return ["$dir/inicio.png", "$dir/fim.png"];
    }

    /** The clip from the approved frames ($nota: the customer's wish for a new version). Returns its path. */
    public function animar(FerramentaIa $f, ?string $nota = null): string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $this->configurado();
        $p = self::prompts($f);
        $segundos = $f->segundos();
        $inicio = $this->kie->upload($disk->get($f->inicio), 'inicio.png');
        $fim = $this->kie->upload($disk->get($f->fim), 'fim.png');
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
