<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Sees and controls the `queue:work` processes behind each queue.
 *
 * Laravel has no registry of running workers, so this reads the process table:
 * a worker is a `php artisan queue:work --queue=<name>` process. That is enough
 * to answer the three questions the Settings panel asks — is a worker up, is it
 * doing anything, and how much is waiting — without a supervisor, a PID file, or
 * a heartbeat table to keep in sync.
 *
 * It only works where the web process and the workers share a process namespace.
 * They do in the Docker image — supervisord runs web, workers and scheduler side
 * by side in one container — so the panel is live there too. Where the process
 * table cannot be read at all, `manageable()` is false and the panel says so,
 * rather than showing an empty list that reads as "everything is stopped".
 *
 * Under supervisord the workers are also RESTARTED AUTOMATICALLY, so Stop is
 * temporary; `supervised()` lets the panel say that instead of looking broken.
 */
class QueueWorkers
{
    /** Queue names are interpolated into a command line — only these may be. */
    public function queues(): array
    {
        return (array) config('contentmachine.workers.queues', []);
    }

    public function isQueue(string $queue): bool
    {
        return array_key_exists($queue, $this->queues());
    }

    /**
     * Reject anything not on the whitelist. Every public method that builds a
     * command routes through here, so a crafted request cannot inject a queue
     * name — the only values that ever reach a command line are config literals.
     */
    private function assertQueue(string $queue): void
    {
        if (! $this->isQueue($queue)) {
            throw new RuntimeException("Unknown queue «{$queue}».");
        }
    }

    /**
     * Can workers be seen and controlled from here at all?
     *
     * Requires a working process table (proc_open available — some hosts disable
     * it) AND the workers living beside us rather than in another container.
     */
    public function manageable(): bool
    {
        return function_exists('proc_open') && $this->processTable() !== null;
    }

    /**
     * Is supervisord running the workers (the Docker image)? Then every program is
     * `autorestart=true`, so a stopped worker comes back within seconds — worth
     * saying out loud, or Stop looks like it silently failed.
     */
    public function supervised(): bool
    {
        return is_file('/etc/supervisor/supervisord.conf');
    }

    /**
     * Status of every managed queue.
     *
     * `$tabela` overrides the process listing — the one seam that lets this be
     * tested, since otherwise every assertion depends on which workers happen to
     * be running on the machine running the suite.
     *
     * @return array<string,array{queue:string,label:string,pids:array<int,int>,running:bool,busy:?bool,pending:int,reserved:int,uptime:?string}>
     */
    public function status(?string $tabela = null): array
    {
        $tabela ??= $this->processTable() ?? '';
        $out = [];

        foreach ($this->queues() as $queue => $label) {
            $pids = $this->pidsFor($queue, $tabela);
            $contagem = $this->counts($queue);
            $out[$queue] = [
                'queue' => $queue,
                'label' => $label,
                'pids' => $pids,
                'running' => $pids !== [],
                // Busy only means something when a worker is up AND the driver
                // lets us see reservations; null renders as "—", never as "free".
                'busy' => $pids === [] || $contagem['reserved'] === null ? null : $contagem['reserved'] > 0,
                'pending' => $contagem['pending'],
                'reserved' => $contagem['reserved'] ?? 0,
                'uptime' => $pids !== [] && $this->processTable() !== null ? $this->uptime($pids[0]) : null,
            ];
        }

        return $out;
    }

    /** Start a worker for the queue. No-op (returns false) if one is already up. */
    public function start(string $queue): bool
    {
        $this->assertQueue($queue);
        if ($this->pidsFor($queue) !== []) {
            return false;
        }

        $php = $this->phpBinary();
        $c = config('contentmachine.workers');
        $log = storage_path('logs/worker-'.$queue.'.log');

        // Backgrounded through a shell so the worker outlives this request —
        // Symfony\Process kills its children on destruct. Every interpolated part
        // is either a config literal, a whitelisted queue name, or escaped.
        $cmd = sprintf(
            'nohup %s %s queue:work --queue=%s --tries=%d --timeout=%d >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($queue),
            (int) ($c['tries'] ?? 3),
            (int) ($c['timeout'] ?? 1800),
            escapeshellarg($log),
        );

        $p = Process::fromShellCommandline($cmd, base_path());
        $p->setTimeout(15);
        $p->run();

        // The shell returns immediately; give the worker a moment to appear so the
        // panel does not redraw as "stopped" the instant after you pressed Start.
        usleep(700_000);

        return $this->pidsFor($queue) !== [];
    }

    /**
     * Stop every worker on the queue.
     *
     * TERM first so it finishes the job in hand (a render can be minutes) and
     * exits cleanly. A worker blocked on its queue poll can sit on TERM, so after
     * a grace period it is killed outright — otherwise "Stop" appears to do
     * nothing and the button lies.
     */
    public function stop(string $queue): bool
    {
        $this->assertQueue($queue);
        $pids = $this->pidsFor($queue);
        if ($pids === []) {
            return false;
        }

        foreach ($pids as $pid) {
            $this->signal($pid, 'TERM');
        }
        for ($i = 0; $i < 10 && $this->pidsFor($queue) !== []; $i++) {
            usleep(300_000);
        }
        foreach ($this->pidsFor($queue) as $pid) {
            $this->signal($pid, 'KILL');
        }
        usleep(300_000);

        return $this->pidsFor($queue) === [];
    }

    /**
     * Stop then start — which is also the ONLY way a code change reaches a
     * worker: `queue:work` boots the framework once and reuses it for every job,
     * so a running worker keeps serving the code it started with.
     */
    public function restart(string $queue): bool
    {
        $this->assertQueue($queue);
        $this->stop($queue);

        return $this->start($queue);
    }

    // ── process table ────────────────────────────────────────────────────

    /** The raw `ps` output, or null when it cannot be read (no proc_open, no ps). */
    private function processTable(): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        try {
            $p = new Process(['ps', '-eo', 'pid=,args=']);
            $p->setTimeout(10);
            $p->run();
        } catch (\Throwable) {
            return null;
        }

        return $p->isSuccessful() ? $p->getOutput() : null;
    }

    /**
     * PIDs of the workers serving a queue.
     *
     * A worker may serve SEVERAL queues in priority order — the container runs
     * `--queue=default,previews` as one process — so the value is split on commas
     * and checked for membership. Matching the name straight after `--queue=`
     * would have reported `previews` as stopped on every Docker deploy while a
     * process was serving it perfectly well.
     *
     * `queue:work` must appear on the same line, so this command's own `ps` (or a
     * grep) is never counted as a worker, and exact membership means `default`
     * never also matches a hypothetical `default-slow`.
     *
     * @return array<int,int>
     */
    public function pidsFor(string $queue, ?string $tabela = null): array
    {
        $this->assertQueue($queue);
        $tabela ??= $this->processTable() ?? '';
        $pids = [];

        foreach (preg_split('/\R/', $tabela) ?: [] as $linha) {
            if (! preg_match('/^\s*(\d+)\s+(.*)$/', $linha, $m)) {
                continue;
            }
            $args = $m[2];
            if (! str_contains($args, 'queue:work') || (int) $m[1] === getmypid()) {
                continue;
            }
            if (! preg_match('/--queue[=\s]+["\']?([\w,-]+)/', $args, $q)) {
                continue;
            }
            if (! in_array($queue, explode(',', $q[1]), true)) {
                continue;
            }
            $pids[] = (int) $m[1];
        }

        return $pids;
    }

    private function signal(int $pid, string $sinal): void
    {
        $p = new Process(['kill', '-'.$sinal, (string) $pid]);
        $p->setTimeout(10);
        $p->run();
    }

    /** How long the worker has been up, as ps reports it ("01:23" / "1-02:03:04"). */
    private function uptime(int $pid): ?string
    {
        $p = new Process(['ps', '-o', 'etime=', '-p', (string) $pid]);
        $p->setTimeout(10);
        $p->run();
        $t = trim($p->getOutput());

        return $t !== '' ? $t : null;
    }

    /**
     * Waiting and in-flight job counts for a queue.
     *
     * Only the database driver exposes reservations, so `reserved` is null on any
     * other connection — the caller renders that as unknown rather than guessing
     * "free", which would be a worse lie than admitting it cannot tell.
     *
     * @return array{pending:int,reserved:?int}
     */
    private function counts(string $queue): array
    {
        $conexao = (string) config('queue.default');
        if (config("queue.connections.{$conexao}.driver") !== 'database') {
            return ['pending' => 0, 'reserved' => null];
        }

        try {
            $tabela = (string) config("queue.connections.{$conexao}.table", 'jobs');
            $linhas = DB::table($tabela)->where('queue', $queue)->get(['reserved_at']);
        } catch (\Throwable) {
            return ['pending' => 0, 'reserved' => null];
        }

        $reservados = $linhas->filter(fn ($l) => $l->reserved_at !== null)->count();

        return ['pending' => $linhas->count() - $reservados, 'reserved' => $reservados];
    }

    /**
     * The PHP CLI binary to launch the worker with.
     *
     * PHP_BINARY under FPM is the php-fpm binary, which cannot run artisan — so it
     * is only trusted when it does not look like a server SAPI. Configure
     * WORKER_PHP_BINARY when neither guess is right.
     */
    private function phpBinary(): string
    {
        $configurado = trim((string) config('contentmachine.workers.php_binary', ''));
        if ($configurado !== '') {
            return $configurado;
        }
        if (PHP_BINARY !== '' && ! preg_match('/fpm|cgi/i', basename(PHP_BINARY))) {
            return PHP_BINARY;
        }

        return 'php';
    }
}
