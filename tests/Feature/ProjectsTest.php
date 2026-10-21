<?php

namespace Tests\Feature;

use App\Http\Middleware\SetActiveProject;
use App\Livewire\Definicoes;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Themes\ThemeRepository;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    public function test_default_project_is_seeded_from_the_legacy_vault(): void
    {
        $default = app(ProjectRepository::class)->default();

        $this->assertSame('default', $default->slug);
        $this->assertSame(config('contentmachine.projects.default_vault'), $default->path);
    }

    public function test_creating_a_project_makes_a_vault_directory_and_registers_it(): void
    {
        $repo = app(ProjectRepository::class);
        $project = $repo->create('Brand X', 'pt');

        $this->assertSame('brand-x', $project->slug);
        $this->assertSame('pt', $project->language);
        $this->assertDirectoryExists($project->path);
        $this->assertDirectoryExists($project->path.'/noticias');
        $this->assertTrue($repo->exists('brand-x'));
    }

    public function test_middleware_uses_the_configured_project_and_the_chosen_theme(): void
    {
        $project = app(ProjectRepository::class)->create('Brand X', 'pt');
        config(['contentmachine.project' => $project->slug]);
        app(ProjectActivator::class)->activate($project);
        $tema = app(ThemeRepository::class)->create('Verde');

        $request = Request::create('/imoveis', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('project_slug', 'ignored');
        $request->session()->put('theme', $tema->slug);

        app(SetActiveProject::class)->handle($request, fn () => response('ok'));

        $this->assertSame($project->slug, app(ProjectContext::class)->current()->slug);
        $this->assertSame($project->path, config('contentmachine.vault.path'));
        $this->assertSame($project->path.'/themes/verde/design-system.md', config('contentmachine.design_system.path'));
        $this->assertSame($project->path.'/themes/verde/estilo-animacao.md', config('contentmachine.clips.style_md'));
        $this->assertSame('pt', app()->getLocale());
    }

    public function test_settings_page_updates_the_active_project_language(): void
    {
        $repo = app(ProjectRepository::class);
        $default = $repo->default();
        $target = $default->language === 'pt' ? 'en' : 'pt';

        Livewire::test(Definicoes::class)
            ->assertSet('idioma', $default->language)
            ->set('idioma', $target)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($target, $repo->find($default->slug)->language);
    }

    public function test_monitoring_data_is_isolated_per_project(): void
    {
        config(['cache.default' => 'array']);
        $store = app(MonitoringStore::class);
        $repo = app(ProjectRepository::class);
        $context = app(ProjectContext::class);

        $context->set($repo->default());
        $store->guardar('youtube', [['id' => 'default-video']]);

        // A different project sees none of the default project's monitoring data.
        $context->set($repo->create('Brand B', 'en'));
        $this->assertFalse($store->recolhido('youtube'));
        $store->guardar('youtube', [['id' => 'brandb-video']]);
        $this->assertSame('brandb-video', $store->itens('youtube')[0]['id']);

        // Back to default → its own data, untouched.
        $context->set($repo->default());
        $this->assertSame('default-video', $store->itens('youtube')[0]['id']);
    }

    public function test_activator_repoints_design_and_style_at_the_projects_theme(): void
    {
        $project = app(ProjectRepository::class)->create('Brand B', 'pt');
        app(ProjectActivator::class)->activate($project);

        $tema = $project->path.'/themes/'.config('contentmachine.themes.default');
        $this->assertSame($tema.'/design-system.md', config('contentmachine.design_system.path'));
        $this->assertSame($tema.'/estilo-animacao.md', config('contentmachine.clips.style_md'));
        $this->assertSame($project->path, config('contentmachine.vault.path'));
        $this->assertSame('pt', app()->getLocale());
    }
}
