<?php

namespace Tests\Feature\Clips;

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

    public function test_page_still_renders_its_title(): void
    {
        $this->get('/clips-animados')->assertOk()->assertSee('Clips Animados');
    }

    public function test_creates_an_animation_project_and_dispatches_pipeline(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->set('activeTab', 'animation')
            ->set('text', 'Olá mundo IATECA')
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertSame(1, ClipProject::where('type', 'animation')->count());
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_requires_a_video_for_the_overlay_tab(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->set('activeTab', 'overlay')
            ->call('submitOverlay')
            ->assertHasErrors('video');
    }

    public function test_creates_an_overlay_project_from_an_uploaded_video(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(ClipsAnimados::class)
            ->set('activeTab', 'overlay')
            ->set('video', UploadedFile::fake()->create('peca.mp4', 200, 'video/mp4'))
            ->call('submitOverlay')
            ->assertHasNoErrors();

        $this->assertSame(1, ClipProject::where('type', 'overlay')->count());
        Queue::assertPushed(TranscribeJob::class);
    }
}
