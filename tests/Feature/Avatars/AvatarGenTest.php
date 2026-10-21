<?php

namespace Tests\Feature\Avatars;

use App\Jobs\Avatars\GenerateLipsyncJob;
use App\Livewire\AvatarGen;
use App\Services\Avatars\AvatarStore;
use App\Services\Avatars\FalLipsync;
use App\Services\Clips\FfmpegVideoCompositor;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Costs\CostLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AvatarGenTest extends TestCase
{
    use RefreshDatabase;

    private function store(): AvatarStore
    {
        return app(AvatarStore::class);
    }

    private function runJob(string $id): void
    {
        (new GenerateLipsyncJob($id))->handle(
            $this->store(), app(FalLipsync::class),
            app(FfmpegVideoCompositor::class), app(CostLedger::class),
        );
    }

    /** The full fal.ai conversation: upload ×2 → submit → status → result → download. */
    private function fakeFal(string $bytes = 'FINISHED-MP4', float $units = 10 / 60): void
    {
        config(['services.fal.key' => 'test-key']);

        Http::fake([
            'rest.fal.ai/storage/upload/initiate*' => Http::response([
                'file_url' => 'https://cdn.fal.ai/files/in.bin',
                'upload_url' => 'https://upload.fal.ai/put/in.bin',
            ]),
            'upload.fal.ai/*' => Http::response('', 200),
            'queue.fal.run/fal-ai/sync-lipsync/v2/pro' => Http::response([
                'request_id' => 'req-1',
                'status_url' => 'https://queue.fal.run/req/1/status',
                'response_url' => 'https://queue.fal.run/req/1',
            ]),
            'queue.fal.run/req/1/status' => Http::response(['status' => 'COMPLETED']),
            'queue.fal.run/req/1' => Http::response(['video' => ['url' => 'https://cdn.fal.ai/out.mp4']]),
            'cdn.fal.ai/out.mp4' => Http::response($bytes, 200),
            // fal's own record of what it billed: 10s of video = 1/6 of a minute.
            'rest.fal.ai/requests/*' => Http::response(['billable_units' => $units]),
        ]);
    }

    public function test_the_form_stores_both_uploads_and_queues_the_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(AvatarGen::class)
            ->set('video', UploadedFile::fake()->create('face.mp4', 200, 'video/mp4'))
            ->set('audio', UploadedFile::fake()->create('voice.mp3', 50, 'audio/mpeg'))
            ->call('gerar')
            ->assertHasNoErrors();

        $avatar = $this->store()->all()->first();
        $this->assertNotNull($avatar);
        $this->assertSame(EffectRecord::STATUS_PENDING, $avatar->status);
        $this->assertSame('face.mp4', $avatar->display_name);
        Storage::disk('local')->assertExists($avatar->get('video_path'));
        Storage::disk('local')->assertExists($avatar->get('audio_path'));

        Queue::assertPushed(GenerateLipsyncJob::class, fn ($j) => $j->avatarId === $avatar->id());
    }

    public function test_a_wrong_video_format_is_refused_before_anything_is_uploaded_to_fal(): void
    {
        Queue::fake();
        Storage::fake('local');

        Livewire::test(AvatarGen::class)
            ->set('video', UploadedFile::fake()->create('face.avi', 200, 'video/x-msvideo'))
            ->set('audio', UploadedFile::fake()->create('voice.mp3', 50, 'audio/mpeg'))
            ->call('gerar')
            ->assertHasErrors('video');

        Queue::assertNothingPushed();
    }

    /** At $5 a minute an over-long video is refused before it can be billed. */
    public function test_a_video_over_the_ceiling_is_refused(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['contentmachine.custos.fal_lipsync_max_seconds' => 30]);

        // ffprobe is not available to a fake upload, so drive the guard directly.
        $this->partialMock(FfmpegVideoCompositor::class,
            fn ($m) => $m->shouldReceive('probeDuration')->andReturn(95.0));

        Livewire::test(AvatarGen::class)
            ->set('video', UploadedFile::fake()->create('face.mp4', 200, 'video/mp4'))
            ->set('audio', UploadedFile::fake()->create('voice.mp3', 50, 'audio/mpeg'))
            ->call('gerar')
            ->assertHasErrors('video');

        Queue::assertNothingPushed();
        $this->assertCount(0, $this->store()->all());
    }

    public function test_the_job_saves_the_lipsynced_video_and_records_what_it_cost(): void
    {
        Storage::fake('local');
        $this->fakeFal();

        $avatar = $this->store()->create([
            'video_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('face.mp4', 10), 'face.mp4'),
            'audio_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('voice.mp3', 2), 'voice.mp3'),
            'display_name' => 'face.mp4',
        ]);

        // The "downloaded" file is not a real video, so ffprobe is stood in for.
        $this->partialMock(FfmpegVideoCompositor::class,
            fn ($m) => $m->shouldReceive('probeDuration')->andReturn(30.0));

        (new GenerateLipsyncJob($avatar->id()))->handle(
            $this->store(),
            app(FalLipsync::class),
            app(FfmpegVideoCompositor::class),
            app(CostLedger::class),
        );

        $avatar->refresh();
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $avatar->status, (string) $avatar->error);
        $this->assertSame('FINISHED-MP4', file_get_contents($this->store()->videoPath($avatar->id())));

        // Billed what FAL says it billed (1/6 min at $5), not what we measured.
        $custo = (float) DB::table('custos')->where('peca', $avatar->id())->sum('custo');
        $this->assertSame(0.83333, $custo);
        $this->assertSame($avatar->get('cost'), $custo);
        $this->assertFalse($avatar->get('cost_estimated'));

        // The inputs only existed to reach fal — they must not linger.
        Storage::disk('local')->assertMissing($avatar->get('video_path'));
    }

    public function test_a_failure_is_shown_and_costs_nothing(): void
    {
        Storage::fake('local');
        config(['services.fal.key' => 'test-key']);
        Http::fake(['*' => Http::response(['detail' => 'video too long'], 422)]);

        $avatar = $this->store()->create([
            'video_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('face.mp4', 10), 'face.mp4'),
            'audio_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('voice.mp3', 2), 'voice.mp3'),
        ]);

        (new GenerateLipsyncJob($avatar->id()))->handle(
            $this->store(),
            app(FalLipsync::class),
            app(FfmpegVideoCompositor::class),
            app(CostLedger::class),
        );

        $avatar->refresh();
        $this->assertSame(EffectRecord::STATUS_FAILED, $avatar->status);
        $this->assertSame(0, DB::table('custos')->where('peca', $avatar->id())->count());
    }

    /**
     * With fal silent about its units AND the video unmeasurable, the price is
     * unknowable — it has to read as "not measured", never as a free generation.
     */
    public function test_an_unmeasurable_result_is_not_reported_as_free(): void
    {
        Storage::fake('local');
        $this->fakeFal(units: 0);   // fal declines to say what it billed
        $this->partialMock(FfmpegVideoCompositor::class,
            fn ($m) => $m->shouldReceive('probeDuration')->andReturn(0.0));

        $avatar = $this->store()->create([
            'video_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('face.mp4', 10), 'face.mp4'),
            'audio_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('voice.mp3', 2), 'voice.mp3'),
        ]);

        (new GenerateLipsyncJob($avatar->id()))->handle(
            $this->store(), app(FalLipsync::class),
            app(FfmpegVideoCompositor::class), app(CostLedger::class),
        );

        $avatar->refresh();
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $avatar->status);
        $this->assertNull($avatar->get('cost'), 'an unpriced generation must not claim to have cost $0');
    }

    /**
     * The lid-closed failure: the machine suspended mid-poll, curl gave up, and a
     * generation fal had already run (and billed) was thrown away. The request
     * handle is written down before the wait, so the same request can be
     * collected instead of paying for a second one.
     */
    public function test_an_interrupted_wait_leaves_a_resumable_request_behind(): void
    {
        Storage::fake('local');
        config(['services.fal.key' => 'test-key', 'services.fal.poll_every' => 0, 'services.fal.poll_failures' => 2]);

        $vivo = false;   // flipped once fal's answer starts coming back
        Http::fake([
            'rest.fal.ai/storage/upload/initiate*' => Http::response([
                'file_url' => 'https://cdn.fal.ai/files/in.bin',
                'upload_url' => 'https://upload.fal.ai/put/in.bin',
            ]),
            'upload.fal.ai/*' => Http::response('', 200),
            'queue.fal.run/fal-ai/sync-lipsync/v2/pro' => Http::response([
                'request_id' => 'req-1',
                'status_url' => 'https://queue.fal.run/req/1/status',
                'response_url' => 'https://queue.fal.run/req/1',
            ]),
            // First run: the wait dies the way a suspended laptop kills it.
            'queue.fal.run/req/1/status' => function () use (&$vivo) {
                if (! $vivo) {
                    throw new ConnectionException('cURL error 28: Operation timed out');
                }

                return Http::response(['status' => 'COMPLETED']);
            },
            'queue.fal.run/req/1' => Http::response(['video' => ['url' => 'https://cdn.fal.ai/out.mp4']]),
            'cdn.fal.ai/out.mp4' => Http::response('FINISHED-MP4', 200),
        ]);

        $avatar = $this->store()->create([
            'video_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('face.mp4', 10), 'face.mp4'),
            'audio_path' => Storage::disk('local')->putFileAs('avatars/uploads', UploadedFile::fake()->create('voice.mp3', 2), 'voice.mp3'),
        ]);

        $this->runJob($avatar->id());

        $avatar->refresh();
        $this->assertSame(EffectRecord::STATUS_FAILED, $avatar->status);
        $this->assertSame('req-1', $avatar->get('fal_request')['request_id'], 'the paid request must be recoverable');

        // Resume: it collects that same request and must never submit a second one
        // — the inputs are gone by now, so a resubmit could not even work.
        $vivo = true;
        $this->partialMock(FfmpegVideoCompositor::class,
            fn ($m) => $m->shouldReceive('probeDuration')->andReturn(12.0));

        $this->runJob($avatar->id());

        $avatar->refresh();
        $this->assertSame(EffectRecord::STATUS_ACTIVE, $avatar->status, (string) $avatar->error);
        $this->assertSame('FINISHED-MP4', file_get_contents($this->store()->videoPath($avatar->id())));

        $submissoes = 0;
        Http::assertSent(function ($r) use (&$submissoes) {
            $submissoes += (int) ($r->url() === 'https://queue.fal.run/fal-ai/sync-lipsync/v2/pro');

            return true;
        });
        $this->assertSame(1, $submissoes, 'the resume must not pay for a second generation');
    }

    /**
     * The fallback estimate. fal rounds the video UP to whole seconds — a 9.26s
     * result was billed as 10s (0.16667 min) — so the estimate must too, or it
     * reads low every time.
     */
    public function test_the_fallback_estimate_rounds_seconds_up(): void
    {
        config(['contentmachine.custos.fal_lipsync_min' => 5.00]);

        $this->assertSame(0.83333, GenerateLipsyncJob::preco(9.26));
        $this->assertSame(0.83333, GenerateLipsyncJob::preco(10));
        $this->assertSame(0.91667, GenerateLipsyncJob::preco(10.01));
        $this->assertSame(5.0, GenerateLipsyncJob::preco(60));
    }
}
