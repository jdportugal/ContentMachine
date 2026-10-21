<?php

namespace Tests\Feature;

use App\Livewire\Clips;
use App\Services\Clips\EffectGenerator;
use App\Services\Projects\ProjectLanguage;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anything the studio writes follows the ACTIVE PROJECT's language. These were
 * three places that didn't: the shorts source defaulted to a hardcoded 'pt'
 * (so an English project transcribed as Portuguese before cutting), the SFX/VFX
 * generator never named the language at all, and the clips screen printed the
 * vault's Portuguese state values straight into the badges.
 */
class ProjectLanguageDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_shorts_source_takes_the_project_language(): void
    {
        $this->app->setLocale('en');
        $fonte = app(ShortsPipeline::class)->criarFonte('/tmp/a.mp4', 'Interview');
        $this->assertSame('en', $fonte->get('lingua'));

        $this->app->setLocale('pt');
        $fonte = app(ShortsPipeline::class)->criarFonte('/tmp/b.mp4', 'Entrevista');
        $this->assertSame('pt', $fonte->get('lingua'));
    }

    public function test_an_explicit_language_still_wins(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('es', app(ShortsPipeline::class)->criarFonte('/tmp/c.mp4', 'Charla', 'es')->get('lingua'));
    }

    public function test_the_effect_prompt_names_the_project_language(): void
    {
        $this->app->setLocale('en');
        $this->assertStringContainsString('English', app(EffectGenerator::class)->componentContract());

        $this->app->setLocale('pt');
        $this->assertStringContainsString('European Portuguese', app(EffectGenerator::class)->componentContract());
    }

    public function test_the_language_code_falls_back_to_english(): void
    {
        $this->app->setLocale('');
        $this->assertSame('en', ProjectLanguage::code());
        $this->assertSame('English', ProjectLanguage::name());
    }

}
