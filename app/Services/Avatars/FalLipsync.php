<?php

namespace App\Services\Avatars;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * fal.ai adapter for the lip-sync endpoint (Sync Lipsync 2 Pro, see
 * services.fal.lipsync_model): feed it a talking-head video and an audio track,
 * get the same video with the mouth re-animated to match the audio.
 *
 * Three steps, all plain HTTP (no SDK): the two local files are uploaded to
 * fal's storage (the model only takes URLs), the job is submitted to the queue
 * endpoint, and the queue is polled until the result carries a video URL, which
 * is downloaded to disk.
 *
 * submit() and collect() are separate on purpose. fal starts charging the moment
 * a request is accepted, so the caller persists the request id BEFORE the long
 * poll — then a dropped connection, a closed laptop lid or a killed worker costs
 * a resume, not another generation.
 */
class FalLipsync
{
    /** How long to keep polling the queue before giving up (seconds). */
    private const MAX_WAIT = 1200;

    public function configured(): bool
    {
        return filled($this->key());
    }

    /**
     * Upload the two files and hand the job to fal. Returns the request handle to
     * persist — from here on fal is billing, whatever happens to this process.
     *
     * @return array{request_id:string,status_url:string,response_url:string}
     */
    public function submit(string $videoPath, string $audioPath): array
    {
        return $this->enfileirar([
            'video_url' => $this->upload($videoPath),
            'audio_url' => $this->upload($audioPath),
        ]);
    }

    /**
     * What fal actually billed for a finished request, in its own billing units
     * (minutes, for the lip-sync endpoint). Null when fal will not say — the
     * caller then falls back to an estimate.
     *
     * Worth the extra call: fal rounds the video up to whole seconds, so a 9.26s
     * result bills as 10s. Reading the number beats modelling their arithmetic.
     */
    public function billableUnits(string $requestId): ?float
    {
        if ($requestId === '') {
            return null;
        }

        try {
            $r = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Key '.$this->key()])
                ->get(rtrim((string) config('services.fal.rest_url'), '/').'/requests/'.$requestId);
        } catch (\Throwable) {
            return null;
        }

        $units = $r->json('billable_units');

        return is_numeric($units) && $units > 0 ? (float) $units : null;
    }

    /**
     * Wait for a submitted request and write the finished mp4 to $outPath.
     * Safe to call again for the same request: fal keeps a finished result, so a
     * resume collects it without paying for a second generation.
     *
     * @param  array{status_url:string,response_url:string}  $request
     */
    public function collect(array $request, string $outPath): void
    {
        $url = $this->poll($request['status_url'], $request['response_url']);

        $this->download($url, $outPath);
    }

    // ------------------------------------------------------------------ steps

    /**
     * Upload a local file to fal storage and return its public URL. Two calls:
     * initiate (gives a presigned PUT target) then the PUT itself, streamed from
     * the file handle so a 100MB video never has to sit in the worker's memory.
     */
    private function upload(string $path): string
    {
        $type = $this->contentType($path);

        $r = Http::timeout(60)
            ->withHeaders(['Authorization' => 'Key '.$this->key()])
            ->post(rtrim((string) config('services.fal.rest_url'), '/').'/storage/upload/initiate?storage_type=fal-cdn-v3', [
                'content_type' => $type,
                'file_name' => basename($path),
            ]);

        $fileUrl = (string) $r->json('file_url', '');
        $uploadUrl = (string) $r->json('upload_url', '');
        if (! $r->successful() || $fileUrl === '' || $uploadUrl === '') {
            throw new \RuntimeException('fal.ai: upload could not be initiated ('.$r->status().').');
        }

        $handle = fopen($path, 'r');
        try {
            $put = Http::timeout(600)->withBody($handle, $type)->put($uploadUrl);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $put->successful()) {
            throw new \RuntimeException('fal.ai: upload of '.basename($path).' failed ('.$put->status().').');
        }

        return $fileUrl;
    }

    /**
     * @param  array<string,string>  $input
     * @return array{request_id:string,status_url:string,response_url:string}
     */
    private function enfileirar(array $input): array
    {
        $r = Http::timeout(60)
            ->withHeaders(['Authorization' => 'Key '.$this->key()])
            ->post($this->queueBase(), $input);

        $status = (string) $r->json('status_url', '');
        $response = (string) $r->json('response_url', '');
        if (! $r->successful() || $status === '' || $response === '') {
            throw new \RuntimeException('fal.ai: '.$this->erro($r));
        }

        return [
            'request_id' => (string) $r->json('request_id', ''),
            'status_url' => $status,
            'response_url' => $response,
        ];
    }

    /** Poll until the queue says COMPLETED, then read the video URL from the result. */
    private function poll(string $statusUrl, string $responseUrl): string
    {
        $deadline = time() + self::MAX_WAIT;
        $falhas = 0;

        while (true) {
            // A status poll that cannot reach fal says nothing about the request,
            // which is still running and still being paid for. Ride it out.
            try {
                $r = Http::timeout(30)
                    ->withHeaders(['Authorization' => 'Key '.$this->key()])
                    ->get($statusUrl);
                $falhas = 0;
            } catch (ConnectionException $e) {
                if (++$falhas >= (int) config('services.fal.poll_failures', 6)) {
                    throw new \RuntimeException(
                        'fal.ai: lost contact while waiting ('.Str::limit($e->getMessage(), 160).').'
                    );
                }
                sleep($this->pollEvery());

                continue;
            }

            $status = (string) $r->json('status', '');

            if ($status === 'COMPLETED') {
                break;
            }
            // Anything that is neither queued nor running is a dead request —
            // keeping the poll alive would only burn twenty minutes to say so.
            if (! in_array($status, ['IN_QUEUE', 'IN_PROGRESS'], true)) {
                throw new \RuntimeException('fal.ai: '.($status !== '' ? 'request '.$status : $this->erro($r)));
            }
            if (time() >= $deadline) {
                throw new \RuntimeException('fal.ai: still '.$status.' after '.self::MAX_WAIT.'s — gave up waiting.');
            }
            sleep($this->pollEvery());
        }

        $r = Http::timeout(60)
            ->withHeaders(['Authorization' => 'Key '.$this->key()])
            ->get($responseUrl);

        $url = (string) $r->json('video.url', '');
        if (! $r->successful() || $url === '') {
            throw new \RuntimeException('fal.ai: finished without a video ('.$this->erro($r).').');
        }

        return $url;
    }

    /** Write the finished video to disk. Kling lipsync caps at 10s, so the body
     *  is small enough to hold in memory — and a plain body() keeps this testable. */
    private function download(string $url, string $outPath): void
    {
        @mkdir(dirname($outPath), 0775, true);
        $r = Http::timeout(600)->get($url);

        if (! $r->successful() || $r->body() === '') {
            throw new \RuntimeException('fal.ai: the finished video could not be downloaded ('.$r->status().').');
        }

        file_put_contents($outPath, $r->body());
    }

    // ------------------------------------------------------------------ bits

    /** Seconds between status polls (0 in tests, so the suite never sleeps). */
    private function pollEvery(): int
    {
        return max(0, (int) config('services.fal.poll_every', 5));
    }

    private function queueBase(): string
    {
        return rtrim((string) config('services.fal.queue_url'), '/')
            .'/'.trim((string) config('services.fal.lipsync_model'), '/');
    }

    private function key(): string
    {
        return (string) config('services.fal.key');
    }

    private function erro(Response $r): string
    {
        $detail = $r->json('detail');

        return Str::limit(
            is_string($detail) && $detail !== '' ? $detail : 'HTTP '.$r->status().' '.$r->body(),
            300,
        );
    }

    private function contentType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'm4a', 'aac' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }
}
