<?php

namespace Tests\Feature\Clips;

use App\Livewire\AfterIntroSetting;
use App\Livewire\ClipsAnimadosSfx;
use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\RealEstate\VideoClipMatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OutroTest extends TestCase
{
    private function scene(float $start, float $end, string $type = 'card'): array
    {
        return ['start' => $start, 'end' => $end, 'layers' => [['type' => $type, 'text' => 'x', 'params' => []]]];
    }

    public function test_a_short_last_scene_becomes_the_outro(): void
    {
        $plan = ['scenes' => [$this->scene(0, 3), $this->scene(3, 6), $this->scene(6, 8.5)]];

        $out = (new SceneVisualFiller)->enforceOutro($plan, ['agent-card']);

        $this->assertCount(3, $out['scenes']);
        $this->assertSame('agent-card', $out['scenes'][2]['layers'][0]['type']);
        $this->assertSame(6.0, (float) $out['scenes'][2]['start']);
    }

    public function test_a_long_last_scene_keeps_its_start_and_ends_on_the_outro(): void
    {
        $plan = ['scenes' => [$this->scene(0, 3), $this->scene(3, 6), $this->scene(6, 14)]];

        $out = (new SceneVisualFiller)->enforceOutro($plan, ['agent-card']);

        $this->assertCount(4, $out['scenes']);
        $this->assertSame('card', $out['scenes'][2]['layers'][0]['type']);   // the planner's content, first
        $this->assertSame(11.0, (float) $out['scenes'][2]['end']);          // 14 − the typical 3s
        $this->assertSame('agent-card', $out['scenes'][3]['layers'][0]['type']);
        $this->assertSame(14.0, (float) $out['scenes'][3]['end']);
    }

    /** The planner put the card one scene early: it is stretched to the end, never shown twice. */
    public function test_an_outro_on_the_second_to_last_scene_is_not_repeated(): void
    {
        $plan = ['scenes' => [$this->scene(0, 3), $this->scene(3, 6, 'agent-card'), $this->scene(6, 8.5)]];

        $out = (new SceneVisualFiller)->enforceOutro($plan, ['agent-card']);

        $this->assertCount(2, $out['scenes']);
        $this->assertSame('agent-card', $out['scenes'][1]['layers'][0]['type']);
        $this->assertSame(8.5, (float) $out['scenes'][1]['end']);
    }

    /** A leftover under 1.5s is a flash — the outro takes the whole closing scene, and picks no photo itself. */
    public function test_a_short_leftover_goes_to_the_outro(): void
    {
        $house = ['type' => 'zoom-fx', 'text' => null, 'params' => ['src' => 'img_house']];
        $plan = ['scenes' => [$this->scene(0, 3), $this->scene(3, 6), ['start' => 6.0, 'end' => 9.5, 'layers' => [$house]]]];

        $out = (new SceneVisualFiller)->enforceOutro($plan, ['agent-card']);

        $this->assertCount(3, $out['scenes']);
        $this->assertSame(6.0, (float) $out['scenes'][2]['start']);
        $this->assertSame(['type' => 'agent-card', 'text' => '', 'params' => []], $out['scenes'][2]['layers'][0]);
    }

    /** A picture used in an intro/outro slot is not shown again elsewhere; an emptied scene hands its time back. */
    public function test_a_slot_picture_is_not_repeated_elsewhere(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'listing', 'params' => ['src' => 'img_house', 'avatar' => 'img_agent']]]],
            ['start' => 3, 'end' => 6, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_room']], ['type' => 'zoom-fx', 'params' => ['src' => 'img_agent']]]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_agent']]]],
            ['start' => 8, 'end' => 11, 'layers' => [['type' => 'agent-card', 'params' => ['src' => 'img_agent']]]],
        ]];

        $out = (new SceneVisualFiller)->dropSlotDuplicates($plan, ['listing', 'agent-card']);

        $this->assertCount(3, $out['scenes']);
        $this->assertSame([['type' => 'zoom-fx', 'params' => ['src' => 'img_room']]], $out['scenes'][1]['layers']);
        $this->assertSame(8, $out['scenes'][1]['end']);                 // took the emptied scene's time
        $this->assertSame('agent-card', $out['scenes'][2]['layers'][0]['type']);
    }

    /** More images than free scenes: split the longest scene rather than stacking two full-frame photos. */
    public function test_extra_images_split_a_scene_instead_of_stacking(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_a']]]],
            ['start' => 2, 'end' => 8, 'layers' => []],
        ]];

        $out = (new SceneVisualFiller)->ensureProvidedImages($plan, [['id' => 'img_a'], ['id' => 'img_b'], ['id' => 'img_c']], ['zoom-fx'], ['zoom-fx']);

        foreach ($out['scenes'] as $scene) {
            $this->assertLessThanOrEqual(1, count($scene['layers']), 'no stacked photos');
        }
        $this->assertCount(3, $out['scenes']);
    }

    /** Every scene already has a photo: an unused one still gets in, by splitting. */
    public function test_an_extra_image_gets_in_when_every_scene_already_has_one(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_a']]]],
            ['start' => 2, 'end' => 6, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_b']]]],
        ]];

        $out = (new SceneVisualFiller)->ensureProvidedImages($plan, [['id' => 'img_a'], ['id' => 'img_b'], ['id' => 'img_c']], ['zoom-fx'], ['zoom-fx']);

        $this->assertSame(['img_a', 'img_b', 'img_c'], array_map(fn ($s) => $s['layers'][0]['params']['src'], $out['scenes']));
    }

    /** A multi-image effect filled only through srcs/src2 is not "empty". */
    public function test_a_fan_filled_through_srcs_is_kept(): void
    {
        $plan = ['scenes' => [['start' => 0, 'end' => 3, 'layers' => [
            ['type' => 'fan', 'params' => ['srcs' => ['img_a', 'img_b']]],
            ['type' => 'fan', 'params' => ['src2' => 'img_c']],
            ['type' => 'fan', 'params' => ['title' => 'x']],
        ]]]];

        $out = (new SceneVisualFiller)->dropDeadLayers($plan, ['fan']);

        $this->assertCount(2, $out['scenes'][0]['layers']);
    }

    /** A five-photo fan the planner gave three gets all five — never the agent's portrait or the logo. */
    public function test_empty_fan_slots_are_topped_up(): void
    {
        $slots = ['src', 'src2', 'src3', 'src4', 'src5'];
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'fan', 'params' => ['src' => 'img_1', 'src2' => 'img_2', 'src3' => 'img_3']]]],
            ['start' => 3, 'end' => 6, 'layers' => [['type' => 'zoom-fx', 'params' => ['src' => 'img_4']]]],
            ['start' => 6, 'end' => 9, 'layers' => [['type' => 'agent-card', 'params' => ['src' => 'img_agent', 'logo' => 'img_logo']]]],
        ]];
        $images = array_map(fn ($id) => ['id' => $id], ['img_1', 'img_2', 'img_3', 'img_4', 'img_5', 'img_agent', 'img_logo']);

        $out = (new SceneVisualFiller)->fillGallerySlots($plan, $images, ['fan' => $slots], ['agent-card']);

        $fan = $out['scenes'][0]['layers'][0]['params'];
        $this->assertCount(5, array_filter(array_map(fn ($k) => $fan[$k] ?? null, $slots)));
        $this->assertSame('img_5', $fan['src4']);              // the unused picture first
        $this->assertSame('img_4', $fan['src5']);              // then the least used
        $this->assertNotContains('img_agent', $fan);
        $this->assertNotContains('img_logo', $fan);
    }

    /** "Use main.webp on the intro": the name becomes the id; a name that matches nothing is dropped, not rendered. */
    public function test_image_file_names_resolve_to_ids_or_are_dropped(): void
    {
        $f = new SceneVisualFiller;
        $plan = ['scenes' => [['start' => 0, 'end' => 3, 'layers' => [
            ['type' => 'listing', 'params' => ['src' => 'main.webp', 'srcs' => ['ghost.webp', 'img_b']]],
        ]]]];

        $plan = $f->resolveImageNames($plan, [['id' => 'img_a', 'name' => 'Main.webp'], ['id' => 'img_b', 'name' => 'kitchen.jpg']]);
        $plan = $f->stripUnknownImageIds($plan, ['img_a', 'img_b']);

        $params = $plan['scenes'][0]['layers'][0]['params'];
        $this->assertSame('img_a', $params['src']);
        $this->assertSame(['img_b'], $params['srcs']);   // the unknown file name is gone, still a list
    }

    /** A full-frame photo hides the scene background, so its text must read as on dark. */
    public function test_photo_effect_scenes_are_treated_as_dark(): void
    {
        $plan = ['scenes' => [
            ['background' => 'papyrus', 'layers' => [['type' => 'blur-title', 'params' => ['src' => 'img_a']]]],
            ['background' => 'papyrus', 'layers' => [['type' => 'card', 'params' => []]]],
        ]];

        $out = (new SceneVisualFiller)->darkenPhotoScenes($plan, ['blur-title']);

        $this->assertSame(['ink', 'papyrus'], array_column($out['scenes'], 'background'));
    }

    /** The "after intro" video follows the intro; the voiceover waits for it and everything after moves back. */
    public function test_the_after_intro_video_pauses_the_voiceover(): void
    {
        $plan = ['duration' => 12.0, 'scenes' => [$this->scene(0, 3), $this->scene(3, 6), $this->scene(6, 9), $this->scene(9, 12, 'agent-card')]];
        // A sentence ends at 3.4 s, a little after the intro's end — the voice breaks there, not mid-sentence.
        $words = [['word' => 'Olá', 'start' => 2.0, 'end' => 2.8], ['word' => 'casa.', 'start' => 2.9, 'end' => 3.3], ['word' => 'Tem', 'start' => 3.5, 'end' => 3.8], ['word' => 'piscina', 'start' => 3.9, 'end' => 4.5]];
        $f = new SceneVisualFiller;

        $out = $f->insertAfterIntro($plan, 'vid_x', 4.5, $words);   // no line set

        $this->assertEquals([[0, 3.4], [3.4, 7.9], [7.9, 10.5], [10.5, 13.5], [13.5, 16.5]], array_map(fn ($s) => [$s['start'], $s['end']], $out['scenes']));
        $this->assertSame(['type' => 'video-intro', 'text' => '', 'params' => ['src' => 'vid_x']], $out['scenes'][1]['layers'][0]);
        $this->assertSame([true, false], [$out['scenes'][1]['plain'], $out['scenes'][1]['karaoke']]);   // just the uploaded video, nothing on top
        $this->assertSame([16.5, ['at' => 3.4, 'seconds' => 4.5]], [$out['duration'], $out['voice_gap']]);
        $this->assertSame($out, $f->insertAfterIntro($out, 'vid_x', 4.5, $words));          // runs once

        [$back, $id, $d] = $f->removeAfterIntro($out);                                     // a re-voice takes it out again
        $this->assertSame(['vid_x', 4.5, 12.0, 4], [$id, $d, $back['duration'], count($back['scenes'])]);
        $this->assertEquals([10.5 - 4.5, 13.5 - 4.5], [$back['scenes'][2]['start'], $back['scenes'][2]['end']]);
    }

    /** With its line in the voiceover, the video starts on the line, plays once, and the voice waits only for what the line doesn't cover. */
    public function test_the_after_intro_video_is_synced_to_its_line(): void
    {
        $w = fn ($word, $a, $b) => ['word' => $word, 'start' => $a, 'end' => $b];
        $words = [$w('Casa', 0.2, 1.0), $w('rústica.', 1.1, 2.0),
            $w('Se', 2.4, 2.6), $w('queres', 2.6, 2.9), $w('vender', 2.9, 3.2), $w('casa,', 3.2, 3.6), $w('fala', 3.7, 3.9), $w('com', 3.9, 4.0), $w('o', 4.0, 4.1), $w('Nuno', 4.1, 4.4), $w('Narcizo.', 4.4, 5.0),
            $w('Tem', 5.4, 5.7), $w('vista', 5.7, 6.0)];
        $plan = ['duration' => 12.0, 'scenes' => [$this->scene(0, 3), $this->scene(3, 6), $this->scene(6, 9), $this->scene(9, 12, 'agent-card')]];
        $line = 'Se queres vender casa, fala com o Nuno Narciso';
        $f = new SceneVisualFiller;

        // A 5 s video over a 2.6 s line: the voice waits 2.4 s after "Narciso".
        $out = $f->insertAfterIntro($plan, 'clips/after-intro/x.mp4', 5.0, $words, $line);
        $this->assertEquals([[0, 2.4], [2.4, 7.4], [7.4, 8.4], [8.4, 11.4], [11.4, 14.4]], array_map(fn ($s) => [round($s['start'], 2), round($s['end'], 2)], $out['scenes']));
        $this->assertEquals(['at' => 5.0, 'seconds' => 2.4], $out['voice_gap']);
        $this->assertCount(1, array_filter($out['scenes'], fn ($s) => ($s['layers'][0]['type'] ?? '') === 'video-intro'));   // played once

        // A 1.5 s video: no wait — the reel comes back while the line is still being said.
        $short = $f->insertAfterIntro($plan, 'clips/after-intro/x.mp4', 1.5, $words, $line);
        $this->assertEquals([[0, 2.4], [2.4, 3.9], [3.9, 6]], array_map(fn ($s) => [round($s['start'], 2), round($s['end'], 2)], array_slice($short['scenes'], 0, 3)));

        // Leaving 0.5 s of a scene would flash it: the next scene takes that time.
        $tight = ['duration' => 12.0, 'scenes' => [$this->scene(0, 3), $this->scene(3, 5.5), $this->scene(5.5, 9), $this->scene(9, 12, 'agent-card')]];
        $flash = $f->insertAfterIntro($tight, 'clips/after-intro/x.mp4', 5.0, $words, $line);
        $this->assertEquals([[0, 2.4], [2.4, 7.4], [7.4, 11.4]], array_map(fn ($s) => [round($s['start'], 2), round($s['end'], 2)], array_slice($flash['scenes'], 0, 3)));
        $this->assertEquals(0.0, $short['voice_gap']['seconds']);

        // A re-voice takes it out — the video's time back to the intro — and puts it back.
        [$back, $src, $len] = $f->removeAfterIntro($out);
        $this->assertSame(['clips/after-intro/x.mp4', 5.0, 12.0], [$src, $len, $back['duration']]);
        $this->assertEquals([[0, 5.0], [5.0, 6], [6, 9], [9, 12]], array_map(fn ($s) => [round($s['start'], 2), round($s['end'], 2)], $back['scenes']));
    }

    public function test_the_line_is_said_right_after_the_intro_once(): void
    {
        $line = 'Se queres vender casa, fala com o Nuno Narciso';
        $v = ListingScriptWriter::withAfterIntroLine('Uma casa rústica em Mafra. Tem vista mar.', $line);

        $this->assertSame('Uma casa rústica em Mafra. Se queres vender casa, fala com o Nuno Narciso. Tem vista mar.', $v);
        $this->assertSame($v, ListingScriptWriter::withAfterIntroLine($v, $line));
    }

    /** A video clip never freezes: the scene ends with its clip and other clips fill the rest; tiny leftovers go to the next scene. */
    public function test_video_scenes_fit_their_clips(): void
    {
        $vid = fn ($a, $b, $src, $type = 'zoom-fx') => ['start' => $a, 'end' => $b, 'layers' => [['type' => $type, 'text' => 'T', 'params' => ['src' => $src]]]];
        $plan = ['scenes' => [
            $vid(0, 3, 'img_main', 'listing'),
            $vid(3, 11, 'vid_sala'),            // 8 s over a 3 s clip
            $vid(11, 13.5, 'vid_cozinha'),      // 2.5 s over a 2 s clip: 0.5 s left
            $vid(13.5, 16, 'img_x'),
        ]];
        $lengths = ['vid_sala' => 3.0, 'vid_cozinha' => 2.0, 'vid_mar' => 4.0];

        $out = (new SceneVisualFiller)->fitVideoScenes($plan, $lengths)['scenes'];

        $this->assertEquals([[0, 3], [3, 6], [6, 10], [10, 11], [11, 13], [13, 16]], array_map(fn ($s) => [$s['start'], $s['end']], $out));
        $this->assertSame(['vid_sala', 'vid_mar', 'vid_sala', 'vid_cozinha', 'img_x'], array_map(fn ($s) => $s['layers'][0]['params']['src'], array_slice($out, 1)));
        $this->assertSame('image-reveal', $out[2]['layers'][0]['type']);   // the fill: just the clip, full-frame
        $this->assertSame('T', $out[1]['layers'][0]['text']);               // the planned scene keeps its text, for its clip's length
    }

    /** Before the after-intro scene nothing can shift: a short leftover holds the clip, never a flash of another. */
    public function test_a_leftover_before_the_after_intro_is_held(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2.9, 'layers' => [['type' => 'listing', 'params' => ['src' => 'vid_mar']]]],
            ['start' => 2.9, 'end' => 7.9, 'plain' => true, 'layers' => [['type' => 'video-intro', 'params' => ['src' => 'clips/after-intro/x.mp4']]]],
        ]];

        $out = (new SceneVisualFiller)->fitVideoScenes($plan, ['vid_mar' => 2.5, 'vid_sala' => 3.0])['scenes'];

        $this->assertEquals([[0, 2.9], [2.9, 7.9]], array_map(fn ($s) => [$s['start'], $s['end']], $out));
        $this->assertSame('vid_mar', $out[0]['layers'][0]['params']['src']);
    }

    /** Clips follow the voiceover: each video scene and each fill shows what is said over it. */
    public function test_video_clips_follow_the_voiceover(): void
    {
        $w = fn ($word, $a, $b) => ['word' => $word, 'start' => $a, 'end' => $b];
        // The matcher's topics, by first word: "dois quartos amplos | cozinha equipada | vista mar".
        $phrases = VideoClipMatcher::toSegments([
            $w('dois', 0.0, 0.3), $w('quartos', 0.3, 0.8), $w('amplos', 0.8, 1.2),
            $w('cozinha', 1.6, 2.1), $w('equipada', 2.1, 2.7),
            $w('vista', 6.0, 6.4), $w('mar', 6.4, 7.0),
        ], [['from' => 1, 'clips' => []], ['from' => 4, 'clips' => ['vid_ghost']], ['from' => 6, 'clips' => []]], ['vid_cozinha'], ['at' => 5.0, 'seconds' => 1.0]);   // words after 5 s move back 1 s (an after-intro wait)
        $this->assertSame(['dois quartos amplos', 'cozinha equipada', 'vista mar'], array_column($phrases, 'text'));
        $this->assertSame([7.0, 8.0], [$phrases[2]['start'], $phrases[2]['end']]);
        $this->assertSame([], $phrases[1]['clips']);   // a clip that doesn't exist is dropped

        $phrases[0]['clips'] = ['vid_quarto', 'vid_quarto2'];
        $phrases[1]['clips'] = ['vid_cozinha'];
        $phrases[2]['clips'] = ['vid_mar'];
        $vid = fn ($a, $b, $src) => ['start' => $a, 'end' => $b, 'layers' => [['type' => 'zoom-fx', 'text' => '', 'params' => ['src' => $src]]]];
        $plan = ['scenes' => [$vid(0, 1.5, 'vid_sala'), $vid(1.5, 6.5, 'vid_sala'), $vid(6.5, 9, 'img_main')]];
        $f = new SceneVisualFiller;

        $synced = $f->syncVideoClips($plan, $phrases, ['vid_sala' => 3.0, 'vid_quarto' => 3.0, 'vid_quarto2' => 3.0, 'vid_cozinha' => 2.0, 'vid_mar' => 4.0]);
        $this->assertSame(['vid_quarto', 'vid_cozinha', 'img_main'], array_map(fn ($s) => $s['layers'][0]['params']['src'], $synced['scenes']));

        // The kitchen clip is 2 s: from 3.5 s the words are… nothing, then "vista mar" at 7 s — the fill takes the sea once it is said.
        $fit = $f->fitVideoScenes($synced, ['vid_quarto' => 3.0, 'vid_cozinha' => 2.0, 'vid_mar' => 4.0, 'vid_sala' => 3.0], $phrases)['scenes'];
        $this->assertSame('vid_cozinha', $fit[1]['layers'][0]['params']['src']);
        $this->assertNotContains('vid_cozinha', array_map(fn ($s) => $s['layers'][0]['params']['src'], array_slice($fit, 2)));
    }

    /** Enough footage: no clip repeats while an unused one fits the words; the scene before the after-intro gets a clip that covers it. */
    public function test_clips_do_not_repeat_and_the_intro_cuts_cleanly(): void
    {
        $vid = fn ($a, $b, $src) => ['start' => $a, 'end' => $b, 'layers' => [['type' => 'zoom-fx', 'text' => '', 'params' => ['src' => $src]]]];
        $phrases = [
            ['start' => 0.0, 'end' => 3.0, 'clips' => ['vid_mar', 'vid_mar2']],
            ['start' => 8.0, 'end' => 14.0, 'clips' => ['vid_sala', 'vid_sala2']],
            ['start' => 14.0, 'end' => 20.0, 'clips' => ['vid_mar', 'vid_mar2']],
        ];
        $plan = ['scenes' => [
            $vid(0, 3, 'vid_sala'),
            ['start' => 3, 'end' => 8, 'plain' => true, 'layers' => [['type' => 'video-intro', 'params' => ['src' => 'clips/after-intro/x.mp4']]]],
            $vid(8, 11, 'vid_sala'), $vid(11, 14, 'vid_sala'), $vid(14, 17, 'vid_sala'), $vid(17, 20, 'vid_sala'),
        ]];
        $lengths = ['vid_mar' => 2.5, 'vid_mar2' => 4.0, 'vid_sala' => 3.0, 'vid_sala2' => 3.0];

        $out = (new SceneVisualFiller)->syncVideoClips($plan, $phrases, $lengths)['scenes'];

        $srcs = array_map(fn ($s) => $s['layers'][0]['params']['src'], $out);
        $this->assertSame('vid_mar2', $srcs[0]);   // vid_mar ranks first but is 2.5 s: the intro would cut away before the video

        // Nothing that fits the words covers the intro: the longest that fits (the next one finishes it), never an unrelated room.
        $short = (new SceneVisualFiller)->syncVideoClips($plan, $phrases, ['vid_mar' => 2.5, 'vid_mar2' => 2.0, 'vid_sala' => 9.0], 'img_main')['scenes'];
        $this->assertSame('vid_mar', $short[0]['layers'][0]['params']['src']);
        $this->assertSame(['vid_sala', 'vid_sala2', 'vid_mar', 'vid_mar2'], array_slice($srcs, 2));   // four scenes, four different clips
    }

    /** A fill is never the clip the next scene opens with. */
    public function test_a_fill_does_not_repeat_the_next_scene(): void
    {
        $vid = fn ($a, $b, $src) => ['start' => $a, 'end' => $b, 'layers' => [['type' => 'zoom-fx', 'text' => '', 'params' => ['src' => $src]]]];
        $plan = ['scenes' => [$vid(0, 5, 'vid_a'), $vid(5, 8, 'vid_b')]];
        $phrases = [['start' => 0.0, 'end' => 8.0, 'clips' => ['vid_b', 'vid_c']]];

        $out = (new SceneVisualFiller)->fitVideoScenes($plan, ['vid_a' => 3.0, 'vid_b' => 3.0, 'vid_c' => 3.0], $phrases)['scenes'];

        $this->assertSame(['vid_a', 'vid_c', 'vid_b'], array_map(fn ($s) => $s['layers'][0]['params']['src'], $out));
    }

    /** Video listings: the middle is footage under the voiceover — what is said is shown, other takes of a room before any repeat, cuts on the pauses. */
    public function test_a_video_listing_is_footage_under_the_voiceover(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'listing', 'text' => 'T2', 'params' => ['src' => 'vid_mar']]]],
            ['start' => 3, 'end' => 8, 'plain' => true, 'layers' => [['type' => 'video-intro', 'params' => ['src' => 'clips/after-intro/x.mp4']]]],
            ['start' => 8, 'end' => 13, 'layers' => [['type' => 'leque-imagens', 'text' => 'COZINHA EQUIPADA', 'params' => ['src' => 'img_x']]]],
            ['start' => 13, 'end' => 18, 'layers' => [['type' => 'grelha', 'text' => 'DOIS QUARTOS', 'params' => []]]],
            ['start' => 18, 'end' => 22, 'layers' => [['type' => 'agent-card', 'text' => 'Nuno', 'params' => ['src' => 'img_agent']]]],
        ]];
        $phrases = [
            ['start' => 8.0, 'end' => 12.5, 'clips' => ['vid_coz']],           // "uma cozinha equipada…" — longer than one take
            ['start' => 13.0, 'end' => 17.6, 'clips' => ['vid_quarto', 'vid_sala']],
        ];
        $lengths = ['vid_mar' => 3.0, 'vid_coz' => 3.0, 'vid_coz2' => 3.0, 'vid_quarto' => 3.0, 'vid_sala' => 4.0];
        $rooms = ['vid_mar' => 'vista-mar', 'vid_coz' => 'cozinha', 'vid_coz2' => 'cozinha', 'vid_quarto' => 'quarto', 'vid_sala' => 'sala'];

        $out = (new SceneVisualFiller)->footageTimeline($plan, $phrases, $lengths, $rooms)['scenes'];

        $body = array_slice($out, 2, -1);
        $this->assertSame(['vid_coz', 'vid_coz2', 'vid_quarto', 'vid_sala'], array_map(fn ($sc) => $sc['layers'][0]['params']['src'], $body));
        $this->assertTrue(collect($body)->every(fn ($sc) => $sc['footage'] === true));                      // full-frame footage, no effects
        $this->assertSame([8.0, 11.0], [$body[0]['start'], $body[0]['end']]);
        $this->assertSame(['COZINHA EQUIPADA', 'DOIS QUARTOS'], array_values(array_filter(array_map(fn ($sc) => $sc['layers'][0]['text'], $body))));
        $this->assertEqualsWithDelta(18.0, end($body)['end'], 0.01);                                          // runs to the agent's card
        $this->assertSame('agent-card', end($out)['layers'][0]['type']);
        $this->assertEquals(array_column(array_slice($out, 1), 'start'), array_column(array_slice($out, 0, -1), 'end'));   // no gaps
    }

    /** A quick topic ("cozinha equipada", under a second) still gets its own shot, of at least 2 s. */
    public function test_a_quick_topic_gets_its_shot(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'layers' => [['type' => 'listing', 'params' => ['src' => 'vid_mar']]]],
            ['start' => 3, 'end' => 12, 'layers' => [['type' => 'grelha', 'params' => []]]],
            ['start' => 12, 'end' => 15, 'layers' => [['type' => 'agent-card', 'params' => []]]],
        ]];
        $topics = [
            ['start' => 3.0, 'end' => 5.8, 'clips' => ['vid_quarto']],
            ['start' => 5.8, 'end' => 6.7, 'clips' => ['vid_coz']],
            ['start' => 7.0, 'end' => 11.8, 'clips' => ['vid_sala']],
        ];
        $lengths = ['vid_mar' => 3.0, 'vid_quarto' => 4.0, 'vid_coz' => 4.0, 'vid_sala' => 5.0];

        $body = array_slice((new SceneVisualFiller)->footageTimeline($plan, $topics, $lengths, [])['scenes'], 1, -1);

        $this->assertSame([['vid_quarto', 3.0, 5.8], ['vid_coz', 5.8, 7.8], ['vid_sala', 7.8, 12.0]],
            array_map(fn ($sc) => [$sc['layers'][0]['params']['src'], $sc['start'], $sc['end']], $body));
    }

    /** A split never shows one clip top and bottom: the sync skips what another slot shows, and a repeat is replaced. */
    public function test_an_effect_never_shows_the_same_clip_twice(): void
    {
        $f = new SceneVisualFiller;
        $plan = ['scenes' => [['start' => 0, 'end' => 3, 'layers' => [['type' => 'split-slide-duo', 'text' => '', 'params' => ['src' => 'vid_sala', 'src2' => 'vid_vista']]]]]];
        $phrases = [['start' => 0.0, 'end' => 3.0, 'clips' => ['vid_vista', 'vid_mar']]];

        $synced = $f->syncVideoClips($plan, $phrases, ['vid_sala' => 4.0, 'vid_vista' => 4.0, 'vid_mar' => 4.0]);
        $this->assertSame(['src' => 'vid_mar', 'src2' => 'vid_vista'], $synced['scenes'][0]['layers'][0]['params']);   // the best one not already shown

        $dup = ['scenes' => [['start' => 0, 'end' => 3, 'layers' => [['type' => 'leque-imagens', 'text' => '', 'params' => ['src' => 'a', 'src2' => 'b', 'src3' => 'a']]]]]];
        $p = $f->distinctSlots($dup, ['a', 'b', 'c'])['scenes'][0]['layers'][0]['params'];
        $this->assertSame(['src' => 'a', 'src2' => 'b', 'src3' => 'c'], $p);
    }

    /** One after-intro video per project (SFX studio and Design System); replacing it leaves one file. */
    public function test_the_after_intro_video_is_uploaded_in_the_studio(): void
    {
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $store = app(EffectStore::class);

        $video = fn (string $name) => tap(sys_get_temp_dir().'/'.uniqid().$name, fn ($f) => exec('ffmpeg -v error -y -f lavfi -i color=c=black:s=64x64:d=1 -f mp4 '.escapeshellarg($f)));
        Livewire::test(AfterIntroSetting::class)->set('afterIntroUpload', UploadedFile::fake()->createWithContent('a.mp4', file_get_contents($video('a.mp4'))))->assertHasNoErrors();
        Livewire::test(AfterIntroSetting::class)->set('afterIntroUpload', UploadedFile::fake()->createWithContent('b.mp4', file_get_contents($video('b.mp4'))))->assertHasNoErrors();
        Livewire::test(AfterIntroSetting::class)->set('afterIntroUpload', UploadedFile::fake()->create('c.mp4', 10, 'video/mp4'))->assertHasErrors('afterIntroUpload'); // unreadable

        $this->assertCount(1, $disk->files('clips/after-intro'));
        $this->assertNotNull($store->afterIntroPath());

        Livewire::test(AfterIntroSetting::class)->set('afterIntroUpload', UploadedFile::fake()->create('x.png', 10, 'image/png'))->assertHasErrors('afterIntroUpload');
        Livewire::test(AfterIntroSetting::class)->call('apagarAfterIntro');
        $this->assertNull($store->afterIntroPath());
    }

    public function test_nothing_changes_when_the_planner_already_closed_with_one(): void
    {
        $plan = ['scenes' => [$this->scene(0, 3), $this->scene(3, 9, 'agent-card')]];

        $this->assertSame($plan, (new SceneVisualFiller)->enforceOutro($plan, ['agent-card']));
        $this->assertSame($plan, (new SceneVisualFiller)->enforceOutro($plan, []));
    }

    public function test_effects_are_marked_outro_in_the_studio_and_reach_the_planner(): void
    {
        Queue::fake();
        $effect = app(EffectStore::class)->create([
            'slug' => 'agent-card', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true, 'prompt' => 'x',
            'display_name' => 'Agent', 'description' => 'agent card', 'param_schema' => '{ "src"?: string }', 'tsx' => 'export default () => null;',
        ]);
        $library = app(EffectLibrary::class);

        Livewire::test(ClipsAnimadosSfx::class)->call('alternarOutro', $effect->id());
        Livewire::test(ClipsAnimadosSfx::class)->call('alternarOutroBuiltin', 'card');

        $this->assertEqualsCanonicalizing(['agent-card', 'card'], $library->outroSlugs());
        $this->assertNotContains('agent-card', $library->simpleImageEffects()); // an outro card never stands in for a photo

        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function sys(): string
            {
                return $this->systemPrompt('dense', false, [], false, '', false, ['card', 'agent-card']);
            }
        };
        $this->assertStringContainsString('# CLOSING (OUTRO)', $planner->sys());
    }
}
