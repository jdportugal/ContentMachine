<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\RenderJob;
use App\Jobs\Clips\TranscribeJob;
use App\Livewire\ClipsAnimados;
use App\Models\ClipProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ClipsAnimadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_title(): void
    {
        $this->get('/clips-animados')->assertOk()->assertSee('Clips Animados')->assertSee('Novo clip');
    }

    public function test_create_flow_makes_an_animation_project(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo IATECA')
            ->call('submitAnimation')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $this->assertSame(1, ClipProject::where('type', 'animation')->count());
        Queue::assertPushed(TranscribeJob::class);
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

        $this->assertSame('audio', ClipProject::where('type', 'animation')->first()->input_kind);
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_adds_images_with_descriptions_and_saves_them(): void
    {
        Queue::fake();
        Storage::fake('local');

        $component = Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('newImage', UploadedFile::fake()->image('logo.png', 120, 120))
            ->set('newImageDesc', 'logótipo da empresa')
            ->call('adicionarImagem')
            ->assertHasNoErrors()
            ->assertSet('images', fn ($v) => count($v) === 1)
            ->set('text', 'Olá mundo IATECA')
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $p = ClipProject::where('type', 'animation')->first();
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

        $this->assertSame(1, ClipProject::where('type', 'overlay')->count());
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_edit_plan_validates_and_dispatches_render(): void
    {
        Queue::fake();

        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipProject::STATUS_DONE,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => false, 'punchWord' => null, 'layers' => [['type' => 'timeline', 'text' => null, 'params' => ['caption' => 'Modelos', 'items' => [['label' => 'A']]]]]],
            ]],
        ]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('view', 'editPlan')
            ->assertSet('editScenes.0.layerText', 'Modelos')   // timeline caption is the editable text
            ->set('editScenes.0.background', 'ink')
            ->set('editScenes.0.punchWord', 'ÊNFASE')
            ->set('editScenes.0.layerText', 'Cronologia')
            ->call('guardarPlano')
            ->assertHasNoErrors()
            ->assertSet('view', 'dashboard');

        $p->refresh();
        $this->assertSame(ClipProject::STATUS_RENDERING, $p->status);
        $this->assertSame('ink', $p->plan['scenes'][0]['background']);
        $this->assertSame('ÊNFASE', $p->plan['scenes'][0]['punchWord']);
        // the visual layer survives the edit and its text was updated
        $this->assertSame('timeline', $p->plan['scenes'][0]['layers'][0]['type']);
        $this->assertSame('Cronologia', $p->plan['scenes'][0]['layers'][0]['params']['caption']);
        Queue::assertPushed(RenderJob::class);
    }

    public function test_edit_raw_json_saves_plan_verbatim_and_renders(): void
    {
        Queue::fake();

        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION, 'input_kind' => 'audio', 'status' => ClipProject::STATUS_DONE,
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
        $this->assertSame(ClipProject::STATUS_RENDERING, $p->status);
        $this->assertEquals(5.0, $p->plan['duration']);
        $this->assertSame('pie-chart', $p->plan['scenes'][0]['layers'][0]['type']); // verbatim, not capped/normalized
        Queue::assertPushed(RenderJob::class);
    }

    public function test_edit_raw_json_rejects_invalid_json(): void
    {
        $p = ClipProject::create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => ['duration' => 3.0, 'scenes' => []]]);

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editMode', 'json')
            ->set('editPlanJson', '{ not valid json ')
            ->call('guardarJson')
            ->assertHasErrors('editPlanJson');
    }

    public function test_edit_plan_rejects_end_before_start(): void
    {
        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION, 'input_kind' => 'audio',
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

        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION, 'input_kind' => 'audio',
            'status' => ClipProject::STATUS_DONE,
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
        $this->assertSame(ClipProject::STATUS_PLANNING, $p->status);
        Queue::assertPushed(PlanAnimationsJob::class);
    }

    public function test_delete_removes_the_project(): void
    {
        $p = ClipProject::create(['type' => 'animation', 'input_kind' => 'text', 'source_text' => 'x']);

        Livewire::test(ClipsAnimados::class)->call('apagar', $p->id);

        $this->assertDatabaseMissing('clip_projects', ['id' => $p->id]);
    }
}
