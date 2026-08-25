<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\FinalizeClipPlanJob;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The animated intro video: a reusable asset generated from a start image. */
class IntroVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_video_envia_o_modelo_kling_e_regista_o_custo(): void
    {
        config(['services.kie.key' => 'k', 'contentmachine.custos.kie_video' => 0.5]);
        Http::fake([
            '*createTask*' => Http::response(['data' => ['taskId' => 'vt1']]),
            '*recordInfo*' => Http::response(['data' => ['state' => 'success', 'resultJson' => json_encode(['resultUrls' => ['https://kie/v.mp4']])]]),
        ]);

        app(\App\Services\Costs\CostLedger::class)->contexto('clip', 'c1');
        $url = (new KieClient)->generateVideo('a mascot waves', 'https://kie/start.png');

        $this->assertSame('https://kie/v.mp4', $url);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'createTask')
            && $r['model'] === 'kling-2.6/image-to-video'
            && $r['input']['image_urls'] === ['https://kie/start.png']
            && $r['input']['duration'] === '5'
            && $r['input']['sound'] === false);
        $this->assertEqualsWithDelta(0.5, app(\App\Services\Costs\CostLedger::class)->totaisPorPeca('clip')['c1'], 0.0001);
    }

    public function test_a_sugestao_de_video_so_aparece_com_o_efeito_marcado_como_intro(): void
    {
        $library = app(EffectLibrary::class);

        $this->assertSame([], ImageRequests::withIntroVideo([], $library), 'not marked intro → no suggestion');

        $library->toggleIntroBuiltin('video-intro');
        $requests = ImageRequests::withIntroVideo([], $library);
        $this->assertSame(ImageRequests::VIDEO_KEY, $requests[0]['key']);

        // A clip that already carries a video (site capture, upload) gets none.
        $this->assertSame([], ImageRequests::withIntroVideo([], $library, [['id' => 'x', 'video' => true]]));

        // And the plain intro-image suggestion stands aside for it.
        $this->assertSame($requests, ImageRequests::withIntro($requests, $library));
    }

    /**
     * Without an approved video (no start image generated, or declined), the
     * classic intro path must NOT grab `video-intro` and feed it a random still —
     * that rendered a broken opening with an arbitrary image.
     */
    public function test_sem_video_aprovado_o_video_intro_nunca_recebe_uma_imagem_aleatoria(): void
    {
        Queue::fake();
        config(['contentmachine.clips.generate_images' => false]);
        app(EffectLibrary::class)->toggleIntroBuiltin('video-intro');
        Storage::fake(config('contentmachine.clips.disk'));

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_COLLECTING,
            'images' => [['id' => 'img_a', 'path' => 'clips/uploads/a.png', 'description' => 'random pic', 'transparent' => true, 'tone' => 'mixed']],
            'plan' => ['duration' => 6.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [['type' => 'card', 'text' => null, 'params' => ['title' => 'A']]]],
                ['start' => 3, 'end' => 6, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [['type' => 'card', 'text' => null, 'params' => ['title' => 'B']]]],
            ]],
            'meta' => [
                'image_requests' => [[
                    'key' => ImageRequests::VIDEO_KEY, 'prompt' => 'An animated video: x', 'label' => 'x',
                    'site' => null, 'animado' => true, // imageless — never generated
                ]],
                'image_uploads' => [],
            ],
        ]);

        (new FinalizeClipPlanJob($p->id))->handle(app(ClipStore::class));

        $p = app(ClipStore::class)->findOrFail($p->id);
        foreach ($p->plan['scenes'] as $scene) {
            foreach ($scene['layers'] as $layer) {
                $this->assertNotSame('video-intro', $layer['type'] ?? null);
            }
        }
    }

    /** «Generate now» makes a plain suggestion's image DURING review and shows it. */
    public function test_gerar_agora_preenche_a_sugestao_durante_a_revisao(): void
    {
        config(['services.kie.key' => 'k']);
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        Http::fake([
            '*createTask*' => Http::response(['data' => ['taskId' => 't9']]),
            '*recordInfo*' => Http::response(['data' => ['state' => 'success', 'resultJson' => json_encode(['resultUrls' => ['https://kie/img.png']])]]),
            'kie/img.png' => Http::response('PNG'),
        ]);

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_COLLECTING,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'scenes' => []],
            'meta' => [
                'image_requests' => [['key' => 'intro', 'prompt' => 'The brand logo, centred.', 'label' => 'Opening image', 'site' => null]],
                'site_collecting' => ['intro' => true],
            ],
        ]);

        (new \App\Jobs\Clips\GenerateRequestImageJob($p->id, 'intro'))->handle(app(ClipStore::class));

        $p = app(ClipStore::class)->findOrFail($p->id);
        $id = $p->meta['image_uploads']['intro'] ?? null;
        $this->assertNotNull($id, 'the generated image fulfils the suggestion');
        $this->assertTrue((bool) collect($p->images)->firstWhere('id', $id)['generated']);
        $this->assertArrayNotHasKey('intro', $p->meta['site_collecting'] ?? [], 'the wait flag is cleared');
    }

    /** The user can edit the generation prompt — generation uses THEIR wording. */
    public function test_o_prompt_editado_e_persistido_e_usado_na_geracao(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->comSessaoIniciada();

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_COLLECTING,
            'plan' => ['duration' => 3.0, 'mode' => 'dense', 'scenes' => []],
            'meta' => ['image_requests' => [['key' => 'intro', 'prompt' => 'original prompt', 'label' => 'Opening image', 'site' => null]]],
        ]);

        \Livewire\Livewire::test(\App\Livewire\ClipsAnimados::class)
            ->call('revisarImagens', $p->id)
            ->call('editarPrompt', 'intro')
            ->assertSet('promptEdits.intro', 'original prompt')
            ->set('promptEdits.intro', 'a glowing watermark inside printed text')
            ->call('gerarImagemAgora', 'intro');

        $p = app(ClipStore::class)->findOrFail($p->id);
        $this->assertSame('a glowing watermark inside printed text', $p->meta['image_requests'][0]['prompt']);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Clips\GenerateRequestImageJob::class);
    }

    /** A generated image's thumbnail must actually be served (clips/generated). */
    public function test_a_rota_de_miniaturas_serve_imagens_geradas(): void
    {
        $this->comSessaoIniciada();
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $disk->put('clips/generated/abc123.png', 'PNG');

        $this->get(route('clips-animados.upload', 'abc123.png'))->assertOk();
        $this->get(route('clips-animados.upload', 'missing.png'))->assertNotFound();
    }

    public function test_aprovar_gera_o_video_guarda_o_asset_e_abre_o_clip_com_ele(): void
    {
        Queue::fake();
        config(['services.kie.key' => 'k', 'contentmachine.clips.generate_images' => false]);
        app(EffectLibrary::class)->toggleIntroBuiltin('video-intro');

        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $disk->put('clips/generated/start.png', 'PNGBYTES');

        Http::fake([
            '*file-base64-upload*' => Http::response(['data' => ['downloadUrl' => 'https://kie/files/start.png']]),
            '*createTask*' => Http::response(['data' => ['taskId' => 'vt2']]),
            '*recordInfo*' => Http::response(['data' => ['state' => 'success', 'resultJson' => json_encode(['resultUrls' => ['https://kie/out.mp4']])]]),
            'kie/out.mp4' => Http::response('MP4BYTES'),
        ]);

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_COLLECTING,
            'images' => [['id' => 'gen_1', 'path' => 'clips/generated/start.png', 'description' => 'start', 'transparent' => false, 'tone' => 'mixed']],
            'plan' => ['duration' => 6.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [['type' => 'card', 'text' => null, 'params' => ['title' => 'A']]]],
                ['start' => 3, 'end' => 6, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'layers' => [['type' => 'card', 'text' => null, 'params' => ['title' => 'B']]]],
            ]],
            'meta' => [
                'image_requests' => [[
                    'key' => ImageRequests::VIDEO_KEY, 'prompt' => 'An animated video: mascot waves', 'label' => 'Mascote a acenar',
                    'site' => null, 'animado' => true, 'imagem' => 'gen_1',
                    'video_prompt' => 'the mascot waves at the camera', 'imagem_prompt' => 'a friendly robot mascot, centered',
                ]],
                'image_uploads' => [],
            ],
        ]);

        (new FinalizeClipPlanJob($p->id))->handle(app(ClipStore::class));

        $p = app(ClipStore::class)->findOrFail($p->id);
        $video = collect($p->images)->firstWhere('video', true);
        $this->assertNotNull($video, 'the generated video joins the clip images');
        $this->assertSame('MP4BYTES', $disk->get($video['path']));

        $intro = $p->plan['scenes'][0]['layers'][0];
        $this->assertSame('video-intro', $intro['type']);
        $this->assertSame($video['id'], $intro['params']['src']);

        $asset = collect(app(ImageLibrary::class)->all())->firstWhere('video', true);
        $this->assertNotNull($asset, 'the video is saved as a reusable asset');
        $this->assertStringContainsString('Mascote a acenar', $asset['description']);
        $this->assertStringContainsString('start frame: a friendly robot mascot', $asset['description']);

        Queue::assertPushed(RenderJob::class);
    }
}
