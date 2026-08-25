<?php

namespace Tests\Feature;

use App\Jobs\AgregarConteudoJob;
use App\Jobs\Middleware\ActivateProject;
use App\Services\Aggregation\NewsAggregator;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Tests\TestCase;

/**
 * A queue worker is long-lived and processes jobs from every project in turn.
 * Whatever project the PREVIOUS job left active must not leak into the next
 * one — otherwise a run reads one project's channels and archives into
 * another's vault, which is exactly how two projects get mixed together.
 */
class JobProjectScopeTest extends TestCase
{
    private function vaultRootOf(object $service, string $prop = 'vault'): string
    {
        $r = new \ReflectionProperty($service, $prop);
        $r->setAccessible(true);

        return $r->getValue($service)->root();
    }

    /**
     * The base TestCase pins VaultContract to one temp directory so tests stay
     * isolated. That hides the very behaviour under test, so restore the real
     * binding: a fresh repository built from whatever project is active now.
     */
    private function comLigacaoRealDoVault(): void
    {
        $this->app->bind(
            VaultContract::class,
            fn ($app) => new VaultRepository($app->make(ProjectContext::class)->vaultPath()),
        );
    }

    public function test_a_jobs_dependencies_are_built_for_its_own_project(): void
    {
        $this->comLigacaoRealDoVault();

        $repo = app(ProjectRepository::class);
        $activator = app(ProjectActivator::class);

        $outro = $repo->create('Outro Projeto', 'pt');
        $meu = $repo->create('O Meu Projeto', 'pt');

        // The job is dispatched while "meu" is active, so it captures that.
        $activator->activate($meu);
        $job = new AgregarConteudoJob('tok-teste');
        $this->assertSame($meu->slug, $job->projectSlug);

        // The worker, meanwhile, is left on a different project by an earlier job.
        $activator->activate($outro);
        $this->assertSame($outro->slug, app(ProjectContext::class)->current()->slug);

        // Running the job goes through its middleware first — which is what makes
        // the dependencies resolve against the RIGHT project.
        $resolvido = null;
        foreach ($job->middleware as $mw) {
            $mw->handle($job, function () use (&$resolvido) {
                // Stands in for handle()'s type-hinted arguments.
                $resolvido = app(NewsAggregator::class);
            });
        }

        $this->assertNotNull($resolvido, 'the middleware must call through');
        $this->assertSame($meu->path, $this->vaultRootOf($resolvido));
        $this->assertSame($meu->path, app(VaultContract::class)->root());
        $this->assertSame($meu->slug, app(ProjectContext::class)->current()->slug);
    }

    public function test_every_project_scoped_job_carries_the_activation_middleware(): void
    {
        $repo = app(ProjectRepository::class);
        app(ProjectActivator::class)->activate($repo->create('Terceiro', 'pt'));

        $job = new AgregarConteudoJob('tok');

        $this->assertNotEmpty($job->middleware, 'RunsInProject must attach ActivateProject');
        $this->assertInstanceOf(ActivateProject::class, $job->middleware[0]);
    }
}
