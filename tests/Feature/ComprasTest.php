<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Pagamentos\StripeWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
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

    /** A free lead whose reel is rendered; $limpo: also its clean copy (as RenderJob leaves it). */
    private function leadPronto(bool $limpo): object
    {
        Queue::fake();
        Livewire::test(Landing::class)->set(['anuncio' => self::ANUNCIO, 'consentimento' => true])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        $dir = sys_get_temp_dir().'/cm-compras-'.uniqid();
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/clip.mp4", 'MARCA');
        $meta = app(ClipStore::class)->find($lead->clip_id)->meta;
        if ($limpo) {
            file_put_contents("$dir/clip-limpo.mp4", 'LIMPO');
            $meta['limpo_path'] = "$dir/clip-limpo.mp4";
        }
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => "$dir/clip.mp4", 'meta' => $meta]);
        Session::forget('lead_id');

        return $lead;
    }

    public function test_paying_hands_over_the_clean_copy_at_once_and_signs_the_buyer_in(): void
    {
        $lead = $this->leadPronto(limpo: true);

        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();

        $compra = DB::table('compras')->sole();
        $this->assertSame('cs_test_1', $compra->sessao);
        $this->assertSame(2000, $compra->total);
        $this->assertSame(1, $compra->videos);
        $this->assertSame(1, $compra->videos_usados);
        $this->assertSame('ana@exemplo.pt', $compra->email);
        $this->assertSame('PT123456789', $compra->nif);
        $this->assertSame($lead->id, $compra->lead_id);
        $this->assertNotNull($compra->entregue_em);

        // The buyer's account: made from the checkout email, a customer, owning the lead.
        $user = User::query()->where('email', 'ana@exemplo.pt')->sole();
        $this->assertFalse($user->isAdmin());
        $this->assertSame($user->id, $compra->user_id);
        $this->assertSame($user->id, (int) DB::table('leads')->value('user_id'));
        $this->assertNotNull(DB::table('leads')->value('pago_em'));

        // Instant: the clip now serves the clean copy, nothing re-rendered.
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertFalse($clip->meta['free_lead']);
        $this->assertStringEndsWith('clip-limpo.mp4', $clip->output_path);
        Queue::assertNotPushed(RenderJob::class);
        $this->assertSame('LIMPO', file_get_contents(app(\App\Services\Leads\LeadVideo::class)->ficheiro(DB::table('leads')->sole())));

        // An email with the sign-in link went to the buyer.
        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $this->assertStringContainsString('/entrar/'.$user->id, $enviados[0]->toString());

        // Stripe retries: the same session is not recorded twice.
        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();
        $this->assertSame(1, DB::table('compras')->count());

        // The thank-you page signs them in (once) and shows the video right there.
        $this->get('/obrigado?session_id=cs_test_1')->assertOk()->assertSee('sem marca d’água', false)->assertSee('/v/'.$lead->id, false)->assertSee('Descarregar MP4');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(DB::table('compras')->value('login_em'));
        $this->get('/conta')->assertOk()->assertSee('SEM MARCA D’ÁGUA', false);
        $this->get('/imoveis')->assertRedirect('/conta');   // the studio is the operator's

        Auth::logout();
        $this->get('/obrigado?session_id=cs_test_1')->assertOk();
        $this->assertGuest();   // the session id signs in only once
    }

    public function test_paying_while_the_clean_copy_renders_hands_it_over_when_it_lands(): void
    {
        $lead = $this->leadPronto(limpo: false);
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $clip->update(['meta' => array_merge($clip->meta, ['limpo_pendente' => true])]);

        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();

        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertTrue($clip->meta['pago']);
        $this->assertTrue($clip->meta['free_lead']);   // still the watermarked file until the copy lands
        Queue::assertNotPushed(RenderJob::class);
    }

    public function test_paying_for_an_older_video_without_a_clean_copy_re_renders_it(): void
    {
        $lead = $this->leadPronto(limpo: false);

        $this->webhook($this->sessao(['client_reference_id' => 'lead-'.$lead->id]))->assertOk();

        $clip = app(ClipStore::class)->find($lead->clip_id);
        $this->assertFalse($clip->meta['free_lead']);
        $this->assertSame(ClipRecord::STATUS_RENDERING, $clip->status);
        Queue::assertPushed(RenderJob::class, fn ($j) => $j->projectId === $clip->id && ! $j->limpo);
    }

    public function test_the_thank_you_page_waits_for_the_webhook(): void
    {
        $this->get('/obrigado?session_id=cs_test_nao_chegou')->assertOk()->assertSee('A CONFIRMAR O PAGAMENTO')->assertSee('http-equiv="refresh"', false);
        $this->assertGuest();
    }

    public function test_a_pack_is_recorded_with_its_credits(): void
    {
        $this->webhook($this->sessao(['id' => 'cs_test_2', 'amount_total' => 10000, 'metadata' => ['videos' => '10']]))->assertOk();

        $compra = DB::table('compras')->sole();
        $this->assertSame(10, $compra->videos);
        $this->assertSame(0, $compra->videos_usados);
        $this->assertNull($compra->lead_id);
        $this->get('/obrigado?session_id=cs_test_2')->assertOk()->assertSee('pack de 10 vídeos');
        $this->assertSame(10, User::query()->where('email', 'ana@exemplo.pt')->sole()->creditos());
    }

    public function test_unpaid_sessions_are_ignored(): void
    {
        $this->webhook($this->sessao(['payment_status' => 'unpaid']))->assertOk();
        $this->assertSame(0, DB::table('compras')->count());
    }
}
