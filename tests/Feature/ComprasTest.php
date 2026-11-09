<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Pagamentos\StripeWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Paying through the Stripe Payment Links: the landing's buttons, the webhook, and what a purchase delivers. */
class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contentmachine.stripe.webhook_secret', self::SECRET);
        config()->set('contentmachine.stripe.links', ['avulso' => 'https://buy.stripe.com/avulso', 'pack10' => 'https://buy.stripe.com/pack10', 'pack20' => '']);
    }

    private function sessao(array $extra = []): array
    {
        return $extra + [
            'id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_link' => 'plink_1',
            'amount_total' => 2000, 'currency' => 'eur', 'metadata' => ['videos' => '1', 'app' => 'imocena'],
            'customer_details' => ['email' => 'ana@exemplo.pt', 'name' => 'Ana Silva', 'tax_ids' => []],
            'custom_fields' => [['key' => 'nifvatparaafatura', 'type' => 'text', 'label' => ['type' => 'custom', 'custom' => 'NIF / VAT (para a fatura)'], 'text' => ['value' => ' PT123456789 ']]],
        ];
    }

    private function webhook(array $sessao, ?string $header = null)
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => $sessao]]);

        return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $header ?? StripeWebhook::sign($payload, self::SECRET)], $payload);
    }

    public function test_the_buy_buttons_open_the_payment_links_and_fall_back_to_email(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('https://buy.stripe.com/avulso?locale=pt', false)
            ->assertSee('https://buy.stripe.com/pack10?locale=pt', false)
            ->assertSee('mailto:', false);   // pack20 has no link yet → email
    }

    public function test_a_visitor_with_a_free_video_carries_the_lead_into_checkout(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->set(['anuncio' => self::ANUNCIO, 'consentimento' => true])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();

        $this->get('/')->assertSee('https://buy.stripe.com/avulso?client_reference_id=lead-'.$lead->id.'&amp;locale=pt', false);
    }

    public function test_the_webhook_rejects_bad_signatures(): void
    {
        $this->webhook($this->sessao(), 't=1,v1=deadbeef')->assertStatus(400);
        $this->webhook($this->sessao(), StripeWebhook::sign('other', self::SECRET))->assertStatus(400);
        $this->assertSame(0, DB::table('compras')->count());
    }

    public function test_a_paid_single_video_is_recorded_and_the_leads_reel_is_rendered_clean(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->set(['anuncio' => self::ANUNCIO, 'consentimento' => true])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => '/tmp/x.mp4']);
        Queue::assertPushed(ImportListingJob::class);

        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();

        $compra = DB::table('compras')->sole();
        $this->assertSame('cs_test_1', $compra->sessao);
        $this->assertSame(2000, $compra->total);
        $this->assertSame(1, $compra->videos);
        $this->assertSame('ana@exemplo.pt', $compra->email);
        $this->assertSame('PT123456789', $compra->nif);
        $this->assertSame($lead->id, $compra->lead_id);
        $this->assertNotNull($compra->entregue_em);
        $this->assertNotNull(DB::table('leads')->value('pago_em'));

        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertFalse($clip->meta['free_lead']);
        $this->assertSame(ClipRecord::STATUS_RENDERING, $clip->status);
        Queue::assertPushed(RenderJob::class, fn ($j) => $j->projectId === $clip->id);

        // Stripe retries: the same session is not recorded or rendered twice.
        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();
        $this->assertSame(1, DB::table('compras')->count());
        Queue::assertPushed(RenderJob::class, 1);

        $this->get('/obrigado?session_id=cs_test_1')->assertOk()->assertSee('versão limpa')->assertSee('/v/'.$lead->id, false);
    }

    public function test_a_pack_is_recorded_with_its_credits(): void
    {
        $this->webhook($this->sessao(['id' => 'cs_test_2', 'amount_total' => 10000, 'metadata' => ['videos' => '10']]))->assertOk();

        $compra = DB::table('compras')->sole();
        $this->assertSame(10, $compra->videos);
        $this->assertSame(0, $compra->videos_usados);
        $this->assertNull($compra->lead_id);
        $this->get('/obrigado?session_id=cs_test_2')->assertOk()->assertSee('10 vídeos');
    }

    public function test_unpaid_sessions_are_ignored(): void
    {
        $this->webhook($this->sessao(['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame(0, DB::table('compras')->count());
    }
}
