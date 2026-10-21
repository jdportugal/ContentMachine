<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Livewire\Imoveis;
use App\Livewire\Landing;
use App\Mail\VideoGratisPronto;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** The public free-video landing page, its email delivery, and the agent portal. */
class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    private function pedir(array $campos = [])
    {
        return Livewire::test(Landing::class)
            ->set($campos + ['anuncio' => self::ANUNCIO, 'nome' => 'Rui Francisco', 'email' => 'Rui@Exemplo.pt', 'telefone' => '912 345 678', 'consentimento' => true])
            ->call('pedir');
    }

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')->assertOk()->assertSee('O vídeo do seu imóvel')->assertSee('Peça o seu vídeo grátis');
    }

    public function test_a_lead_starts_the_listing_import_and_is_recorded(): void
    {
        Queue::fake();

        $this->pedir()->assertHasNoErrors()->assertSet('pedido', true)->assertSee('Pedido recebido');

        $lead = DB::table('leads')->sole();
        $this->assertSame('rui@exemplo.pt', $lead->email);
        $this->assertNotNull($lead->consentimento_em);
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertSame(ClipRecord::STATUS_IMPORTING, $clip->status);
        $this->assertTrue($clip->meta['lead']);
        $this->assertArrayNotHasKey('auto', $clip->meta);   // never auto-published
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->clipId === $clip->id && $j->url === self::ANUNCIO);
    }

    public function test_the_form_rejects_bad_input_in_portuguese(): void
    {
        Queue::fake();

        $this->pedir(['anuncio' => 'https://www.example.com/casa', 'consentimento' => false, 'telefone' => 'abc'])
            ->assertHasErrors(['anuncio', 'consentimento', 'telefone'])
            ->assertSee('RE/MAX, iad e idealista');

        $this->assertSame(0, DB::table('leads')->count());
        Queue::assertNothingPushed();
    }

    public function test_one_free_video_per_email(): void
    {
        Queue::fake();
        $this->pedir();

        $this->pedir(['email' => 'rui@exemplo.pt'])->assertHasErrors('email');
        $this->assertSame(1, DB::table('leads')->count());
    }

    public function test_the_honeypot_pretends_success_and_does_nothing(): void
    {
        Queue::fake();

        $this->pedir(['site' => 'spam.example'])->assertSet('pedido', true);

        $this->assertSame(0, DB::table('leads')->count());
        Queue::assertNothingPushed();
    }

    public function test_the_form_is_rate_limited_per_ip(): void
    {
        Queue::fake();
        config(['contentmachine.leads.per_hour' => 1]);
        $this->pedir();

        $this->pedir(['email' => 'outro@exemplo.pt'])->assertHasErrors('anuncio');
        $this->assertSame(1, DB::table('leads')->count());
    }

    /** A lead row with its clip in the given state (and, when done, a rendered file). */
    private function leadCom(string $status): object
    {
        Queue::fake();
        $this->pedir();
        $lead = DB::table('leads')->sole();
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $attrs = ['status' => $status];
        if ($status === ClipRecord::STATUS_DONE) {
            $ficheiro = sys_get_temp_dir().'/lead-'.uniqid().'.mp4';
            file_put_contents($ficheiro, 'mp4');
            $attrs['output_path'] = $ficheiro;
        }
        $clip->update($attrs);

        return $lead;
    }

    public function test_the_link_is_emailed_once_the_reel_is_rendered(): void
    {
        Mail::fake();
        $lead = $this->leadCom(ClipRecord::STATUS_RENDERING);

        $this->artisan('leads:enviar')->assertSuccessful();
        Mail::assertNothingSent();

        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => tap(sys_get_temp_dir().'/lead-'.uniqid().'.mp4', fn ($f) => file_put_contents($f, 'mp4'))]);
        $this->artisan('leads:enviar')->assertSuccessful();
        $this->artisan('leads:enviar')->assertSuccessful();

        Mail::assertSent(VideoGratisPronto::class, 1);
        Mail::assertSent(VideoGratisPronto::class, fn ($m) => $m->hasTo('rui@exemplo.pt') && str_contains($m->link, '/v/'.$lead->id));
        $this->assertNotNull(DB::table('leads')->value('enviado_em'));
    }

    public function test_a_failed_reel_is_flagged_not_emailed(): void
    {
        Mail::fake();
        $this->leadCom(ClipRecord::STATUS_FAILED);

        $this->artisan('leads:enviar')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNotNull(DB::table('leads')->value('falhou_em'));
    }

    public function test_the_video_page_needs_a_valid_signature(): void
    {
        $lead = $this->leadCom(ClipRecord::STATUS_DONE);

        $this->get('/v/'.$lead->id)->assertForbidden();
        $this->get(URL::signedRoute('lead.video', ['lead' => $lead->id]))->assertOk()->assertSee('Descarregar MP4');
        $this->get(URL::signedRoute('lead.video.ficheiro', ['lead' => $lead->id, 'download' => 1]))->assertOk()->assertDownload('imocena-video.mp4');
    }

    public function test_the_video_page_is_not_found_until_the_reel_is_ready(): void
    {
        $lead = $this->leadCom(ClipRecord::STATUS_PLANNING);

        $this->get(URL::signedRoute('lead.video', ['lead' => $lead->id]))->assertNotFound();
    }

    public function test_the_portal_creates_a_listing_reel_and_lists_it(): void
    {
        Queue::fake();
        $this->comSessaoIniciada();

        Livewire::test(Imoveis::class)
            ->set('anuncio', self::ANUNCIO)->set('notas', 'Destacar a vista mar.')
            ->call('criar')
            ->assertHasNoErrors()
            ->assertSet('anuncio', '')
            ->assertSee('A ler o anúncio');

        $clip = app(ClipStore::class)->all()->sole();
        $this->assertSame('Destacar a vista mar.', $clip->meta['script_notes']);
        Queue::assertPushed(ImportListingJob::class);
    }

    public function test_the_portal_only_reads_supported_listings(): void
    {
        Queue::fake();
        $this->comSessaoIniciada();

        Livewire::test(Imoveis::class)->set('anuncio', 'https://www.example.com/casa')->call('criar')->assertHasErrors('anuncio');
        Queue::assertNothingPushed();
    }
}
