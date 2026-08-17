<?php

namespace Tests\Feature\Clips;

use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\ContentRepurpose;
use App\Livewire\PostsGenerator;
use App\Services\Aggregation\LlmClient;
use App\Services\Content\FinishedContent;
use App\Services\Vault\VaultContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Content Transformer: one nav entry, three subtabs (Shorts · Posts ·
 * Repurpose). Every conversion is a handoff that seeds an existing editor —
 * these tests pin the handoff, not the editors themselves.
 */
class ContentTransformerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
    }

    /** Bind a stub LlmClient so the AI-backed suggestion path can run offline. */
    private function fakeLlmReturning(string $resposta): void
    {
        $this->app->bind(LlmClient::class, fn () => new class($resposta) extends LlmClient
        {
            public function __construct(private string $resposta) {}

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
        });
    }

    // ── navigation ───────────────────────────────────────────────────────

    public function test_all_three_tabs_appear_on_each_transformer_page(): void
    {
        foreach (['/clips', '/clips/posts', '/clips/repurpose'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Shorts Generator')
                ->assertSee('Posts Generator')
                ->assertSee('Content Repurpose')
                // One consolidated sidebar entry.
                ->assertSee('Content Transformer')
                ->assertSee('Shorts · Posts · Repurpose', false);
        }
    }

    public function test_exactly_one_sidebar_entry_and_one_subtab_are_current(): void
    {
        foreach (['/clips', '/clips/posts', '/clips/repurpose'] as $url) {
            $this->assertSame(
                2, // the sidebar entry + the active subtab
                substr_count((string) $this->get($url)->getContent(), 'aria-current="page"'),
                "wrong highlight count on {$url}"
            );
        }
    }

    /** The literal subtab paths must not be captured by '/clips/{slug}/video'. */
    public function test_the_subtab_routes_resolve_to_their_own_pages(): void
    {
        $this->get('/clips/posts')->assertOk()->assertSee('Turn a long video into posts');
        $this->get('/clips/repurpose')->assertOk()->assertSee('Take something already finished');
    }

    // ── posts generator ──────────────────────────────────────────────────

    public function test_posts_generator_lists_sources_and_opens_an_idea_in_the_workshop(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'A long interview',
            'tipo' => 'clip-fonte',
            'fonte' => 'video.mp4',
            'lingua' => 'pt',
            'estado' => 'transcrita',
            'transcricao' => json_encode([['word' => 'hello', 'start' => 0, 'end' => 1]]),
            'publicacoes_sugeridas' => json_encode([
                ['titulo' => 'The three-step method', 'angulo' => 'Break the interview into a how-to.'],
            ]),
        ], 'Source.');

        Livewire::test(PostsGenerator::class)
            ->assertSee('A long interview')
            ->call('alternar', $fonte->path)
            ->assertSee('The three-step method')
            // Opening as a carousel lands on the workshop for that format...
            ->call('abrir', $fonte->path, 0, 'carrossel')
            ->assertRedirect(route('publicacoes.oficina', 'carrossel'));

        // ...with the idea already in the brief the workshop reads.
        $this->assertStringContainsString('The three-step method', (string) session('oficina_brief'));
        $this->assertStringContainsString('Break the interview', (string) session('oficina_brief'));
    }

    /**
     * The angle is only an instruction. Without the video's own words the planner
     * has nothing to write FROM and invents the substance, so the transcript must
     * ride along with it.
     */
    public function test_opening_an_idea_sends_the_source_transcript_not_just_the_angle(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Interview', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4', 'lingua' => 'pt',
            'estado' => 'transcrita',
            'transcricao' => json_encode([
                ['start' => 0, 'end' => 3, 'text' => 'we scraped reddit with apify', 'words' => []],
                ['start' => 3, 'end' => 6, 'text' => 'then elevenlabs read the script', 'words' => []],
            ]),
            'publicacoes_sugeridas' => json_encode([
                ['titulo' => 'The three-step method', 'angulo' => 'Break it into a how-to.'],
            ]),
        ], 'Source.');

        Livewire::test(PostsGenerator::class)
            ->call('abrir', $fonte->path, 0, 'post')
            ->assertRedirect(route('publicacoes.oficina', 'post'));

        $brief = (string) session('oficina_brief');
        $this->assertStringContainsString('The three-step method', $brief, 'the angle should still be there');
        $this->assertStringContainsString('we scraped reddit with apify', $brief);
        $this->assertStringContainsString('then elevenlabs read the script', $brief);
    }

    /** The same handoff from the Shorts Generator must carry the transcript too. */
    public function test_the_shorts_generator_handoff_also_sends_the_transcript(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Interview', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4', 'lingua' => 'pt',
            'estado' => 'transcrita',
            'transcricao' => json_encode([
                ['start' => 0, 'end' => 3, 'text' => 'the whole point is repetition', 'words' => []],
            ]),
            'publicacoes_sugeridas' => json_encode([
                ['titulo' => 'On repetition', 'angulo' => 'Make it a single post.'],
            ]),
        ], 'Source.');

        Livewire::test(Clips::class)
            ->call('abrirPublicacao', $fonte->path, 0)
            ->assertRedirect(route('publicacoes'));

        $this->assertStringContainsString('the whole point is repetition', (string) session('oficina_brief'));
    }

    public function test_opening_a_missing_idea_does_not_redirect(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'No ideas yet', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4',
            'lingua' => 'pt', 'estado' => 'nova', 'transcricao' => '',
        ], 'Source.');

        Livewire::test(PostsGenerator::class)
            ->call('abrir', $fonte->path, 5, 'post')
            ->assertNoRedirect();

        $this->assertNull(session('oficina_brief'));
    }

    /**
     * The suggest button itself — the earlier test only covered opening an idea
     * that was already stored, so this is the first time sugerir() actually runs.
     */
    public function test_suggesting_stores_only_the_post_ideas_and_cuts_no_clips(): void
    {
        $this->fakeLlmReturning(json_encode([
            'segments' => [
                ['title' => 'A segment', 'description' => 'x', 'start_time' => 0, 'end_time' => 60, 'tags' => []],
            ],
            'publications' => [
                ['titulo' => 'Attention is earned', 'angulo' => 'Open with the counter-intuitive bit.'],
            ],
        ]));

        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Interview', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4', 'lingua' => 'pt',
            'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'hello there', 'start' => 0, 'end' => 2]]),
        ], 'Source.');

        Livewire::test(PostsGenerator::class)
            ->call('sugerir', $fonte->path)
            ->assertSee('Attention is earned');

        // Persisted on the source, so it survives a reload.
        $fresh = $vault->get($fonte->path);
        $ideas = json_decode((string) $fresh->get('publicacoes_sugeridas'), true);
        $this->assertSame('Attention is earned', $ideas[0]['titulo']);

        // The Posts tab must NOT cut shorts as a side effect — that is the Shorts tab's job.
        $this->assertCount(0, app(FinishedContent::class)->shorts());
        $this->assertCount(0, $vault->all('clips')->filter(fn ($n) => $n->get('tipo') === 'clip'));
    }

    public function test_suggesting_without_a_transcript_surfaces_the_error_instead_of_throwing(): void
    {
        $this->fakeLlmReturning('{}');

        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Untranscribed', 'tipo' => 'clip-fonte', 'fonte' => 'v.mp4',
            'lingua' => 'pt', 'estado' => 'nova', 'transcricao' => '',
        ], 'Source.');

        Livewire::test(PostsGenerator::class)
            ->call('sugerir', $fonte->path)
            ->assertDispatched('toast', type: 'erro');
    }

    // ── repurpose: video → post/carousel ─────────────────────────────────

    /**
     * The AI must receive everything the clip SAYS, not just its title. Note the
     * real subtitle_data shape: SEGMENTS of {start,end,text,words[]}, which is
     * what SubtitleShifter::shift() writes.
     */
    public function test_a_finished_short_becomes_a_carousel_seeded_with_its_spoken_words(): void
    {
        $vault = app(VaultContract::class);
        $short = $vault->create('clips', [
            'titulo' => 'Why nobody reads your posts',
            'tipo' => 'clip',
            'estado' => 'pronto',
            'descricao' => 'A short about attention.',
            'subtitle_data' => json_encode([
                [
                    'start' => 0, 'end' => 2, 'text' => 'attention is earned',
                    'words' => [['word' => 'attention', 'start' => 0, 'end' => 1]],
                ],
                [
                    'start' => 2, 'end' => 4, 'text' => 'never demanded',
                    'words' => [['word' => 'never', 'start' => 2, 'end' => 3]],
                ],
            ]),
        ], 'Short.');

        Livewire::test(ContentRepurpose::class)
            ->assertSee('Why nobody reads your posts')
            ->call('paraPublicacao', 'short', $short->path, 'carrossel')
            ->assertRedirect(route('publicacoes.oficina', 'carrossel'));

        $brief = (string) session('oficina_brief');
        $this->assertStringContainsString('Why nobody reads your posts', $brief);
        // EVERY segment, not just the first — the planner gets the whole clip.
        $this->assertStringContainsString('attention is earned', $brief);
        $this->assertStringContainsString('never demanded', $brief);
    }

    /** A long spoken text must survive intact, not be clipped to a summary. */
    public function test_the_full_spoken_text_is_sent_not_a_truncated_snippet(): void
    {
        $frases = [];
        for ($i = 1; $i <= 200; $i++) {
            $frases[] = ['start' => $i, 'end' => $i + 1, 'text' => "sentence number {$i}", 'words' => []];
        }

        $vault = app(VaultContract::class);
        $short = $vault->create('clips', [
            'titulo' => 'A long one', 'tipo' => 'clip', 'estado' => 'pronto',
            'descricao' => '', 'subtitle_data' => json_encode($frases),
        ], 'Short.');

        Livewire::test(ContentRepurpose::class)
            ->call('paraPublicacao', 'short', $short->path, 'post')
            ->assertRedirect(route('publicacoes.oficina', 'post'));

        $brief = (string) session('oficina_brief');
        $this->assertStringContainsString('sentence number 1 ', $brief);
        $this->assertStringContainsString('sentence number 200', $brief, 'the tail of the transcript was dropped');
    }

    public function test_a_video_with_no_text_is_refused_rather_than_seeding_an_empty_brief(): void
    {
        $vault = app(VaultContract::class);
        $short = $vault->create('clips', [
            'titulo' => '', 'tipo' => 'clip', 'estado' => 'pronto',
            'descricao' => '', 'subtitle_data' => '',
        ], '');

        Livewire::test(ContentRepurpose::class)
            ->call('paraPublicacao', 'short', $short->path, 'carrossel')
            ->assertNoRedirect();

        $this->assertNull(session('oficina_brief'));
    }

    public function test_an_unknown_target_format_is_rejected(): void
    {
        $vault = app(VaultContract::class);
        $short = $vault->create('clips', [
            'titulo' => 'Something', 'tipo' => 'clip', 'estado' => 'pronto',
            'descricao' => 'Body.', 'subtitle_data' => '',
        ], 'Short.');

        Livewire::test(ContentRepurpose::class)
            ->call('paraPublicacao', 'short', $short->path, 'video')
            ->assertNoRedirect();

        $this->assertNull(session('oficina_brief'));
    }

    // ── repurpose: post → video ──────────────────────────────────────────

    public function test_a_finished_post_becomes_a_video_seeded_into_the_animated_creator(): void
    {
        $vault = app(VaultContract::class);
        $post = $vault->create('rascunhos', [
            'titulo' => 'Five habits that compound',
            'tipo' => 'post',
            'estado' => 'pronto',
        ], 'Small things, repeated, beat big things attempted once.');

        Livewire::test(ContentRepurpose::class)
            ->call('escolherOrigem', 'post')
            ->assertSee('Five habits that compound')
            ->call('paraVideo', $post->path)
            ->assertRedirect(route('clips-animados'));

        $this->assertStringContainsString('Five habits that compound', (string) session('animado_texto'));
        $this->assertStringContainsString('compound', (string) session('animado_texto'));
    }

    /** The receiving end: the animated-clip page opens ready to write. */
    public function test_the_animated_clip_page_picks_up_the_seeded_script(): void
    {
        session(['animado_texto' => 'Small things, repeated.']);

        Livewire::test(ClipsAnimados::class)
            ->assertSet('view', 'create')
            ->assertSet('createType', 'animation')
            ->assertSet('text', 'Small things, repeated.');

        // Consumed, so a later visit is not hijacked by a stale seed.
        $this->assertNull(session('animado_texto'));
    }

    /**
     * The buttons must actually be wired, not merely rendered. Vault refs contain
     * slashes and dots, so a badly quoted wire:click would render fine and then do
     * nothing when clicked — invisible to a plain assertSee.
     */
    public function test_the_repurpose_buttons_call_through_with_the_real_vault_ref(): void
    {
        $vault = app(VaultContract::class);
        $short = $vault->create('clips', [
            'titulo' => 'Attention', 'tipo' => 'clip', 'estado' => 'pronto',
            'descricao' => 'Body.', 'subtitle_data' => json_encode([['word' => 'hello']]),
        ], 'Short.');

        // The ref really does carry the awkward characters.
        $this->assertStringContainsString('/', $short->path);
        $this->assertStringEndsWith('.md', $short->path);

        $html = (string) $this->get('/clips/repurpose')->getContent();
        $this->assertStringContainsString('paraPublicacao', $html);

        // Livewire resolves the rendered call: same method + args the markup carries.
        Livewire::test(ContentRepurpose::class)
            ->call('paraPublicacao', 'short', $short->path, 'post')
            ->assertRedirect(route('publicacoes.oficina', 'post'));
        $this->assertStringContainsString('Attention', (string) session('oficina_brief'));
    }

    // ── the shared "finished" rule ───────────────────────────────────────

    /**
     * Repurpose and the Finished hub must agree on what is finished — the rule
     * lives in FinishedContent precisely so it cannot be restated differently.
     */
    public function test_only_promoted_items_are_offered_for_repurposing(): void
    {
        $vault = app(VaultContract::class);
        $vault->create('clips', ['titulo' => 'Draft short', 'tipo' => 'clip', 'estado' => 'rascunho'], 'x');
        $vault->create('clips', ['titulo' => 'Ready short', 'tipo' => 'clip', 'estado' => 'pronto'], 'x');
        $vault->create('rascunhos', ['titulo' => 'Draft post', 'tipo' => 'post', 'estado' => 'rascunho'], 'x');
        $vault->create('rascunhos', ['titulo' => 'Ready post', 'tipo' => 'post', 'estado' => 'publicado'], 'x');

        $finished = app(FinishedContent::class);
        $this->assertSame(['Ready short'], $finished->shorts()->map(fn ($n) => $n->title())->all());
        $this->assertSame(['Ready post'], $finished->posts()->map(fn ($n) => $n->title())->all());

        Livewire::test(ContentRepurpose::class)
            ->assertSee('Ready short')
            ->assertDontSee('Draft short')
            ->call('escolherOrigem', 'post')
            ->assertSee('Ready post')
            ->assertDontSee('Draft post');
    }
}
