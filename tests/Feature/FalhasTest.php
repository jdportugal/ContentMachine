<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Conta;
use App\Livewire\Landing;
use App\Models\User;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\ListingScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** What happens when things go wrong: branded error pages, and videos that fail or get stuck. */
class FalhasTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/moradia-t3/777777';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function enviados(): array
    {
        return array_map(fn ($m) => [
            'para' => array_map(fn ($a) => $a->getAddress(), $m->getEnvelope()->getRecipients()),
            'assunto' => $m->getOriginalMessage()->getSubject(),
        ], iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    /** A paid video (one of a 10-pack) on its clip, as NovoVideo leaves it. */
    private function videoPago(array $meta = [], string $status = ClipRecord::STATUS_FAILED): array
    {
        $user = User::factory()->cliente()->create(['email' => 'rita@agencia.pt']);
        DB::table('compras')->insert(['user_id' => $user->id, 'sessao' => 'cs_1', 'total' => 10000, 'videos' => 10, 'videos_usados' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $clip = app(ClipStore::class)->create(['type' => 'animation', 'status' => $status, 'error' => 'kie.ai 500',
            'meta' => ['listing' => ['url' => self::ANUNCIO], 'theme' => 'remax-premium', 'musica' => 'lofi-animado.mp3'] + $meta]);
        $lead = DB::table('leads')->insertGetId(['user_id' => $user->id, 'anuncio' => self::ANUNCIO, 'projeto' => 'default', 'clip_id' => $clip->id,
            'consentimento_em' => now(), 'pago_em' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$user, $lead, $clip];
    }

    public function test_a_missing_page_is_branded_and_leads_back_to_the_free_video(): void
    {
        $this->get('/nao-existe-esta-pagina')->assertNotFound()
            ->assertSee('Esta página')->assertSee('não existe.', false)->assertSee('Pedir o vídeo grátis')->assertSee('ERRO 404')->assertSee('<title>Imocena · Esta página não existe.</title>', false);
    }

    public function test_idealista_is_no_longer_offered_or_accepted(): void
    {
        $this->assertFalse(ListingScraper::supports('https://www.idealista.pt/imovel/12345678/'));
        $this->get('/')->assertOk()->assertDontSee('idealista');
        Livewire::test(Landing::class)->set(['anuncio' => 'https://www.idealista.pt/imovel/12345678/'])->call('pedir')
            ->assertHasErrors('anuncio')->assertSee('RE/MAX e da iad Portugal');
        Queue::assertNothingPushed();
    }

    public function test_a_failed_video_is_made_again_once_with_the_same_options_and_the_customer_sees_it_in_progress(): void
    {
        [$user, $lead, $clip] = $this->videoPago();

        $this->artisan('leads:atualizar')->assertSuccessful();

        $registo = DB::table('leads')->find($lead);
        $this->assertNotSame($clip->id, $registo->clip_id);
        $this->assertNull($registo->falhou_em);
        $novo = app(ClipStore::class)->find($registo->clip_id);
        $this->assertSame(2, $novo->meta['tentativa']);
        $this->assertSame('lofi-animado.mp3', $novo->meta['musica']);
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->url === self::ANUNCIO);
        $this->assertSame([], $this->enviados());   // nobody is bothered by the first attempt

        $this->actingAs($user)->get('/conta')->assertOk()->assertDontSee('Não conseguimos');
    }

    public function test_a_second_failure_gives_the_video_back_and_tells_the_customer_and_the_admins(): void
    {
        User::factory()->create(['email' => 'joao@imocena.com']);
        [$user, $lead] = $this->videoPago(['tentativa' => 2]);
        $this->assertSame(9, $user->creditos());

        $this->artisan('leads:atualizar')->assertSuccessful();

        $registo = DB::table('leads')->find($lead);
        $this->assertNotNull($registo->falhou_em);
        $this->assertNotNull($registo->devolvido_em);
        $this->assertNull($registo->pago_em);
        $this->assertSame(10, $user->creditos());
        $mails = $this->enviados();
        $this->assertCount(2, $mails);
        $this->assertContains(['para' => ['rita@agencia.pt'], 'assunto' => 'Não conseguimos fazer o vídeo do seu imóvel'], $mails);
        $this->assertSame(['joao@imocena.com'], collect($mails)->firstWhere('assunto', 'Imocena · vídeo com problema (#'.$lead.')')['para']);

        // Run again: nothing more is given back or sent.
        $this->artisan('leads:atualizar')->assertSuccessful();
        $this->assertSame(10, $user->creditos());
        $this->assertCount(2, $this->enviados());

        // The account says so and offers to try again, which spends the video given back.
        $this->actingAs($user)->get('/conta')->assertOk()->assertSee('Não conseguimos fazer este vídeo.')
            ->assertSee('voltou para a sua conta')->assertSee('Tentar outra vez');
        Livewire::actingAs($user)->test(Conta::class)->call('tentarDeNovo', $lead);
        $registo = DB::table('leads')->find($lead);
        $this->assertNull($registo->falhou_em);
        $this->assertNotNull($registo->pago_em);
        $this->assertSame(9, $user->creditos());
    }

    public function test_a_video_stuck_for_a_long_time_counts_as_failed(): void
    {
        $this->travelTo(now()->subHours(2));
        [, $lead, $clip] = $this->videoPago([], ClipRecord::STATUS_RENDERING);
        $this->travelBack();

        $this->artisan('leads:atualizar')->assertSuccessful();

        $this->assertSame(ClipRecord::STATUS_FAILED, app(ClipStore::class)->find($clip->id)->status);
        $this->assertNotSame($clip->id, DB::table('leads')->find($lead)->clip_id);   // retried
    }

    public function test_a_failed_edit_keeps_the_video_they_had_and_gives_the_edit_back(): void
    {
        $mp4 = sys_get_temp_dir().'/cm-falha-'.uniqid().'.mp4';
        file_put_contents($mp4, 'MP4');
        [$user, $lead, $clip] = $this->videoPago(['edicoes' => 1]);
        $clip->update(['output_path' => $mp4]);

        $this->artisan('leads:atualizar')->assertSuccessful();

        $depois = app(ClipStore::class)->find($clip->id);
        $this->assertSame(ClipRecord::STATUS_DONE, $depois->status);
        $this->assertSame(0, $depois->meta['edicoes']);
        $this->assertSame($clip->id, DB::table('leads')->find($lead)->clip_id);   // not made again
        $this->assertSame(9, $user->creditos());                                    // nothing to give back
        $this->assertContains('A sua edição não foi aplicada', array_column($this->enviados(), 'assunto'));
    }
}
