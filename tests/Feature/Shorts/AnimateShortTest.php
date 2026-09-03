<?php

namespace Tests\Feature\Shorts;

use App\Jobs\Clips\TranscribeJob;
use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Shorts\LocalVideoEngine;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\FakeVideoEngine;
use Tests\TestCase;

/**
 * "Animate" stages a cut clip and opens the normal "video + animations" form with
 * it attached. It must stage the RAW cut: a short with captions already burnt in
 * put two sets on screen, the animation's own plus the short's, in two styles.
 */
class AnimateShortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
        // No ffmpeg in tests: the fake writes stand-in files (see ClipsTest).
        $this->app->singleton(LocalVideoEngine::class, fn () => new FakeVideoEngine);
    }

    /** A clip that has been cut AND rendered — both files on disk. */
    private function clipPronto(): array
    {
        $dir = storage_path('app/shorts/animar-'.bin2hex(random_bytes(3)));
        @mkdir($dir, 0775, true);
        file_put_contents("$dir/raw.mp4", 'RAWCUT');
        file_put_contents("$dir/final.mp4", 'FINAL');

        $vault = app(VaultContract::class);
        $fonte = $vault->create(ShortsPipeline::FONTES, [
            'titulo' => 'Interview', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4', 'estado' => 'transcrita',
        ], 'Source.');

        $clip = $vault->create(ShortsPipeline::CLIPES, [
            'titulo' => 'A short worth animating', 'tipo' => 'clip', 'fonte_path' => $fonte->path,
            'inicio' => 0, 'fim' => 40, 'estado' => 'pronto',
            'clip_path' => "$dir/raw.mp4", 'output_path' => "$dir/final.mp4",
        ], 'A clip.');

        return [$clip, $dir, $fonte];
    }

    public function test_animating_stages_the_clip_and_generates_nothing_yet(): void
    {
        Queue::fake();
        [$clip] = $this->clipPronto();

        Livewire::test(Clips::class)
            ->call('animar', $clip->path)
            ->assertRedirect(route('clips-animados'));

        // Nothing is created until the options form is submitted.
        $this->assertCount(0, app(ClipStore::class)->all());
        Queue::assertNotPushed(TranscribeJob::class);
        $this->assertSame('A short worth animating', session('animado_video_nome'));
        $this->assertNotNull(session('animado_video'));
    }

    public function test_the_staged_clip_opens_the_normal_options_form(): void
    {
        Queue::fake();
        [$clip] = $this->clipPronto();

        Livewire::test(Clips::class)->call('animar', $clip->path);

        Livewire::test(ClipsAnimados::class)
            ->assertSet('view', 'create')
            ->assertSet('createType', 'overlay')
            ->assertSet('videoProntoNome', 'A short worth animating')
            // The options an uploaded video gets, all present.
            ->assertSee('Allowed styles')
            ->assertSee('Split screen')
            ->assertSee('Reduce the number of images')
            ->assertSee('Free SFX')
            ->assertSee('Background music')
            ->assertDontSee('Upload video')
            // The cut has no music of its own; the picker starts at none.
            ->assertSet('musica', 'nenhuma')
            ->assertSet('musicaVolume', 0.0);
    }

    public function test_submitting_the_form_uses_the_staged_cut_and_the_chosen_options(): void
    {
        Queue::fake();
        [$clip] = $this->clipPronto();

        Livewire::test(Clips::class)->call('animar', $clip->path);

        Livewire::test(ClipsAnimados::class)
            ->set('allowedPresents', ['over', 'split'])
            ->set('lead', 'A banner line')
            ->set('instructions', 'keep it dark')
            ->set('poucasImagens', true)
            ->call('submitOverlay')
            ->assertHasNoErrors();

        $projecto = app(ClipStore::class)->all()->first();

        $this->assertSame(ClipRecord::TYPE_OVERLAY, $projecto->type);
        $this->assertSame('video', $projecto->input_kind);
        $this->assertSame('A short worth animating', $projecto->title);
        // The staged cut, not a re-upload.
        $this->assertSame('RAWCUT', Storage::disk(config('contentmachine.clips.disk'))->get($projecto->source_path));
        $this->assertSame(['over', 'split'], $projecto->meta['allowed_present']);
        $this->assertSame('A banner line', $projecto->meta['lead']);
        $this->assertSame('keep it dark', $projecto->meta['instructions']);
        $this->assertTrue($projecto->meta['fewer_images']);
        $this->assertSame('nenhuma', $projecto->meta['musica']);

        Queue::assertPushed(TranscribeJob::class);
    }

    public function test_the_short_is_copied_onto_the_clips_disk_and_left_in_place(): void
    {
        Queue::fake();
        [$clip, $dir] = $this->clipPronto();

        Livewire::test(Clips::class)->call('animar', $clip->path);

        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $staged = (string) session('animado_video');

        // storage/app/shorts sits outside the clips disk, so it must be copied in.
        $this->assertTrue($disk->exists($staged), 'the source must resolve on the clips disk');
        // The RAW cut, even though a finished short exists alongside it — the burnt-in
        // captions of the finished one would collide with the animation's own.
        $this->assertSame('RAWCUT', $disk->get($staged));
        // ...and the Shorts Generator keeps its own files.
        $this->assertFileExists("$dir/raw.mp4");
        $this->assertFileExists("$dir/final.mp4");
    }

    public function test_a_clip_not_cut_yet_is_cut_but_never_subtitled(): void
    {
        Queue::fake();
        $vault = app(VaultContract::class);
        $fonte = $vault->create(ShortsPipeline::FONTES, [
            'titulo' => 'Interview', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4', 'estado' => 'transcrita',
        ], 'Source.');
        $clip = $vault->create(ShortsPipeline::CLIPES, [
            'titulo' => 'Not cut yet', 'tipo' => 'clip', 'fonte_path' => $fonte->path,
            'inicio' => 0, 'fim' => 40, 'estado' => 'rascunho', 'clip_path' => '', 'output_path' => '',
            'subtitle_data' => json_encode([['text' => 'hello there', 'start' => 0, 'end' => 2, 'words' => []]]),
        ], 'A clip.');

        Livewire::test(Clips::class)
            ->call('animar', $clip->path)
            ->assertRedirect(route('clips-animados'));

        $fresh = $vault->get($clip->path);
        // Cut on the way through — but NOT rendered: burning captions here is what
        // produced two sets of subtitles on the animated result.
        $this->assertSame('cortado', $fresh->get('estado'));
        $this->assertSame('', (string) $fresh->get('output_path'), 'animating must not subtitle the short');
        $this->assertNotNull(session('animado_video'));
    }

    public function test_animating_a_finished_short_leaves_its_own_state_alone(): void
    {
        Queue::fake();
        [$clip] = $this->clipPronto();

        Livewire::test(Clips::class)->call('animar', $clip->path);

        $fresh = app(VaultContract::class)->get($clip->path);
        $this->assertSame('pronto', $fresh->get('estado'));
        $this->assertStringEndsWith('final.mp4', (string) $fresh->get('output_path'));
    }

    public function test_the_button_shows_once_there_is_a_video(): void
    {
        [$clip] = $this->clipPronto();

        Livewire::test(Clips::class)
            ->call('abrirFonte', (string) $clip->get('fonte_path'))
            ->assertSee('Animate');
    }
}
