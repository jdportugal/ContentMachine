<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\PlanAnimationsJob;
use App\Jobs\Clips\TranscribeJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\MetadataService;
use App\Services\Clips\Contracts\ResearchService;
use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\VfxStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A video-with-animations clip is composed at the uploaded video's own shape,
 * which is right when you have no opinion and wrong when you are making a clip
 * for a placement with a fixed frame. The create form picks the output format.
 */
class OutputFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
    }

    private function criar(string $formato): ClipRecord
    {
        Queue::fake();
        Storage::fake(config('contentmachine.clips.disk'));

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'overlay')
            ->set('video', UploadedFile::fake()->create('talk.mp4', 120, 'video/mp4'))
            ->set('formato', $formato)
            ->call('submitOverlay')
            ->assertHasNoErrors();

        return app(ClipStore::class)->all()->first();
    }

    public function test_the_chosen_format_is_recorded_on_the_clip(): void
    {
        $this->assertSame('16:9', $this->criar('16:9')->meta['formato']);
        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_auto_is_the_default(): void
    {
        Queue::fake();
        Storage::fake(config('contentmachine.clips.disk'));

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'overlay')
            ->assertSet('formato', 'auto');
    }

    public function test_a_format_outside_the_presets_is_rejected(): void
    {
        Queue::fake();
        Storage::fake(config('contentmachine.clips.disk'));

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'overlay')
            ->set('video', UploadedFile::fake()->create('talk.mp4', 120, 'video/mp4'))
            ->set('formato', '21:9')
            ->call('submitOverlay')
            ->assertHasErrors('formato');

        $this->assertCount(0, app(ClipStore::class)->all());
    }

    /** Runs the planner job with whatever the container has bound. */
    private function correr(string $id): void
    {
        app(PlanAnimationsJob::class, ['projectId' => $id])->handle(
            app(AnimationPlanner::class),
            app(PlanValidator::class),
            app(ResearchService::class),
            app(MetadataService::class),
            app(ClipStore::class),
        );
    }

    /**
     * @return array<string,array{0:string,1:int,2:int}>
     */
    public static function formatos(): array
    {
        $out = [];
        foreach (VfxStore::ASPECTS as $chave => $a) {
            $out[$chave] = [$chave, $a['width'], $a['height']];
        }

        return $out;
    }

    #[DataProvider('formatos')]
    public function test_the_plan_is_composed_at_the_chosen_frame(string $formato, int $w, int $h): void
    {
        $this->app->bind(AnimationPlanner::class, fn () => new FakeAnimationPlanner);

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/nope.mp4', // unprobeable on purpose
            'transcript' => ['text' => 'hello there', 'duration' => 4.0, 'words' => []],
            'meta' => ['formato' => $formato],
        ]);

        $this->correr($p->id);

        $p->refresh();
        $this->assertSame($w, (int) $p->plan['width']);
        $this->assertSame($h, (int) $p->plan['height']);
    }

    public function test_auto_still_composes_at_the_sources_own_shape(): void
    {
        $this->app->bind(AnimationPlanner::class, fn () => new FakeAnimationPlanner);

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/nope.mp4',
            'transcript' => ['text' => 'hello there', 'duration' => 4.0, 'words' => []],
            'meta' => ['formato' => 'auto'],
        ]);

        $this->correr($p->id);

        // Unprobeable source → the configured size, exactly as before the option.
        $c = config('contentmachine.clips');
        $p->refresh();
        $this->assertSame((int) $c['width'], (int) $p->plan['width']);
        $this->assertSame((int) $c['height'], (int) $p->plan['height']);
    }
}
