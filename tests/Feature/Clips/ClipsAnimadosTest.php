<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClipsAnimadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Every route requires a session now (see Authenticate in bootstrap/app.php).
        $this->comSessaoIniciada();
    }

    private function store(): ClipStore
    {
        return app(ClipStore::class);
    }

    public function test_dashboard_renders_with_title(): void
    {
        $this->get('/clips-animados')->assertOk()->assertSee('Animated Clips')->assertSee('New clip');
    }

    public function test_create_flow_makes_an_animation_project(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo Brand Machine')
            ->call('submitAnimation')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $this->assertSame(1, $this->store()->all()->where('type', 'animation')->count());
        Queue::assertPushed(TranscribeJob::class);
    }

    /** The optional LEAD is stored on the clip so RenderJob can pin it on screen. */
    public function test_a_lead_is_stored_in_the_clip_meta(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo')
            ->set('lead', 'GPT-5 just got 60% cheaper')
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertSame('GPT-5 just got 60% cheaper', $this->store()->all()->first()->meta['lead']);
    }

    /** A news bit's `> lead` line is lifted into the lead field, not spoken. */
    public function test_a_seeded_news_bit_splits_lead_from_script(): void
    {
        session(['animado_texto' => "**Headline**\n> GPT-5 just got 60% cheaper\nThe body that gets spoken."]);

        Livewire::test(ClipsAnimados::class)
            ->assertSet('lead', 'GPT-5 just got 60% cheaper')
            ->assertSet('createType', 'animation')
            ->assertSet('text', fn ($t) => ! str_contains($t, '60% cheaper') && str_contains($t, 'body that gets spoken'));
    }

    public function test_accepts_a_browser_recording_detected_as_video_webm(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('audio', UploadedFile::fake()->create('gravacao.webm', 300, 'video/webm'))
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertSame('audio', $this->store()->all()->firstWhere('type', 'animation')->input_kind);
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_adds_images_with_descriptions_and_saves_them(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('newImage', UploadedFile::fake()->image('logo.png', 120, 120))
            ->set('newImageDesc', 'logótipo da empresa')
            ->call('adicionarImagem')
            ->assertHasNoErrors()
            ->assertSet('images', fn ($v) => count($v) === 1)
            ->set('text', 'Olá mundo Brand Machine')
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $p = $this->store()->all()->firstWhere('type', 'animation');
        $this->assertCount(1, $p->images);
        $this->assertSame('logótipo da empresa', $p->images[0]['description']);
        $this->assertStringStartsWith('img_', $p->images[0]['id']);
    }

    public function test_image_requires_a_description(): void
    {
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('newImage', UploadedFile::fake()->image('x.png'))
            ->call('adicionarImagem')
            ->assertHasErrors('newImageDesc');
    }

    public function test_overlay_requires_a_video(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'overlay')
            ->call('submitOverlay')
            ->assertHasErrors('video');
    }

    public function test_creates_an_overlay_project_from_a_video(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'overlay')
            ->set('video', UploadedFile::fake()->create('peca.mp4', 200, 'video/mp4'))
            ->call('submitOverlay')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->store()->all()->where('type', 'overlay')->count());
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_edit_plan_validates_and_dispatches_render(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'punchWord' => null, 'layers' => [['type' => 'timeline', 'text' => null, 'params' => ['caption' => 'Modelos', 'items' => [['label' => 'A']]]]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('view', 'editPlan')
            ->assertSet('editScenes.0.layerText', 'Modelos')
            ->set('editScenes.0.background', 'ink')
            ->set('editScenes.0.punchWord', 'ÊNFASE')
            ->set('editScenes.0.layerText', 'Cronologia')
            ->call('guardarPlano')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $p->refresh();
        $this->assertSame(ClipRecord::STATUS_RENDERING, $p->status);
        $this->assertSame('ink', $p->plan['scenes'][0]['background']);
        $this->assertSame('ÊNFASE', $p->plan['scenes'][0]['punchWord']);
        $this->assertSame('timeline', $p->plan['scenes'][0]['layers'][0]['type']);
        $this->assertSame('Cronologia', $p->plan['scenes'][0]['layers'][0]['params']['caption']);
        Queue::assertPushed(RenderJob::class);
    }

    /**
     * Saving the scene editor rebuilt each scene from a whitelist of fields and
     * silently dropped `present` — on an overlay clip the renderer then defaulted
     * every scene to "video", so a save+re-render ignored all animations and just
     * showed the source video. Edits must merge OVER the original scene.
     */
    public function test_saving_an_overlay_plan_keeps_each_scenes_present_mode(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'status' => ClipRecord::STATUS_DONE,
            'meta' => ['allowed_present' => ['split', 'animation']],
            'plan' => ['duration' => 6.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'present' => 'animation', 'layers' => [['type' => 'bullet-list', 'text' => null, 'params' => ['title' => 'T', 'items' => ['a']]]]],
                ['start' => 3, 'end' => 6, 'background' => 'video', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'present' => 'split', 'layers' => [['type' => 'card', 'text' => null, 'params' => ['title' => 'C', 'lines' => ['x']]]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editScenes.0.punchWord', 'marca')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('animation', $p->plan['scenes'][0]['present']);
        $this->assertSame('split', $p->plan['scenes'][1]['present']);
        $this->assertSame('bullet-list', $p->plan['scenes'][0]['layers'][0]['type']);
        Queue::assertPushed(RenderJob::class);
    }

    /**
     * Picking an animation for a scene that was presenting as plain "video" must
     * make it visible: `present` is cleared so the validator assigns a graphic
     * mode; leaving it "video" would hide the newly chosen layer entirely.
     */
    public function test_picking_an_animation_for_a_video_scene_makes_it_visible(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'status' => ClipRecord::STATUS_DONE,
            'meta' => ['allowed_present' => ['split', 'animation']],
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'present' => 'video', 'layers' => []],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('mudarAnimacao', 0, 'kinetic-text')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('kinetic-text', $p->plan['scenes'][0]['layers'][0]['type']);
        $this->assertContains($p->plan['scenes'][0]['present'], ['split', 'animation'], 'the scene must present its new animation, not plain video');
    }

    public function test_changing_a_scene_animation_swaps_the_layer_effect(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'punchWord' => null, 'layers' => [['type' => 'kinetic-text', 'text' => 'Olá', 'params' => []]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('editScenes.0.animacao', 'kinetic-text')
            ->call('escolherAnimacao', 0)
            ->assertSet('animacaoPickerCena', 0)
            ->call('mudarAnimacao', 0, 'fade')
            ->assertSet('editScenes.0.animacao', 'fade')
            ->assertSet('animacaoPickerCena', null)
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('fade', $p->plan['scenes'][0]['layers'][0]['type']); // animation swapped
        $this->assertSame('Olá', $p->plan['scenes'][0]['layers'][0]['text']);   // scene text preserved
    }

    public function test_edit_raw_json_saves_plan_verbatim_and_renders(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'layers' => [['type' => 'card', 'params' => ['title' => 'Antigo']]]],
            ]],
        ]);

        $newJson = json_encode(['duration' => 5.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
            ['start' => 0, 'end' => 5, 'background' => 'ink', 'transitionIn' => 'zoom', 'layers' => [['type' => 'pie-chart', 'params' => ['slices' => [['label' => 'A', 'value' => 60], ['label' => 'B', 'value' => 40]]]]]],
        ]]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editMode', 'json')
            ->set('editPlanJson', $newJson)
            ->call('guardarJson')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $p->refresh();
        $this->assertSame(ClipRecord::STATUS_RENDERING, $p->status);
        $this->assertEquals(5.0, $p->plan['duration']);
        $this->assertSame('pie-chart', $p->plan['scenes'][0]['layers'][0]['type']);
        Queue::assertPushed(RenderJob::class);
    }

    public function test_editing_a_clip_loads_its_uploaded_images(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'images' => [['id' => 'img_logo', 'path' => 'clips/uploads/logo.png', 'description' => 'the logo']],
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'layers' => []],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('images.0.id', 'img_logo')
            ->assertSet('images.0.description', 'the logo');
    }

    public function test_replacing_an_image_keeps_its_id_and_swaps_the_file(): void
    {
        Storage::fake('local');

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'images' => [['id' => 'img_logo', 'path' => 'clips/uploads/old.png', 'description' => 'the logo']],
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'layers' => []],
            ]],
        ]);

        $component = Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('imageReplace.0', UploadedFile::fake()->image('new.png', 120, 120))
            ->assertHasNoErrors()
            ->assertSet('images.0.id', 'img_logo'); // id preserved → plan keeps using it

        $newPath = $component->get('images')[0]['path'];
        $this->assertNotSame('clips/uploads/old.png', $newPath); // file swapped
    }

    public function test_removing_a_used_image_persists_and_blanks_its_plan_reference(): void
    {
        Queue::fake();
        Storage::fake('local');

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'images' => [
                ['id' => 'img_a', 'path' => 'clips/uploads/a.png', 'description' => 'A'],
                ['id' => 'img_b', 'path' => 'clips/uploads/b.png', 'description' => 'B'],
            ],
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'image-reveal', 'text' => null, 'params' => ['src' => 'img_a', 'variant' => 'fullscreen']]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('removerImagem', 0) // drop img_a (the referenced one)
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertCount(1, $p->images);
        $this->assertSame('img_b', $p->images[0]['id']);
        // The now-dangling reference is blanked so the render shows a placeholder, not a 404.
        $this->assertSame('', $p->plan['scenes'][0]['layers'][0]['params']['src']);
    }

    /**
     * A scene can show an image while having none: the user chose to supply their
     * own and has not yet, or the image it referenced was removed (which blanks
     * the src — see the test above). The editor must still offer an upload there,
     * otherwise that segment can never get its picture back.
     */
    public function test_a_scene_with_an_empty_image_slot_still_offers_an_upload(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'images' => [],
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'image-reveal', 'text' => null, 'params' => ['src' => '', 'variant' => 'fullscreen']]]],
                // A text-only scene shows no image at all and must NOT offer one.
                ['start' => 3, 'end' => 6, 'background' => 'papyrus', 'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'kinetic-text', 'text' => 'Sem imagem', 'params' => []]]],
            ]],
        ]);

        $componente = Livewire::test(ClipsAnimados::class)->call('editarClip', $p->id);
        $slots = $componente->instance()->sceneImages;

        $this->assertArrayHasKey(0, $slots, 'the image scene offers no slot to fill');
        $this->assertNull($slots[0]['id'], 'the slot should read as empty');
        $this->assertSame(0, $slots[0]['layerIndex']);
        $this->assertArrayNotHasKey(1, $slots, 'a text-only scene must not offer an image upload');
    }

    public function test_uploading_fills_an_empty_scene_image_slot(): void
    {
        Queue::fake();
        Storage::fake('local');

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'images' => [],
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'image-reveal', 'text' => null, 'params' => ['src' => '', 'variant' => 'fullscreen']]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('sceneImageUploads.0', UploadedFile::fake()->image('minha.png', 120, 120))
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertCount(1, $p->images, 'the upload was not attached to the clip');
        $this->assertSame(
            $p->images[0]['id'],
            $p->plan['scenes'][0]['layers'][0]['params']['src'],
            'the scene still does not point at the uploaded image'
        );
    }

    public function test_edit_raw_json_rejects_invalid_json(): void
    {
        $p = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => ['duration' => 3.0, 'scenes' => []]]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editMode', 'json')
            ->set('editPlanJson', '{ not valid json ')
            ->call('guardarJson')
            ->assertHasErrors('editPlanJson');
    }

    public function test_edit_plan_accepts_all_renderer_transitions(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 1.5, 'background' => 'vellum', 'transitionIn' => 'slide', 'karaoke' => false, 'layers' => []],
                ['start' => 1.5, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'zoom', 'karaoke' => false, 'layers' => []],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('editScenes.0.transitionIn', 'slide')
            ->call('guardarPlano')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $p->refresh();
        $this->assertSame(ClipRecord::STATUS_RENDERING, $p->status);
        $this->assertSame('slide', $p->plan['scenes'][0]['transitionIn']);
        Queue::assertPushed(RenderJob::class);
    }

    public function test_edit_plan_rejects_end_before_start(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'layers' => []],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editScenes.0.start', 2)
            ->set('editScenes.0.end', 1)
            ->call('guardarPlano')
            ->assertHasErrors('editScenes.0.end');
    }

    public function test_edit_transcript_replans_and_rerenders(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'transcript' => ['duration' => 2.0, 'text' => 'Marquina', 'words' => [['word' => 'Marquina', 'start' => 0.0, 'end' => 2.0]], 'segments' => []],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarTranscricao', $p->id)
            ->assertSet('view', 'editTranscript')
            ->set('editTranscriptText', 'Máquina')
            ->call('regenerar')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $p->refresh();
        $this->assertSame('Máquina', $p->transcript['text']);
        $this->assertSame(ClipRecord::STATUS_PLANNING, $p->status);
        Queue::assertPushed(PlanAnimationsJob::class);
    }

    public function test_music_choice_persists_to_meta_on_create(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('text', 'Olá mundo Brand Machine')
            ->set('musica', 'faixa.mp3')
            ->set('musicaVolume', 0.25)
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $meta = $this->store()->all()->firstWhere('type', 'animation')->meta;
        $this->assertSame('faixa.mp3', $meta['musica']);
        $this->assertEqualsWithDelta(0.25, $meta['musica_volume'], 0.0001);
    }

    public function test_render_job_mixes_chosen_music_and_omits_when_none(): void
    {
        $dir = storage_path('app/testing/musicas-'.uniqid());
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/faixa.mp3", 'ID3');
        $this->app->instance(MusicLibrary::class, new MusicLibrary($dir));

        $renderer = new class implements RemotionRenderer
        {
            public array $props = [];

            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                $this->props = $props;
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'X');

                return $outPath;
            }
        };
        $this->app->instance(RemotionRenderer::class, $renderer);

        $plan = ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => []];

        $chosen = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['musica' => 'faixa.mp3', 'musica_volume' => 0.2]]);
        $this->app->call([new RenderJob($chosen->id), 'handle']);
        $this->assertSame("$dir/faixa.mp3", $renderer->props['musicSrc']);
        $this->assertEqualsWithDelta(0.2, $renderer->props['musicVolume'], 0.0001);

        $none = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['musica' => 'nenhuma']]);
        $this->app->call([new RenderJob($none->id), 'handle']);
        $this->assertArrayNotHasKey('musicSrc', $renderer->props);

        File::deleteDirectory($dir);
    }

    public function test_plan_job_suggests_title_description_and_tags(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'title' => 'Sem título',
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $p->refresh();
        $this->assertNotSame('Sem título', $p->title);
        $this->assertNotEmpty($p->meta['suggested']['description']);
        $this->assertNotEmpty($p->meta['suggested']['tags']);
    }

    /** Each image suggestion is upload / AI image / no image (a text scene instead). */
    public function test_image_suggestion_mode_switches_between_generate_and_text(): void
    {
        $key = ImageRequests::key('a laptop on a desk');
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_COLLECTING,
            'meta' => ['image_requests' => [['key' => $key, 'prompt' => 'a laptop on a desk']]],
        ]);

        $c = Livewire::test(ClipsAnimados::class)->call('revisarImagens', $p->id);
        $this->assertSame('generate', $c->instance()->imageRequests[0]['mode']);

        $c->call('modoImagem', $key, 'text');
        $this->assertTrue($p->refresh()->meta['image_text'][$key]);
        $this->assertSame('text', $c->instance()->imageRequests[0]['mode']);

        $c->call('modoImagem', $key, 'generate');
        $this->assertSame([], $p->refresh()->meta['image_text']);
        $this->assertSame('generate', $c->instance()->imageRequests[0]['mode']);
    }

    public function test_delete_removes_the_project(): void
    {
        $p = $this->store()->create(['type' => 'animation', 'input_kind' => 'text', 'source_text' => 'x']);

        Livewire::test(ClipsAnimados::class)->call('apagar', $p->id);

        $this->assertNull($this->store()->find($p->id));
    }
}
