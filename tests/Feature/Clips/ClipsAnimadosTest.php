<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\FillChosenVisualsJob;
use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\ImproveClipJob;
use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RegenerateVoiceoverJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\ClipReviser;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\ImageDescriber;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Content\FinishedContent;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\Shorts\LocalVideoEngine;
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
        $this->get('/clips-animados')->assertOk()->assertSee('Imóveis')->assertSee('Novo vídeo');
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

    /** Per-clip direction rides on the clip so PlanAnimationsJob can hand it to the planner. */
    public function test_instructions_for_one_clip_are_stored_in_its_meta(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo')
            ->set('instructions', "  Dark and cinematic. No charts.  \n")
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertSame('Dark and cinematic. No charts.', $this->store()->all()->first()->meta['instructions']);
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

    /** The description is optional — left empty, the AI writes it at planning. */
    public function test_an_image_can_be_added_without_a_description(): void
    {
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('newImage', UploadedFile::fake()->image('x.png'))
            ->call('adicionarImagem')
            ->assertHasNoErrors()
            ->assertCount('images', 1)
            ->assertSet('images.0.description', '');
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

    /**
     * Swapping an image animation for a text one must carry the scene's text to
     * the key the NEW primitive reads. image-reveal keeps its text in
     * params.caption; underline-sweep reads layer.text. Retyping the layer in
     * place left textTarget on "caption", so the text was saved where the new
     * effect never looked and it rendered empty — its underline alone, a dash in
     * the middle of the frame.
     */
    public function test_swapping_an_image_animation_moves_its_text_to_the_new_primitive(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [
                    ['type' => 'image-reveal', 'text' => '', 'params' => ['src' => 'img_abc', 'caption' => 'The old caption', 'variant' => 'rise']],
                ]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('mudarAnimacao', 0, 'underline-sweep')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $layer = $p->refresh()->plan['scenes'][0]['layers'][0];

        $this->assertSame('underline-sweep', $layer['type']);
        $this->assertSame('The old caption', $layer['text'], 'the text must follow the swap to the key the new primitive reads');
        $this->assertArrayNotHasKey('src', $layer['params'] ?? [], 'a text primitive must not keep the image left by the old one');
        $this->assertArrayNotHasKey('caption', $layer['params'] ?? [], 'the old primitive\'s params must not survive the swap');
    }

    /** Swapping between two image-showing primitives keeps the picture. */
    public function test_swapping_to_image_reveal_keeps_the_attached_image(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'images' => [['id' => 'img_abc', 'path' => 'clips/uploads/a.png', 'description' => '']],
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [
                    ['type' => 'image-reveal', 'text' => '', 'params' => ['src' => 'img_abc', 'caption' => 'A caption', 'variant' => 'rise']],
                ]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('mudarAnimacao', 0, 'image-reveal')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $layer = $p->refresh()->plan['scenes'][0]['layers'][0];

        $this->assertSame('image-reveal', $layer['type']);
        $this->assertSame('img_abc', $layer['params']['src'] ?? null);
    }

    /**
     * The dash. Swapping a scene to a data primitive leaves it with the type but
     * no items/bars/nodes — only the planner writes those, and the scene editor
     * has no field for them, so the primitive drew its em-dash placeholder. The
     * save must hand the scene off to be written from what is spoken in it.
     */
    public function test_swapping_to_a_data_animation_queues_it_to_be_written(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'images' => [['id' => 'img_abc', 'path' => 'clips/uploads/a.png', 'description' => '']],
            'transcript' => ['duration' => 3.0, 'text' => 'Hundreds of jobs go and partner pay falls thirteen per cent.', 'words' => []],
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [
                    ['type' => 'image-reveal', 'text' => '', 'params' => ['src' => 'img_abc', 'caption' => 'A caption', 'variant' => 'rise']],
                ]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('mudarAnimacao', 0, 'timeline')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $layer = $p->refresh()->plan['scenes'][0]['layers'][0];
        $this->assertSame('timeline', $layer['type']);
        // The old effect's params must not ride along — they are what made the
        // primitive look "filled" while holding none of its own data.
        $this->assertArrayNotHasKey('src', $layer['params'] ?? []);
        $this->assertArrayNotHasKey('variant', $layer['params'] ?? []);

        Queue::assertPushed(
            FillChosenVisualsJob::class,
            fn ($job) => $job->projectId === $p->id && ($job->typeByScene[0] ?? null) === 'timeline',
        );
        Queue::assertNotPushed(RenderJob::class); // the fill job renders once it has the data
    }

    /** A scene the editor CAN feed renders straight away — no planner round trip. */
    public function test_swapping_to_a_text_animation_renders_without_replanning(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_DONE,
            'images' => [['id' => 'img_abc', 'path' => 'clips/uploads/a.png', 'description' => '']],
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [
                    ['type' => 'image-reveal', 'text' => '', 'params' => ['src' => 'img_abc', 'caption' => 'The old caption', 'variant' => 'rise']],
                ]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('mudarAnimacao', 0, 'underline-sweep')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $layer = $p->refresh()->plan['scenes'][0]['layers'][0];
        $this->assertSame('underline-sweep', $layer['type']);
        $this->assertSame('The old caption', $layer['text'], 'the text follows the swap to the key the new primitive reads');

        Queue::assertPushed(RenderJob::class);
        Queue::assertNotPushed(FillChosenVisualsJob::class);
    }

    /**
     * With no LLM configured SceneTextVisuals writes nothing, so the chosen
     * primitive stays empty. It must be dropped rather than rendered — an empty
     * timeline IS the dash — and the clip must still go on to render.
     */
    public function test_the_fill_job_drops_what_it_could_not_write_and_still_renders(): void
    {
        Queue::fake();
        config(['contentmachine.clips.driver' => 'fake']); // no LLM → no layers written

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_PLANNING,
            'transcript' => ['duration' => 3.0, 'text' => 'Some spoken words.', 'words' => []],
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'ink', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [
                    ['type' => 'timeline', 'text' => '', 'params' => []],
                ]],
            ]],
        ]);

        app()->call([new FillChosenVisualsJob($p->id, [0 => 'timeline']), 'handle']);

        $types = array_map(fn ($l) => $l['type'], $p->refresh()->plan['scenes'][0]['layers'] ?? []);
        $this->assertNotContains('timeline', $types, 'an unwritten timeline renders the dash — it must be dropped');
        Queue::assertPushed(RenderJob::class);
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

    /** A listing given as video: each block's footage can be picked from the property video, one moment per picture of its effect. */
    public function test_the_footage_of_a_block_is_picked_from_the_property_video(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $make = function (string $path, int $seconds) use ($disk) {
            $disk->makeDirectory(dirname($path));
            exec('ffmpeg -v error -y -f lavfi -i testsrc=size=160x284:rate=30 -t '.$seconds.' -pix_fmt yuv420p '.escapeshellarg($disk->path($path)));
        };
        $make('clips/uploads/test-property.mp4', 12);
        $make('clips/uploads/test-old.mp4', 2);
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'meta' => ['listing_video' => 'clips/uploads/test-property.mp4', 'listing' => ['voiceover' => 'x']],
            'images' => [['id' => 'vid_old', 'path' => 'clips/uploads/test-old.mp4', 'video' => true, 'description' => 'Sala']],
            'plan' => ['duration' => 6, 'scenes' => [
                ['start' => 0, 'end' => 3, 'footage' => true, 'layers' => [['type' => 'video-intro', 'text' => '', 'params' => ['src' => 'vid_old']]]],
                ['start' => 3, 'end' => 6, 'layers' => [['type' => 'leque-imagens', 'text' => '', 'params' => ['src' => 'vid_old', 'src2' => 'vid_old']]]],
            ]],
        ]);

        $lw = Livewire::test(ClipsAnimados::class)->call('editarClip', $p->id)->assertSet('listingVideoLength', fn ($l) => abs($l - 12) < 0.1);
        $this->assertCount(2, $lw->instance()->videoSlots(1));   // the fan shows two clips: two moments

        $lw->call('abrirMomentos', 1)->assertSee('Escolher do vídeo do imóvel')
            ->call('usarMomentos', 1, [['start' => 2.0, 'end' => 3.0], ['start' => 7.0, 'end' => 10.0]])
            ->assertHasNoErrors()
            ->call('guardarPlano');

        $p = $this->store()->findOrFail($p->id);
        $params = $p->plan['scenes'][1]['layers'][0]['params'];
        $byId = collect($p->images)->keyBy('id');
        $this->assertEquals([2.0, 5.0], $byId[$params['src']]['source']);    // 1 s marked, run on to the block's 3 s
        $this->assertEquals([7.0, 10.0], $byId[$params['src2']]['source']);
        $this->assertSame('vid_old', $p->plan['scenes'][0]['layers'][0]['params']['src']);   // the other block untouched
        $this->assertEqualsWithDelta(3.0, PropertyVideoAnalyzer::probe($disk->path($byId[$params['src']]['path']))['duration'], 0.1);
        Queue::assertPushed(RenderJob::class);
    }

    /** "Improve with AI" on a card: the request is kept and the rework job runs — no picture is generated. */
    public function test_improve_with_ai_on_a_card_reworks_the_clip(): void
    {
        Queue::fake();
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE, 'output_path' => '/tmp/x.mp4',
            'plan' => ['duration' => 3, 'scenes' => [['start' => 0, 'end' => 3, 'layers' => [['type' => 'card', 'text' => 'x', 'params' => []]]]]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('abrirMelhoriaClip', $p->id)->assertSee('Não é gerada nenhuma imagem')
            ->set('improveCardPrompt', 'Fala da barragem aqui perto')
            ->call('melhorarClip')->assertHasNoErrors();

        $p = $this->store()->findOrFail($p->id);
        $this->assertSame(['Fala da barragem aqui perto', ClipRecord::STATUS_IMPROVING], [$p->meta['improve_request'], $p->status]);
        Queue::assertPushed(ImproveClipJob::class);
    }

    /** The job: the script rewritten as asked (the after-intro line kept) and spoken again; the request directs the planner. */
    public function test_the_improve_job_rewrites_the_script_and_directs_the_plan(): void
    {
        Queue::fake();
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_IMPROVING,
            'transcript' => ['text' => 'Quinta em Montargil. Se queres vender casa, fala com o Nuno. Tem piscina.', 'words' => []],
            'meta' => ['listing' => ['title' => 'Quinta'], 'after_intro' => 'clips/uploads/a.mp4', 'after_intro_line' => 'Se queres vender casa, fala com o Nuno.',
                'instructions' => "Use the photos.\n\nTHE USER ASKED FOR THIS VERSION: «older request»", 'improve_request' => 'Fala da barragem'],
        ]);
        $this->mock(ClipReviser::class)->shouldReceive('revise')
            ->withArgs(fn ($script, $req, $ctx, $canRewrite, $keep) => $req === 'Fala da barragem' && $canRewrite && $keep === 'Se queres vender casa, fala com o Nuno.')
            ->andReturn(['voiceover' => 'Quinta em Montargil, perto da barragem. Tem piscina.', 'direction' => 'Show the water and the outside more.']);

        app()->call([new ImproveClipJob($p->id), 'handle']);

        Queue::assertPushed(RegenerateVoiceoverJob::class, fn ($job) => $job->replan
            && $job->text === 'Quinta em Montargil, perto da barragem. Se queres vender casa, fala com o Nuno. Tem piscina.');
        $instructions = $this->store()->findOrFail($p->id)->meta['instructions'];
        $this->assertStringStartsWith('Use the photos.', $instructions);
        $this->assertStringContainsString('Show the water and the outside more.', $instructions);
        $this->assertStringNotContainsString('older request', $instructions);   // the latest request replaces an earlier one
    }

    /** A recorded voice can't change: only the photos, footage, timing and effects are planned again. */
    public function test_improving_a_recorded_clip_only_replans(): void
    {
        Queue::fake();
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_IMPROVING,
            'transcript' => ['text' => 'Olá.', 'words' => []], 'meta' => ['improve_request' => 'cortes mais rápidos'],
        ]);
        $this->mock(ClipReviser::class)->shouldReceive('revise')
            ->withArgs(fn ($s, $r, $c, $canRewrite) => $canRewrite === false)->andReturn(['voiceover' => '', 'direction' => 'Faster cuts.']);

        app()->call([new ImproveClipJob($p->id), 'handle']);

        Queue::assertPushed(PlanAnimationsJob::class);
        Queue::assertNotPushed(RegenerateVoiceoverJob::class);
    }

    /** "Re-render the ending": the closing card gets the inputs it gained (the AMI) — never another agent's details — and only it renders. */
    public function test_rerender_the_ending_fills_new_inputs_and_renders_only_the_last_scene(): void
    {
        Queue::fake();
        app(EffectStore::class)->create(['slug' => 'agente-revelacao', 'status' => 'active', 'tsx' => 'x', 'prompt' => 'x',
            'sample_params' => ['name' => 'Manuel Ferreira', 'phone' => '+351 963 050 290', 'ami' => '11220']]);
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE, 'output_path' => '/tmp/clip.mp4',
            'plan' => ['duration' => 10, 'scenes' => [
                ['start' => 0, 'end' => 6.5, 'layers' => [['type' => 'card', 'text' => 'x', 'params' => []]]],
                ['start' => 6.5, 'end' => 10, 'layers' => [['type' => 'agente-revelacao', 'text' => '', 'params' => ['name' => 'Nuno Narciso']]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)->call('rerenderFinal', $p->id);

        $params = $this->store()->findOrFail($p->id)->plan['scenes'][1]['layers'][0]['params'];
        $this->assertSame(['name' => 'Nuno Narciso', 'ami' => '11220'], $params);   // the AMI added; not the sample agent's phone
        Queue::assertPushed(RenderJob::class, fn ($job) => $job->from === 6.5);
    }

    /** The moment picker's footage and filmstrip are made from the property video on first use. */
    public function test_the_property_video_is_served_to_the_moment_picker(): void
    {
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $disk->makeDirectory('clips/uploads');
        exec('ffmpeg -v error -y -f lavfi -i testsrc=size=160x284:rate=30 -t 4 -pix_fmt yuv420p '.escapeshellarg($disk->path('clips/uploads/test-prop.mp4')));
        $p = $this->store()->create(['type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'meta' => ['listing_video' => 'clips/uploads/test-prop.mp4']]);

        $this->comSessaoIniciada()->get(route('clips-animados.listing-video', ['id' => $p->id, 'what' => 'strip']))->assertOk();
        $this->comSessaoIniciada()->get(route('clips-animados.listing-video', ['id' => $p->id, 'what' => 'footage']))->assertOk();
        $this->assertTrue($disk->exists("clips/listing-preview/{$p->id}.mp4"));
    }

    private function listingClip(): ClipRecord
    {
        Storage::fake(config('contentmachine.clips.disk'))->put('clips/uploads/old-portrait.jpg', 'x');

        return $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'transcript' => ['duration' => 4.0, 'text' => 'Casa em Mafra. Se queres vender casa, fala com o Nuno. Tem vista.', 'words' => [], 'segments' => []],
            'images' => [['id' => 'img_old', 'path' => 'clips/uploads/old-portrait.jpg', 'description' => 'Retrato do consultor Nuno, da IAD Portugal.']],
            'plan' => ['duration' => 4, 'scenes' => [['start' => 0, 'end' => 4, 'layers' => [['type' => 'agent-card', 'text' => '', 'params' => ['src' => 'img_old']]]]]],
            'meta' => ['listing' => ['url' => 'https://www.iadportugal.pt/anuncio/x/r1', 'agent' => 'Nuno', 'gender' => 'm', 'agency' => 'IAD Portugal'],
                'after_intro_line' => 'Se queres vender casa, fala com o Nuno', 'script_notes' => 'Destacar a vista.', 'mix_photos' => false],
        ]);
    }

    /** Editing a listing clip offers the "From a listing" options, filled in as the clip was made. */
    public function test_the_transcript_editor_has_the_listing_options_prefilled(): void
    {
        $p = $this->listingClip();

        Livewire::test(ClipsAnimados::class)->call('editarTranscricao', $p->id)
            ->assertSet('editListing', true)
            ->assertSet('editListingUrl', 'https://www.iadportugal.pt/anuncio/x/r1')
            ->assertSet('editAfterIntroLine', 'Se queres vender casa, fala com o Nuno')
            ->assertSet('editListingNotes', 'Destacar a vista.')
            ->assertSet('editMixPhotos', false)
            ->assertSee('Link do anúncio')->assertSee('Frase após a intro')->assertSee('Notas para o guião')->assertSee('já tem; carregue para substituir');
    }

    /** New notes: the script is written again from the listing — a fresh import. */
    public function test_new_notes_rebuild_the_clip_from_the_listing(): void
    {
        Queue::fake();
        $p = $this->listingClip();

        Livewire::test(ClipsAnimados::class)->call('editarTranscricao', $p->id)
            ->set('editListingNotes', 'O preço baixou para 300.000 €.')
            ->call('regenerar')->assertHasNoErrors();

        $p = $this->store()->findOrFail($p->id);
        $this->assertSame([ClipRecord::STATUS_IMPORTING, 'O preço baixou para 300.000 €.'], [$p->status, $p->meta['script_notes']]);
        Queue::assertPushed(ImportListingJob::class);
        Queue::assertNotPushed(RegenerateVoiceoverJob::class);
    }

    /** A new after-intro line takes the old one's place in the transcript, and is spoken. */
    public function test_a_new_after_intro_line_is_spoken_in_place_of_the_old(): void
    {
        Queue::fake();
        $p = $this->listingClip();

        Livewire::test(ClipsAnimados::class)->call('editarTranscricao', $p->id)
            ->set('editAfterIntroLine', 'Se queres vender ou comprar casa, fala com o Nuno')
            ->call('regenerar')->assertHasNoErrors();

        Queue::assertPushed(RegenerateVoiceoverJob::class,
            fn ($job) => $job->text === 'Casa em Mafra. Se queres vender ou comprar casa, fala com o Nuno. Tem vista.' && $job->replan);
        Queue::assertNotPushed(ImportListingJob::class);
    }

    /** Only a new agent photo: swapped in (scenes follow it), the clip re-planned, no new voiceover. */
    public function test_a_new_agent_photo_is_swapped_in(): void
    {
        Queue::fake();
        $p = $this->listingClip();

        Livewire::test(ClipsAnimados::class)->call('editarTranscricao', $p->id)
            ->set('editAgentPhoto', UploadedFile::fake()->image('nuno.jpg'))
            ->call('regenerar')->assertHasNoErrors();

        $p = $this->store()->findOrFail($p->id);
        $portrait = collect($p->images)->first(fn ($i) => str_starts_with($i['description'], 'Retrato'));
        $this->assertNotSame('img_old', $portrait['id']);
        $this->assertSame($portrait['id'], $p->plan['scenes'][0]['layers'][0]['params']['src']);
        $this->assertCount(1, $p->images);
        Queue::assertPushed(PlanAnimationsJob::class);
        Queue::assertNotPushed(RegenerateVoiceoverJob::class);
    }

    /** A generated voice (a listing) says the edited transcript: spoken again, then planned anew. */
    public function test_editing_a_listing_transcript_regenerates_the_voiceover(): void
    {
        Queue::fake();
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipRecord::STATUS_DONE,
            'transcript' => ['duration' => 2.0, 'text' => 'Fala com o Nuno', 'words' => [['word' => 'Fala', 'start' => 0.0, 'end' => 2.0]], 'segments' => []],
            'meta' => ['listing' => ['voiceover' => 'Fala com o Nuno', 'gender' => 'm']],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarTranscricao', $p->id)
            ->assertSet('editingSpokenAgain', true)
            ->set('editTranscriptText', 'Fala com o Nuno Narciso')
            ->call('regenerar')
            ->assertHasNoErrors();

        Queue::assertPushed(RegenerateVoiceoverJob::class, fn ($job) => $job->text === 'Fala com o Nuno Narciso' && $job->replan);
        Queue::assertNotPushed(PlanAnimationsJob::class);   // the voice job plans, on the new audio
        $this->assertSame(ClipRecord::STATUS_TRANSCRIBING, $p->refresh()->status);
    }

    /** The transcript editor shows the clip's options, prefilled, and saves the changes before re-planning. */
    public function test_edit_transcript_prefills_the_clip_options_and_saves_them_on_regenerate(): void
    {
        Queue::fake();

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY, 'input_kind' => 'video',
            'status' => ClipRecord::STATUS_DONE,
            'transcript' => ['duration' => 2.0, 'text' => 'Marquina', 'words' => [['word' => 'Marquina', 'start' => 0.0, 'end' => 2.0]], 'segments' => []],
            'meta' => ['allowed_present' => ['split'], 'formato' => '9:16', 'background' => 'auto', 'lead' => 'The lead', 'instructions' => 'Be bold', 'fewer_images' => true, 'free_sfx' => false, 'musica' => 'faixa.mp3', 'musica_volume' => 0.25],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarTranscricao', $p->id)
            ->assertSet('editOverlay', true)
            ->assertSet('allowedPresents', ['split'])
            ->assertSet('formato', '9:16')
            ->assertSet('lead', 'The lead')
            ->assertSet('instructions', 'Be bold')
            ->assertSet('poucasImagens', true)
            ->assertSet('musica', 'faixa.mp3')
            ->set('formato', '1:1')
            ->set('allowedPresents', ['over', 'animation'])
            ->set('freeSfx', true)
            ->call('regenerar')
            ->assertHasNoErrors();

        $p->refresh();
        $meta = $p->meta;
        $this->assertSame('1:1', $meta['formato']);
        $this->assertSame(['over', 'animation'], $meta['allowed_present']);
        $this->assertTrue($meta['free_sfx']);
        $this->assertSame('faixa.mp3', $meta['musica'], 'untouched choices survive');
        Queue::assertPushed(PlanAnimationsJob::class);
    }

    public function test_regenerate_needs_at_least_one_style_on_an_overlay_clip(): void
    {
        Queue::fake();
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY, 'input_kind' => 'video', 'status' => ClipRecord::STATUS_DONE,
            'transcript' => ['duration' => 2.0, 'text' => 'x', 'words' => [], 'segments' => []],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarTranscricao', $p->id)
            ->set('allowedPresents', [])
            ->call('regenerar')
            ->assertHasErrors('allowedPresents');
        Queue::assertNothingPushed();
    }

    public function test_music_choice_persists_to_meta_on_create(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('text', 'Olá mundo Brand Machine')
            ->set('musica', 'faixa.mp3')
            ->set('musicaVolume', 0.25)
            ->set('legendas', 'top')
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $meta = $this->store()->all()->firstWhere('type', 'animation')->meta;
        $this->assertSame('faixa.mp3', $meta['musica']);
        $this->assertSame('top', $meta['captions']);
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
        $this->assertSame('bottom', $renderer->props['captions']);   // default placement

        $top = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['captions' => 'none']]);
        $this->app->call([new RenderJob($top->id), 'handle']);
        $this->assertSame('none', $renderer->props['captions']);

        File::deleteDirectory($dir);
    }

    /**
     * "Send straight to Finished" sets the same `finished` flag the promote button
     * does, at the end of the render — so the clip is in the Finished hub (what
     * FinishedContent actually lists) without anyone clicking anything.
     */
    public function test_a_clip_marked_for_finished_lands_there_when_it_renders(): void
    {
        $this->app->instance(RemotionRenderer::class, new class implements RemotionRenderer
        {
            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'X');

                return $outPath;
            }
        });

        $plan = ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => []];

        $auto = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['auto_finish' => true]]);
        $manual = $this->store()->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => []]);

        $this->app->call([new RenderJob($auto->id), 'handle']);
        $this->app->call([new RenderJob($manual->id), 'handle']);

        $this->assertTrue((bool) $auto->refresh()->get('finished'));
        $this->assertFalse((bool) $manual->refresh()->get('finished'), 'without the option it still waits for the promote click');

        // The hub itself, not just the flag — FinishedContent is what the UI reads.
        $naHub = app(FinishedContent::class)->animated()->pluck('id')->all();
        $this->assertContains($auto->id, $naHub);
        $this->assertNotContains($manual->id, $naHub);
    }

    /** The option rides on the clip from the create form, for both clip types. */
    public function test_the_finished_option_is_stored_on_the_clip(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')->call('escolherTipo', 'animation')
            ->set('text', 'Uma história da imprensa escrita para o clip.')
            ->set('paraFinalizados', true)
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertTrue($this->store()->all()->firstWhere('type', 'animation')->meta['auto_finish']);
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

    /**
     * The whole point of the per-clip box: what the user typed must reach the
     * planner. A silent break here means the instructions are simply ignored.
     */
    public function test_the_clips_instructions_reach_the_planner(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;

                return parent::plan($transcript, $mode, $options);
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
            'meta' => ['instructions' => 'Dark and cinematic. No charts.'],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $this->assertSame('Dark and cinematic. No charts.', $planner->vistas['instructions'] ?? null);
    }

    /** "Reduce the number of images" is offered on BOTH create forms and rides on the clip. */
    public function test_reduce_images_is_stored_from_both_create_forms(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo Brand Machine')
            ->set('poucasImagens', true)
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertTrue($this->store()->all()->firstWhere('type', 'animation')->meta['fewer_images']);

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'overlay')
            ->set('video', UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'))
            ->set('poucasImagens', true)
            ->call('submitOverlay')
            ->assertHasNoErrors();

        $this->assertTrue($this->store()->all()->firstWhere('type', 'overlay')->meta['fewer_images']);
    }

    /** The flag halves the clip's image budget — and caps overlay clips, which are otherwise uncapped. */
    public function test_reduce_images_halves_the_image_budget(): void
    {
        Queue::fake();
        // Images need a configured generator; pin one instead of depending on a kie.ai key on the machine.
        $this->app->instance(\App\Services\Clips\ClipImageGenerator::class, new class extends \App\Services\Clips\ClipImageGenerator
        {
            public function __construct() {}

            public function configured(): bool
            {
                return true;
            }
        });
        config(['contentmachine.clips.image_every_seconds' => 10, 'contentmachine.clips.image_max' => 8]);

        $planner = new class extends FakeAnimationPlanner
        {
            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $plan = parent::plan($transcript, $mode, $options);
                $plan['duration'] = 60.0;
                $plan['scenes'] = [];
                for ($i = 0; $i < 6; $i++) {
                    $plan['scenes'][] = [
                        'start' => $i * 10.0, 'end' => ($i + 1) * 10.0, 'transitionIn' => 'cut',
                        'layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'a laptop on a desk '.$i]]],
                    ];
                }

                return $plan;
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $transcript = ['duration' => 60.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []];

        $full = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'transcript' => $transcript,
        ]);
        $this->app->call([new PlanAnimationsJob($full->id), 'handle']);

        $few = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio', 'transcript' => $transcript,
            'meta' => ['fewer_images' => true],
        ]);
        $this->app->call([new PlanAnimationsJob($few->id), 'handle']);

        $contar = fn (ClipRecord $p) => collect($p->refresh()->plan['scenes'] ?? [])
            ->sum(fn (array $s) => collect($s['layers'] ?? [])
                ->filter(fn (array $l) => ! empty($l['params']['generate']) && empty($l['params']['src']))
                ->count());

        $this->assertSame(6, $contar($full), '60s / 10s per image = 6');
        $this->assertSame(3, $contar($few), 'halved');
    }

    /**
     * An overlay clip is composed AROUND the uploaded video, so the composition must
     * take the source's aspect ratio — a fixed 9:16 turned a landscape upload into a
     * cropped portrait. Oversized sources are scaled down to the configured long edge.
     */
    public function test_an_overlay_clip_is_planned_at_the_source_videos_aspect_ratio(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;

                return parent::plan($transcript, $mode, $options);
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $this->app->instance(LocalVideoEngine::class, new class extends LocalVideoEngine
        {
            public function probe(string $path): array
            {
                return ['duration' => 2.0, 'width' => 3840, 'height' => 2160, 'has_audio' => true];
            }
        });

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_OVERLAY, 'input_kind' => 'video',
            'source_path' => 'clips/uploads/landscape.mp4',
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        // 3840x2160 capped at the configured long edge (1920) → 1920x1080, both even.
        $this->assertSame(1920, $planner->vistas['width'] ?? null);
        $this->assertSame(1080, $planner->vistas['height'] ?? null);
        $this->assertSame(1920, $p->refresh()->plan['width'] ?? null);
    }

    /**
     * "No images": the clip is planned with no image layer at all. The planner is not
     * offered image-reveal (nor the clip's own uploads), whatever it returns anyway is
     * stripped, and nothing is collected — so the clip never stops to ask for pictures.
     */
    public function test_no_images_plans_without_any_image_layer(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;
                $plan = parent::plan($transcript, $mode, $options);
                // The planner disobeys: every scene asks for a generated image and a
                // filmed page. None of it may survive.
                $plan['scenes'] = [[
                    'start' => 0.0, 'end' => 2.0, 'transitionIn' => 'cut',
                    'layers' => [
                        ['type' => 'image-reveal', 'params' => ['generate' => 'a laptop on a desk']],
                        ['type' => 'image-reveal', 'params' => ['site' => 'https://example.com']],
                    ],
                ]];

                return $plan;
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'images' => [['id' => 'img1', 'path' => 'clips/images/img1.png']],
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
            'meta' => ['no_images' => true],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $this->assertNotContains('image-reveal', $planner->vistas['layers'] ?? [], 'the planner must not be offered the image layer');
        $this->assertFalse($planner->vistas['can_generate_images'] ?? true);
        $this->assertSame([], $planner->vistas['images'] ?? null, 'uploads are not offered either');

        $p = $p->refresh();
        $camadas = collect($p->plan['scenes'] ?? [])->flatMap(fn (array $s) => array_column($s['layers'] ?? [], 'type'));
        $this->assertNotContains('image-reveal', $camadas->all(), 'image layers must be stripped from the plan');
        $this->assertNotSame(ClipRecord::STATUS_COLLECTING, $p->status, 'the clip must not stop to ask for images');
        $this->assertSame([], $p->meta['image_requests'] ?? [], 'nothing to gather');
    }

    /**
     * "Only reference images": the clip is built from the uploads alone. Undescribed
     * ones (bulk upload) are described before planning, the planner is told to use
     * every one, and nothing is generated, fetched or asked for — even when it asks.
     */
    public function test_only_reference_images_uses_the_uploads_and_asks_for_nothing(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;
                $plan = parent::plan($transcript, $mode, $options);
                $plan['scenes'] = [[
                    'start' => 0.0, 'end' => 2.0, 'transitionIn' => 'cut',
                    'layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'a modern kitchen']]],
                ]];

                return $plan;
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);
        $this->app->instance(ImageDescriber::class, new class extends ImageDescriber
        {
            public function describe(array $images, string $context = ''): array
            {
                return array_map(
                    fn ($i) => ($i['description'] ?? '') === '' ? array_merge($i, ['description' => 'kitchen with island']) : $i,
                    $images,
                );
            }
        });

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'images' => [
                ['id' => 'img1', 'path' => 'clips/images/img1.png', 'description' => ''],
                ['id' => 'img2', 'path' => 'clips/images/img2.png', 'description' => 'the agent'],
            ],
            'transcript' => ['duration' => 2.0, 'text' => 'T3 em Alvalade', 'words' => [], 'segments' => []],
            'meta' => ['only_reference_images' => true],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $this->assertFalse($planner->vistas['can_generate_images'] ?? true);
        $this->assertStringContainsString('ONLY REFERENCE IMAGES', $planner->vistas['instructions'] ?? '');
        $this->assertSame(['kitchen with island', 'the agent'], array_column($planner->vistas['images'] ?? [], 'description'));

        $p = $p->refresh();
        $this->assertNotSame(ClipRecord::STATUS_COLLECTING, $p->status, 'the clip must not stop to ask for images');
        $this->assertSame([], $p->meta['image_requests'] ?? []);
    }

    /** Only reference images with nothing attached is refused up front (the planner would invent names). */
    public function test_only_reference_images_needs_an_image(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')
            ->set('text', 'Moradia em Vouzela')
            ->set('soReferencias', true)
            ->call('submitAnimation')
            ->assertHasErrors('newImages.*');

        $this->assertCount(0, $this->store()->all());
    }

    /** A full listing fits in the clip instructions (the old 2000 cap cut it mid-sentence). */
    public function test_long_instructions_are_kept_and_the_cap_is_ten_thousand(): void
    {
        $longo = str_repeat('Quinta com 9 hectares. ', 300); // ~6.9k chars

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')->set('text', 'Moradia')->set('instructions', $longo)
            ->call('submitAnimation')
            ->assertHasNoErrors();
        $this->assertSame(trim($longo), $this->store()->all()->sole()->meta['instructions']);

        Livewire::test(ClipsAnimados::class)
            ->set('createType', 'animation')->set('text', 'Moradia')->set('instructions', str_repeat('x', 10001))
            ->call('submitAnimation')
            ->assertHasErrors('instructions');
    }

    public function test_bulk_upload_adds_every_file_without_a_description(): void
    {
        Storage::fake(config('contentmachine.clips.disk'));

        Livewire::test(ClipsAnimados::class)
            ->set('newImages', [UploadedFile::fake()->image('sala.jpg'), UploadedFile::fake()->image('cozinha.jpg')])
            ->assertHasNoErrors()
            ->assertCount('images', 2)
            ->assertSet('images.0.description', '')
            ->assertSet('images.0.name', 'sala.jpg')
            ->assertSet('newImages', []);
    }

    /** Without the flag the same planner output keeps its image layers. */
    public function test_the_image_layer_is_offered_by_default(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;

                return parent::plan($transcript, $mode, $options);
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $this->assertContains('image-reveal', $planner->vistas['layers'] ?? []);
    }

    /** No source video → the configured portrait frame, as before. */
    public function test_an_animation_clip_keeps_the_configured_frame(): void
    {
        Queue::fake();

        $planner = new class extends FakeAnimationPlanner
        {
            public array $vistas = [];

            public function plan(array $transcript, string $mode, array $options = []): array
            {
                $this->vistas = $options;

                return parent::plan($transcript, $mode, $options);
            }
        };
        $this->app->instance(AnimationPlanner::class, $planner);

        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION, 'input_kind' => 'audio',
            'transcript' => ['duration' => 2.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
        ]);

        $this->app->call([new PlanAnimationsJob($p->id), 'handle']);

        $this->assertSame(config('contentmachine.clips.width'), $planner->vistas['width'] ?? null);
        $this->assertSame(config('contentmachine.clips.height'), $planner->vistas['height'] ?? null);
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
