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
        Queue::assertNothingPushed();
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

    public function test_drone_frames_keep_the_photo_as_start_and_make_the_end_with_nano_banana_pro(): void
    {
        $this->kie();
        $u = $this->cliente(1);
        $f = $this->video($u);
        Queue::fake();
        app(Ferramentas::class)->iniciar($f);

        app()->call([new FerramentaFramesJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame(FerramentaIa::A_APROVAR, $f->estado);
        $this->assertSame($f->original, $f->inicio);
        $this->assertNotNull($f->fim);
        $this->assertSame([$f->fim], $f->versoes);
        // cut to the photo's exact size, so Kling's two frames match
        $this->assertSame([1600, 1000], array_slice(getimagesizefromstring(Storage::disk('local')->get($f->fim)), 0, 2));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'nano-banana-pro' && $r['input']['resolution'] === '2K');
        $this->assertSame([['frames', 0.09]], $this->custos($f));
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

        $this->assertFalse($s->alterar($f, '   '));   // nothing asked
        foreach (range(1, 3) as $i) {
            $this->assertTrue($s->alterar($f->refresh(), 'mais alto'));
            $s->devolverAlteracao($f->refresh(), 'x');   // failed: given back
            $this->assertSame($i - 1, $f->refresh()->edicoes);
            $this->assertTrue($s->alterar($f->refresh(), 'mais alto'));
            FerramentaIa::whereKey($f->id)->update(['estado' => FerramentaIa::A_APROVAR]);
        }
        $this->assertSame(3, $f->refresh()->edicoes);
        $this->assertFalse($s->alterar($f, 'outra'));
    }

    public function test_frames_lock_once_approved_and_the_clip_is_kling_3_with_sound(): void
    {
        $this->kie();
        $f = $this->video($this->cliente(), FerramentaIa::A_APROVAR);
        $f->update(['inicio' => $f->original, 'fim' => $f->original]);
        Queue::fake();

        $this->assertTrue(app(Ferramentas::class)->aprovar($f));
        $this->assertFalse(app(Ferramentas::class)->alterar($f->refresh(), 'mais alto'));
        app()->call([new FerramentaVideoJob($f->id, false), 'handle']);

        $f->refresh();
        $this->assertSame(FerramentaIa::PRONTO, $f->estado);
        $this->assertSame('mp4', Storage::disk('local')->get($f->video));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createTask') && $r['model'] === 'kling-3.0/video'
            && count($r['input']['image_urls']) === 2 && $r['input']['duration'] === '10' && $r['input']['sound'] === true && $r['input']['mode'] === 'pro');
        $this->assertSame([['video', 1.51]], $this->custos($f));
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
}
