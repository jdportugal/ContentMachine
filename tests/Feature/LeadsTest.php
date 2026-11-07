<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\Imoveis;
use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            ->set($campos + ['anuncio' => self::ANUNCIO, 'consentimento' => true])
            ->call('pedir');
    }

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')->assertOk()->assertSee('O vídeo do seu imóvel')->assertSee('Peça o seu vídeo grátis');
    }

    public function test_the_link_alone_starts_the_listing_import(): void
    {
        Queue::fake();

        $this->pedir()->assertHasNoErrors()->assertSee('Estamos a fazer o seu vídeo')->assertSee('A ler o anúncio');

        $lead = DB::table('leads')->sole();
        $this->assertSame(self::ANUNCIO, $lead->anuncio);
        $this->assertNull($lead->nome);
        $this->assertNotNull($lead->consentimento_em);
        $this->assertSame($lead->id, session('lead_id'));
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertSame(ClipRecord::STATUS_IMPORTING, $clip->status);
        $this->assertTrue($clip->meta['free_lead']);
        $this->assertArrayNotHasKey('lead', $clip->meta);   // meta.lead is the news banner
        $this->assertArrayNotHasKey('auto', $clip->meta);   // never auto-published
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->clipId === $clip->id && $j->url === self::ANUNCIO);
    }

    public function test_the_form_rejects_bad_input_in_portuguese(): void
    {
        Queue::fake();

        $this->pedir(['anuncio' => 'https://www.example.com/casa', 'consentimento' => false])
            ->assertHasErrors(['anuncio', 'consentimento'])
            ->assertSee('RE/MAX, iad e idealista');

        $this->assertSame(0, DB::table('leads')->count());
        Queue::assertNothingPushed();
    }

    public function test_one_free_video_per_listing(): void
    {
        Queue::fake();
        $this->pedir();

        $this->pedir()->assertHasErrors('anuncio');
        $this->assertSame(1, DB::table('leads')->count());
    }

    public function test_the_honeypot_pretends_success_and_does_nothing(): void
    {
        Queue::fake();

        $this->pedir(['site' => 'spam.example'])->assertSee('Estamos a fazer o seu vídeo');

        $this->assertSame(0, DB::table('leads')->count());
        Queue::assertNothingPushed();
    }

    public function test_the_form_is_rate_limited_per_ip(): void
    {
        Queue::fake();
        config(['contentmachine.leads.per_hour' => 1]);
        $this->pedir();

        $this->pedir(['anuncio' => self::ANUNCIO.'7'])->assertHasErrors('anuncio');
        $this->assertSame(1, DB::table('leads')->count());
    }

    /** A lead row with its clip in the given state (and, when done, a rendered file). */
    private function leadCom(string $status, array $listing = []): object
    {
        Queue::fake();
        $this->pedir();
        $lead = DB::table('leads')->sole();
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $attrs = ['status' => $status, 'meta' => array_merge($clip->meta, ['listing' => array_merge($clip->meta['listing'], $listing)])];
        if ($status === ClipRecord::STATUS_DONE) {
            $ficheiro = sys_get_temp_dir().'/lead-'.uniqid().'.mp4';
            file_put_contents($ficheiro, 'mp4');
            $attrs['output_path'] = $ficheiro;
        }
        $clip->update($attrs);

        return $lead;
    }

    public function test_the_page_shows_the_video_once_rendered(): void
    {
        $lead = $this->leadCom(ClipRecord::STATUS_DONE);

        Livewire::test(Landing::class)
            ->assertSee('Publique-o já.')->assertSee('Descarregar MP4')
            ->call('outro')->assertSee('Peça o seu vídeo grátis');
        $this->assertNull(session('lead_id'));

        $this->get('/v/'.$lead->id)->assertForbidden();
        $this->get(URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]))->assertOk()->assertDownload('imocena-video.mp4');
    }

    public function test_a_failed_reel_says_so(): void
    {
        $this->leadCom(ClipRecord::STATUS_FAILED);

        Livewire::test(Landing::class)->assertSee('Não conseguimos fazer este vídeo');
    }

    public function test_the_video_link_is_not_found_until_the_reel_is_ready(): void
    {
        $lead = $this->leadCom(ClipRecord::STATUS_PLANNING);

        $this->get(URL::signedRoute('lead.video', ['lead' => $lead->id]))->assertNotFound();
    }

    public function test_leads_get_the_agent_from_the_listing_and_are_marked_ready(): void
    {
        $lead = $this->leadCom(ClipRecord::STATUS_RENDERING, ['agent' => 'Rui Francisco', 'phone' => '965 659 539']);

        $this->artisan('leads:atualizar')->assertSuccessful();
        $row = DB::table('leads')->find($lead->id);
        $this->assertSame('Rui Francisco', $row->nome);
        $this->assertSame('965 659 539', $row->telefone);
        $this->assertNull($row->pronto_em);

        $ficheiro = sys_get_temp_dir().'/lead-'.uniqid().'.mp4';
        file_put_contents($ficheiro, 'mp4');
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => $ficheiro]);
        $this->artisan('leads:atualizar')->assertSuccessful();
        $this->assertNotNull(DB::table('leads')->value('pronto_em'));
    }

    public function test_a_failed_lead_is_flagged(): void
    {
        $this->leadCom(ClipRecord::STATUS_FAILED);

        $this->artisan('leads:atualizar')->assertSuccessful();

        $this->assertNotNull(DB::table('leads')->value('falhou_em'));
    }

    public function test_a_failed_attempt_does_not_use_up_the_free_video(): void
    {
        $this->leadCom(ClipRecord::STATUS_FAILED);
        $this->artisan('leads:atualizar')->assertSuccessful();

        Livewire::test(Landing::class)->call('outro');
        $this->pedir()->assertHasNoErrors('anuncio');

        $this->assertSame(2, DB::table('leads')->where('anuncio', self::ANUNCIO)->count());
    }

    public function test_only_free_videos_render_with_the_watermark(): void
    {
        $renderer = new class implements RemotionRenderer
        {
            public array $props = [];

            public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
            {
                $this->props = $props;
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'X');

                return $outPath;
            }
        };
        $this->app->instance(RemotionRenderer::class, $renderer);
        $plan = ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => []];
        $store = app(ClipStore::class);

        $gratis = $store->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['free_lead' => true, 'musica' => 'nenhuma']]);
        $this->app->call([new RenderJob($gratis->id), 'handle']);
        $this->assertSame('IMOCENA · VÍDEO GRÁTIS', $renderer->props['watermark']);
        $this->assertArrayNotHasKey('banner', $renderer->props);

        $pago = $store->create(['type' => 'animation', 'input_kind' => 'audio', 'plan' => $plan, 'meta' => ['musica' => 'nenhuma']]);
        $this->app->call([new RenderJob($pago->id), 'handle']);
        $this->assertArrayNotHasKey('watermark', $renderer->props);
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

    public function test_promotional_emails_are_an_optional_separate_opt_in(): void
    {
        Queue::fake();

        $this->pedir()->assertHasNoErrors()->assertSet('marketing', false);
        $lead = DB::table('leads')->sole();
        $this->assertNull($lead->email);
        $this->assertNull($lead->marketing_em);

        session()->forget('lead_id');   // a fresh visitor, not the one whose video is in progress
        Livewire::test(Landing::class)->assertDontSee('O seu email')
            ->set('marketing', true)->assertSee('O seu email')
            ->set(['anuncio' => self::ANUNCIO.'7', 'consentimento' => true])->call('pedir')
            ->assertHasErrors(['email' => 'required']);
    }

    public function test_opting_in_records_the_email_time_and_exact_wording(): void
    {
        Queue::fake();

        $this->pedir(['marketing' => true, 'email' => ' ana@exemplo.pt '])->assertHasNoErrors();

        $lead = DB::table('leads')->sole();
        $this->assertSame('ana@exemplo.pt', $lead->email);
        $this->assertNotNull($lead->marketing_em);
        $this->assertSame(Landing::MARKETING, $lead->marketing_texto);
        $this->assertNull($lead->marketing_cancelado_em);
    }

    public function test_the_signed_link_cancels_promotional_emails_on_post_only(): void
    {
        Queue::fake();
        $this->pedir(['marketing' => true, 'email' => 'ana@exemplo.pt']);
        $id = DB::table('leads')->value('id');
        $url = URL::signedRoute('lead.emails.cancelar', ['lead' => $id]);

        $this->get('/emails/cancelar/'.$id)->assertForbidden();
        $this->get($url)->assertOk()->assertSee('Cancelar os emails promocionais?');
        $this->assertNull(DB::table('leads')->value('marketing_cancelado_em'));

        $this->post($url)->assertOk()->assertSee('Já não vai receber emails promocionais.');
        $this->assertNotNull(DB::table('leads')->value('marketing_cancelado_em'));
    }
}
