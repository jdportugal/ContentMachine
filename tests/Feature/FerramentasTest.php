<?php

namespace Tests\Feature;

use App\Jobs\Ferramentas\FerramentaFramesJob;
use App\Jobs\Ferramentas\FerramentaVideoJob;
use App\Models\FerramentaIa;
use App\Models\User;
use App\Services\Ferramentas\Ferramentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ferramentas IA: packs of AI videos, one spent per video, the frames the customer approves (3 changes), the
 * Kling clip (2 new versions), refunds, and what each step costs.
 */
class FerramentasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['contentmachine.stripe.webhook_secret' => 'whsec_test']);
        foreach (['ia1', 'ia10', 'ia20'] as $pack) {
            config(['contentmachine.stripe.links.'.$pack => 'https://buy.stripe.com/test_'.$pack]);
        }
    }

    private function cliente(int $videos = 0): User
    {
        $u = User::factory()->cliente()->create();
        if ($videos) {
            DB::table('compras')->insert(['user_id' => $u->id, 'sessao' => 'cs_'.uniqid(), 'total' => 22500, 'videos_ia' => $videos, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $u;
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('casa.jpg', 1600, 1000);
    }

    private function video(User $u, string $estado = FerramentaIa::RASCUNHO): FerramentaIa
    {
        $f = app(Ferramentas::class)->criar($u, 'drone', $this->foto());
        $f->update(['estado' => $estado]);

        return $f->refresh();
    }

    /** kie faked: uploads, tasks (Kling ones apart), results; $falha makes every generation fail. */
    private function kie(bool $falha = false): void
    {
        config(['services.kie.key' => 'kie-test', 'services.kie.base_url' => 'https://api.kie.ai']);
        $png = UploadedFile::fake()->image('x.png', 1376, 768)->getContent();
        Http::fake([
            'kieai.redpandaai.co/*' => Http::response(['data' => ['downloadUrl' => 'https://files.test/up.png']]),
            'api.kie.ai/api/v1/jobs/createTask' => fn (HttpRequest $r) => Http::response(['data' => ['taskId' => str_contains($r['model'], 'kling') ? 'tv' : 'ti']]),
            'api.kie.ai/api/v1/jobs/recordInfo?taskId=tv' => Http::response(['data' => $falha ? ['state' => 'fail'] : ['state' => 'success', 'model' => 'kling-3.0/video', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/clip.mp4']])]]),
            'api.kie.ai/api/v1/jobs/recordInfo*' => Http::response(['data' => $falha ? ['state' => 'fail'] : ['state' => 'success', 'model' => 'nano-banana-pro', 'resultJson' => json_encode(['resultUrls' => ['https://files.test/quadro.png']])]]),
            'files.test/clip.mp4' => Http::response('mp4'),
            'files.test/*' => Http::response($png),
        ]);
    }

    private function custos(FerramentaIa $f): array
    {
        return DB::table('custos')->where('tipo', 'ferramenta')->where('peca', (string) $f->id)->orderBy('id')
            ->get(['passo', 'custo'])->map(fn ($c) => [$c->passo, round((float) $c->custo, 3)])->all();
    }

    public function test_a_paid_pack_becomes_ai_videos(): void
    {
        $u = $this->cliente();
        app(\App\Services\Pagamentos\Compras::class)->registar([
            'id' => 'cs_ia10', 'payment_status' => 'paid', 'amount_total' => 22500, 'currency' => 'eur',
            'metadata' => ['videos_ia' => '10'], 'customer_details' => ['email' => $u->email],
        ]);

        $this->assertSame(10, $u->videosIaDisponiveis());
        $this->assertSame(0, $u->creditos());   // not Imocena videos
    }

    public function test_starting_spends_one_video_and_only_once(): void
    {
        Queue::fake();
        $u = $this->cliente(10);
        $f = $this->video($u);

        $this->assertTrue(app(Ferramentas::class)->iniciar($f));
        $this->assertFalse(app(Ferramentas::class)->iniciar($f->refresh()));   // a double click

        $this->assertSame(9, $u->videosIaDisponiveis());
        $this->assertSame(FerramentaIa::A_ENQUADRAR, $f->refresh()->estado);
        Queue::assertPushed(FerramentaFramesJob::class, 1);
    }

    public function test_without_videos_nothing_starts(): void
    {
        Queue::fake();
        $f = $this->video($this->cliente());

        $this->assertFalse(app(Ferramentas::class)->iniciar($f));
        $this->assertSame(FerramentaIa::RASCUNHO, $f->refresh()->estado);
        Queue::assertNotPushed(FerramentaFramesJob::class);
    }

    public function test_admins_and_free_accounts_spend_nothing(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $gratis = User::factory()->cliente()->create(['email' => 'gratis@example.com']);
        config(['contentmachine.imoplanta.gratis' => ['gratis@example.com']]);

        $this->assertTrue(app(Ferramentas::class)->iniciar($this->video($admin)));
        $this->assertTrue(app(Ferramentas::class)->iniciar($this->video($gratis)));
        $this->assertSame(0, DB::table('compras')->sum('videos_ia_usados'));
    }

    public function test_upload_is_stored_as_an_upright_jpeg_no_bigger_than_2560(): void
    {
        $f = app(Ferramentas::class)->criar($this->cliente(), 'drone', UploadedFile::fake()->image('grande.png', 4000, 3000));
        [$w, $h, $tipo] = getimagesizefromstring(Storage::disk('local')->get($f->original));

        $this->assertSame([2560, 1920, IMAGETYPE_JPEG], [$w, $h, $tipo]);
    }

    public function test_drone_makes_both_frames_the_end_from_the_start_and_each_is_cut_to_the_photo(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = $this->video($u);
        Queue::fake();
        app(Ferramentas::class)->iniciar($f);

        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame(FerramentaIa::A_APROVAR, $f->estado);
        $this->assertNotSame($f->original, $f->inicio);   // both frames are the AI's: the aerial view, then after orbiting
        $this->assertSame(['inicio' => [$f->inicio], 'fim' => [$f->fim]], $f->versoes);
        foreach ([$f->inicio, $f->fim] as $quadro) {   // cut to the photo's exact size, so Kling's two frames match
            $this->assertSame([1600, 1000], array_slice(getimagesizefromstring(Storage::disk('local')->get($quadro)), 0, 2));
        }
        $pedidos = collect(Http::recorded())->map(fn ($r) => $r[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->values();
        $this->assertCount(2, $pedidos);
        $this->assertTrue($pedidos->every(fn ($r) => $r['model'] === 'nano-banana-pro' && $r['input']['resolution'] === '2K'));
        $this->assertCount(1, $pedidos[0]['input']['image_input']);   // the start: from the photo
        $this->assertCount(2, $pedidos[1]['input']['image_input']);   // the end: from the start, and the photo
        $this->assertSame([['frames', 0.09], ['frames', 0.09]], $this->custos($f));
    }

    public function test_failed_first_frames_refund_once_and_go_back_to_draft(): void
    {
        $this->kie(falha: true);
        $u = $this->cliente(1);
        $f = $this->video($u);
        Queue::fake();
        app(Ferramentas::class)->iniciar($f);
        $this->assertSame(0, $u->videosIaDisponiveis());

        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);
        app(Ferramentas::class)->devolver($f->refresh(), 'outra vez');   // a second failure report gives nothing more back

        $f->refresh();
        $this->assertSame(FerramentaIa::RASCUNHO, $f->estado);
        $this->assertNull($f->compra_id);
        $this->assertSame(1, $u->videosIaDisponiveis());
        $this->assertNotNull($f->original);
    }

    public function test_three_frame_changes_then_no_more_and_a_failed_change_is_not_counted(): void
    {
        Queue::fake();
        $f = $this->video($this->cliente(), FerramentaIa::A_APROVAR);
        $s = app(Ferramentas::class);

        $this->assertFalse($s->alterar($f, 'fim', '   '));   // nothing asked
        $this->assertFalse($s->alterar($f, 'original', 'x'));   // the customer's photo is never changed
        foreach (range(1, 3) as $i) {
            $this->assertTrue($s->alterar($f->refresh(), 'fim', 'mais alto'));
            $s->devolverAlteracao($f->refresh(), 'x');   // failed: given back
            $this->assertSame($i - 1, $f->refresh()->edicoes);
            $this->assertTrue($s->alterar($f->refresh(), 'inicio', 'mais alto'));
            FerramentaIa::whereKey($f->id)->update(['estado' => FerramentaIa::A_APROVAR]);
        }
        $this->assertSame(3, $f->refresh()->edicoes);
        $this->assertFalse($s->alterar($f, 'fim', 'outra'));
    }

    public function test_frames_lock_once_approved_and_the_drone_clip_is_kling_3_silent(): void
    {
        $this->kie();
        $f = $this->video($this->cliente(), FerramentaIa::A_APROVAR);
        $f->update(['inicio' => $f->original, 'fim' => $f->original]);
        Queue::fake();

        $this->assertTrue(app(Ferramentas::class)->aprovar($f));
        $this->assertFalse(app(Ferramentas::class)->alterar($f->refresh(), 'fim', 'mais alto'));
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame(FerramentaIa::PRONTO, $f->estado);
        $this->assertSame('mp4', Storage::disk('local')->get($f->video));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && count($r['input']['image_urls']) === 2 && $r['input']['duration'] === '10' && $r['input']['sound'] === false && $r['input']['mode'] === 'pro');
        $this->assertSame([['video', 1.01]], $this->custos($f));
    }

    public function test_two_new_versions_of_the_clip_and_a_failed_one_keeps_the_video(): void
    {
        $f = $this->video($this->cliente(), FerramentaIa::PRONTO);
        $f->update(['inicio' => $f->original, 'fim' => $f->original, 'video' => 'antigo.mp4']);
        $this->kie(falha: true);
        Queue::fake();

        $this->assertTrue(app(Ferramentas::class)->refazer($f, 'mais devagar'));
        $this->assertSame('mais devagar', $f->refresh()->pedido);
        app()->call([new FerramentaVideoJob($f->id, true), 'handle']);
        $f->refresh();
        $this->assertSame([FerramentaIa::PRONTO, 'antigo.mp4', 0], [$f->estado, $f->video, $f->refeitos]);
        Http::assertSentCount(2 + 2 * 2);   // 2 uploads, then 2 Kling attempts (submit + poll), not 4

        $this->assertTrue(app(Ferramentas::class)->refazer($f, ''));
        FerramentaIa::whereKey($f->id)->update(['estado' => FerramentaIa::PRONTO]);
        $this->assertTrue(app(Ferramentas::class)->refazer($f->refresh(), ''));
        FerramentaIa::whereKey($f->id)->update(['estado' => FerramentaIa::PRONTO]);
        $this->assertFalse(app(Ferramentas::class)->refazer($f->refresh(), ''));
    }

    public function test_the_pack_link_carries_the_video_and_the_email(): void
    {
        $u = $this->cliente();
        $f = $this->video($u);

        $this->assertSame('https://buy.stripe.com/test_ia10?'.http_build_query(['client_reference_id' => 'ferramenta-'.$f->id, 'prefilled_email' => $u->email, 'locale' => 'pt']),
            Ferramentas::link($u, 'ia10', $f));
        $this->assertSame(7, \App\Services\Pagamentos\Compras::ferramentaId('ferramenta-7'));
    }

    public function test_only_the_listed_accounts_see_the_tools(): void
    {
        $outro = $this->cliente();
        $joao = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        config(['contentmachine.imoplanta.acesso' => ['joaodiogocferreira@gmail.com']]);

        $this->actingAs($outro)->get(route('ferramentas'))->assertForbidden();
        $this->actingAs($outro)->get(route('conta'))->assertDontSee(route('ferramentas'), false);
        $this->actingAs($joao)->get(route('conta'))->assertSee(route('ferramentas'), false);
        $this->actingAs($joao)->get(route('ferramentas'))->assertOk()->assertSee('Vista drone')->assertSee(route('ferramentas.nova', 'drone'), false);
        $this->get(route('ferramentas.sobre'))->assertOk();   // the landing is public
    }

    public function test_customers_see_only_their_own_videos(): void
    {
        $dono = User::factory()->cliente()->create(['email' => 'joaodiogocferreira@gmail.com']);
        $outro = User::factory()->cliente()->create(['email' => 'outro@example.com']);
        config(['contentmachine.imoplanta.acesso' => ['joaodiogocferreira@gmail.com', 'outro@example.com']]);
        $f = $this->video($dono);

        $this->actingAs($outro)->get(route('ferramentas.video', $f->id))->assertForbidden();
        $this->actingAs($outro)->get(route('ferramentas.ficheiro', [$f->id, 'original']))->assertNotFound();
        $this->actingAs($dono)->get(route('ferramentas.ficheiro', [$f->id, 'original']))->assertOk();
        $this->actingAs($dono)->get(route('ferramentas.video', $f->id))->assertOk();
    }

    public function test_upload_validates_then_is_checked_and_creating_images_spends_one_video(): void
    {
        Queue::fake();
        $u = $this->cliente(1);
        \Illuminate\Support\Facades\Gate::define('ferramentas', fn () => true);

        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Ferramentas\Video::class, ['ferramenta' => 'drone'])
            ->set('foto', UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'))->assertHasErrors('foto');
        $this->assertSame(0, FerramentaIa::count());

        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Ferramentas\Video::class, ['ferramenta' => 'drone'])
            ->set('foto', $this->foto())->assertRedirect(route('ferramentas.video', FerramentaIa::first()->id));
        $f = FerramentaIa::first();
        $this->assertSame([FerramentaIa::RASCUNHO, 'a_analisar'], [$f->estado, $f->analise['estado']]);
        Queue::assertPushed(\App\Jobs\Ferramentas\AnalisarFotoJob::class);
        $this->assertSame(1, $u->videosIaDisponiveis());   // nothing spent by uploading

        \Livewire\Livewire::actingAs($u)->test(\App\Livewire\Ferramentas\Video::class, ['video' => $f])->call('criar');
        $this->assertSame(FerramentaIa::A_ENQUADRAR, $f->refresh()->estado);
        $this->assertSame(0, $u->videosIaDisponiveis());
    }

    public function test_a_photo_that_is_not_ideal_is_flagged_with_why_and_the_customer_can_still_go_on(): void
    {
        Queue::fake();
        config(['services.anthropic.key' => 'sk-test']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode([
            'adequada' => false, 'problemas' => ['A casa está cortada dos dois lados.'], 'dica' => 'Use uma fotografia tirada da rua com a casa inteira.',
        ])]], 'usage' => ['input_tokens' => 1500, 'output_tokens' => 80]])]);
        $u = $this->cliente(1);
        \Illuminate\Support\Facades\Gate::define('ferramentas', fn () => true);
        $f = $this->video($u);   // 1600 px: big enough

        app()->call([new \App\Jobs\Ferramentas\AnalisarFotoJob($f->id, $f->original), 'handle']);

        $f->refresh();
        $this->assertTrue($f->fotoNaoIdeal());
        $this->assertSame(['A casa está cortada dos dois lados.'], $f->analise['problemas']);
        $this->assertNotEmpty(DB::table('custos')->where('tipo', 'ferramenta')->where('peca', (string) $f->id)->where('passo', 'analise')->get());
        $this->actingAs($u)->get(route('ferramentas.video', $f->id))->assertOk()
            ->assertSee('Esta fotografia não é a ideal')->assertSee('A casa está cortada dos dois lados.')
            ->assertSee('Continuar com esta fotografia');
    }

    public function test_a_small_photo_is_never_ideal_and_a_failed_check_blocks_nothing(): void
    {
        Queue::fake();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'drone', UploadedFile::fake()->image('pequena.jpg', 800, 500));
        config(['services.anthropic.key' => 'sk-test']);
        Http::fake(['api.anthropic.com/*' => Http::sequence()   // the first check answers; then the API is down
            ->push(['content' => [['type' => 'text', 'text' => '{"adequada": true, "problemas": [], "dica": ""}']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])
            ->whenEmpty(Http::response('down', 500))]);

        app()->call([new \App\Jobs\Ferramentas\AnalisarFotoJob($f->id, $f->original), 'handle']);
        $this->assertTrue($f->refresh()->fotoNaoIdeal());
        $this->assertStringContainsString('800×500', $f->analise['problemas'][0]);

        $g = $this->video($u);
        app()->call([new \App\Jobs\Ferramentas\AnalisarFotoJob($g->id, $g->original), 'handle']);
        $this->assertSame('falhou', $g->refresh()->analise['estado']);
        $this->assertFalse($g->aAnalisar());
        $this->assertFalse($g->fotoNaoIdeal());
    }

    public function test_with_no_videos_left_the_draft_keeps_the_photo_and_the_page_offers_the_packs(): void
    {
        Queue::fake();
        $u = $this->cliente();
        \Illuminate\Support\Facades\Gate::define('ferramentas', fn () => true);
        $f = $this->video($u);

        $this->actingAs($u)->get(route('ferramentas.video', $f->id))->assertOk()
            ->assertSee('Já não tem vídeos IA')->assertDontSee('Criar imagens →')
            ->assertSee(e(Ferramentas::link($u, 'ia1', $f)), false)->assertSee("225\u{00A0}€");
    }

    public function test_back_from_paying_goes_to_the_video(): void
    {
        $u = $this->cliente();
        $f = $this->video($u);
        DB::table('compras')->insert(['user_id' => $u->id, 'sessao' => 'cs_back', 'total' => 2500, 'videos_ia' => 1,
            'dados' => json_encode(['client_reference_id' => 'ferramenta-'.$f->id]), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($u)->get(route('obrigado', ['session_id' => 'cs_back']))->assertRedirect(route('ferramentas.video', $f->id));
    }

    public function test_the_side_move_builds_a_real_sideways_end_frame_from_a_depth_map_then_a_5_second_silent_clip(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'lateral', $this->foto());
        $this->assertSame('direita', $f->direcao());
        $this->assertTrue(app(Ferramentas::class)->direcao($f, 'esquerda'));
        $this->assertFalse(app(Ferramentas::class)->direcao($f, 'cima'));
        Queue::fake();
        app(Ferramentas::class)->iniciar($f->refresh());
        $this->assertFalse(app(Ferramentas::class)->direcao($f->refresh(), 'direita'));   // fixed once the frames are being made

        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame(FerramentaIa::A_APROVAR, $f->estado);
        $this->assertTrue(Storage::disk('local')->exists($f->pasta().'/profundidade.png'));
        // both frames: the same window of the photo (1600 px wide, 92 %), the start a cut of it, the end built from it
        $this->assertNotSame($f->original, $f->inicio);
        $this->assertSame([1472, 1000], array_slice(getimagesizefromstring(Storage::disk('local')->get($f->inicio)), 0, 2));
        $this->assertSame([1472, 1000], array_slice(getimagesizefromstring(Storage::disk('local')->get($f->fim)), 0, 2));
        $pedidos = collect(Http::recorded())->map(fn ($r) => $r[0])->filter(fn ($r) => str_contains($r->url(), 'createTask'))->values();
        $this->assertStringContainsString('depth map', $pedidos[0]['input']['prompt']);
        $this->assertStringContainsString('warping artifacts', $pedidos[1]['input']['prompt']);
        $this->assertSame([['frames', 0.09], ['frames', 0.09]], $this->custos($f));

        app(Ferramentas::class)->aprovar($f);
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);
        $this->assertSame(FerramentaIa::PRONTO, $f->refresh()->estado);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && count($r['input']['image_urls']) === 2 && $r['input']['duration'] === '5' && $r['input']['sound'] === false
            && str_contains($r['input']['prompt'], 'travels sideways to the left'));
        $this->assertSame([['frames', 0.09], ['frames', 0.09], ['video', 0.505]], $this->custos($f));
    }

    public function test_the_sideways_shift_moves_near_pixels_further_than_far_ones_the_way_the_camera_goes(): void
    {
        $dir = sys_get_temp_dir().'/deslocar-'.uniqid();
        mkdir($dir);
        // a photo of random vertical stripes (no repeating pattern to mistake one shift for another);
        // depth: the left half far (black), the right half near (white)
        mt_srand(7);
        $foto = imagecreatetruecolor(1600, 1000);
        for ($x = 0; $x < 1600; $x++) {
            $v = mt_rand(0, 255);
            imageline($foto, $x, 0, $x, 999, ($v << 16) | ($v << 8) | $v);
        }
        imagepng($foto, "$dir/foto.png");
        $prof = imagecreatetruecolor(1600, 1000);
        imagefilledrectangle($prof, 800, 0, 1599, 999, 0xFFFFFF);
        imagepng($prof, "$dir/prof.png");
        $desvio = function (string $fim, string $inicio, int $x0) {   // how far the stripes at $x0 moved (px)
            $a = imagecreatefrompng($inicio);
            $b = imagecreatefrompng($fim);
            $melhor = [PHP_INT_MAX, 0];
            foreach (range(-130, 130) as $d) {
                $e = 0;
                foreach (range($x0, $x0 + 200, 2) as $x) {
                    $e += abs((imagecolorat($a, $x, 500) & 0xFF) - (imagecolorat($b, $x + $d, 500) & 0xFF));
                }
                $melhor = min($melhor, [$e, $d]);
            }

            return $melhor[1];
        };

        [$inicio, $fim] = \App\Services\Ferramentas\Realizador::deslocar("$dir/foto.png", "$dir/prof.png", false, $dir);
        $longe = $desvio($fim, $inicio, 300);    // far, left half
        $perto = $desvio($fim, $inicio, 1100);   // near, right half
        $this->assertLessThan(0, $perto);        // moving right: the picture slides left
        $this->assertLessThan(abs($perto), abs($longe) * 2.5);   // near pixels move several times further than far ones

        [$inicio, $fim] = \App\Services\Ferramentas\Realizador::deslocar("$dir/foto.png", "$dir/prof.png", true, $dir);
        $this->assertGreaterThan(0, $desvio($fim, $inicio, 1000));   // moving left: the picture slides right
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    public function test_the_cleanup_is_a_photo_tidied_with_the_customers_list_and_no_video(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'lateral', $this->foto());
        $this->assertFalse(app(Ferramentas::class)->texto($f, 'o carro'));   // the side move has no text
        $f = app(Ferramentas::class)->criar($u, 'limpeza', $this->foto());
        $this->assertTrue(app(Ferramentas::class)->texto($f, '  o carro e as caixas  '));
        Queue::fake();
        app(Ferramentas::class)->iniciar($f->refresh());
        $this->assertFalse(app(Ferramentas::class)->texto($f->refresh(), 'mais'));   // fixed once the photo is being made

        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame([FerramentaIa::A_APROVAR, $f->original], [$f->estado, $f->inicio]);   // "before" is the photo itself
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro'
            && str_contains($r['input']['prompt'], 'The client also asks to remove: o carro e as caixas.'));

        $this->assertTrue(app(Ferramentas::class)->aprovar($f));
        $this->assertSame(FerramentaIa::PRONTO, $f->refresh()->estado);   // done: no video
        Queue::assertNotPushed(FerramentaVideoJob::class);
        $this->assertFalse(app(Ferramentas::class)->refazer($f, ''));
        $this->assertSame([['frames', 0.09]], $this->custos($f));
        \Illuminate\Support\Facades\Gate::define('ferramentas', fn () => true);
        $this->actingAs($u)->get(route('ferramentas.video', $f->id))->assertOk()
            ->assertSee('Descarregar fotografia limpa')->assertSee('Antes')->assertDontSee('Refazer vídeo');
    }

    public function test_the_remodel_draws_the_renovated_room_in_the_customers_style_and_plays_the_works_with_sound(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'remodelacao', $this->foto());
        $this->assertTrue(app(Ferramentas::class)->texto($f, 'escandinavo'));
        Queue::fake();
        app(Ferramentas::class)->iniciar($f->refresh());
        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame([FerramentaIa::A_APROVAR, $f->original], [$f->estado, $f->inicio]);   // the start is the photo
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro'
            && str_contains($r['input']['prompt'], 'The client wants this style: escandinavo.'));

        $this->assertTrue(app(Ferramentas::class)->aprovar($f));
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);
        $this->assertSame(FerramentaIa::PRONTO, $f->refresh()->estado);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && $r['input']['sound'] === true && str_contains($r['input']['prompt'], 'timelapse'));
        $this->assertSame([['frames', 0.09], ['video', 1.51]], $this->custos($f));
    }

    public function test_a_ruin_is_rebuilt_on_its_footprint_from_the_customers_description_in_a_longer_clip(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'remodelacao', $this->foto());
        $this->assertSame('interior', $f->obra());                                  // interior unless chosen
        $this->assertFalse(app(Ferramentas::class)->obra($f, 'castelo'));
        $this->assertTrue(app(Ferramentas::class)->obra($f, 'ruina'));
        $this->assertTrue(app(Ferramentas::class)->texto($f, 'um armazém moderno de 60 m2'));
        Queue::fake();
        app(Ferramentas::class)->iniciar($f->refresh());
        $this->assertFalse(app(Ferramentas::class)->obra($f->refresh(), 'exterior'));  // fixed once the frames start
        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro'
            && str_contains($r['input']['prompt'], "on the ruin's footprint")
            && str_contains($r['input']['prompt'], 'The client describes what is built: um armazém moderno de 60 m2.'));
        $this->assertTrue(app(Ferramentas::class)->aprovar($f->refresh()));
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && str_contains($r['input']['prompt'], 'the bare structure, in full view'));
        $this->assertEqualsWithDelta(15 * 0.151, $this->custos($f)[1][1], 0.001);   // a build from the ground: 15 s
    }

    public function test_the_new_build_draws_the_customers_house_on_their_plot_and_builds_it_in_order(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = app(Ferramentas::class)->criar($u, 'construcao', $this->foto());
        $this->assertTrue(app(Ferramentas::class)->texto($f, 'moradia T3 de dois pisos'));
        Queue::fake();
        app(Ferramentas::class)->iniciar($f->refresh());
        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame([FerramentaIa::A_APROVAR, $f->original], [$f->estado, $f->inicio]);   // the start is the plot
        $this->assertNotSame($f->original, $f->fim);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro'
            && str_contains($r['input']['prompt'], 'The ground shows where the building goes')
            && str_contains($r['input']['prompt'], 'The client describes what is built: moradia T3 de dois pisos.'));
        $this->assertTrue(app(Ferramentas::class)->aprovar($f));
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && $r['input']['sound'] === true && $r['input']['duration'] === '15' && str_contains($r['input']['prompt'], 'the ground is prepared'));
    }
}
