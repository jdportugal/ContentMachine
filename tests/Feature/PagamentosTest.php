<?php

namespace Tests\Feature;

use App\Livewire\Imoveis;
use App\Models\User;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Stripe Checkout, the webhook, and customers spending the videos they bought. */
class PagamentosTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);
    }

    private function cliente(): User
    {
        return User::factory()->create();
    }

    private function sessao(User $u, array $extra = []): array
    {
        return array_replace_recursive([
            'id' => 'cs_test_1', 'object' => 'checkout.session', 'payment_status' => 'paid',
            'amount_total' => 10000, 'currency' => 'eur', 'client_reference_id' => (string) $u->id,
            'metadata' => ['plano' => 'pack10'], 'payment_intent' => 'pi_1',
            'customer_details' => ['tax_ids' => [['type' => 'eu_vat', 'value' => '123456789']]],
        ], $extra);
    }

    private function webhook(string $tipo, array $objeto, ?string $segredo = 'whsec_test')
    {
        $payload = json_encode(['type' => $tipo, 'data' => ['object' => $objeto]]);
        $t = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t=$t,v1=".hash_hmac('sha256', "$t.$payload", $segredo),
        ], $payload);
    }

    private function comVideos(User $u, int $n = 1): int
    {
        return DB::table('compras')->insertGetId(['user_id' => $u->id, 'plano' => 'pack10', 'videos' => $n, 'restantes' => $n,
            'valor' => 10000, 'stripe_sessao' => 'cs_'.uniqid(), 'expira_em' => now()->addYear(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_guest_signs_up_before_paying(): void
    {
        $this->get('/comprar/pack10')->assertRedirect('/register');
        $this->get('/comprar/nada')->assertNotFound();
    }

    public function test_buying_opens_stripe_checkout_for_the_plan_price(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
        $u = $this->cliente();

        $this->actingAs($u)->get('/comprar/pack20')->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        Http::assertSent(fn (HttpRequest $r) => $r['line_items'][0]['price_data']['unit_amount'] === 17500
            && $r['client_reference_id'] === (string) $u->id && $r['metadata']['plano'] === 'pack20' && $r['mode'] === 'payment');
    }

    public function test_the_webhook_needs_a_valid_signature(): void
    {
        $u = $this->cliente();
        $this->webhook('checkout.session.completed', $this->sessao($u), 'whsec_wrong')->assertStatus(400);
        $this->assertSame(0, DB::table('compras')->count());
    }

    public function test_a_paid_session_becomes_a_purchase_once(): void
    {
        $u = $this->cliente();
        $this->webhook('checkout.session.completed', $this->sessao($u))->assertNoContent();
        $this->webhook('checkout.session.completed', $this->sessao($u))->assertNoContent();

        $c = DB::table('compras')->sole();
        $this->assertSame([10, 10, '123456789', 'pi_1'], [(int) $c->videos, (int) $c->restantes, $c->nif, $c->stripe_pagamento]);
        $this->assertSame(10, $u->videosDisponiveis());
    }

    public function test_multibanco_counts_only_once_paid_and_tampered_amounts_never(): void
    {
        $u = $this->cliente();
        $this->webhook('checkout.session.completed', $this->sessao($u, ['payment_status' => 'unpaid']));
        $this->webhook('checkout.session.completed', $this->sessao($u, ['id' => 'cs_2', 'amount_total' => 100]));
        $this->assertSame(0, DB::table('compras')->count());

        $this->webhook('checkout.session.async_payment_succeeded', $this->sessao($u));
        $this->assertSame(1, DB::table('compras')->count());
    }

    public function test_a_full_refund_takes_the_remaining_videos_back(): void
    {
        $u = $this->cliente();
        $this->webhook('checkout.session.completed', $this->sessao($u));
        $this->webhook('charge.refunded', ['payment_intent' => 'pi_1', 'refunded' => true]);

        $this->assertSame(0, $u->videosDisponiveis());
    }

    public function test_returning_from_checkout_records_the_purchase(): void
    {
        $u = $this->cliente();
        Http::fake(['api.stripe.com/*' => Http::response($this->sessao($u))]);

        $this->actingAs($u)->get('/imoveis?pago=cs_test_1')->assertOk()->assertSee('Pagamento recebido');
        $this->assertSame(10, $u->videosDisponiveis());
    }

    public function test_without_videos_a_customer_is_sent_to_buy(): void
    {
        Queue::fake();
        $u = $this->cliente();

        Livewire::actingAs($u)->test(Imoveis::class)->assertSee('Compre vídeos sem marca')
            ->set('anuncio', self::ANUNCIO)->call('criar')->assertHasErrors('anuncio');
        $this->assertSame(0, app(ClipStore::class)->all()->count());
    }

    public function test_each_video_spends_one_and_customers_see_only_their_own(): void
    {
        Queue::fake();
        $u = $this->cliente();
        $compra = $this->comVideos($u, 2);

        Livewire::actingAs($u)->test(Imoveis::class)->set('anuncio', self::ANUNCIO)->call('criar')->assertHasNoErrors();

        $this->assertSame(1, $u->videosDisponiveis());
        $video = DB::table('videos')->sole();
        $this->assertSame($compra, (int) $video->compra_id);
        $clip = app(ClipStore::class)->find($video->clip_id);
        $this->assertArrayNotHasKey('free_lead', $clip->meta);   // paid: no watermark

        $outro = $this->cliente();
        Livewire::actingAs($outro)->test(Imoveis::class)->assertCount('videos', 0);
        Livewire::actingAs($u)->test(Imoveis::class)->assertCount('videos', 1);
        $this->actingAs($outro)->get("/imoveis/{$clip->id}/video")->assertNotFound();
    }

    public function test_customers_cannot_reach_the_studio(): void
    {
        $u = $this->cliente();
        $this->actingAs($u)->get('/definicoes')->assertForbidden();
        $this->actingAs($u)->get('/clips-animados')->assertForbidden();
        $this->actingAs($u)->get('/imoveis')->assertOk()
            ->assertDontSee('href="'.route('definicoes').'"', false)   // the studio's settings: not for customers
            ->assertSee('Definições da conta');                         // their own account settings: yes
    }

    public function test_a_failed_reel_gives_the_video_back(): void
    {
        Queue::fake();
        $u = $this->cliente();
        $this->comVideos($u, 1);
        Livewire::actingAs($u)->test(Imoveis::class)->set('anuncio', self::ANUNCIO)->call('criar');
        $this->assertSame(0, $u->videosDisponiveis());

        app(ClipStore::class)->find(DB::table('videos')->value('clip_id'))->update(['status' => ClipRecord::STATUS_FAILED]);
        $this->artisan('videos:devolver')->assertSuccessful();
        $this->artisan('videos:devolver')->assertSuccessful();

        $this->assertSame(1, $u->videosDisponiveis());
        $this->assertNotNull(DB::table('videos')->value('devolvido_em'));
    }
}
