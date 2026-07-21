<?php

namespace App\Services\Shorts;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP para a API Flask "ShortsCreator" (edição de vídeo por jobs
 * assíncronos). Envolve as chamadas e o polling de estado.
 *
 * Todos os endpoints de processamento devolvem {job_id, status:"pending"};
 * o trabalho corre em background e é preciso sondar /job-status até
 * "completed" (ou "failed").
 *
 * Usa a Http facade do Laravel — testável com Http::fake().
 */
class ShortsClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 120,
    ) {}

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** GET /health → estado do serviço. */
    public function health(): array
    {
        return $this->http()->get('/health')->throw()->json();
    }

    /**
     * POST /generate-subtitles → transcreve o vídeo completo (Whisper).
     * Devolve a resposta crua (contém job_id ou status "already_exists").
     */
    public function generateSubtitles(string $url, string $language = 'pt', array $extra = []): array
    {
        return $this->http()
            ->post('/generate-subtitles', array_merge([
                'url' => $url,
                'language' => $language,
            ], $extra))
            ->throw()
            ->json();
    }

    /**
     * GET /download-subtitles/{job} → o corpo é o JSON do projeto de
     * transcrição. Devolve o array de segmentos {start,end,text,words[]}.
     *
     * @return array<int,array<string,mixed>>
     */
    public function downloadSubtitlesData(string $jobId): array
    {
        $body = $this->http()->get("/download-subtitles/{$jobId}")->throw()->body();

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new ShortsException('Resposta de legendas inválida (não é JSON).');
        }

        return $decoded['subtitle_data'] ?? $decoded;
    }

    /**
     * POST /split-video → corta um clip do vídeo original.
     * $params: {url|job_id, start_time, end_time}. Devolve o job_id.
     */
    public function splitVideo(array $params): string
    {
        return $this->job('/split-video', $params);
    }

    /**
     * POST /add-subtitles → grava legendas estilizadas no vídeo.
     * $params: {url|job_id, subtitle_data, settings, word_level_mode, ...}.
     * Devolve o job_id.
     */
    public function addSubtitles(array $params): string
    {
        return $this->job('/add-subtitles', $params);
    }

    /**
     * POST /add-music → mistura música de fundo.
     * $params: {video_url, music_url, volume, fade_in, fade_out, loop_music}.
     */
    public function addMusic(array $params): string
    {
        return $this->job('/add-music', $params);
    }

    /** GET /job-status/{job} → estado atual do job. */
    public function jobStatus(string $jobId): array
    {
        return $this->http()->get("/job-status/{$jobId}")->throw()->json();
    }

    /**
     * Sonda /job-status até "completed" (devolve o corpo) ou lança em
     * "failed"/timeout.
     */
    public function waitForJob(string $jobId, int $timeoutSeconds = 300, int $intervalSeconds = 2): array
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $status = $this->jobStatus($jobId);
            $state = $status['status'] ?? 'unknown';

            if ($state === 'completed') {
                return $status;
            }

            if ($state === 'failed') {
                throw new ShortsException("Job {$jobId} falhou: ".($status['error'] ?? 'erro desconhecido'));
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            usleep($intervalSeconds * 1_000_000);
        } while (true);

        throw new ShortsException("Job {$jobId} excedeu o tempo limite ({$timeoutSeconds}s).");
    }

    /**
     * GET /download/{job} → grava o ficheiro final em $destPath.
     * Devolve o número de bytes escritos.
     */
    public function download(string $jobId, string $destPath): int
    {
        $body = $this->http()->get("/download/{$jobId}")->throw()->body();

        @mkdir(dirname($destPath), 0775, true);
        file_put_contents($destPath, $body);

        return strlen($body);
    }

    // ------------------------------------------------------------------

    private function job(string $path, array $params): string
    {
        $response = $this->http()->post($path, $params)->throw()->json();

        if (empty($response['job_id'])) {
            throw new ShortsException("Sem job_id na resposta de {$path}.");
        }

        return $response['job_id'];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
