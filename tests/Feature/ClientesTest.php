<?php

namespace Tests\Feature;

use App\Livewire\Clientes;
use App\Livewire\Conta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin: giving customers videos by email, and taking spare ones back. */
class ClientesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_see_the_page(): void
    {
        $this->get('/clientes')->assertRedirect('/login');
        $this->actingAs(User::factory()->cliente()->create())->get('/clientes')->assertRedirect('/conta');
        $this->actingAs(User::factory()->create())->get('/clientes')->assertOk()->assertSee('Adicionar vídeos');
    }

    public function test_an_admin_gives_videos_to_a_new_email_and_they_can_spend_them(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)->test(Clientes::class)
            ->set('email', '  Nova@Agencia.PT ')->set('quantidade', 3)->set('nota', 'oferta de lançamento')
            ->call('adicionar')->assertHasNoErrors()
            ->assertSee('Conta criada. 3 vídeos adicionados a nova@agencia.pt (tem agora 3).')
            ->assertSee('nova@agencia.pt');

        $cliente = User::query()->where('email', 'nova@agencia.pt')->sole();
        $this->assertFalse($cliente->isAdmin());
        $this->assertSame(3, $cliente->creditos());
        $compra = DB::table('compras')->sole();
        $this->assertSame(0, (int) $compra->total);
        $this->assertSame('admin', $compra->payment_link);
        $this->assertSame('oferta de lançamento', json_decode($compra->dados, true)['nota']);
        $this->assertSame($admin->email, json_decode($compra->dados, true)['por']);

        // The sign-in link went to them.
        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $this->assertStringContainsString('/entrar/'.$cliente->id, $enviados[0]->getOriginalMessage()->getHtmlBody());

        // An existing customer gets more on top; no email when unticked.
        Livewire::actingAs($admin)->test(Clientes::class)
            ->set('email', 'nova@agencia.pt')->set('quantidade', 2)->set('enviarLink', false)
            ->call('adicionar')->assertSee('2 vídeos adicionados a nova@agencia.pt (tem agora 5).');
        $this->assertSame(5, $cliente->creditos());
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());

        // They spend one like a bought one.
        Queue::fake();
        Livewire::actingAs($cliente)->test(Conta::class)->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/555')->call('usarCredito')->assertHasNoErrors();
        $this->assertSame(4, $cliente->creditos());

        // Taking back: one spare credit, never below zero.
        Livewire::actingAs($admin)->test(Clientes::class)->call('retirar', $cliente->id)->assertSee('tem agora 3');
        Livewire::actingAs($admin)->test(Clientes::class)->call('retirar', $cliente->id, 10);
        $this->assertSame(0, $cliente->creditos());
    }

    public function test_admins_and_bad_input_are_refused(): void
    {
        $admin = User::factory()->create();
        Livewire::actingAs($admin)->test(Clientes::class)
            ->set('email', $admin->email)->call('adicionar')->assertHasErrors('email')
            ->set('email', 'nao-e-email')->call('adicionar')->assertHasErrors('email')
            ->set('email', 'x@y.pt')->set('quantidade', 0)->call('adicionar')->assertHasErrors('quantidade');
        $this->assertSame(0, DB::table('compras')->count());
    }

    public function test_the_search_finds_a_customer(): void
    {
        $admin = User::factory()->create();
        User::factory()->cliente()->create(['email' => 'ana@remax.pt']);
        User::factory()->cliente()->create(['email' => 'rui@iad.pt']);

        Livewire::actingAs($admin)->test(Clientes::class)
            ->assertSee('ana@remax.pt')->assertSee('rui@iad.pt')
            ->set('procura', 'remax')->assertSee('ana@remax.pt')->assertDontSee('rui@iad.pt');
    }
}
