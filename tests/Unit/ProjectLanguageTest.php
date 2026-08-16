<?php

namespace Tests\Unit;

use App\Services\Aggregation\AggregatedItem;
use App\Services\Aggregation\TopicsBuilder;
use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Projects\ProjectLanguage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Everything the studio writes follows the PROJECT's language. */
class ProjectLanguageTest extends TestCase
{
    private function builder(): object
    {
        return new class
        {
            use BuildsAnimationPrompt;

            public function sys(): string
            {
                return $this->systemPrompt('dense');
            }

            public function user(array $transcript): string
            {
                return $this->userPrompt($transcript, 'dense', 30.0);
            }
        };
    }

    /** The news pipeline writes in the project's language, not a hardcoded one. */
    public function test_news_topics_and_summaries_follow_the_project_language(): void
    {
        $this->app->setLocale('pt');
        config([
            'services.openai.key' => 'k',
            'contentmachine.aggregation.llm_provider' => 'auto', // the suite pins this to 'none'
            'contentmachine.aggregation.claude_cli_bin' => '/nonexistent/claude', // GPT answers here
            'contentmachine.aggregation.gerar_resumos' => true,
        ]);

        $capturado = '';
        Http::fake(function ($request) use (&$capturado) {
            $capturado .= json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return Http::response(['choices' => [['message' => ['content' => '{"topicos":[]}']]]]);
        });

        app(TopicsBuilder::class)->build([new AggregatedItem(
            id: 'a1', plataforma: 'youtube', titulo: 'Kimi K3', canal: 'bycloud', data: '2026-07-29',
            url: 'https://youtu.be/x', thumbnail: '', descricao: '', transcricao: 'open weights model',
            tags: [], fontes: [],
        )]);

        $this->assertStringContainsString('European Portuguese', $capturado);
    }

    public function test_name_follows_the_active_locale(): void
    {
        $this->app->setLocale('pt');
        $this->assertSame('European Portuguese', ProjectLanguage::name());

        $this->app->setLocale('en');
        $this->assertSame('English', ProjectLanguage::name());
    }

    public function test_planner_is_told_the_project_language_even_when_the_transcript_differs(): void
    {
        $this->app->setLocale('pt');
        $builder = $this->builder();

        $this->assertStringContainsString('European Portuguese', $builder->sys());

        // Whisper detected English audio — the visible text still follows the project.
        $user = $builder->user(['language' => 'en', 'text' => 'hello there', 'words' => []]);
        $this->assertStringContainsString('European Portuguese', $user);
        $this->assertStringNotContainsString('Transcript language', $user);
    }
}
