<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Conta;
use App\Livewire\Imoplanta\Comecar;
use App\Mail\EmailImocena;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Account settings: other emails that sign in to the same account (a customer sharing it). */
class ContaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_shares_the_account_and_the_other_email_signs_in_to_it(): void
    {
        Mail::fake();
        $ana = User::factory()->create(['email' => 'ana@exemplo.pt']);
        $rui = User::factory()->create(['email' => 'rui@exemplo.pt']);

        $this->actingAs($ana)->get(route('conta'))->assertOk()->assertSee('Quem pode entrar nesta conta');
        $this->get(route('imoveis'))->assertSee('Definições da conta');   // in the menu, for customers too

        Livewire::actingAs($ana)->test(Conta::class)
            ->set('email', ' Assistente@Exemplo.pt ')->call('adicionar')->assertHasNoErrors()->assertSee('assistente@exemplo.pt')
            ->set('email', 'rui@exemplo.pt')->call('adicionar')->assertHasErrors(['email' => 'unique'])        // has its own account
            ->set('email', 'assistente@exemplo.pt')->call('adicionar')->assertHasErrors(['email' => 'unique']);   // already here
        Livewire::actingAs($rui)->test(Conta::class)->set('email', 'assistente@exemplo.pt')->call('adicionar')->assertHasErrors('email');
        $this->assertSame(['assistente@exemplo.pt'], $ana->emailsPartilhados());
        auth()->logout();

        // the plans page: straight into Ana's account
        Livewire::test(Comecar::class)->set('email', 'assistente@exemplo.pt')->set('consentimento', true)->call('continuar');
        $this->assertAuthenticatedAs($ana);
        $this->assertSame(2, User::count());   // no new account
        auth()->logout();

        // the sign-in page: the link goes to the assistant's own inbox and opens Ana's account
        Livewire::test(Login::class)->set('email', 'assistente@exemplo.pt')->call('enviarLink');
        $url = null;
        Mail::assertSent(EmailImocena::class, function ($m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('assistente@exemplo.pt') && ! $m->hasTo('ana@exemplo.pt');
        });
        $this->get($url);
        $this->assertAuthenticatedAs($ana);

        // removed: it no longer gets in
        Livewire::actingAs($ana)->test(Conta::class)->call('remover', 'assistente@exemplo.pt');
        $this->assertNull(User::porEmail('assistente@exemplo.pt'));
        $this->assertSame($ana->id, User::porEmail('ANA@exemplo.pt')->id);
    }

    public function test_someone_else_cannot_remove_an_email_from_an_account_that_is_not_theirs(): void
    {
        $ana = User::factory()->create();
        $rui = User::factory()->create();
        Livewire::actingAs($ana)->test(Conta::class)->set('email', 'x@exemplo.pt')->call('adicionar');
        Livewire::actingAs($rui)->test(Conta::class)->call('remover', 'x@exemplo.pt');
        $this->assertSame(['x@exemplo.pt'], $ana->emailsPartilhados());
    }
}
