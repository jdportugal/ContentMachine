<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Auth\Login;
use App\Jobs\Clips\RegenerateVoiceoverJob;
use App\Livewire\Conta;
use App\Livewire\EditarVideo;
use App\Models\User;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Contas\LinkDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** An owned, rendered listing video: two scenes with pictures, an end card, a logo. */
    private function videoDe(User $user): object
    {
        $disco = Storage::disk(config('contentmachine.clips.disk'));
        foreach (['a', 'b', 'c', 'logo'] as $n) {
            $disco->put("clips/uploads/teste-$n.png", 'PNG');
        }
        $plan = ['duration' => 30.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
            ['start' => 0, 'end' => 10, 'layers' => [['type' => 'duo-sobreposto', 'params' => ['src' => 'img_a', 'src2' => 'img_b', 'label' => 'Sala ampla']]]],
            ['start' => 10, 'end' => 30, 'layers' => [['type' => 'agente-revelacao', 'params' => ['src' => 'img_c', 'name' => 'Ana Velha', 'phone' => '+351 911 111 111', 'cta' => 'Ligue +351 911 111 111', 'legal' => 'IAD PORTUGAL | AMI: 11220']]]],
        ]];
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'title' => 'Moradia T3', 'plan' => $plan, 'status' => ClipRecord::STATUS_DONE, 'output_path' => '/tmp/x.mp4',
            'images' => [
                ['id' => 'img_a', 'path' => 'clips/uploads/teste-a.png', 'description' => 'Sala.'],
                ['id' => 'img_b', 'path' => 'clips/uploads/teste-b.png', 'description' => 'Cozinha.'],
                ['id' => 'img_c', 'path' => 'clips/uploads/teste-c.png', 'description' => 'Retrato da consultora Ana Velha.'],
                ['id' => 'img_logo', 'path' => 'clips/uploads/teste-logo.png', 'description' => 'Logótipo da iad.'],
            ],
            'meta' => ['musica' => 'aleatoria', 'listing' => ['agent' => 'Ana Velha', 'phone' => '+351 911 111 111', 'licence' => 'AMI 11220', 'voiceover' => str_repeat('Moradia com vista para a serra. ', 3)]]]);
        $id = DB::table('leads')->insertGetId(['user_id' => $user->id, 'anuncio' => 'https://www.remax.pt/pt/imoveis/x/'.uniqid(), 'projeto' => 'default', 'clip_id' => $clip->id, 'consentimento_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('leads')->find($id);
    }

    public function test_the_editor_shows_voiceover_scenes_agent_and_music_on_its_own_page(): void
    {
        $user = $this->cliente();
        $lead = $this->videoDe($user);

        $this->actingAs($user)->get('/conta')->assertSee('/conta/videos/'.$lead->id.'/editar', false)->assertSee('2 grátis');
        $this->get('/conta/videos/'.$lead->id.'/editar')->assertOk()
            ->assertSee('Narração')->assertSee('Moradia com vista para a serra.')
            ->assertSee('CENA 1')->assertSee('CENA 2')->assertSee('/conta/videos/'.$lead->id.'/imagens/img_a', false)
            ->assertDontSee('Sala ampla')                       // scenes show pictures and effects, no text
            ->assertSee('Licença AMI')->assertSee('Logótipo da agência')->assertSee('Escolher ficheiro de música')
            ->assertSee('Edição 1 de 2');
        $this->get('/conta/videos/'.$lead->id.'/imagens/img_a')->assertOk();

        $outro = User::factory()->cliente()->create();
        $this->actingAs($outro)->get('/conta/videos/'.$lead->id.'/editar')->assertNotFound();
        $this->get('/conta/videos/'.$lead->id.'/imagens/img_a')->assertNotFound();
    }

    public function test_saving_swaps_pictures_updates_the_card_and_music_and_re_renders(): void
    {
        Queue::fake();
        $user = $this->cliente();
        $lead = $this->videoDe($user);

        Livewire::actingAs($user)->test(EditarVideo::class, ['lead' => $lead->id])
            ->assertSet('nome', 'Ana Velha')->assertSet('licenca', '11220')
            ->call('escolherFoto', 0, '0.src2', 'img_a')
            ->set('nome', 'Ana Nova')->set('telefone', '+351 922 222 222')->set('licenca', '99999')
            ->set('musica', UploadedFile::fake()->create('minha.mp3', 500, 'audio/mpeg'))
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('guardar')->assertHasNoErrors()->assertRedirect('/conta');

        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertSame('img_a', $clip->plan['scenes'][0]['layers'][0]['params']['src2']);
        $card = $clip->plan['scenes'][1]['layers'][0]['params'];
        $this->assertSame('Ana Nova', $card['name']);
        $this->assertSame('+351 922 222 222', $card['phone']);
        $this->assertSame('Ligue +351 922 222 222', $card['cta']);
        $this->assertSame('IAD PORTUGAL | AMI 99999', $card['legal']);
        $this->assertNotSame('clips/uploads/teste-logo.png', collect($clip->images)->firstWhere('id', 'img_logo')['path']);
        $this->assertSame('propria', $clip->meta['musica']);
        $this->assertFileExists($clip->meta['musica_ficheiro']);
        $this->assertSame(1, $clip->meta['edicoes']);
        $this->assertSame(ClipRecord::STATUS_RENDERING, $clip->status);
        Queue::assertPushed(RenderJob::class);              // same words: no new voice
        Queue::assertNotPushed(RegenerateVoiceoverJob::class);
    }

    public function test_a_new_voiceover_is_spoken_again_and_only_two_edits_are_free(): void
    {
        Queue::fake();
        $user = $this->cliente();
        $lead = $this->videoDe($user);
        $texto = 'Uma moradia nova em Lajeosa, com jardim, garagem e vista desafogada para a serra.';

        Livewire::actingAs($user)->test(EditarVideo::class, ['lead' => $lead->id])->set('narracao', $texto)->call('guardar')->assertHasNoErrors();
        Queue::assertPushed(RegenerateVoiceoverJob::class, fn ($j) => $j->text === $texto && ! $j->replan);

        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE]);
        Livewire::actingAs($user)->test(EditarVideo::class, ['lead' => $lead->id])->assertSee('Edição 2 de 2')->call('guardar')->assertHasNoErrors();
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE]);

        // The third needs a video: none in the account → buy one; with one → spend it on two more.
        Livewire::actingAs($user)->test(EditarVideo::class, ['lead' => $lead->id])
            ->assertSee('Comprar 1 vídeo (20 €) para mais 2 edições')->call('guardar')->assertHasErrors('guardar');
        DB::table('compras')->insert(['user_id' => $user->id, 'sessao' => 'cs_x', 'total' => 2000, 'videos' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Livewire::actingAs($user)->test(EditarVideo::class, ['lead' => $lead->id])
            ->call('comprarEdicoes')->assertSee('Edição 3 de 4')->call('guardar')->assertHasNoErrors();
        $this->assertSame(0, $user->creditos());
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
