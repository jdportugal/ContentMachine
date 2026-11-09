<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Auth\Login;
use App\Livewire\Conta;
use App\Models\User;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Contas\LinkDeAcesso;
use App\Services\Contas\TextosDoVideo;
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

    /** An owned, rendered video with on-screen texts. */
    private function videoDe(User $user): object
    {
        $plan = ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
            ['layers' => [['effect' => 'card', 'params' => ['title' => 'Duas casa de banho', 'src' => '/x/y.jpg', 'value' => '620', 'items' => [['text' => 'Garagem', 'icon' => 'check']]]]]],
        ]];
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'title' => 'Moradia T3', 'plan' => $plan, 'status' => ClipRecord::STATUS_DONE, 'output_path' => '/tmp/x.mp4', 'meta' => ['listing' => true, 'musica' => 'aleatoria']]);
        $id = DB::table('leads')->insertGetId(['user_id' => $user->id, 'anuncio' => 'https://www.remax.pt/pt/imoveis/x/'.uniqid(), 'projeto' => 'default', 'clip_id' => $clip->id, 'consentimento_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('leads')->find($id);
    }

    public function test_only_plain_on_screen_texts_are_offered_for_editing(): void
    {
        $plan = ['scenes' => [['layers' => [['params' => ['title' => 'Sala ampla', 'src' => '/a.jpg', 'value' => '620', 'color' => '#ffffff', 'items' => [['text' => 'Garagem', 'icon' => 'check']]]]]]]];

        $this->assertSame(['Sala ampla', 'Garagem'], array_column(TextosDoVideo::extrair($plan), 'valor'));
        $novo = TextosDoVideo::aplicar($plan, ['scenes.0.layers.0.params.title' => '  Sala   grande ', 'scenes.0.layers.0.params.src' => '/etc/passwd', 'scenes.0.layers.0.params.items.0.text' => '']);
        $this->assertSame('Sala grande', $novo['scenes'][0]['layers'][0]['params']['title']);
        $this->assertSame('/a.jpg', $novo['scenes'][0]['layers'][0]['params']['src']);        // not a text: untouched
        $this->assertSame('Garagem', $novo['scenes'][0]['layers'][0]['params']['items'][0]['text']);   // empty: kept
    }

    public function test_a_customer_edits_music_captions_and_texts_and_the_video_is_made_again(): void
    {
        Queue::fake();
        $user = $this->cliente();
        $lead = $this->videoDe($user);

        Livewire::actingAs($user)->test(Conta::class)
            ->call('abrirEditor', $lead->id)
            ->assertSet('textos', ['Duas casa de banho', 'Garagem'])
            ->set('musica', 'nenhuma')->set('volume', 0.2)->set('legendas', 'top')
            ->set('textos.0', 'Duas casas de banho')
            ->call('guardarEdicao')->assertHasNoErrors()->assertSet('editar', null);

        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertSame('Duas casas de banho', $clip->plan['scenes'][0]['layers'][0]['params']['title']);
        $this->assertSame('nenhuma', $clip->meta['musica']);
        $this->assertSame(0.2, $clip->meta['musica_volume']);
        $this->assertSame('top', $clip->meta['captions']);
        $this->assertSame(1, $clip->meta['edicoes']);
        $this->assertSame(ClipRecord::STATUS_RENDERING, $clip->status);
        Queue::assertPushed(RenderJob::class, fn ($j) => $j->projectId === $clip->id && ! $j->limpo);
    }

    public function test_nobody_edits_someone_elses_video(): void
    {
        Queue::fake();
        $dono = $this->cliente();
        $lead = $this->videoDe($dono);
        $outro = User::factory()->cliente()->create();

        Livewire::actingAs($outro)->test(Conta::class)->call('abrirEditor', $lead->id)->assertSet('editar', null)
            ->set('editar', $lead->id)->call('guardarEdicao');

        Queue::assertNothingPushed();
        $this->assertSame('Duas casa de banho', app(ClipStore::class)->find($lead->clip_id)->plan['scenes'][0]['layers'][0]['params']['title']);
    }

    public function test_the_account_page_offers_one_video_and_the_packs(): void
    {
        config()->set('contentmachine.stripe.links', ['avulso' => 'https://buy.stripe.com/avulso', 'pack10' => 'https://buy.stripe.com/p10', 'pack20' => 'https://buy.stripe.com/p20']);
        $user = $this->cliente();
        $this->actingAs($user)->get('/conta')->assertOk()
            ->assertSee('https://buy.stripe.com/avulso?prefilled_email=rui%40exemplo.pt', false)
            ->assertSee('https://buy.stripe.com/p10?', false)->assertSee('https://buy.stripe.com/p20?', false);
    }
}
