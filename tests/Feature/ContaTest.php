<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Auth\Login;
use App\Livewire\Conta;
use App\Models\User;
use App\Services\Contas\LinkDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** A customer's account: passwordless sign-in by email link, and spending pack credits. */
class ContaTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(int $videos = 0): User
    {
        $user = User::factory()->cliente()->create(['email' => 'rui@exemplo.pt']);
        if ($videos) {
            DB::table('compras')->insert(['user_id' => $user->id, 'sessao' => 'cs_'.uniqid(), 'total' => 10000, 'videos' => $videos, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user;
    }

    public function test_a_customer_asks_for_a_sign_in_link_and_it_signs_them_in(): void
    {
        $user = $this->cliente();

        Livewire::test(Login::class)->set('email', 'RUI@exemplo.pt')->call('enviarLink')->assertHasNoErrors()->assertSet('linkEnviado', true);

        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        preg_match('#https?://[^"\s]+/entrar/'.$user->id.'[^"\s]*#', html_entity_decode($enviados[0]->getOriginalMessage()->getHtmlBody()), $m);
        $this->assertNotEmpty($m);

        $this->get($m[0])->assertRedirect('/conta');
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_link_form_does_not_reveal_who_has_an_account_and_never_mails_the_operator(): void
    {
        $admin = User::factory()->create(['email' => 'op@imocena.com']);

        Livewire::test(Login::class)->set('email', 'ninguem@exemplo.pt')->call('enviarLink')->assertSet('linkEnviado', true);
        Livewire::test(Login::class)->set('email', 'op@imocena.com')->call('enviarLink')->assertSet('linkEnviado', true);

        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
        $this->get(URL::temporarySignedRoute('entrar.link', now()->addMinutes(5), ['user' => $admin->id]))->assertForbidden();
    }

    public function test_links_expire_and_must_be_signed(): void
    {
        $user = $this->cliente();
        $this->get('/entrar/'.$user->id)->assertForbidden();
        $expirado = URL::temporarySignedRoute('entrar.link', now()->subMinute(), ['user' => $user->id]);
        $this->get($expirado)->assertForbidden();
        $this->assertGuest();
    }

    public function test_a_credit_makes_a_clean_video_of_a_new_listing(): void
    {
        Queue::fake();
        $user = $this->cliente(videos: 10);
        $this->actingAs($user);

        Livewire::actingAs($user)->test(Conta::class)
            ->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/999')
            ->call('usarCredito')->assertHasNoErrors();

        $lead = DB::table('leads')->sole();
        $this->assertSame($user->id, (int) $lead->user_id);
        $this->assertNotNull($lead->pago_em);
        Queue::assertPushed(ImportListingJob::class);
        $this->assertSame(9, $user->creditos());
        $this->assertEmpty(app(\App\Services\Clips\Store\ClipStore::class)->find($lead->clip_id)->meta['free_lead'] ?? null);   // no watermark
        $this->get('/conta')->assertOk()->assertSee('A ler o anúncio');
    }

    public function test_no_credits_no_video(): void
    {
        Queue::fake();
        $user = $this->cliente(videos: 0);

        Livewire::actingAs($user)->test(Conta::class)
            ->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/999')
            ->call('usarCredito')->assertHasErrors('anuncio');

        $this->assertSame(0, DB::table('leads')->count());
        Queue::assertNothingPushed();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/conta')->assertRedirect('/login');
    }
}
