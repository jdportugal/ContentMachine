<?php

namespace Tests\Feature;

use App\Livewire\Rascunhos;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FinishedTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-fin-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        config(['services.blotato.key' => 'test-key']);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp.'/*/*.md') ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /** A promoted post (no media) with a linkedin account configured. */
    private function seedPost(): string
    {
        app(SettingsRepository::class)->save(['blotato' => ['linkedin' => 'acc-1']]);
        $note = app(VaultContract::class)->create('rascunhos', [
            'titulo' => 'My finished post',
            'tipo' => 'post',
            'estado' => 'pronto',
        ], 'The body.');

        return $note->path;
    }

    public function test_promoted_post_shows_in_unpublished(): void
    {
        $this->seedPost();

        Livewire::test(Rascunhos::class)
            ->assertSet('aba', 'unpublished')
            ->assertSee('My finished post');
    }

    public function test_post_now_publishes_and_moves_to_posted(): void
    {
        $path = $this->seedPost();
        $id = 'post_'.md5($path);
        Http::fake(['*/v2/posts' => Http::response(['id' => 'p1'])]);

        Livewire::test(Rascunhos::class)
            ->set('plataformas.'.$id, ['linkedin'])
            ->set('quando.'.$id, 'now')
            ->call('publicar', 'post', $path)
            ->assertHasNoErrors()
            ->assertSet('aba', 'posted');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/posts')
            && $r->data()['post']['accountId'] === 'acc-1'
            && ! array_key_exists('scheduledTime', $r->data()));

        $this->assertSame('publicado', app(VaultContract::class)->get($path)->get('estado'));
    }

    public function test_scheduling_moves_to_scheduled_with_time(): void
    {
        $path = $this->seedPost();
        $id = 'post_'.md5($path);
        Http::fake(['*/v2/posts' => Http::response(['id' => 'p2'])]);

        Livewire::test(Rascunhos::class)
            ->set('plataformas.'.$id, ['linkedin'])
            ->set('quando.'.$id, 'time')
            ->set('datas.'.$id, '2099-01-02T10:30')
            ->call('publicar', 'post', $path)
            ->assertHasNoErrors()
            ->assertSet('aba', 'scheduled');

        Http::assertSent(fn (Request $r) => str_contains($r->data()['scheduledTime'] ?? '', '2099-01-02T10:30'));

        $note = app(VaultContract::class)->get($path);
        $this->assertSame('agendado', $note->get('estado'));
        $this->assertSame(['linkedin'], $note->get('plataformas'));
    }

    public function test_publish_requires_a_platform(): void
    {
        $path = $this->seedPost();
        $id = 'post_'.md5($path);
        Http::fake();

        Livewire::test(Rascunhos::class)
            ->call('publicar', 'post', $path)
            ->assertHasErrors('plataformas.'.$id);

        Http::assertNothingSent();
    }

    public function test_missing_account_id_reports_and_does_not_publish(): void
    {
        // promoted post but no account configured for the chosen platform
        $note = app(VaultContract::class)->create('rascunhos', [
            'titulo' => 'No account post', 'tipo' => 'post', 'estado' => 'pronto',
        ], 'x');
        $id = 'post_'.md5($note->path);
        Http::fake(['*/v2/posts' => Http::response(['id' => 'x'])]);

        Livewire::test(Rascunhos::class)
            ->set('plataformas.'.$id, ['tiktok'])
            ->set('quando.'.$id, 'now')
            ->call('publicar', 'post', $note->path);

        Http::assertNothingSent();
        $this->assertSame('pronto', app(VaultContract::class)->get($note->path)->get('estado'));
    }
}
