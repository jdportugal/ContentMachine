<?php

namespace Tests\Feature;

use App\Livewire\ProjectSwitcher;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCloneTest extends TestCase
{
    private function activate(string $slug): void
    {
        app(ProjectActivator::class)->activate(app(ProjectRepository::class)->find($slug));
    }

    public function test_a_clone_copies_the_setup_and_then_lives_on_its_own(): void
    {
        $projects = app(ProjectRepository::class);
        $orig = $projects->create('Imob', 'pt');
        $this->activate($orig->slug);
        app(DesignSystemRepository::class)->write("# Azul\nPaleta azul.");
        $effect = app(EffectStore::class)->create([
            'slug' => 'card', 'status' => EffectRecord::STATUS_ACTIVE, 'enabled' => true, 'prompt' => 'x',
            'display_name' => 'Card', 'tsx' => 'export default () => null; // v1', 'preview_path' => '/shared/card.mp4',
        ]);
        @mkdir($orig->path.'/clips-animados', 0775, true);
        file_put_contents($orig->path.'/clips-animados/some-clip.json', '{}');
        @mkdir($orig->path.'/image-library', 0775, true);
        file_put_contents($orig->path.'/image-library/lib.json', json_encode(['path' => $orig->path.'/image-library/lib.png']));

        $clone = $projects->duplicate($orig->slug, 'Imob B');

        // Setup copied, content not.
        $this->assertSame('pt', $clone->language);
        $this->assertFileExists($clone->path.'/design-system.md');
        $this->assertFileDoesNotExist($clone->path.'/clips-animados/some-clip.json');
        $this->assertSame($clone->path.'/image-library/lib.png', json_decode(file_get_contents($clone->path.'/image-library/lib.json'), true)['path']);

        // The clone's effect is its own record — editing it leaves the original alone.
        $this->activate($clone->slug);
        $copy = app(EffectStore::class)->all()->firstWhere('slug', 'card');
        $this->assertNull($copy->preview_path, 'never point at the original\'s cached preview');
        $copy->update(['tsx' => 'export default () => null; // v2']);
        app(DesignSystemRepository::class)->write("# Verde\nPaleta verde.");
        $clonePreview = app(EffectLibrary::class)->previewPath('card');

        $this->activate($orig->slug);
        $this->assertStringContainsString('v1', app(EffectStore::class)->find($effect->id())->tsx);
        $this->assertStringContainsString('Azul', app(DesignSystemRepository::class)->read());
        $this->assertNotSame($clonePreview, app(EffectLibrary::class)->previewPath('card'), 'previews never collide');
    }

    public function test_the_switcher_clones_the_current_project_and_switches_to_it(): void
    {
        $orig = app(ProjectRepository::class)->create('Imob', 'pt');
        session(['project_slug' => $orig->slug]);
        $this->activate($orig->slug);

        Livewire::test(ProjectSwitcher::class)
            ->call('abrirClone')
            ->assertSet('cloneName', 'Imob (copy)')
            ->call('clonar')
            ->assertHasNoErrors()
            ->assertRedirect(route('painel'));

        $this->assertSame('imob-copy', session('project_slug'));
        $this->assertNotNull(app(ProjectRepository::class)->find('imob-copy'));
    }
}
