<?php

namespace Tests\Feature\Editor;

use App\Jobs\Editor\AnalyseEditJob;
use App\Jobs\Editor\RenderEditJob;
use App\Livewire\VideoEditor;
use App\Services\Aggregation\LlmClient;
use App\Services\Editor\DuplicateTakeDetector;
use App\Services\Editor\EditorStore;
use App\Services\Editor\MultiCutEngine;
use App\Services\Editor\Removal;
use App\Services\Editor\SfxOverlayEngine;
use App\Services\Editor\SfxPlanner;
use App\Services\Shorts\LocalVideoEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VideoEditorTest extends TestCase
{
    use RefreshDatabase;

    /** What the stub cut engine was asked to do. A property, not a local: an
     *  arrow function captures by value, so a by-ref local would bind to a copy. */
    private array $cortes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
    }

    private function store(): EditorStore
    {
        return app(EditorStore::class);
    }

    /** An LlmClient stub returning a canned response. */
    private function llm(?string $resposta): LlmClient
    {
        return new class($resposta) extends LlmClient
        {
            public function __construct(private ?string $resposta) {}

            public function paraPasso(string $passo): static
            {
                return $this;
            }

            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                return $this->resposta;
            }
        };
    }

    /** @param array<int,array{0:float,1:float,2:string}> $rows */
    private function transcript(array $rows): array
    {
        return array_map(fn ($r) => [
            'start' => $r[0], 'end' => $r[1], 'text' => $r[2],
            'words' => [['word' => $r[2], 'start' => $r[0], 'end' => $r[1]]],
        ], $rows);
    }

    // ── duplicate takes ──────────────────────────────────────────────────

    /** Every attempt but the LAST is removed — that is the whole point. */
    public function test_only_the_final_take_of_a_repeated_line_survives(): void
    {
        $segments = $this->transcript([
            [0.0, 2.0, 'Today I want to talk about'],
            [2.5, 4.5, 'Today I want to talk about pricing'],
            [5.0, 7.0, 'Today I want to talk about pricing.'],
            [8.0, 9.0, 'Something else entirely'],
        ]);

        $removals = (new DuplicateTakeDetector($this->llm('{"groups": [[0,1,2]]}')))->detect($segments);

        $this->assertCount(2, $removals);
        $this->assertEqualsWithDelta(0.0, $removals[0]->start, 0.001);
        $this->assertEqualsWithDelta(2.5, $removals[1]->start, 0.001);
        $this->assertSame(Removal::DUPLICATE, $removals[0]->reason);

        // The keeper (5.0–7.0) is never in the removals.
        foreach ($removals as $r) {
            $this->assertNotEqualsWithDelta(5.0, $r->start, 0.001);
        }
    }

    public function test_a_group_naming_one_segment_removes_nothing(): void
    {
        $segments = $this->transcript([[0.0, 1.0, 'a'], [2.0, 3.0, 'b']]);

        $this->assertSame([], (new DuplicateTakeDetector($this->llm('{"groups": [[1]]}')))->detect($segments));
    }

    public function test_unknown_segment_indices_are_ignored(): void
    {
        $segments = $this->transcript([[0.0, 1.0, 'a'], [2.0, 3.0, 'b']]);

        $this->assertSame([], (new DuplicateTakeDetector($this->llm('{"groups": [[7,9]]}')))->detect($segments));
    }

    /** A flaky model must not cost the dead-air pass or the transcript. */
    public function test_an_unusable_response_yields_no_cuts_instead_of_throwing(): void
    {
        $segments = $this->transcript([[0.0, 1.0, 'a'], [2.0, 3.0, 'b']]);

        $this->assertSame([], (new DuplicateTakeDetector($this->llm('sorry, I cannot')))->detect($segments));
        $this->assertSame([], (new DuplicateTakeDetector($this->llm(null)))->detect($segments));
    }

    // ── analysis ─────────────────────────────────────────────────────────

    public function test_analysis_transcribes_once_and_leaves_the_edit_in_review(): void
    {
        $edit = $this->store()->create(['title' => 'Take', 'camera_path' => $this->ficheiro('cam.mp4')]);

        $this->app->bind(LocalVideoEngine::class, fn () => new class($this->transcript([[0.0, 1.0, 'first line'], [4.0, 5.0, 'second line after a long pause']])) extends LocalVideoEngine
        {
            public function __construct(private array $segs) {}

            public function transcribe(string $videoPath, string $language = 'pt'): array
            {
                return $this->segs;
            }

            public function probe(string $path): array
            {
                return ['duration' => 6.0, 'width' => 1920, 'height' => 1080, 'has_audio' => true];
            }
        });
        $this->app->bind(DuplicateTakeDetector::class, fn () => new DuplicateTakeDetector($this->llm('{"groups": []}')));

        app()->call([new AnalyseEditJob($edit->id()), 'handle']);

        $edit = $this->store()->find($edit->id());
        $this->assertSame(EditorStore::REVIEW, $edit->status);
        $this->assertCount(2, $edit->transcript());
        // The 3s pause plus the tail after the last word.
        $this->assertNotEmpty($edit->removals());
        $this->assertSame(Removal::SILENCE, $edit->removals()[0]->reason);
    }

    public function test_a_missing_camera_file_fails_the_edit_with_a_reason(): void
    {
        $edit = $this->store()->create(['title' => 'Take', 'camera_path' => '/nope/missing.mp4']);

        app()->call([new AnalyseEditJob($edit->id()), 'handle']);

        $edit = $this->store()->find($edit->id());
        $this->assertSame(EditorStore::FAILED, $edit->status);
        $this->assertStringContainsString('camera file is missing', (string) $edit->error);
    }

    // ── rendering ────────────────────────────────────────────────────────

    /** The sync guarantee: both tracks cut with one plan, computed once. */
    public function test_rendering_cuts_both_tracks_with_the_same_ranges(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take',
            'camera_path' => $this->ficheiro('cam.mp4'),
            'screen_path' => $this->ficheiro('scr.mp4'),
            'duration' => 10.0,
        ]);
        $edit->setRemovals([new Removal(2.0, 4.0, Removal::SILENCE)]);

        $this->app->bind(MultiCutEngine::class, fn () => new class($this->cortes) extends MultiCutEngine
        {
            public function __construct(private array &$visto) {}

            public function cut(string $source, array $keepRanges, string $dest): string
            {
                $this->visto[] = ['source' => $source, 'ranges' => $keepRanges];
                @mkdir(dirname($dest), 0775, true);
                file_put_contents($dest, 'CUT');

                return $dest;
            }
        });

        app()->call([new RenderEditJob($edit->id()), 'handle']);

        $this->assertCount(2, $this->cortes, 'both tracks should be cut');
        $this->assertSame($this->cortes[0]['ranges'], $this->cortes[1]['ranges'], 'the two tracks were cut differently');
        $this->assertSame([[0.0, 2.0], [4.0, 10.0]], $this->cortes[0]['ranges']);

        $edit = $this->store()->find($edit->id());
        $this->assertSame(EditorStore::DONE, $edit->status);
        $this->assertArrayHasKey('camera', (array) $edit->get('outputs'));
        $this->assertArrayHasKey('screen', (array) $edit->get('outputs'));
    }

    public function test_an_edit_without_a_screen_feed_still_renders_the_camera(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take', 'camera_path' => $this->ficheiro('cam.mp4'), 'duration' => 5.0,
        ]);

        $this->app->bind(MultiCutEngine::class, fn () => new class extends MultiCutEngine
        {
            public function __construct() {}

            public function cut(string $source, array $keepRanges, string $dest): string
            {
                @mkdir(dirname($dest), 0775, true);
                file_put_contents($dest, 'CUT');

                return $dest;
            }
        });

        app()->call([new RenderEditJob($edit->id()), 'handle']);

        $edit = $this->store()->find($edit->id());
        $this->assertSame(EditorStore::DONE, $edit->status);
        $this->assertSame(['camera'], array_keys((array) $edit->get('outputs')));
    }

    // ── the transcript editor ────────────────────────────────────────────

    public function test_clicking_a_sentence_cuts_it_and_clicking_again_restores_it(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take', 'duration' => 10.0,
            'transcript' => $this->transcript([[0.0, 2.0, 'keep me'], [3.0, 5.0, 'cut me']]),
            'status' => EditorStore::REVIEW,
        ]);

        $c = Livewire::test(VideoEditor::class)->set('aberto', $edit->id());

        $c->call('alternarSegmento', 1);
        $removals = $this->store()->find($edit->id())->removals();
        $this->assertCount(1, $removals);
        $this->assertSame(Removal::MANUAL, $removals[0]->reason);

        $c->call('alternarSegmento', 1);
        $this->assertSame([], $this->store()->find($edit->id())->removals());
    }

    /** Restoring must also undo a cut the AI proposed, not just a manual one. */
    public function test_clicking_a_detected_cut_restores_it(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take', 'duration' => 10.0,
            'transcript' => $this->transcript([[0.0, 2.0, 'a bad take'], [3.0, 5.0, 'the good one']]),
            'status' => EditorStore::REVIEW,
        ]);
        $edit->setRemovals([new Removal(0.0, 2.0, Removal::DUPLICATE)]);

        Livewire::test(VideoEditor::class)
            ->set('aberto', $edit->id())
            ->call('alternarSegmento', 0);

        $this->assertSame([], $this->store()->find($edit->id())->removals());
    }

    public function test_the_transcript_shows_removed_lines_and_the_silence_between_them(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take', 'duration' => 10.0,
            'transcript' => $this->transcript([[0.0, 1.0, 'first'], [4.0, 5.0, 'second']]),
            'status' => EditorStore::REVIEW,
        ]);
        $edit->setRemovals([new Removal(1.15, 3.85, Removal::SILENCE)]);

        $linhas = Livewire::test(VideoEditor::class)->set('aberto', $edit->id())->get('linhas');

        $this->assertFalse($linhas[0]['removed']);
        $this->assertFalse($linhas[1]['removed'], 'a silence removes no words, so no sentence is struck');
        $this->assertEqualsWithDelta(2.7, $linhas[1]['gap'], 0.05, 'the pause should be reported before the next line');
    }

    public function test_keeping_everything_clears_every_cut(): void
    {
        $edit = $this->store()->create([
            'title' => 'Take', 'duration' => 10.0,
            'transcript' => $this->transcript([[0.0, 2.0, 'x']]),
            'status' => EditorStore::REVIEW,
        ]);
        $edit->setRemovals([new Removal(0.0, 1.0, Removal::SILENCE)]);

        Livewire::test(VideoEditor::class)->set('aberto', $edit->id())->call('limparCortes');

        $this->assertSame([], $this->store()->find($edit->id())->removals());
    }

    // ── step 2 ───────────────────────────────────────────────────────────

    public function test_the_sfx_planner_places_effects_on_the_edited_timeline(): void
    {
        $segmentos = [
            ['start' => 0.0, 'end' => 2.0, 'text' => 'we grew a lot'],
            ['start' => 2.0, 'end' => 5.0, 'text' => 'revenue hit 320 thousand'],
        ];

        $momentos = (new SfxPlanner($this->llm('{"effects": [{"segment": 1, "brief": "320 counts up"}]}')))->plan($segmentos);

        $this->assertCount(1, $momentos);
        $this->assertEqualsWithDelta(2.0, $momentos[0]['at'], 0.001);
        $this->assertEqualsWithDelta(3.0, $momentos[0]['duration'], 0.001);
        $this->assertSame('320 counts up', $momentos[0]['brief']);
    }

    public function test_the_sfx_planner_ignores_segments_it_invented(): void
    {
        $segmentos = [['start' => 0.0, 'end' => 2.0, 'text' => 'only one line']];

        $this->assertSame([], (new SfxPlanner($this->llm('{"effects": [{"segment": 9, "brief": "x"}]}')))->plan($segmentos));
    }

    /**
     * Each overlay must be shifted to its moment AND gated, or the last frame
     * lingers over the rest of the video.
     */
    public function test_each_effect_is_shifted_to_its_moment_and_gated(): void
    {
        $args = (new SfxOverlayEngine)->buildOverlayArgs('screen.mp4', [
            ['path' => 'a.mov', 'at' => 3.0, 'duration' => 2.0],
            ['path' => 'b.mov', 'at' => 10.5, 'duration' => 4.0],
        ], 'out.mp4');

        $filtro = $args[array_search('-filter_complex', $args, true) + 1];

        $this->assertStringContainsString('[1:v]setpts=PTS-STARTPTS+3/TB[fx1]', $filtro);
        $this->assertStringContainsString("enable='between(t,3,5)'", $filtro);
        $this->assertStringContainsString('[2:v]setpts=PTS-STARTPTS+10.5/TB[fx2]', $filtro);
        $this->assertStringContainsString("enable='between(t,10.5,14.5)'", $filtro);
        // Chained: the second overlay composites onto the first's output.
        $this->assertStringContainsString('[v1][fx2]overlay', $filtro);
    }

    public function test_placing_no_effects_refuses_rather_than_re_encoding_for_nothing(): void
    {
        $this->expectExceptionMessage('No effects to place');

        (new SfxOverlayEngine)->apply('screen.mp4', [], 'out.mp4');
    }

    // ── serving ──────────────────────────────────────────────────────────

    /** A crafted role must not reach an arbitrary path. */
    public function test_the_media_route_only_serves_roles_the_record_lists(): void
    {
        $ficheiro = $this->ficheiro('done.mp4');
        $edit = $this->store()->create(['title' => 'Take', 'outputs' => ['camera' => $ficheiro]]);

        $this->get(route('video-editor.media', [$edit->id(), 'camera']))->assertOk();
        $this->get(route('video-editor.media', [$edit->id(), 'screen']))->assertNotFound();
    }

    public function test_deleting_removes_the_record_and_its_files(): void
    {
        $edit = $this->store()->create(['title' => 'Take']);
        $ficheiro = $this->store()->filePath($edit->id(), 'camera');
        @mkdir(dirname($ficheiro), 0775, true);
        file_put_contents($ficheiro, 'VIDEO');

        Livewire::test(VideoEditor::class)->call('apagar', $edit->id());

        $this->assertCount(0, $this->store()->all());
        $this->assertFileDoesNotExist($ficheiro);
    }

    /** A stand-in file on disk, so is_file() checks pass. */
    private function ficheiro(string $nome): string
    {
        $path = $this->store()->dir().'/'.$nome;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'VIDEO');

        return $path;
    }
}
