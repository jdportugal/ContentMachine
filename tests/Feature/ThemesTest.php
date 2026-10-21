<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Middleware\ActivateProject;
use App\Livewire\Imoveis;
use App\Livewire\Landing;
use App\Livewire\ThemeSwitcher;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Themes\ThemeContext;
use App\Services\Themes\ThemeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Themes: a design system with its own SFX and backgrounds, chosen per video. */
class ThemesTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    private function usar(string $slug): void
    {
        app(ThemeContext::class)->use($slug);
    }

    public function test_the_default_theme_comes_first_even_before_it_has_a_folder(): void
    {
        app(ThemeRepository::class)->create('Azul Atlântico');

        $temas = app(ThemeRepository::class)->all();

        $this->assertSame(config('contentmachine.themes.default'), $temas[0]->slug);
        $this->assertSame('Azul Atlântico', $temas[1]->name);
        $this->assertSame(config('contentmachine.themes.default'), app(ThemeContext::class)->current()->slug);
    }

    public function test_each_theme_keeps_its_own_design_sfx_and_backgrounds(): void
    {
        $verde = app(ThemeRepository::class)->create('Verde');

        $this->usar($verde->slug);
        app(DesignSystemRepository::class)->write("# Verde\nPaleta verde.");
        app(EffectStore::class)->create(['slug' => 'folha', 'status' => EffectRecord::STATUS_ACTIVE, 'prompt' => 'x', 'display_name' => 'Folha', 'tsx' => 'x']);
        app(BackgroundStore::class)->create(['slug' => 'prado', 'kind' => 'code', 'status' => EffectRecord::STATUS_ACTIVE, 'display_name' => 'Prado', 'tsx' => 'x']);

        $this->assertFileExists($verde->path.'/design-system.md');
        $this->assertSame(['folha'], app(EffectStore::class)->all()->pluck('slug')->all());
        $this->assertSame(['prado'], app(BackgroundStore::class)->all()->pluck('slug')->all());

        $this->usar('');   // back to the default
        $this->assertStringNotContainsString('Verde', app(DesignSystemRepository::class)->read());
        $this->assertSame([], app(EffectStore::class)->all()->pluck('slug')->all());
        $this->assertSame([], app(BackgroundStore::class)->all()->pluck('slug')->all());
    }

    public function test_a_clip_records_the_theme_it_was_made_in(): void
    {
        $verde = app(ThemeRepository::class)->create('Verde');
        $this->usar($verde->slug);

        $feito = app(ClipStore::class)->create(['title' => 'A']);
        $escolhido = app(ClipStore::class)->create(['title' => 'B', 'meta' => ['theme' => 'outro']]);

        $this->assertSame($verde->slug, $feito->meta['theme']);
        $this->assertSame('outro', $escolhido->meta['theme']);
    }

    public function test_a_clips_jobs_run_in_the_clips_theme_whatever_was_queued(): void
    {
        $verde = app(ThemeRepository::class)->create('Verde');
        $clip = app(ClipStore::class)->create(['title' => 'A', 'meta' => ['theme' => $verde->slug]]);
        $this->usar('');

        $job = new class($clip->id) { public function __construct(public string $projectId) {} };
        (new ActivateProject('', 'qualquer'))->handle($job, fn () => null);
        $this->assertSame($verde->slug, app(ThemeContext::class)->current()->slug);

        // Not a clip's job (an SFX, a sample): the theme it was queued in.
        $outro = new class { public string $effectId = 'fx-1'; };
        (new ActivateProject('', config('contentmachine.themes.default')))->handle($outro, fn () => null);
        $this->assertSame(config('contentmachine.themes.default'), app(ThemeContext::class)->current()->slug);
    }

    public function test_the_switcher_changes_the_studio_theme_and_creates_new_ones(): void
    {
        $this->comSessaoIniciada();
        $verde = app(ThemeRepository::class)->create('Verde');

        Livewire::test(ThemeSwitcher::class)->call('mudar', $verde->slug);
        $this->assertSame($verde->slug, session('theme'));

        Livewire::test(ThemeSwitcher::class)->set('novo', 'Terracota')->call('criar')->assertRedirect(route('design-system'));
        $this->assertSame('terracota', session('theme'));
        $this->assertSame('Terracota', app(ThemeRepository::class)->find('terracota')->name);

        Livewire::test(ThemeSwitcher::class)->call('mudar', 'nao-existe');
        $this->assertSame('terracota', session('theme'));
    }

    public function test_a_new_video_is_made_in_the_chosen_theme(): void
    {
        Queue::fake();
        $this->comSessaoIniciada();
        $verde = app(ThemeRepository::class)->create('Verde');

        Livewire::test(Imoveis::class)
            ->assertSet('tema', config('contentmachine.themes.default'))
            ->assertSee('Verde')
            ->set('anuncio', self::ANUNCIO)->set('tema', $verde->slug)
            ->call('criar')->assertHasNoErrors();

        $this->assertSame($verde->slug, app(ClipStore::class)->all()->sole()->meta['theme']);
        Queue::assertPushed(ImportListingJob::class);

        Livewire::test(Imoveis::class)->set('anuncio', self::ANUNCIO)->set('tema', 'nao-existe')->call('criar')->assertHasErrors('tema');
    }

    public function test_free_videos_always_use_the_default_theme(): void
    {
        Queue::fake();
        $verde = app(ThemeRepository::class)->create('Verde');
        $this->usar($verde->slug);

        Livewire::test(Landing::class)->set('anuncio', self::ANUNCIO)->set('consentimento', true)->call('pedir')->assertHasNoErrors();

        $this->assertSame(config('contentmachine.themes.default'), app(ClipStore::class)->all()->sole()->meta['theme']);
    }
}
