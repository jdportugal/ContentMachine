<?php

namespace Tests\Feature;

use App\Jobs\Imoplanta\AereoJob;
use App\Jobs\Imoplanta\AlterarReferenciaJob;
use App\Jobs\Imoplanta\QuadroJob;
use App\Jobs\Imoplanta\AlterarModeloJob;
use App\Jobs\Imoplanta\FotografarJob;
use App\Jobs\Imoplanta\VideoJob;
use App\Jobs\Imoplanta\LerPlantasJob;
use App\Jobs\Imoplanta\PlantaVideoJob;
use App\Jobs\Clips\ImportListingJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Symfony\Component\Process\Process;
use App\Livewire\Imoplanta\Imovel;
use App\Livewire\Imoplanta\Lista;
use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use App\Models\User;
use App\Services\Pagamentos\Creditos;
use App\Services\Imoplanta\ImoplantaCasa;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\Fotografo;
use App\Services\Imoplanta\Imoplantas;
use App\Services\Imoplanta\LeitorPlantas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Imoplanta: credit packs, spending a credit per property, re-do limits, cameras, the browser-render upload,
 * jobs and their costs. The 3D model and the renders are three.js in the browser: no server build step.
 */
class ImoplantaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['contentmachine.stripe.webhook_secret' => 'whsec_test']);
        foreach (['imoplanta1', 'imoplanta5', 'imoplanta10', 'extra_planta', 'extra_video', 'extra_ambos'] as $plano) {
            config(['contentmachine.stripe.links.'.$plano => 'https://buy.stripe.com/test_'.$plano]);
        }
        // Imoplanta is open to one email for now (imoplanta.acesso); these tests are about Imoplanta itself
        \Illuminate\Support\Facades\Gate::define('imoplanta', fn () => true);
    }

    public function test_the_landing_page_shows_the_steps_with_the_demo_flat_gifs(): void
    {
        $r = $this->get(route('plantas.sobre'))->assertOk()->assertSee('Da planta ao vídeo do imóvel')
            ->assertSee(route('plantas'), false)->assertSee("50\u{00A0}€")
            ->assertSee('EXTRAS OPCIONAIS')->assertDontSee("15\u{00A0}€")->assertDontSee("20\u{00A0}€");   // the add-ons: shown, not priced
        foreach (['capa.gif', 'passo-1-planta.gif', 'passo-2-modelo.gif', 'passo-3-acabamentos.gif', 'passo-4-fotos.gif', 'passo-5-video.gif', 'video-imocena.mp4', 'video-imocena.jpg'] as $f) {
            $r->assertSee(asset('imoplanta-demo/landing/'.$f), false);
            $this->assertFileExists(public_path('imoplanta-demo/landing/'.$f));
        }
    }

    public function test_for_now_only_the_listed_email_has_imoplanta(): void
    {
        \Illuminate\Support\Facades\Gate::define('imoplanta', fn (User $u) => $u->veImoplanta() || $u->is_admin);   // the real rule
        $outro = User::factory()->cliente()->create();
        $joao = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        $p = $this->imovel($outro);

        $this->actingAs($outro)->get(route('conta'))->assertOk()->assertDontSee(route('imoplanta'), false);   // no way in
        $this->actingAs($outro)->get(route('imoplanta'))->assertForbidden();
        $this->actingAs($outro)->get(route('imoplanta.imovel', $p->id))->assertForbidden();
        $this->actingAs($joao)->get(route('conta'))->assertSee(route('imoplanta'), false);                      // from their account
        $this->actingAs($joao)->get(route('imoplanta'))->assertOk();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('imoplanta'))->assertOk();   // admins can open it
    }

    private function casa(): array
    {
        return ImoplantaCasa::normalizar(json_decode(file_get_contents(base_path('scripts/imoplanta/exemplo_moradia.json')), true));
    }

    /** A purchase of $pack property credits ($n of them left), as live's webhook records it. */
    private function comCreditos(User $u, int $n = 1, int $pack = 10, int $total = 40000): int
    {
        return DB::table('compras')->insertGetId(['user_id' => $u->id, 'sessao' => 'cs_'.uniqid(), 'email' => $u->email, 'total' => $total,
            'videos' => $pack * Creditos::IMOPLANTA, 'videos_usados' => ($pack - $n) * Creditos::IMOPLANTA, 'imoveis' => $pack, 'imoveis_usados' => $pack, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A paid Payment Link checkout session, as Stripe sends it: the buyer's email, the link's metadata, our reference. */
    private function sessao(User $u, string $id, int $total, array $metadata, ?string $ref = null): array
    {
        return ['id' => $id, 'payment_status' => 'paid', 'amount_total' => $total, 'currency' => 'eur', 'customer_details' => ['email' => $u->email],
            'metadata' => $metadata, 'client_reference_id' => $ref, 'payment_link' => 'plink_test'];
    }

    /** @return list<string> the HTML of every email sent (the array mailer) */
    private function emails(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage()->getHtmlBody(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    private function imovel(User $u, array $extra = []): Imoplanta
    {
        return Imoplanta::create($extra + ['user_id' => $u->id, 'nome' => 'Moradia', 'estado' => Imoplanta::MODELO, 'plantas' => ['imoplanta/1/plantas/a.png'],
            'casa' => $this->casa(), 'versao' => 1, 'cameras' => [['u' => 30, 'v' => 25, 'altura' => 1.6, 'yaw' => 310, 'fov' => 70]]]);
    }

    private function webhook(array $sessao)
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $sessao]]);
        $t = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t=$t,v1=".hash_hmac('sha256', "$t.$payload", 'whsec_test'),
        ], $payload);
    }

    // ── purchase ──────────────────────────────────────────────────────────

    public function test_each_tier_is_its_payment_link_with_the_buyer_and_the_property(): void
    {
        $u = User::factory()->cliente()->create(['email' => 'ana@exemplo.pt']);
        $p = $this->imovel($u, ['estado' => Imoplanta::A_PAGAR]);

        $this->assertSame('https://buy.stripe.com/test_imoplanta10?client_reference_id=imoplanta-'.$p->id.'&prefilled_email=ana%40exemplo.pt&locale=pt',
            Imoplantas::link($u, 'imoplanta10', $p));
        $this->assertSame('https://buy.stripe.com/test_imoplanta1?prefilled_email=ana%40exemplo.pt&locale=pt', Imoplantas::link($u, 'imoplanta1'));   // a pack: no property
        config(['contentmachine.stripe.links.imoplanta20' => '']);
        $this->assertStringStartsWith('mailto:', Imoplantas::link($u, 'imoplanta20'));   // no link yet: an email to us, as the video buttons
        // €50 / €45 / €40 per property, for 1, 5 or 10 (the prices shown; the link charges its own)
        $this->assertSame([5000, 4500, 4000], collect(config('contentmachine.imoplanta.planos'))->map(fn ($p) => intdiv($p['preco'], $p['imoveis']))->values()->all());
    }

    public function test_a_paid_property_pack_becomes_video_credits(): void
    {
        $u = User::factory()->cliente()->create();
        $this->webhook($this->sessao($u, 'cs_1', 60000, ['imoveis' => '20']))->assertOk();

        $this->assertSame(20, (int) DB::table('compras')->sole()->imoveis);
        $this->assertSame(100, $u->creditos());   // 20 properties = 100 video credits, one balance
    }

    // ── the public plans page: email → plans → payment ────────────────────

    public function test_anyone_goes_from_email_to_plans_to_payment_and_paying_starts_the_property(): void
    {
        Queue::fake();
        $this->get('/plantas')->assertRedirect(route('login'));   // for now behind the sign-in (and the imoplanta gate)

        // 1. the email: a new one gets its account, no password
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('email', ' Ana@Exemplo.pt')->call('continuar')->assertHasErrors(['consentimento']);
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('email', ' Ana@Exemplo.pt')->set('consentimento', true)->call('continuar')->assertHasNoErrors();
        $u = User::where('email', 'ana@exemplo.pt')->sole();
        $this->assertAuthenticatedAs($u);
        $this->assertFalse($u->isAdmin());

        // 2. the plans: kept, not read, no credit spent
        $c = Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('nome', 'Moradia T3')->set('plantas', [UploadedFile::fake()->image('planta.png')])
            ->set('anuncio', 'https://www.exemplo.pt/anuncio/1')->set('notas', 'Cozinha branca, soalho de carvalho.')
            ->call('enviar')->assertHasNoErrors()
            ->assertSee('Tudo pronto para começar')->assertSee('Criar o meu imóvel')->assertDontSee('Pagar');
        $p = Imoplanta::sole();
        $this->assertSame([Imoplanta::A_PAGAR, null, 'https://www.exemplo.pt/anuncio/1', 'Cozinha branca, soalho de carvalho.'], [$p->estado, $p->compra_id, $p->anuncio, $p->notas]);
        Queue::assertNothingPushed();

        // 3. the payment, for this property: its Payment Link
        $c->call('pagar')->assertRedirect(Imoplantas::link($u, 'imoplanta1', $p));

        $pago = $this->sessao($u, 'cs_9', 5000, ['imoveis' => '1'], 'imoplanta-'.$p->id);
        $this->webhook($pago)->assertOk();
        $this->webhook($pago)->assertOk();   // Stripe retries: still one property started, one credit spent

        $p->refresh();
        $this->assertSame(Imoplanta::A_LER, $p->estado);
        $this->assertSame(DB::table('compras')->sole()->id, $p->compra_id);
        $this->assertSame(0, intdiv($u->creditos(), Creditos::IMOPLANTA));
        Queue::assertPushed(LerPlantasJob::class, 1);

        // back from Stripe (/obrigado): straight to the editor
        $this->get('/obrigado?session_id=cs_9')->assertRedirect(route('imoplanta.imovel', $p->id));
    }

    public function test_the_plans_page_emails_existing_accounts_a_link_back_and_starts_at_once_with_credit(): void
    {
        Queue::fake();
        $outro = User::factory()->cliente()->create(['email' => 'rui@exemplo.pt']);
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('email', 'Rui@exemplo.pt')->set('consentimento', true)->call('continuar')
            ->assertHasNoErrors()->assertSee('Veja o seu email');
        $this->assertGuest();   // an account with videos and credits: only its inbox gets in
        $this->assertCount(1, $this->emails());
        preg_match('/href="([^"]+entrar[^"]+)"/', $this->emails()[0], $m);
        $this->get(html_entity_decode($m[1]))->assertRedirect('/plantas');   // the link signs in, back to the plans
        $this->assertAuthenticatedAs($outro);

        $admin = User::factory()->create(['email' => 'admin@exemplo.pt', 'is_admin' => true]);
        auth()->logout();
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('email', 'admin@exemplo.pt')->set('consentimento', true)->call('continuar')->assertSee('Veja o seu email');
        $this->assertCount(1, $this->emails());   // the operator is never mailed a link

        $this->comCreditos($outro);
        $this->actingAs($outro);
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('nome', 'Casa')->set('plantas', [UploadedFile::fake()->image('planta.png')])->call('enviar')
            ->assertRedirect(route('imoplanta.imovel', Imoplanta::sole()->id));
        $this->assertSame(Imoplanta::A_LER, Imoplanta::sole()->estado);
        Queue::assertPushed(LerPlantasJob::class, 1);
    }


    public function test_a_customer_gets_an_email_when_the_model_is_ready_and_their_notes_reach_the_ai(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::A_LER, 'casa' => null, 'cameras' => null, 'versao' => 0,
            'notas' => 'Cozinha branca, «soalho» de carvalho.', 'anuncio' => 'https://www.exemplo.pt/a/1']);
        Storage::disk('local')->put('imoplanta/1/plantas/a.png', UploadedFile::fake()->image('a.png')->getContent());
        config(['services.anthropic.key' => 'sk-ant-api-test']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($this->casa())]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]])]);

        app()->call([new LerPlantasJob($p->id), 'handle']);

        Http::assertSent(function (HttpRequest $r) {
            $texto = collect($r['messages'][0]['content'])->where('type', 'text')->pluck('text')->implode(' ');

            return str_contains($texto, 'Cozinha branca, "soalho" de carvalho.') && str_contains($texto, 'https://www.exemplo.pt/a/1');
        });
        $this->assertCount(1, $this->emails());   // the model-ready email: a sign-in link straight to the property
        $this->assertStringContainsString('Moradia', $this->emails()[0]);
        $this->assertStringContainsString('para=%2Fimoplanta%2F'.$p->id, $this->emails()[0]);
        $this->assertStringContainsString('Cozinha branca', app(\App\Services\Imoplanta\Acabamentos::class)->texto($p->refresh()));
    }

    public function test_the_free_account_is_a_normal_customer_whose_prices_are_all_zero(): void
    {
        Queue::fake();
        Mail::fake();
        Http::fake();
        config(['contentmachine.imoplanta.gratis' => ['joaodiogocferreira@gmail.com']]);

        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('email', 'JoaoDiogoCFerreira@gmail.com')->set('consentimento', true)->call('continuar')->assertSee('Fale-nos do imóvel');
        $u = User::where('email', 'joaodiogocferreira@gmail.com')->sole();
        $this->assertAuthenticatedAs($u);   // straight in, no email check
        $this->assertTrue($u->imoplantaGratis());
        $this->assertFalse($u->is_admin);
        Mail::assertNothingSent();

        $this->actingAs($u);
        $c = Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('nome', 'Casa')->set('plantas', [UploadedFile::fake()->image('planta.png')])->call('enviar')
            ->assertSee('Tudo pronto para começar')->assertSee("0\u{00A0}€")->assertSee('Sem custo')->assertDontSee("50\u{00A0}€")->assertDontSee('Stripe');
        $p = Imoplanta::sole();
        $c->call('pagar')->assertRedirect(route('imoplanta.imovel', $p->id));

        Http::assertNothingSent();   // no Stripe
        $this->assertSame([Imoplanta::A_LER, null], [$p->refresh()->estado, $p->compra_id]);
        $this->assertSame(0, DB::table('compras')->count());
        Queue::assertPushed(LerPlantasJob::class, 1);

        $this->get(route('imoplanta'))->assertSee('custa <strong>0 €</strong>', false)->assertDontSee("50\u{00A0}€");
        $this->assertFalse(User::factory()->cliente()->create(['email' => 'outra@gmail.com'])->imoplantaGratis());
    }

    public function test_the_last_step_sells_packs_and_the_rest_stay_as_available_properties(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $this->actingAs($u);
        $c = Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('nome', 'Casa')->set('plantas', [UploadedFile::fake()->image('planta.png')])->call('enviar')
            ->assertSee('Quantos créditos de vídeo?')->assertSee('10 imóveis')->assertSee("40\u{00A0}€ por imóvel")
            ->set('plano', 'imoplanta10')->assertSee("400\u{00A0}€")->assertSee('Os outros 45 ficam na sua conta')->assertSee('50 créditos');
        $p = Imoplanta::sole();
        $c->call('pagar')->assertRedirect(Imoplantas::link($u, 'imoplanta10', $p));

        $this->webhook($this->sessao($u, 'cs_10', 40000, ['imoveis' => '10'], 'imoplanta-'.$p->id))->assertOk();

        $this->assertSame(Imoplanta::A_LER, $p->refresh()->estado);
        $this->assertSame(9, intdiv($u->creditos(), Creditos::IMOPLANTA));
        $this->get('/plantas')->assertSee('45</strong> créditos de vídeo', false);       // the count in the top bar
        $this->get(route('imoplanta'))->assertSee('CRÉDITOS DE VÍDEO<br>DISPONÍVEIS', false)->assertDontSee('Cada imóvel usa 5 créditos de vídeo</p>', false);   // packs only below a property's 5
        Livewire::test(\App\Livewire\Imoplanta\Comecar::class)->set('plano', 'inventado')->set('nome', 'Outra')
            ->set('plantas', [UploadedFile::fake()->image('p.png')])->call('enviar')->assertRedirect();   // with credit: straight to the editor
    }

    public function test_a_property_not_paid_for_has_no_editor_yet(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::A_PAGAR, 'casa' => null, 'cameras' => null]);

        $this->actingAs($u)->get(route('imoplanta.imovel', $p->id))->assertRedirect(route('plantas'));
        $this->get(route('imoplanta'))->assertSee('Por pagar')->assertSee('href="'.route('plantas').'"', false);
    }

    public function test_changing_the_plans_before_paying_deletes_the_old_ones(): void
    {
        $u = User::factory()->cliente()->create();
        $this->actingAs($u);
        $c = Livewire::test(\App\Livewire\Imoplanta\Comecar::class)
            ->set('nome', 'Casa')->set('plantas', [UploadedFile::fake()->image('planta.png')])->call('enviar');
        $p = Imoplanta::sole();
        Storage::disk('local')->assertExists($p->plantas[0]);

        $c->call('trocarPlantas')->assertSet('nome', 'Casa')->assertSee('Fale-nos do imóvel');
        $this->assertSame(0, Imoplanta::count());
        Storage::disk('local')->assertMissing($p->plantas[0]);
    }

    // ── credits ───────────────────────────────────────────────────────────

    public function test_without_credit_no_property_is_made(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        DB::table('compras')->insert(['user_id' => $u->id, 'sessao' => 'cs_v', 'total' => 2000, 'videos' => 4, 'created_at' => now(), 'updated_at' => now()]);   // fewer than a property's 5 credits

        // not bought yet: what Imoplanta is, the five steps and the packs; no upload form
        Livewire::actingAs($u)->test(Lista::class)->assertSee('Da planta ao vídeo do imóvel')->assertSee('Cinco passos, do papel ao vídeo')
            ->assertSee('Comprar 5 créditos')->assertSee('imoplanta-demo/landing/capa.gif')->assertDontSee('Criar modelo 3D')
            ->set('nome', 'Casa')->set('plantas', [UploadedFile::fake()->image('planta.png')])->call('criar')->assertHasErrors('plantas');

        $this->assertSame(0, Imoplanta::count());
        Queue::assertNothingPushed();
    }

    public function test_creating_a_property_spends_one_credit_up_front_and_starts_reading(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $compra = $this->comCreditos($u, 2);   // 2 left of a pack of 10 (€400)

        Livewire::actingAs($u)->test(Lista::class)
            ->set('nome', 'Moradia T4')->set('plantas', [UploadedFile::fake()->image('planta.png'), UploadedFile::fake()->image('alcado.jpg')])
            ->call('criar')->assertHasNoErrors()->assertRedirect();

        $p = Imoplanta::sole();
        $this->assertSame([1, $compra, Imoplanta::A_LER, 2], [intdiv($u->creditos(), Creditos::IMOPLANTA), $p->compra_id, $p->estado, count($p->plantas)]);
        Storage::disk('local')->assertExists($p->plantas[0]);
        Queue::assertPushed(LerPlantasJob::class, fn ($j) => $j->imovel === $p->id);
        // Stripe's fee on a €400 pack of 10, per credit: (1.5% × 400 + 0.25) / 10
        $this->assertEqualsWithDelta(0.625, (float) DB::table('custos')->where(['peca' => (string) $p->id, 'passo' => 'stripe_fee'])->value('custo'), 0.0001);
    }

    public function test_the_property_type_and_a_flats_floor_reach_the_plan_reader_and_the_3d_model(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $this->comCreditos($u, 2);
        $planta = fn () => [UploadedFile::fake()->image('planta.png')];

        // a flat needs its floor; nothing is spent without it
        Livewire::actingAs($u)->test(Lista::class)->set('nome', 'T2')->set('tipologia', 'apartamento')->set('plantas', $planta())
            ->call('criar')->assertHasErrors('piso');
        $this->assertSame(0, Imoplanta::count());

        Livewire::actingAs($u)->test(Lista::class)->set('nome', 'T2')->set('tipologia', 'apartamento')->set('piso', 3)->set('plantas', $planta())
            ->call('criar')->assertHasNoErrors();
        $p = Imoplanta::sole();
        $this->assertSame(['apartamento', 3, 'Apartamento · 3.º andar'], [$p->tipologia, $p->piso, $p->tipologiaRotulo()]);
        $this->assertStringContainsString('APARTMENT on floor 3', $p->contextoPlantas());
        $p->update(['casa' => ['descricao' => 'x']]);
        $this->assertSame(['tipo' => 'apartamento', 'piso' => 3], array_slice($p->casaParaVer(), 0, 2));

        Livewire::actingAs($u)->test(Lista::class)->set('nome', 'Banda')->set('tipologia', 'banda')->set('piso', 5)->set('plantas', $planta())
            ->call('criar')->assertHasNoErrors();
        $b = Imoplanta::latest('id')->first();
        $this->assertSame(['banda', null], [$b->tipologia, $b->piso]);   // a floor only means something for a flat
        $this->assertStringContainsString('TERRACED', $b->contextoPlantas());

        Livewire::actingAs($u)->test(Lista::class)->set('nome', 'X')->set('tipologia', 'castelo')->set('plantas', $planta())
            ->call('criar')->assertHasErrors('tipologia');
    }

    public function test_the_photo_prompt_follows_where_the_camera_stands_and_never_invents_a_pool(): void
    {
        $casa = ImoplantaCasa::normalizar(json_decode(file_get_contents(base_path('scripts/imoplanta/exemplo_moradia.json')), true));
        $casa['descricao'] = 'Moradia com piscina e relvado';
        $quarto = collect($casa['niveis'][0]['divisoes'])->firstWhere('uso', 'quarto');
        $u = array_sum(array_column($quarto['poligono'], 0)) / count($quarto['poligono']);
        $v = array_sum(array_column($quarto['poligono'], 1)) / count($quarto['poligono']);
        $f = app(\App\Services\Imoplanta\Fotografo::class);

        $dentro = $f->prompt($casa, ['u' => $u, 'v' => $v, 'altura' => 1.5, 'yaw' => 0, 'fov' => 80]);
        $this->assertStringContainsString('INTERIOR photograph of the bedroom', $dentro);
        $this->assertStringNotContainsString('piscina', $dentro);

        $fora = $f->prompt($casa, ['u' => -30, 'v' => -30, 'altura' => 1.6, 'yaw' => 0, 'fov' => 70]);
        $this->assertStringContainsString('eye-level exterior photograph', $fora);
        $this->assertStringContainsString('do not add a pool', $fora);
        $this->assertStringNotContainsString('piscina', $fora);

        $this->assertStringContainsString('aerial drone photograph', $f->prompt($casa, ['u' => $u, 'v' => $v, 'altura' => 30, 'yaw' => 0, 'fov' => 70]));
    }

    public function test_a_terrace_keeps_whether_it_is_covered_and_an_upper_floor_height(): void
    {
        $casa = json_decode(file_get_contents(base_path('scripts/imoplanta/exemplo_moradia.json')), true);
        $casa['terracos'] = [
            ['poligono' => [[0, 0], [3, 0], [3, 2], [0, 2]], 'altura' => 6.2, 'coberto' => false],   // a rooftop terrace
            ['poligono' => [[0, 0], [3, 0], [3, 2], [0, 2]]],                                         // unsaid: the builder decides
        ];
        $t = ImoplantaCasa::normalizar($casa)['terracos'];

        $this->assertSame([6.2, false], [$t[0]['altura'], $t[0]['coberto']]);
        $this->assertArrayNotHasKey('coberto', $t[1]);
        $this->assertStringContainsString('"coberto"', ImoplantaCasa::esquema());   // the plan reader is told about it
    }

    public function test_a_flats_first_cameras_are_inside_its_rooms_at_eye_level(): void
    {
        $casa = ImoplantaCasa::normalizar(json_decode(file_get_contents(base_path('scripts/imoplanta/exemplo_moradia.json')), true));
        $cams = ImoplantaCasa::camerasIniciais($casa, 'apartamento');
        $salas = array_values(array_filter($casa['niveis'][0]['divisoes'], fn ($d) => in_array($d['uso'], ['sala', 'cozinha', 'quarto', 'wc'], true)));

        $this->assertNotEmpty($cams);
        foreach ($cams as $c) {
            $this->assertSame([1.65, 110], [$c['altura'], $c['fov']]);   // 1.5 m above the floor; the widest lens, as the editor's
            $this->assertTrue(collect($salas)->contains(fn ($d) => $this->dentroDe([$c['u'], $c['v']], $d['poligono'])), 'camera outside every room');
        }
        $this->assertSame(1.6, ImoplantaCasa::camerasIniciais($casa)[0]['altura']);   // a house keeps its outside cameras
    }

    public function test_a_houses_first_cameras_show_the_front_and_the_main_rooms(): void
    {
        $casa = $this->casa();
        $cams = ImoplantaCasa::camerasIniciais($casa);
        [$u1, $v1, $u2, $v2] = ImoplantaCasa::limites($casa);
        $usos = fn ($cs) => array_map(fn ($c) => ImoplantaCasa::divisaoDa($casa, $c)['uso'] ?? 'fora', $cs);

        $this->assertSame(['fora', 'fora', 'sala', 'cozinha', 'quarto', 'wc'], $usos($cams));
        $this->assertGreaterThan($u2, $cams[0]['u']);   // the street runs along the right: that is the front
        $this->assertGreaterThan($u2, $cams[1]['u']);
        $semRua = ImoplantaCasa::camerasIniciais(['rua' => null] + $casa);   // no street drawn: the bottom of the drawing
        $this->assertTrue($semRua[0]['v'] > $v2 && $semRua[1]['v'] > $v2);
        $this->assertSame([110], array_values(array_unique(array_column($cams, 'fov'))));

        $banda = ImoplantaCasa::camerasIniciais($casa, 'banda');   // the sides touch the neighbours: front and back, straight on
        $this->assertSame(['fora', 'fora'], array_slice($usos($banda), 0, 2));
        $this->assertTrue($banda[0]['u'] > $u2 && $banda[1]['u'] < $u1);
    }

    private function dentroDe(array $p, array $poly): bool
    {
        $c = false;
        for ($i = 0, $j = count($poly) - 1; $i < count($poly); $j = $i++) {
            if (($poly[$j][1] > $p[1]) !== ($poly[$i][1] > $p[1])
                && $p[0] < $poly[$j][0] + ($p[1] - $poly[$j][1]) * ($poly[$i][0] - $poly[$j][0]) / ($poly[$i][1] - $poly[$j][1])) {
                $c = ! $c;
            }
        }

        return $c;
    }

    // ── re-do limits ──────────────────────────────────────────────────────

    public function test_three_ai_changes_to_the_3d_model_and_no_more(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u);
        $svc = app(Imoplantas::class);

        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue($svc->pedirAlteracao($p->refresh(), "mudança $i"));
            $this->assertFalse($svc->pedirAlteracao($p->refresh(), 'enquanto trabalha'));   // busy: not spent twice
            $p->update(['estado' => Imoplanta::MODELO]);
        }
        $this->assertFalse($svc->pedirAlteracao($p->refresh(), 'a quarta'));
        $this->assertSame(3, $p->refresh()->refeitos_3d);
        Queue::assertPushed(AlterarModeloJob::class, 3);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->set('pedido', 'mais uma alteração')->call('alterarModelo')->assertHasErrors('pedido');
    }

    public function test_a_failed_3d_change_is_given_back(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::A_ALTERAR, 'refeitos_3d' => 1]);
        config(['services.anthropic.key' => 'sk-ant-api-test', 'contentmachine.clips.claude_attempts' => 1]);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'não sei']], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 10]])]);

        (new AlterarModeloJob($p->id, 'telhado de duas águas'))->handle(app(LeitorPlantas::class), app(ImoplantaCustos::class), app(Imoplantas::class));

        $p->refresh();
        $this->assertSame([Imoplanta::MODELO, 0], [$p->estado, $p->refeitos_3d]);
        $this->assertNotNull($p->erro);
        $this->assertSame(1, DB::table('custos')->where(['peca' => (string) $p->id, 'passo' => 'ai_change_3d'])->count());   // the call still cost money
    }

    public function test_two_ai_changes_per_image(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::PRONTA, 'render' => 'r.png', 'foto' => 'f.png']);
        $svc = app(Imoplantas::class);

        $this->assertTrue($svc->alterarImagem($img, 'relva mais verde'));
        $this->assertFalse($svc->alterarImagem($img->refresh(), 'enquanto trabalha'));
        $img->update(['estado' => ImoplantaImagem::PRONTA]);
        $this->assertTrue($svc->alterarImagem($img->refresh(), 'sem carro'));
        $img->update(['estado' => ImoplantaImagem::PRONTA]);
        $this->assertFalse($svc->alterarImagem($img->refresh(), 'a terceira'));
        $this->assertSame(2, $img->refresh()->refeitos);
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->refazer && $j->imagem === $img->id);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->set("pedidosImagem.{$img->id}", 'outra alteração')
            ->call('alterarImagem', $img->id)->assertHasErrors("pedidosImagem.{$img->id}");
    }

    public function test_a_photo_can_be_made_again_from_its_render_using_one_change_admins_without_limit(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::PRONTA, 'render' => 'r.png', 'foto' => 'f.png']);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('gerarImagemDeNovo', $img->id)->assertHasNoErrors();
        $img->refresh();
        $this->assertSame([ImoplantaImagem::A_FOTOGRAFAR, 1, 'f.png'], [$img->estado, $img->refeitos, $img->foto]);   // old photo kept until the new one lands
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->imagem === $img->id && ! $j->refazer && $j->novamente);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('gerarImagemDeNovo', $img->id)->assertHasErrors("pedidosImagem.{$img->id}");   // still working

        $img->update(['estado' => ImoplantaImagem::PRONTA, 'refeitos' => 2]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('gerarImagemDeNovo', $img->id)->assertHasErrors("pedidosImagem.{$img->id}");   // no changes left
        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(Imovel::class, ['imovel' => $p])->call('gerarImagemDeNovo', $img->id)->assertHasNoErrors();
        $this->assertSame([ImoplantaImagem::A_FOTOGRAFAR, 2], [$img->refresh()->estado, $img->refeitos]);

        // a failed one is made again for free, alone
        $falhou = $p->imagens()->create(['numero' => 2, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::FALHOU, 'render' => 'r2.png', 'erro' => 'kie']);
        $semRender = $p->imagens()->create(['numero' => 3, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::FALHOU]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Gerar de novo (sem custo)')->call('gerarImagemDeNovo', $falhou->id)->assertHasNoErrors();
        $this->assertSame([ImoplantaImagem::A_FOTOGRAFAR, 0, null], [$falhou->refresh()->estado, $falhou->refeitos, $falhou->erro]);
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->imagem === $falhou->id && ! $j->novamente);
        $this->assertSame(ImoplantaImagem::FALHOU, $semRender->refresh()->estado);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('gerarImagemDeNovo', $semRender->id);
        $this->assertSame(ImoplantaImagem::A_RENDERIZAR, $semRender->refresh()->estado);   // the page renders its view again
    }

    // ── authorization ─────────────────────────────────────────────────────

    public function test_customers_see_only_their_own_properties(): void
    {
        $dono = User::factory()->cliente()->create();
        $outro = User::factory()->cliente()->create();
        $p = $this->imovel($dono, ['nome' => 'Casa do dono']);
        Storage::disk('local')->put('imoplanta/1/plantas/a.png', 'png');

        $this->actingAs($outro)->get(route('imoplanta.imovel', $p->id))->assertNotFound();
        $this->actingAs($outro)->get(route('imoplanta.ficheiro', [$p->id, 'planta', 0]))->assertNotFound();
        $this->actingAs($outro)->get(route('imoplanta'))->assertOk()->assertDontSee('Casa do dono');
        Livewire::actingAs($outro)->test(Imovel::class, ['imovel' => $p])->assertNotFound();

        $this->actingAs($dono)->get(route('imoplanta'))->assertOk()->assertSee('Casa do dono');
        // the page carries the house description the browser builds the 3D model from, and the planner
        $this->actingAs($dono)->get(route('imoplanta.imovel', $p->id))->assertOk()->assertSee('Modelo 3D e câmaras')
            ->assertSee('x-data="imoplanta(', false);
        $this->actingAs($dono)->get(route('imoplanta.ficheiro', [$p->id, 'planta', 0]))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('imoplanta.imovel', $p->id))->assertOk();
    }

    public function test_costs_are_for_admins_only(): void
    {
        $this->actingAs(User::factory()->cliente()->create())->get(route('custos.imoplanta'))->assertRedirect(route('conta'));   // the studio sends customers to their account
        $this->actingAs(User::factory()->admin()->create())->get(route('custos.imoplanta'))->assertOk()->assertSee('Custo vs. receita');
        $this->artisan('imoplanta:custos')->assertSuccessful();
    }

    // ── cameras ───────────────────────────────────────────────────────────

    public function test_cameras_are_validated_and_saved(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u);
        $ok = [['u' => 10, 'v' => 30, 'altura' => 1.6, 'yaw' => -30, 'fov' => 75], ['u' => 50, 'v' => -20, 'altura' => 25, 'yaw' => 225, 'fov' => 60]];

        $c = Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p]);
        $c->call('guardarCameras', $ok)->assertHasNoErrors()->assertReturned(true);
        $this->assertEquals(330, $p->refresh()->cameras[0]['yaw']);   // normalised to 0–360

        foreach ([
            [], // none
            array_fill(0, 11, $ok[0]), // more than max_cameras (10)
            [['u' => 500, 'v' => 0, 'altura' => 1.6, 'yaw' => 0, 'fov' => 70]], // far off the plot
            [['u' => 10, 'v' => 10, 'altura' => 1.6, 'yaw' => 0, 'fov' => 170]], // impossible lens
            [['u' => 10, 'v' => 10, 'yaw' => 0, 'fov' => 70]], // no height
        ] as $mau) {
            $c->call('guardarCameras', $mau)->assertHasErrors('cameras')->assertReturned(false);
        }
        $this->assertCount(2, $p->refresh()->cameras);
    }

    public function test_generating_images_fixes_the_plan_and_waits_for_the_browser_renders(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['cameras' => [['u' => 30, 'v' => 25, 'altura' => 1.6, 'yaw' => 310, 'fov' => 70], ['u' => 0, 'v' => 25, 'altura' => 1.6, 'yaw' => 50, 'fov' => 70]]]);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('gerarImagens')->assertHasNoErrors()
            // the page is told what to render and where to send it: a top view per storey first (one here), then the shots
            ->assertViewHas('tres', fn ($t) => ! $t['editavel'])
            ->assertViewHas('envios', fn ($t) => count($t['pendentes']) === 3
                && $t['pendentes'][0]['envio'] === route('imoplanta.aereo', [$p->id, 0]) && $t['pendentes'][0]['topo'] === 0
                && $t['pendentes'][1]['envio'] === route('imoplanta.render', [$p->id, $p->imagens()->first()->id]));

        $this->assertSame(Imoplanta::IMAGENS, $p->refresh()->estado);
        $this->assertSame([1, 2], $p->imagens->pluck('numero')->all());
        $this->assertSame([ImoplantaImagem::A_RENDERIZAR], $p->imagens->pluck('estado')->unique()->all());
        Queue::assertNothingPushed();   // nothing paid starts before a render arrives

        $this->assertFalse(app(Imoplantas::class)->gerarImagens($p));                     // once
        $this->assertFalse(app(Imoplantas::class)->guardarCameras($p, $p->cameras));      // fixed
        $this->assertFalse(app(Imoplantas::class)->pedirAlteracao($p, 'mudar o telhado')); // fixed
    }

    public function test_an_admin_can_retake_the_pictures_but_a_customer_cannot(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['cameras' => [['u' => 30, 'v' => 25, 'altura' => 1.6, 'yaw' => 310, 'fov' => 70]]]);
        app(Imoplantas::class)->gerarImagens($p);
        Storage::disk('local')->put('foto.png', 'x');
        $p->imagens()->update(['estado' => ImoplantaImagem::PRONTA, 'foto' => 'foto.png']);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('refazerFotografias')->assertForbidden();
        $this->assertSame(Imoplanta::IMAGENS, $p->refresh()->estado);

        // not while an image is still with the AI
        $p->imagens()->update(['estado' => ImoplantaImagem::A_FOTOGRAFAR]);
        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)->test(Imovel::class, ['imovel' => $p])->call('refazerFotografias')->assertHasErrors('refazer');

        $p->imagens()->update(['estado' => ImoplantaImagem::PRONTA]);
        Livewire::actingAs($admin)->test(Imovel::class, ['imovel' => $p])->call('refazerFotografias')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame([Imoplanta::MODELO, 0, 1], [$p->estado, $p->imagens()->count(), count($p->cameras)]);   // back to the cameras, kept
        Storage::disk('local')->assertMissing('foto.png');
        $this->assertTrue(app(Imoplantas::class)->gerarImagens($p));   // and they can be taken again
    }

    // ── the jobs (no real AI, no real Blender) ────────────────────────────

    public function test_reading_the_plans_records_the_llm_tokens_and_the_model_is_ready_at_once(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::A_LER, 'casa' => null, 'cameras' => null, 'versao' => 0]);
        Storage::disk('local')->put('imoplanta/1/plantas/a.png', UploadedFile::fake()->image('a.png')->getContent());
        config(['services.anthropic.key' => 'sk-ant-api-test']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "Aqui está:\n".json_encode($this->casa())]],
            'usage' => ['input_tokens' => 12000, 'output_tokens' => 8000, 'cache_read_input_tokens' => 2000],
        ])]);

        app()->call([new LerPlantasJob($p->id), 'handle']);

        $p->refresh();
        $this->assertSame([Imoplanta::MODELO, 1], [$p->estado, $p->versao]);   // no build step: the browser builds it
        $this->assertCount(count($this->casa()['niveis'][0]['paredes']), $p->casa['niveis'][0]['paredes']);
        $this->assertCount(6, $p->cameras);   // a first shot plan: 2 outside, living room, kitchen, bedroom, bathroom
        Http::assertSent(fn (HttpRequest $r) => collect($r['messages'][0]['content'])->contains('type', 'image'));   // the plans were sent
        Queue::assertNothingPushed();

        $c = DB::table('custos')->where(['tipo' => 'imoplanta', 'peca' => (string) $p->id])->sole();
        $this->assertSame(['extract', 'claude', 'USD', 12000, 8000, 2000], [$c->passo, $c->servico, $c->moeda, (int) $c->tokens_entrada, (int) $c->tokens_saida, (int) $c->tokens_cache]);
        // claude-opus-5 at [5, 25, 0.5] USD per 1M tokens
        $this->assertEqualsWithDelta((12000 * 5 + 8000 * 25 + 2000 * 0.5) / 1e6, (float) $c->custo, 1e-5);
    }

    public function test_a_reading_with_a_room_nobody_can_reach_or_walk_through_gets_one_second_look(): void
    {
        $boa = $this->casa();
        $this->assertSame([], ImoplantaCasa::problemas($boa));   // open plan and doors: every room has a way in

        $ma = $boa;
        foreach ($ma['niveis'][0]['paredes'] as &$w) {
            $w['aberturas'] = array_values(array_filter($w['aberturas'], fn ($o) => $o['tipo'] === 'janela'));
        }
        unset($w);
        $ma['niveis'][0]['divisoes'][] = ['nome' => 'Corredor', 'uso' => 'circulacao', 'poligono' => [[0, 0], [3, 0], [3, 0.3], [0, 0.3]]];
        $problemas = ImoplantaCasa::problemas($ma);
        $this->assertContains('«Corredor» (Piso 0) has a part narrower than 0.7 m (nobody can walk there): a wall is in the wrong place, check the cotas.', $problemas);
        $this->assertTrue(collect($problemas)->contains(fn ($x) => str_contains($x, 'no way in or out')));

        config(['services.anthropic.key' => 'sk-ant-api-test']);
        $resposta = fn ($c) => Http::response(['content' => [['type' => 'text', 'text' => json_encode($c)]], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 500]]);
        Http::fakeSequence('api.anthropic.com/*')->pushResponse($resposta($ma))->pushResponse($resposta($boa));

        $casa = app(LeitorPlantas::class)->ler([]);

        $this->assertSame([], ImoplantaCasa::problemas($casa));   // the corrected reading
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r) => str_contains(json_encode($r['messages']), 'no way in or out'));
    }

    public function test_an_ai_change_updates_the_house_and_its_version(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::A_ALTERAR, 'refeitos_3d' => 1]);
        $nova = array_merge($this->casa(), ['descricao' => 'Com telhado de duas águas']);
        config(['services.anthropic.key' => 'sk-ant-api-test']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($nova)]], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 500]])]);

        app()->call([new AlterarModeloJob($p->id, 'telhado de duas águas'), 'handle']);

        $p->refresh();
        $this->assertSame([Imoplanta::MODELO, 2, 1, 'Com telhado de duas águas'], [$p->estado, $p->versao, $p->refeitos_3d, $p->casa['descricao']]);
        $this->assertSame(30.0, (float) $p->cameras[0]['u']);   // the shot plan is kept
    }

    public function test_retrying_a_failed_reading_or_image_is_free_and_resumes_from_the_last_good_point(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $lida = $this->imovel($u, ['estado' => Imoplanta::FALHOU, 'casa' => null, 'erro' => 'x']);
        app(Imoplantas::class)->tentarDeNovo($lida);
        $this->assertSame(Imoplanta::A_LER, $lida->refresh()->estado);
        Queue::assertPushed(LerPlantasJob::class, 1);

        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $comRender = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::FALHOU, 'render' => 'r.png']);
        $semRender = $p->imagens()->create(['numero' => 2, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::FALHOU]);
        app(Imoplantas::class)->tentarDeNovo($p->refresh());

        $this->assertSame([ImoplantaImagem::A_FOTOGRAFAR, ImoplantaImagem::A_RENDERIZAR], [$comRender->refresh()->estado, $semRender->refresh()->estado]);
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->imagem === $comRender->id);
        Queue::assertPushed(FotografarJob::class, 1);   // the other waits for the page to render it again
    }

    // ── the browser's render upload ───────────────────────────────────────

    private function aRenderizar(User $u): array
    {
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_RENDERIZAR]);

        return [$p, $img];
    }

    private function png(int $w = 1600, int $h = 900, string $nome = 'vista.png'): UploadedFile
    {
        return UploadedFile::fake()->image($nome, $w, $h);
    }

    public function test_the_owner_uploads_a_render_once_and_its_photo_is_queued_once(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        [$p, $img] = $this->aRenderizar($u);
        $url = route('imoplanta.render', [$p->id, $img->id]);

        $this->actingAs($u)->post($url, ['render' => $this->png()])->assertOk()->assertJson(['ok' => true]);

        $img->refresh();
        $this->assertSame(ImoplantaImagem::A_FOTOGRAFAR, $img->estado);
        Storage::disk('local')->assertExists($img->render);
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->imagem === $img->id && ! $j->refazer);

        // a second tab / a resumed page: refused, nothing queued again, the first render kept
        $this->actingAs($u)->post($url, ['render' => $this->png()])->assertStatus(409);
        Queue::assertPushed(FotografarJob::class, 1);
        $this->assertSame($img->render, $img->refresh()->render);
        $this->assertCount(1, Storage::disk('local')->files($p->pasta().'/renders'));
        $this->assertSame(0, $img->refeitos);   // no re-do spent
    }

    public function test_only_the_owner_may_upload_a_render(): void
    {
        Queue::fake();
        $dono = User::factory()->cliente()->create();
        [$p, $img] = $this->aRenderizar($dono);
        [$outroP] = $this->aRenderizar(User::factory()->cliente()->create());
        $url = route('imoplanta.render', [$p->id, $img->id]);

        $this->post($url, ['render' => $this->png()])->assertRedirect();   // guests go to log in
        $this->actingAs(User::factory()->cliente()->create())->post($url, ['render' => $this->png()])->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->post($url, ['render' => $this->png()])->assertNotFound();
        $this->actingAs($dono)->post(route('imoplanta.render', [$outroP->id, $img->id]), ['render' => $this->png()])->assertNotFound();   // not that property's image

        $this->assertSame(ImoplantaImagem::A_RENDERIZAR, $img->refresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_a_render_must_be_a_1600x900_png(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        [$p, $img] = $this->aRenderizar($u);
        $url = route('imoplanta.render', [$p->id, $img->id]);

        foreach ([
            [],                                                     // nothing
            ['render' => $this->png(1280, 720)],                    // wrong size
            ['render' => $this->png(1600, 900, 'vista.jpg')],       // not a PNG
            ['render' => UploadedFile::fake()->create('vista.png', 100, 'text/plain')],   // not an image
            ['render' => UploadedFile::fake()->create('vista.png', 13000, 'image/png')],  // too big
        ] as $mau) {
            $this->actingAs($u)->post($url, $mau, ['Accept' => 'application/json'])->assertUnprocessable();   // multipart, as the page sends it
        }
        $this->assertSame(ImoplantaImagem::A_RENDERIZAR, $img->refresh()->estado);
        Queue::assertNothingPushed();
    }

    public function test_a_render_is_refused_for_an_image_that_is_not_waiting_for_one(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::PRONTA, 'render' => 'r.png', 'foto' => 'f.png']);

        $this->actingAs($u)->post(route('imoplanta.render', [$p->id, $img->id]), ['render' => $this->png()])->assertStatus(409);
        $this->assertSame(['r.png', ImoplantaImagem::PRONTA], [$img->refresh()->render, $img->estado]);
        Queue::assertNothingPushed();
    }

    public function test_a_photo_is_made_through_kie_and_its_cost_recorded_by_model_and_step(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        Storage::disk('local')->put('imoplanta/1/renders/1-abc.png', 'render');
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/1/renders/1-abc.png']);
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/render.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => Http::response(['data' => ['taskId' => 't1']]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/foto.png']])]]),
            'files.test/foto.png' => Http::response('PNGDATA'),
        ]);

        app()->call([new FotografarJob($img->id, false), 'handle']);

        $img->refresh();
        $this->assertSame(ImoplantaImagem::PRONTA, $img->estado);
        $this->assertSame('PNGDATA', Storage::disk('local')->get($img->foto));
        // first the finishes board (text to image, once per property), kept on the property
        $p->refresh();
        $this->assertNotNull($p->acabamentos);
        Storage::disk('local')->assertExists($p->acabamentos_img);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && ! isset($r['input']['image_input'])
            && str_contains($r['input']['prompt'], 'FINISHES BOARD'));
        // then the photo: Nano Banana Pro at 2K, the render and the board as its two input images, the finish list in the prompt
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro'
            && ($r['input']['image_input'] ?? null) === ['https://files.test/render.png', 'https://files.test/render.png']
            && $r['input']['resolution'] === '2K' && $r['input']['aspect_ratio'] === '16:9'
            && str_contains($r['input']['prompt'], 'every lamp OFF') && str_contains($r['input']['prompt'], 'exterior photograph')
            && str_contains($r['input']['prompt'], 'SECOND image is its finishes board') && str_contains($r['input']['prompt'], 'Interior doors:'));
        $c = DB::table('custos')->where('peca', (string) $p->id)->orderBy('id')->get(['passo', 'servico', 'modelo', 'custo'])
            ->map(fn ($c) => [$c->passo, $c->servico, $c->modelo, (float) $c->custo])->all();
        $this->assertSame([['references', 'kie.ai', 'nano-banana-pro', 0.09], ['image_generate', 'kie.ai', 'nano-banana-pro', 0.09]], $c);

        // a second photo reuses the board: no new board request
        $img2 = $p->imagens()->create(['numero' => 2, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/1/renders/1-abc.png']);
        app()->call([new FotografarJob($img2->id, false), 'handle']);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($x) => str_contains($x[0]->url(), 'createTask') && str_contains($x[0]['input']['prompt'], 'FINISHES BOARD'))->count());
    }

    public function test_the_top_view_prompt_lists_each_room_where_it_is_and_only_its_pieces(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $salas = [
            ['uso' => 'circulacao', 'nome' => 'Hall', 'x' => [10, 30], 'y' => [5, 60], 'itens' => ['runner rug', 'potted plant']],
            ['uso' => 'quarto', 'nome' => 'Quarto <b>1</b>', 'x' => [30, 70], 'y' => [5, 50], 'itens' => ['double bed', 'bedside table', 'bedside table', 'wardrobe']],
            ['uso' => 'arrumos', 'nome' => '', 'x' => [70, 90], 'y' => [0, 20], 'itens' => []],
            ['uso' => 'sala', 'x' => 'nope', 'y' => [0, 1], 'itens' => []],   // malformed: dropped
        ];
        $this->actingAs($u)->post(route('imoplanta.aereo', $p->id), ['render' => $this->png(), 'salas' => json_encode($salas)])->assertOk();
        $guardadas = json_decode($p->refresh()->aereo(0, 'salas'), true);
        $this->assertCount(3, $guardadas);
        $this->assertSame('Quarto 1', $guardadas[1]['nome']);

        $prompt = app(Fotografo::class)->promptPlanta('apartamento', 'oak floors', false, [], $guardadas);
        $this->assertStringContainsString('- hallway, from 10% to 30% across and 5% to 60% down the image: runner rug, potted plant.', $prompt);
        $this->assertStringContainsString('double bed, 2 × bedside table, wardrobe.', $prompt);
        $this->assertStringContainsString('storage room, from 70% to 90% across and 0% to 20% down the image: nothing (bare floor).', $prompt);
        $this->assertStringContainsString('ADD NOTHING', $prompt);
        $this->assertStringNotContainsString('THE ROOMS on this floor:', $prompt);
        $this->assertNull(Imoplantas::salasTopo('not json'));
    }

    public function test_furniture_drawn_on_the_plan_is_kept_with_its_room(): void
    {
        $casa = ImoplantaCasa::normalizar(['niveis' => [[
            'paredes' => [['de' => [0, 0], 'a' => [4, 0]]],
            'divisoes' => [
                ['nome' => 'Quarto', 'uso' => 'quarto', 'poligono' => [[0, 0], [4, 0], [4, 3], [0, 3]],
                    'moveis' => [['tipo' => 'roupeiro', 'poligono' => [[0, 0], [1.6, 0], [1.6, 0.6], [0, 0.6]]], ['tipo' => 'piano', 'poligono' => [[2, 2], [3, 2], [3, 3]]], ['tipo' => 'cama']]],
                ['nome' => 'Hall', 'uso' => 'hall', 'poligono' => [[0, 3], [4, 3], [4, 4]]],
            ],
        ]]]);
        $d = $casa['niveis'][0]['divisoes'];
        $this->assertSame(['roupeiro', 'outro'], array_column($d[0]['moveis'], 'tipo'));   // unknown kind → outro, no footprint → dropped
        $this->assertArrayNotHasKey('moveis', $d[1]);
    }

    public function test_the_top_view_reference_is_made_once_from_the_browser_render_and_sent_with_every_photo(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);

        // the owner uploads the top-view render once
        $this->actingAs($u)->post(route('imoplanta.aereo', $p->id), ['render' => $this->png()])->assertOk();
        $this->actingAs($u)->post(route('imoplanta.aereo', $p->id), ['render' => $this->png()])->assertStatus(409);
        $this->actingAs(User::factory()->cliente()->create())->post(route('imoplanta.aereo', $p->id), ['render' => $this->png()])->assertNotFound();
        Storage::disk('local')->assertExists($p->refresh()->aereo(0, 'render'));
        Queue::assertPushed(AereoJob::class, fn ($j) => $j->imovel === $p->id && $j->nivel === 0);   // made straight away

        Storage::disk('local')->put('imoplanta/1/renders/1-abc.png', 'render');
        $mk = fn ($n) => $p->imagens()->create(['numero' => $n, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/1/renders/1-abc.png']);
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/x.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => Http::response(['data' => ['taskId' => 't1']]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/foto.png']])]]),
            'files.test/foto.png' => Http::response('PNGDATA'),
        ]);
        app()->call([new FotografarJob($mk(1)->id, false), 'handle']);
        app()->call([new FotografarJob($mk(2)->id, false), 'handle']);

        $p->refresh();
        Storage::disk('local')->assertExists($p->aereo(0));
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'aereo', 0, 'download' => 1]))->assertDownload('imoplanta-'.$p->id.'-planta-3d-piso-0.png');
        $tarefas = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->values();
        // board, top view (render + board, as a photoreal 3D floor plan), then two photos with three images each — no second board or top view
        $this->assertSame(4, $tarefas->count());
        $this->assertStringContainsString('FINISHES BOARD', $tarefas[0]['input']['prompt']);
        $this->assertStringContainsString('3D FLOOR PLAN', $tarefas[1]['input']['prompt']);
        $this->assertSame('16:9', $tarefas[1]['input']['aspect_ratio']);   // the top view's own ratio (this render is 1600 x 900)
        $this->assertStringContainsString('pixel for pixel', $tarefas[1]['input']['prompt']);
        $this->assertStringContainsString('THE ROOMS on this floor:', $tarefas[1]['input']['prompt']);   // so hallway rugs don't become sofas
        $this->assertCount(2, $tarefas[1]['input']['image_input']);
        foreach ([2, 3] as $k) {
            $this->assertCount(3, $tarefas[$k]['input']['image_input']);
            $this->assertStringContainsString('THIRD image is a realistic top view', $tarefas[$k]['input']['prompt']);
        }
        $this->assertSame(['references', 'references', 'image_generate', 'image_generate'],
            DB::table('custos')->where('peca', (string) $p->id)->orderBy('id')->pluck('passo')->all());
    }

    public function test_the_top_view_is_framed_on_the_house_at_its_own_proportions(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $alta = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $this->actingAs($u)->post(route('imoplanta.aereo', $alta->id), ['render' => $this->png(1200, 1600)])->assertOk();   // a tall house
        $this->assertSame('3:4', Fotografo::proporcao(Storage::disk('local')->get($alta->refresh()->aereo(0, 'render'))));

        $outra = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $this->actingAs($u)->post(route('imoplanta.aereo', $outra->id), ['render' => $this->png(800, 600)])->assertStatus(422);   // not 1600 on its long side
        $this->assertSame(['1:1', '4:3', '9:16', '21:9'], array_map(fn ($wh) => Fotografo::proporcao(UploadedFile::fake()->image('x.png', ...$wh)->getContent()),
            [[1600, 1550], [1600, 1180], [880, 1600], [1600, 640]]));
    }

    public function test_a_two_storey_house_gets_a_top_view_per_floor_and_each_photo_the_one_of_its_floor(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $casa = $this->casa();
        $cima = $casa['niveis'][0];
        $cima['nome'] = 'Piso 1';
        $cima['cota'] = 3.15;
        $casa['niveis'][] = $cima;
        $quarto = collect($cima['divisoes'])->firstWhere('uso', 'quarto');
        [$cu, $cv] = [array_sum(array_column($quarto['poligono'], 0)) / count($quarto['poligono']), array_sum(array_column($quarto['poligono'], 1)) / count($quarto['poligono'])];
        $p = $this->imovel($u, ['casa' => $casa, 'estado' => Imoplanta::IMAGENS]);

        $t = Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->viewData('envios');
        $this->assertSame([0, 1], array_column(array_filter($t['pendentes'], fn ($x) => isset($x['topo'])), 'topo'));
        foreach ([0, 1] as $n) {
            $this->actingAs($u)->post(route('imoplanta.aereo', [$p->id, $n]), ['render' => $this->png(1200, 1600)])->assertOk();
        }
        $this->actingAs($u)->post(route('imoplanta.aereo', [$p->id, 2]), ['render' => $this->png(1200, 1600)])->assertStatus(409);   // no such storey
        Queue::assertPushed(AereoJob::class, 2);
        $this->assertNotSame($p->refresh()->aereo(0, 'render'), $p->aereo(1, 'render'));

        // each realistic floor plan is made from its own floor; an upstairs photo gets the upstairs one
        Storage::disk('local')->put('imoplanta/1/r.png', 'render');
        $img = $p->imagens()->create(['numero' => 1, 'camera' => ['u' => $cu, 'v' => $cv, 'altura' => 4.65, 'yaw' => 0, 'fov' => 70], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/1/r.png']);
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => fn (HttpRequest $r) => Http::response(['data' => ['downloadUrl' => 'https://files.test/'.md5($r->body()).'.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => Http::response(['data' => ['taskId' => 't1']]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/foto.png']])]]),
            'files.test/foto.png' => Http::response('PNGDATA'),
        ]);
        app()->call([new AereoJob($p->id, 0), 'handle']);
        app()->call([new AereoJob($p->id, 1), 'handle']);
        $this->assertNotNull($p->refresh()->aereo(0));
        $this->assertNotNull($p->aereo(1));
        app()->call([new FotografarJob($img->id, false), 'handle']);

        $this->assertSame(ImoplantaImagem::PRONTA, $img->refresh()->estado, (string) $img->erro);
        $this->assertSame(1, ImoplantaCasa::nivelDa($p->casa, $img->camera));
        $foto = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->last();
        $this->assertCount(3, $foto['input']['image_input']);   // render, board, the upstairs floor plan
        $this->get(route('imoplanta.imovel', ['imovel' => $p->id, 'passo' => 2]))->assertSee('Planta 3D · Piso 0')->assertSee('Planta 3D · Piso 1')
            ->assertSee(route('imoplanta.ficheiro', [$p->id, 'aereo', 1, 'download' => 1]), false);
        $this->get(route('imoplanta.ficheiro', [$p->id, 'aereo', 1, 'download' => 1]))->assertDownload('imoplanta-'.$p->id.'-planta-3d-piso-1.png');
    }

    /** A property at step 4: every image ready, the walkthrough made, storey 0's 3D floor plan made. */
    private function noVideo(User $u): Imoplanta
    {
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS, 'video' => 'imoplanta/1/video.mp4', 'video_estado' => null]);
        $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::PRONTA, 'render' => 'r.png', 'foto' => 'f.png']);
        Storage::disk('local')->put('imoplanta/1/video.mp4', 'WALKTHROUGH');
        Storage::disk('local')->put('imoplanta/1/aereo-0.png', UploadedFile::fake()->image('a.png', 1200, 1600)->getContent());
        $p->guardarAereo(0, 'img', 'imoplanta/1/aereo-0.png');

        return $p->refresh();
    }

    public function test_the_plan_video_add_on_is_paid_then_built_from_an_empty_plot_walls_then_furniture(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Vídeo da planta 3D')->assertSee("15\u{00A0}€")->assertDontSee("25\u{00A0}€")
            ->call('comprarExtra', 'planta')->assertRedirect(Imoplantas::link($u, 'extra_planta', $p));   // its Payment Link, for this property
        $this->assertSame('a_pagar', $p->refresh()->extra('planta'));
        Queue::assertNotPushed(PlantaVideoJob::class);

        $sessao = $this->sessao($u, 'cs_x', 1500, ['extra' => 'planta'], 'imoplanta-'.$p->id);
        $this->webhook($sessao)->assertSuccessful();
        $this->webhook($sessao)->assertSuccessful();                                   // a repeated webhook starts nothing more
        Queue::assertPushed(PlantaVideoJob::class, 1);
        $this->assertSame('a_fazer', $p->refresh()->extra('planta'));
        $this->assertSame(0, intdiv($u->creditos(), Creditos::IMOPLANTA));              // not a property credit

        // the job: per storey one clip, bare plot → built and furnished; the storeys joined
        $tmp = sys_get_temp_dir().'/planta-'.uniqid().'.mp4';
        (new Process(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'color=white:s=320x240:d=0.4', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=0.4',
            '-pix_fmt', 'yuv420p', '-shortest', $tmp]))->mustRun();
        // a floor clip made before sound was on: silent, so it is made again
        Storage::disk('local')->put('imoplanta/1/velho.mp4', '');
        (new Process(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'color=white:s=320x240:d=0.4', '-pix_fmt', 'yuv420p', Storage::disk('local')->path('imoplanta/1/velho.mp4')]))->mustRun();
        $p->guardarAereo(0, 'construcao', 'imoplanta/1/velho.mp4');
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => fn (HttpRequest $r) => Http::response(['data' => ['downloadUrl' => 'https://files.test/up-'.count(Http::recorded()).'.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => fn (HttpRequest $r) => Http::response(['data' => ['taskId' => str_contains($r['model'], 'kling') ? 'tv' : 'ti']]),
            'api.kie.ai/api/v1/jobs/recordInfo?taskId=tv' => Http::response(['data' => ['state' => 'success', 'model' => 'x', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/clip.mp4']])]]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'x', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/vazia.png']])]]),
            'files.test/clip.mp4' => Http::response((string) file_get_contents($tmp)),
            'files.test/vazia.png' => Http::response(UploadedFile::fake()->image('v.png', 1200, 1600)->getContent()),
        ]);
        app()->call([new PlantaVideoJob($p->id), 'handle']);
        @unlink($tmp);

        $p->refresh();
        $this->assertSame('pronto', $p->extra('planta'), (string) $p->extra('planta', 'erro'));
        foreach (['lote', 'construcao'] as $k) {
            Storage::disk('local')->assertExists($p->aereo(0, $k));
        }
        $tarefas = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->values();
        $this->assertCount(1, $tarefas);                                                             // ONE clip for the floor, no «empty» edit
        $c = $tarefas[0]['input'];
        $this->assertStringContainsString('bare, empty rectangle', $c['prompt']);                   // plot → floors and walls → furniture
        $this->assertStringContainsString('FIRST HALF', $c['prompt']);
        $this->assertStringContainsString('SECOND HALF, the furniture appears', $c['prompt']);
        $this->assertSame('10', $c['duration']);
        $this->assertTrue($c['sound']);
        $this->assertStringContainsString('Sound,', $c['prompt']);
        $this->assertNotSame('imoplanta/1/velho.mp4', $p->aereo(0, 'construcao'));
        $this->assertTrue(Fotografo::temSom(Storage::disk('local')->path($p->extra('planta', 'video'))));   // the joined video keeps it
        $this->assertGreaterThan(0.3, (float) (new Process(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0',
            Storage::disk('local')->path($p->extra('planta', 'video'))]))->mustRun()->getOutput());   // both clips, one after the other

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Descarregar')
            ->call('comprarExtra', 'planta')->assertHasErrors('extra.planta');                         // made: once per property
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'planta_video', 'download' => 1]))->assertDownload('imoplanta-'.$p->id.'-video-planta.mp4');
    }

    public function test_a_floor_already_finished_as_two_clips_is_reused_without_new_ai(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        foreach (['obra', 'mobilia'] as $k) {
            Storage::disk('local')->put("imoplanta/1/$k.mp4", '');
            (new Process(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'color=white:s=320x240:d=0.5', '-f', 'lavfi', '-i', 'sine=duration=0.5',
                '-pix_fmt', 'yuv420p', '-shortest', Storage::disk('local')->path("imoplanta/1/$k.mp4")]))->mustRun();
            $p->guardarAereo(0, $k, "imoplanta/1/$k.mp4");
        }
        config(['services.kie.key' => 'kie-test']);
        Http::fake();

        $video = app(Fotografo::class)->plantaVideo($p->refresh());

        Http::assertNothingSent();                                               // no new clip paid for
        Storage::disk('local')->assertExists($p->refresh()->aereo(0, 'construcao'));
        $this->assertGreaterThan(0.9, (float) (new Process(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0',
            Storage::disk('local')->path($video)]))->mustRun()->getOutput());   // both halves, as one
    }

    public function test_the_bare_plot_is_the_footprint_of_the_top_render(): void
    {
        $r = imagecreatetruecolor(100, 100);
        imagefill($r, 0, 0, imagecolorallocate($r, 226, 226, 226));   // the grey around a flat's render, not white
        imagefilledrectangle($r, 20, 30, 60, 80, imagecolorallocate($r, 40, 40, 40));
        ob_start();
        imagepng($r);
        $lote = imagecreatefromstring(Fotografo::lote(UploadedFile::fake()->image('p.png', 200, 200)->getContent(), (string) ob_get_clean()));

        $this->assertSame([200, 200], [imagesx($lote), imagesy($lote)]);
        $this->assertSame(0xFFFFFF, imagecolorat($lote, 10, 10));     // outside: white
        $this->assertSame(0xE2DED5, imagecolorat($lote, 80, 110));    // inside the footprint: the plain plot
        $this->assertSame(0xFFFFFF, imagecolorat($lote, 150, 110));
    }

    public function test_the_imocena_reel_add_on_takes_a_link_or_typed_details_and_runs_the_listing_pipeline(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);   // a free account: 0 €, no Stripe
        $p = $this->noVideo($u);
        $p->update(['notas' => 'Vista mar.']);
        $musicas = sys_get_temp_dir().'/musicas-'.uniqid();   // the library, isolated: one real estate track and another
        $this->app->instance(\App\Services\Shorts\MusicLibrary::class, new \App\Services\Shorts\MusicLibrary($musicas));
        mkdir($musicas);
        file_put_contents($musicas.'/real-estate-teste.mp3', 'MP3');
        file_put_contents($musicas.'/outra.mp3', 'MP3');
        $this->beforeApplicationDestroyed(fn () => array_map('unlink', glob($musicas.'/*')) && rmdir($musicas));

        // payment first (a free account: nothing to pay), no questions before it
        $t = Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Vídeo Imocena')->assertSee("0\u{00A0}€")
            ->assertDontSee('O SEU CONTACTO')->call('comprarExtra', 'video')->assertHasNoErrors()->assertNoRedirect();
        $this->assertSame('dados', $p->refresh()->extra('video'));
        Queue::assertNotPushed(ImportListingJob::class);
        // then the details, all optional: the listing facts and the contact (name, phone, email, photo, logo)
        $t->assertSee('O SEU CONTACTO')->assertSet('reel.notas', 'Vista mar.')
            ->set('reel.anuncio', 'https://www.exemplo.pt/casa')->call('enviarDadosReel')->assertHasErrors('reel.anuncio');
        $t->set('reel.anuncio', '')->set('reel.preco', '265.000 €')->set('reel.local', 'Braga')->set('reel.telefone', '912 345 678')
            ->set('reel.email', 'rui@exemplo.pt')->set('fotoContacto', UploadedFile::fake()->image('eu.jpg'))->set('logoContacto', UploadedFile::fake()->image('logo.png'))
            ->call('enviarDadosReel')->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('a_fazer', $p->extra('video'));
        $clip = app(ClipStore::class)->find($p->extra('video', 'clip'));
        Queue::assertPushed(ImportListingJob::class, fn ($j) => $j->clipId === $clip->id);
        $this->assertSame($p->id, $clip->meta['imoplanta']);
        $this->assertSame('WALKTHROUGH', Storage::disk(config('contentmachine.clips.disk'))->get($clip->meta['listing_video']));   // the walkthrough is the footage
        $l = $clip->meta['listing_manual'];
        $this->assertSame(265000, $l['price']);
        $this->assertSame('Braga', $l['details']['Localização']);
        $this->assertSame('', $l['agent']['name']);
        $this->assertStringContainsString('Vista mar.', $clip->meta['script_notes']);
        $this->assertStringContainsString('Não há consultor', $clip->meta['script_notes']);
        $this->assertStringContainsString('912 345 678', $clip->meta['script_notes']);
        $this->assertSame(['phone' => '912 345 678', 'email' => 'rui@exemplo.pt'], $clip->meta['contacto']);
        $this->assertSame('real-estate-teste.mp3', $clip->meta['musica']);                       // one of the real estate tracks
        Storage::disk(config('contentmachine.clips.disk'))->assertExists($clip->meta['agent_photo']);
        Storage::disk(config('contentmachine.clips.disk'))->assertExists($clip->meta['agency_logo']);

        $saida = sys_get_temp_dir().'/reel-'.uniqid().'.mp4';
        file_put_contents($saida, 'REEL');
        $clip->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => $saida]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee(route('imoplanta.ficheiro', [$p->id, 'reel', 'download' => 1]), false);
        $this->assertSame('pronto', $p->refresh()->extra('video'));
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'reel', 'download' => 1]))->assertDownload('imoplanta-'.$p->id.'-video-imocena.mp4');
        $this->actingAs(User::factory()->cliente()->create())->get(route('imoplanta.ficheiro', [$p->id, 'reel']))->assertNotFound();
        @unlink($saida);
    }

    public function test_both_add_ons_together_cost_20_and_the_reel_waits_for_the_plan_video_and_uses_it(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee("Os dois por 20\u{00A0}€")->assertSee("15\u{00A0}€")
            ->call('comprarExtra', 'ambos')->assertRedirect(Imoplantas::link($u, 'extra_ambos', $p));

        $this->webhook($this->sessao($u, 'cs_y', 2000, ['extra' => 'ambos'], 'imoplanta-'.$p->id))->assertSuccessful();
        $p->refresh();
        Queue::assertPushed(PlantaVideoJob::class, 1);
        $this->assertSame('dados', $p->extra('video'));                    // paid: now its details
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->set('reel.preco', '300000')->call('enviarDadosReel')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame('a_fazer', $p->extra('video'));
        $this->assertTrue($p->extra('video', 'espera'));                  // the reel waits for the plan video
        $this->assertNull($p->extra('video', 'clip'));
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('À espera do vídeo da planta');

        // the plan video is made (its job's end starts the waiting reel, with it)
        Storage::disk('local')->put('imoplanta/1/planta.mp4', '');
        (new Process(['ffmpeg', '-y', '-v', 'error', '-f', 'lavfi', '-i', 'color=white:s=320x180:d=0.4', '-pix_fmt', 'yuv420p', Storage::disk('local')->path('imoplanta/1/planta.mp4')]))->mustRun();
        $limpo = Storage::disk('local')->get('imoplanta/1/planta.mp4');
        $fotografo = \Mockery::mock(Fotografo::class);
        $fotografo->shouldReceive('plantaVideo')->andReturn('imoplanta/1/planta.mp4');
        app()->call([new PlantaVideoJob($p->id), 'handle'], ['fotografo' => $fotografo]);
        $p->refresh();
        $this->assertSame('pronto', $p->extra('planta'));
        $clip = app(ClipStore::class)->find($p->extra('video', 'clip'));
        $this->assertNull($p->extra('video', 'espera'));
        $this->assertSame($limpo, Storage::disk(config('contentmachine.clips.disk'))->get($clip->meta['imoplanta_planta']));
        $this->assertSame(300000, $clip->meta['listing_manual']['price']);
    }

    public function test_a_reel_asked_after_the_plan_video_uses_it_too(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        $p = $this->noVideo($u);
        Storage::disk('local')->put('imoplanta/1/planta.mp4', 'PLANTA');
        $p->guardarExtra('planta', 'estado', 'pronto');
        $p->guardarExtra('planta', 'video', 'imoplanta/1/planta.mp4');
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertDontSee('Os dois por')->assertSee('Inclui o vídeo da planta 3D')
            ->call('comprarExtra', 'video')->call('enviarDadosReel')->assertHasNoErrors();
        $clip = app(ClipStore::class)->find($p->refresh()->extra('video', 'clip'));
        $this->assertSame('PLANTA', Storage::disk(config('contentmachine.clips.disk'))->get($clip->meta['imoplanta_planta']));
    }

    public function test_test_accounts_keep_the_steps_on_the_finished_page_to_go_back(): void
    {
        $u = User::factory()->cliente()->create(['email' => 'teste@exemplo.pt']);
        config(['contentmachine.imoplanta.gratis' => ['teste@exemplo.pt'], 'contentmachine.imoplanta.acesso' => ['teste@exemplo.pt']]);
        $p = $this->noVideo($u);
        $p->update(['video_estado' => 'pronto']);

        $this->actingAs($u)->get(route('imoplanta.imovel', $p->id))->assertSee('O seu vídeo está pronto')->assertSee('PASSO 1');
        $this->actingAs($u)->get(route('imoplanta.imovel', $p->id).'?passo=1')->assertOk()->assertSee('aria-current="step"', false)->assertDontSee('O seu vídeo está pronto');
    }

    public function test_the_finished_page_says_the_video_is_ready_hides_the_steps_and_zips_scenes_photos_and_plans(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        $img = $p->imagens()->first();
        Storage::disk('local')->put('imoplanta/1/f.png', 'FOTO');
        Storage::disk('local')->put('imoplanta/1/c.mp4', 'CENA');
        $img->update(['foto' => 'imoplanta/1/f.png']);
        $p->update(['video_estado' => 'pronto', 'video_clips' => [$img->id => 'imoplanta/1/c.mp4']]);

        $this->actingAs($u)->get(route('imoplanta.imovel', $p->id))->assertSee('O seu vídeo está pronto')->assertDontSee('PASSO 1')
            ->assertSee('Vídeo completo')->assertSee('Todas as cenas do vídeo')->assertSee('Todas as fotografias')->assertSee('Planta 3D')
            ->assertDontSee('Cenas em separado');
        foreach (['cenas' => 'cena-1.mp4', 'fotos' => 'foto-1.png', 'plantas' => 'planta-3d-piso-0.png'] as $qual => $nome) {
            $r = $this->actingAs($u)->get(route('imoplanta.zip', [$p->id, $qual]))->assertDownload('imoplanta-'.$p->id.'-'.$qual.'.zip');
            $zip = new \ZipArchive;
            $zip->open($r->baseResponse->getFile()->getPathname());
            $this->assertSame($nome, $zip->getNameIndex(0));
            $zip->close();
        }
        $this->actingAs(User::factory()->cliente()->create())->get(route('imoplanta.zip', [$p->id, 'fotos']))->assertNotFound();
    }

    public function test_the_imocena_reel_spends_a_video_credit_when_there_is_one_and_gives_it_back_if_it_fails(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $compra = DB::table('compras')->insertGetId(['user_id' => $u->id, 'sessao' => 'cs_v', 'total' => 1000, 'videos' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $p = $this->noVideo($u);

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Usar 1 dos seus vídeos (2 disponíveis)')->assertDontSee('Os dois por')
            ->call('comprarExtra', 'video')->assertHasNoErrors()->assertNoRedirect();   // no Stripe
        $this->assertSame(1, $u->creditos());
        $this->assertSame('dados', $p->refresh()->extra('video'));          // paid with the credit: now its details
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('enviarDadosReel')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame('a_fazer', $p->extra('video'));
        $this->assertSame($compra, $p->extra('video', 'compra'));

        app(ClipStore::class)->find($p->extra('video', 'clip'))->update(['status' => ClipRecord::STATUS_FAILED]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p]);                // seen twice: given back once
        $this->assertSame('falhou', $p->refresh()->extra('video'));
        $this->assertSame(2, $u->creditos());

        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('comprarExtra', 'video');   // a retry spends one again
        $this->assertSame(1, $u->creditos());
    }

    public function test_the_extras_show_only_once_the_ai_video_is_done(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        $p->update(['video' => null, 'video_estado' => 'a_fazer']);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('A criar o vídeo')->assertDontSee('Extras')->assertDontSee('Vídeo da planta 3D');
        $p->update(['video' => 'imoplanta/1/video.mp4', 'video_estado' => 'a_fazer']);   // an admin remaking it
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertDontSee('Vídeo da planta 3D');
        $p->update(['video_estado' => 'pronto']);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Extras')->assertSee('Vídeo da planta 3D');
    }

    public function test_the_last_seconds_of_the_video_offer_the_add_ons_with_previews(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->noVideo($u);
        $p->update(['video_estado' => 'pronto']);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('$el.duration - $el.currentTime <= 4', false)
            ->assertSee('Mais dois vídeos deste imóvel')->assertSee('EXEMPLO')
            ->assertSee(asset('imoplanta-demo/demo-planta.gif'), false);                    // the plan video's preview: one demo GIF for everyone

        $p->guardarExtra('planta', 'estado', 'pronto');
        $p->guardarExtra('video', 'estado', 'a_fazer');
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertDontSee('Mais dois vídeos deste imóvel');   // nothing left to offer
    }

    public function test_only_the_reel_gets_a_preview_made_and_buying_it_as_previewed_only_re_renders(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        $p = $this->noVideo($u);
        $p->update(['notas' => 'Vista mar.']);
        app(Imoplantas::class)->fazerPrevias($p->refresh());
        app(Imoplantas::class)->fazerPrevias($p->refresh());                    // once

        Queue::assertNotPushed(PlantaVideoJob::class);                         // the plan video's preview is the demo GIF: nothing made
        Queue::assertPushed(ImportListingJob::class, 1);
        $previa = app(ClipStore::class)->find($p->refresh()->extra('video', 'previa_clip'));
        $this->assertSame('PRÉ-VISUALIZAÇÃO', $previa->meta['watermark']);
        $this->assertTrue($previa->meta['watermark_grande']);
        $this->assertSame(['notas' => 'Vista mar.'], $p->extra('video', 'previa_dados'));
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('A PREPARAR');

        $previa->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => $saida = sys_get_temp_dir().'/previa-'.uniqid().'.mp4']);
        file_put_contents($saida, 'REEL');
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])
            ->assertSee(route('imoplanta.ficheiro', [$p->id, 'reel_previa', 'v' => substr(md5($saida), 0, 8)]), false);

        // the reel bought as previewed: the same clip re-rendered without the mark (no new script or voice)
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('comprarExtra', 'video')->call('enviarDadosReel')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame($previa->id, $p->extra('video', 'clip'));
        Queue::assertPushed(\App\Jobs\Clips\RenderJob::class, fn ($j) => $j->projectId === $previa->id);
        Queue::assertPushed(ImportListingJob::class, 1);
        $this->assertNull(app(ClipStore::class)->find($previa->id)->meta['watermark']);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p]);
        $this->assertSame('a_fazer', $p->refresh()->extra('video'));          // the marked file is not taken for the bought one
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'reel_previa']))->assertNotFound();

        // the plan video bought: made then (no preview to unlock)
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('comprarExtra', 'planta')->assertHasNoErrors();
        $this->assertSame('a_fazer', $p->refresh()->extra('planta'));
        Queue::assertPushed(PlantaVideoJob::class, 1);
        @unlink($saida);
    }

    public function test_a_reel_from_typed_details_has_no_studio_agent_and_says_there_is_none(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        $p = $this->noVideo($u);
        app(Imoplantas::class)->pedirExtra($p, 'video', $u);
        app(Imoplantas::class)->enviarDadosReel($p, ['preco' => '200000']);
        $clip = app(ClipStore::class)->find($p->refresh()->extra('video', 'clip'));
        $this->assertSame('', $clip->meta['listing']['url']);
        $this->assertFalse(app(Imoplantas::class)->pedirExtra($p, 'video', $u));       // being made: not again
        $this->assertFalse(app(Imoplantas::class)->enviarDadosReel($p, []));
    }

    public function test_step_by_step_cameras_then_materials_and_floor_plans_then_images(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u);
        $cams = [['u' => 10, 'v' => 30, 'altura' => 1.6, 'yaw' => 30, 'fov' => 110], ['u' => 50, 'v' => -20, 'altura' => 25, 'yaw' => 225, 'fov' => 90]];

        // 1. the model and the cameras: "Continuar" saves them and moves on
        $c = Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertViewHas('passo', 1)->assertSee('Continuar: acabamentos e plantas 3D')
            ->assertViewHas('tres', fn ($t) => $t['editavel'] && $t['fov'] === 110 && $t['max'] === 10);
        $c->call('avancar', $cams)->assertHasNoErrors()->assertReturned(true)->assertViewHas('passo', 2)->assertSee('Acabamentos e plantas 3D');
        $p->refresh();
        $this->assertSame([Imoplanta::REFERENCIAS, 'a_fazer', 2], [$p->estado, $p->quadro_estado, count($p->cameras)]);
        Queue::assertPushed(QuadroJob::class, 1);
        $this->assertFalse($p->editavel());                                  // the model and the cameras are fixed now
        $c->assertViewHas('envios', fn ($e) => $e['pendentes'][0]['topo'] === 0);   // the browser sends the floor's top view

        // 2. "Gerar imagens" waits for the board and the floor plans
        $c->call('gerarImagens')->assertHasErrors('cameras');
        $this->assertSame(Imoplanta::REFERENCIAS, $p->refresh()->estado);
        Storage::disk('local')->put('imoplanta/1/q.png', 'board');
        Storage::disk('local')->put('imoplanta/1/a0.png', 'plan');
        $p->update(['acabamentos_img' => 'imoplanta/1/q.png', 'quadro_estado' => null]);
        $p->guardarAereo(0, 'render', 'imoplanta/1/a0.png');
        $p->guardarAereo(0, 'img', 'imoplanta/1/a0.png');
        $this->assertTrue($p->refresh()->referenciasProntas());

        // a change to the board: 3 at most for a customer, one at a time
        foreach ([1, 2, 3] as $k) {
            $c->set('pedidosRef.q', 'Soalho mais claro, versão '.$k)->call('alterarReferencia', null)->assertHasNoErrors();
            $this->assertSame('a_fazer', $p->refresh()->quadro_estado);
            $c->set('pedidosRef.q', 'Outra coisa qualquer')->call('alterarReferencia', null)->assertHasErrors('pedidosRef.q');   // one at a time
            $p->update(['quadro_estado' => null]);
        }
        $c->set('pedidosRef.q', 'A quarta alteração')->call('alterarReferencia', null)->assertHasErrors('pedidosRef.q');
        $this->assertSame(3, $p->refresh()->acabamentos_refeitos);
        Queue::assertPushed(AlterarReferenciaJob::class, 3);
        $c->set('pedidosRef.0', 'O corredor fica vazio, sem sofá.')->call('alterarReferencia', 0)->assertHasNoErrors('pedidosRef.0');   // each floor has its own 3
        $this->assertSame([1, 'a_fazer'], [$p->refresh()->aereos[0]['refeitos'], $p->aereos[0]['estado']]);
        $p->guardarAereo(0, 'estado', null);

        // 3. the images
        $c->call('gerarImagens')->assertHasNoErrors('cameras')->assertViewHas('passo', 3)->assertSee('Imagens');
        $this->assertSame([Imoplanta::IMAGENS, 2], [$p->refresh()->estado, $p->imagens()->count()]);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->set('ver', 1)->assertViewHas('passo', 1)
            ->assertViewHas('tres', fn ($t) => ! $t['editavel'])                      // earlier steps: look, don't change
            ->set('ver', 4)->assertViewHas('passo', 3);                                // the video opens once every image is ready
    }

    public function test_a_new_finishes_board_redraws_the_floor_plans_and_a_failed_change_is_given_back(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::REFERENCIAS, 'acabamentos' => 'claro', 'acabamentos_img' => 'imoplanta/1/q.png', 'acabamentos_refeitos' => 1]);
        Storage::disk('local')->put('imoplanta/1/q.png', UploadedFile::fake()->image('q.png', 1600, 900)->getContent());
        Storage::disk('local')->put('imoplanta/1/r0.png', UploadedFile::fake()->image('r.png', 1200, 1600)->getContent());
        $p->guardarAereo(0, 'render', 'imoplanta/1/r0.png');
        $p->guardarAereo(0, 'img', 'imoplanta/1/r0.png');
        $p->guardarAereo(0, 'construcao', 'imoplanta/1/velho.mp4');
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        $kieEmBaixo = false;
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/x.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => function () use (&$kieEmBaixo) {
                return Http::response($kieEmBaixo ? ['code' => 500, 'msg' => 'busy'] : ['data' => ['taskId' => 't1']]);
            },
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/out.png']])]]),
            'files.test/out.png' => Http::response('PNG'),
        ]);

        app()->call([new AlterarReferenciaJob($p->id, null, 'Cozinha em verde-azeitona'), 'handle']);

        $p->refresh();
        $this->assertNotSame('imoplanta/1/q.png', $p->acabamentos_img);
        $this->assertSame(['Cozinha em verde-azeitona'], $p->acabamentos_pedidos);
        $this->assertNotSame('imoplanta/1/r0.png', $p->aereo(0));               // the floor plan was redrawn with the new board
        $this->assertNull($p->aereo(0, 'construcao'));                           // and its old plan-video clip dropped
        $this->assertStringContainsString('Cozinha em verde-azeitona', app(\App\Services\Imoplanta\Acabamentos::class)->texto($p));   // the photos follow it
        $prompts = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->map(fn ($r) => $r['input']['prompt'])->values();
        $this->assertStringContainsString('finishes board', $prompts[0]);
        $this->assertStringContainsString('3D FLOOR PLAN', $prompts[1]);

        // kie down: the change is given back
        $kieEmBaixo = true;
        $p->update(['acabamentos_refeitos' => 2, 'quadro_estado' => 'a_fazer']);
        app()->call([new AlterarReferenciaJob($p->id, null, 'Mais uma'), 'handle']);
        $this->assertSame([1, null], [$p->refresh()->acabamentos_refeitos, $p->quadro_estado]);
        $this->assertStringContainsString('Não foi descontada', (string) $p->quadro_erro);
    }

    public function test_kie_failures_are_retried_on_the_main_model_then_another_model_then_later_without_a_warning(): void
    {
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS, 'acabamentos' => 'claro', 'acabamentos_img' => 'imoplanta/1/quadro.png']);
        Storage::disk('local')->put('imoplanta/1/quadro.png', 'board');
        Storage::disk('local')->put('imoplanta/1/r.png', 'render');
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/1/r.png']);
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        $falha = Http::response(['data' => ['state' => 'fail', 'failMsg' => 'internal error']]);
        $ok = Http::response(['data' => ['state' => 'success', 'model' => 'google/nano-banana-edit', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/foto.png']])]]);
        $semCreditos = false;
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/x.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => function () use (&$semCreditos) {
                return Http::response($semCreditos ? ['code' => 402, 'msg' => 'Credits insufficient'] : ['data' => ['taskId' => 't1']]);
            },
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::sequence()->pushResponse($falha)->pushResponse($falha)->pushResponse($ok),
            'files.test/foto.png' => Http::response('PNGDATA'),
        ]);

        app()->call([new FotografarJob($img->id, false), 'handle']);

        $this->assertSame([ImoplantaImagem::PRONTA, null], [$img->refresh()->estado, $img->erro]);
        $modelos = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->pluck('model')->values()->all();
        $this->assertSame(['nano-banana-pro', 'nano-banana-pro', 'google/nano-banana-edit'], $modelos);   // two tries, then the other model
        $alt = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => ($r['model'] ?? null) === 'google/nano-banana-edit')->first();
        $this->assertCount(2, $alt['input']['image_urls']);   // the same images: render and board

        // kie down: every attempt fails — no warning, the image stays «a fotografar» and tries again in 5 minutes
        Queue::fake();
        $img->update(['estado' => ImoplantaImagem::A_FOTOGRAFAR, 'foto' => null]);
        $semCreditos = true;
        app()->call([new FotografarJob($img->id, false), 'handle']);
        $this->assertSame([ImoplantaImagem::A_FOTOGRAFAR, null], [$img->refresh()->estado, $img->erro]);
        Queue::assertPushed(FotografarJob::class, fn ($j) => $j->imagem === $img->id && $j->ronda === 1 && $j->delay !== null);

        // the last round gives up: the customer gets a calm line and the free retry, the admin kie's reason
        app()->call([new FotografarJob($img->id, false, false, FotografarJob::RONDAS), 'handle']);
        $this->assertSame(ImoplantaImagem::FALHOU, $img->refresh()->estado);
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertDontSee('Credits insufficient')
            ->assertSee('Esta imagem não ficou pronta')->assertSee('Gerar de novo (sem custo)');
        Livewire::actingAs(User::factory()->create(['is_admin' => true]))->test(Imovel::class, ['imovel' => $p])->assertSee('Credits insufficient');
    }

    public function test_elevations_and_photos_uploaded_with_the_plans_reach_the_plan_reader_and_every_photo(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $this->comCreditos($u, 1);
        Livewire::actingAs($u)->test(Lista::class)->set('nome', 'T3')->set('plantas', [UploadedFile::fake()->image('planta.png')])
            ->set('referencias', [UploadedFile::fake()->image('alcado.png'), UploadedFile::fake()->image('fachada.jpg')])
            ->call('criar')->assertHasNoErrors();
        $p = Imoplanta::sole();
        $this->assertCount(2, $p->referencias);
        Storage::disk('local')->assertExists($p->referencias[1]);
        $this->assertSame([...$p->plantas, ...$p->referencias], $p->imagensParaLer());   // plans first, as the context says
        $this->assertStringContainsString('The first 1 attached image(s) are the plans. The last 2 are other references', $p->contextoPlantas());
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'referencia', 1]))->assertOk();

        // each photo gets them after the render and the board
        $q = $this->imovel($u);
        $p->update(['casa' => $q->casa, 'cameras' => $q->cameras, 'estado' => Imoplanta::IMAGENS]);
        Storage::disk('local')->put('imoplanta/r.png', 'render');
        $img = $p->imagens()->create(['numero' => 1, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'imoplanta/r.png']);
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/x.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => Http::response(['data' => ['taskId' => 't1']]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/foto.png']])]]),
            'files.test/foto.png' => Http::response('PNGDATA'),
        ]);
        app()->call([new FotografarJob($img->id, false), 'handle']);

        $this->assertSame(ImoplantaImagem::PRONTA, $img->refresh()->estado, (string) $img->erro);
        $foto = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->last();
        $this->assertCount(4, $foto['input']['image_input']);   // render, board, two references
        $this->assertStringContainsString("The last 2 images are the owner's own drawings or photos", $foto['input']['prompt']);
    }

    public function test_the_walkthrough_joins_one_ai_clip_per_photo_once_every_photo_is_ready(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        $p = $this->imovel($u, ['estado' => Imoplanta::IMAGENS]);
        $disk = Storage::disk('local');
        foreach ([1, 2] as $n) {
            $disk->put("imoplanta/{$p->id}/fotos/{$n}.png", 'png');
            $p->imagens()->create(['numero' => $n, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::PRONTA, 'render' => 'r.png', 'foto' => "imoplanta/{$p->id}/fotos/{$n}.png"]);
        }
        $p->imagens()->create(['numero' => 3, 'camera' => $p->cameras[0], 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'render' => 'r.png']);

        // not before every photo is ready
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('criarVideo')->assertHasErrors('video');
        $p->imagens()->where('numero', 3)->delete();
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->call('criarVideo')->assertHasNoErrors();
        $this->assertSame('a_fazer', $p->refresh()->video_estado);
        Queue::assertPushed(VideoJob::class, fn ($j) => $j->imovel === $p->id);

        // the job: a real 1 s clip from kie for each photo, joined by the real ffmpeg
        $clip = tempnam(sys_get_temp_dir(), 'clip').'.mp4';
        (new \Symfony\Component\Process\Process(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'color=c=gray:s=320x180:d=1:r=30', '-pix_fmt', 'yuv420p', $clip]))->mustRun();
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/foto.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => Http::response(['data' => ['taskId' => 't1']]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => ['state' => 'success', 'model' => 'kling-2.6/image-to-video', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/clip.mp4']])]]),
            'files.test/clip.mp4' => Http::response(file_get_contents($clip)),
        ]);
        app()->call([new VideoJob($p->id), 'handle']);
        @unlink($clip);

        $p->refresh();
        $this->assertSame(['pronto', null, 2], [$p->video_estado, $p->video_erro, count($p->video_clips)]);
        $dur = (float) (new \Symfony\Component\Process\Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $disk->path($p->video)]))->mustRun()->getOutput();
        $this->assertEqualsWithDelta(1 + 1 - \App\Services\Imoplanta\Walkthrough::FUNDIDO, $dur, 0.15);   // two clips, one crossfade
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-2.6/image-to-video'
            && str_contains($r['input']['prompt'], 'nothing appears, disappears'));
        $this->assertSame(['video', 'video'], DB::table('custos')->where('peca', (string) $p->id)->pluck('passo')->all());
        // each scene can be downloaded on its own, named after its image
        $cena = $p->imagens()->find(array_key_first($p->video_clips));
        $this->actingAs($u)->get(route('imoplanta.ficheiro', [$p->id, 'clip', $cena->id]))->assertOk()
            ->assertDownload('imoplanta-'.$p->id.'-cena-'.$cena->numero.'.mp4');
        $this->actingAs(User::factory()->cliente()->create())->get(route('imoplanta.ficheiro', [$p->id, 'clip', $cena->id]))->assertNotFound();
        Livewire::actingAs($u)->test(Imovel::class, ['imovel' => $p])->assertSee('Todas as cenas do vídeo')->assertDontSee('Cena '.$cena->numero);   // one zip, not a button per scene

        // once for a customer; an admin may make it again
        Queue::fake();
        $this->assertFalse(app(Imoplantas::class)->pedirVideo($p, $u));
        $this->assertTrue(app(Imoplantas::class)->pedirVideo($p, User::factory()->create(['is_admin' => true])));
        $this->assertNull($p->refresh()->video_clips);   // made from new clips
    }

    // ── cost vs revenue ───────────────────────────────────────────────────

    public function test_cost_per_property_and_margin_per_tier(): void
    {
        config(['contentmachine.imoplanta.custos.eur_por_usd' => 0.9]);
        $u = User::factory()->cliente()->create();
        $a = $this->imovel($u, ['compra_id' => $this->comCreditos($u, 9, 10, 40000)]);
        $b = $this->imovel($u, ['compra_id' => $this->comCreditos($u, 0, 1, 5000)]);
        $linha = fn ($p, $passo, $custo, $moeda = 'USD') => ['projeto' => 'x', 'tipo' => 'imoplanta', 'peca' => (string) $p->id, 'servico' => 's',
            'custo' => $custo, 'detalhe' => '', 'passo' => $passo, 'moeda' => $moeda, 'created_at' => now()];
        DB::table('custos')->insert([
            $linha($a, 'extract', 0.50), $linha($a, 'ai_change_3d', 0.30), $linha($a, 'ai_change_3d', 0.30),
            $linha($a, 'image_generate', 0.10), $linha($a, 'image_redo', 0.02, 'EUR'), $linha($a, 'stripe_fee', 0.625, 'EUR'),
            $linha($b, 'extract', 1.00), $linha($b, 'stripe_fee', 1.00, 'EUR'),
            ['projeto' => 'x', 'tipo' => 'clip', 'peca' => (string) $a->id, 'servico' => 's', 'custo' => 99, 'detalhe' => '', 'passo' => null, 'moeda' => 'USD', 'created_at' => now()],   // not Imoplanta
        ]);

        $linhas = app(ImoplantaCustos::class)->porImovel()->keyBy('id');
        // A: USD 1.20 × 0.9 = 1.08 + EUR 0.645 = 1.725 against €40
        $this->assertEqualsWithDelta(1.725, $linhas[$a->id]['custo'], 1e-4);
        $this->assertEqualsWithDelta(0.54, $linhas[$a->id]['passos']['ai_change_3d'], 1e-4);
        $this->assertSame([40.0, 38.28, 95.7], [$linhas[$a->id]['receita'], $linhas[$a->id]['margem'], $linhas[$a->id]['margem_pct']]);
        // B: 0.9 + 1.0 against €50
        $this->assertSame([50.0, 48.1], [$linhas[$b->id]['receita'], $linhas[$b->id]['margem']]);

        $r = app(ImoplantaCustos::class)->resumo();
        $this->assertSame(['imoplanta1', 'imoplanta10'], array_keys($r['tiers']));
        $this->assertSame(2, $r['media']['imoveis']);
        $this->assertEqualsWithDelta((1.725 + 1.9) / 2, $r['media']['custo'], 1e-4);
        $this->assertEqualsWithDelta((0.45 + 0.9) / 2, $r['passos']['extract'], 1e-4);
    }

    public function test_blender_is_gone(): void
    {
        $this->assertNotContains('blender', array_keys(config('contentmachine.workers.queues')));
        $this->assertSame(['extract', 'ai_change_3d', 'references', 'image_generate', 'image_redo', 'video', 'furniture_video', 'stripe_fee'], ImoplantaCustos::PASSOS);
        $this->assertFileDoesNotExist(base_path('scripts/imoplanta/build.py'));
        $this->assertFileExists(base_path('resources/js/imoplanta/builder.js'));
        $ficheiros = [base_path('Dockerfile'), base_path('docker/supervisord.conf'), base_path('deploy/README.md'), config_path('contentmachine.php'), base_path('routes/web.php')];
        foreach (['app', 'resources/views', 'resources/js'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)) as $f) {
                $ficheiros[] = $f->getPathname();
            }
        }
        foreach ($ficheiros as $f) {
            $this->assertDoesNotMatchRegularExpression('/blender/i', file_get_contents($f), "$f still mentions Blender");
        }
    }

    public function test_llm_prices_come_from_config_by_longest_model_prefix(): void
    {
        $c = app(ImoplantaCustos::class);
        $this->assertSame([0.15, 0.60, 0.075], $c->precoLlm('gpt-4o-mini-2024'));
        $this->assertSame([2.5, 10.0, 1.25], $c->precoLlm('gpt-4o'));
        $this->assertSame([5.0, 25.0, 0.5], $c->precoLlm('some-unknown-model'));
    }

    public function test_with_enough_credits_the_last_step_shows_no_price_and_the_property_starts_with_credits(): void
    {
        Queue::fake();
        $u = User::factory()->cliente()->create();
        DB::table('compras')->insert(['user_id' => $u->id, 'sessao' => 'cs_c', 'total' => 10000, 'videos' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($u);
        $p = $this->imovel($u, ['estado' => Imoplanta::A_PAGAR, 'casa' => null, 'cameras' => null]);

        $c = Livewire::test(\App\Livewire\Imoplanta\Comecar::class)->assertSee('dos seus 10 créditos de vídeo')
            ->assertDontSee("50\u{00A0}€")->assertDontSee('Quantos créditos de vídeo?');
        $c->call('pagar')->assertRedirect(route('imoplanta.imovel', $p->id));
        $this->assertSame([Imoplanta::A_LER, 5], [$p->refresh()->estado, $u->creditos()]);

        $this->get(route('conta'))->assertSee('Créditos de vídeo')->assertSee('Comprar mais');   // the balance in the sidebar
    }
}
