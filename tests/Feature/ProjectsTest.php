<?php

namespace Tests\Feature;

use App\Http\Middleware\SetActiveProject;
use App\Livewire\Definicoes;
use App\Livewire\ProjectSwitcher;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
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

    public function test_middleware_repoints_the_whole_vault_surface_at_the_active_project(): void
    {
        $project = app(ProjectRepository::class)->create('Brand X', 'pt');

        $request = Request::create('/painel', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('project_slug', $project->slug);

        app(SetActiveProject::class)->handle($request, fn () => response('ok'));

        $this->assertSame($project->slug, app(ProjectContext::class)->current()->slug);
        $this->assertSame($project->path, config('contentmachine.vault.path'));
        $this->assertSame($project->path.'/design-system.md', config('contentmachine.design_system.path'));
        $this->assertSame($project->path.'/estilo-animacao.md', config('contentmachine.clips.style_md'));
        $this->assertSame('pt', app()->getLocale());
    }

    public function test_switcher_stores_the_selected_project_in_the_session(): void
    {
        $project = app(ProjectRepository::class)->create('Brand X', 'en');

        Livewire::test(ProjectSwitcher::class)->call('trocar', $project->slug);

        $this->assertSame($project->slug, session('project_slug'));
    }

    public function test_switcher_creates_a_project_and_switches_to_it(): void
    {
        Livewire::test(ProjectSwitcher::class)
            ->set('newName', 'Second Brand')
            ->set('newLanguage', 'pt')
            ->call('criar')
            ->assertHasNoErrors();

        $this->assertTrue(app(ProjectRepository::class)->exists('second-brand'));
        $this->assertSame('second-brand', session('project_slug'));
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

    public function test_activator_repoints_design_and_style_for_background_jobs(): void
    {
        $project = app(ProjectRepository::class)->create('Brand B', 'pt');
        app(ProjectActivator::class)->activate($project);

        $this->assertSame($project->path.'/design-system.md', config('contentmachine.design_system.path'));
        $this->assertSame($project->path.'/estilo-animacao.md', config('contentmachine.clips.style_md'));
        $this->assertSame($project->path, config('contentmachine.vault.path'));
        $this->assertSame('pt', app()->getLocale());
    }
}
