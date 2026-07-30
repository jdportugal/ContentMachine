<?php

namespace App\Services\Publicacoes\Rendering;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * HTTP adapter for the kie.ai API (Nano Banana models), like AdsMaker's
 * KieClient: submit task, poll result, upload reference image.
 *
 *  - generate(): text → image (with optional references for consistency).
 *  - edit(): image → image — uploads the reference image and requests an edit.
 */
class KieClient
{
    private const MAX_SONDAGENS = 90;

    public function configurado(): bool
    {
        return filled(config('services.kie.key'));
    }

    /** Text → image. $refs are URLs (kie) used as visual reference. */
    public function generate(string $prompt, string $proporcao, array $refs = []): string
    {
        $taskId = $this->submeter($prompt, $proporcao, $refs);

        return $this->sondar($taskId);
    }

    /**
     * Submit a generation task and return its taskId WITHOUT polling. Lets a caller
     * persist the taskId before the slow poll, so a retry resumes that same task
     * (see KieProgress) instead of submitting a duplicate generation.
     */
    public function submit(string $prompt, string $proporcao, array $refs = []): string
    {
        return $this->submeter($prompt, $proporcao, $refs);
    }

    /** Poll a previously-submitted task until it finishes; returns the image URL. */
    public function poll(string $taskId): string
    {
        return $this->sondar($taskId);
    }

    /**
     * Image → image: uploads the reference image bytes to
     * kie's storage and requests an edit according to the instruction.
     */
    public function edit(string $prompt, string $proporcao, string $refBytes, string $refNome = 'ref.png'): string
    {
        $url = $this->upload($refBytes, $refNome);

        return $this->generate($prompt, $proporcao, [$url]);
    }

    /**
     * Uploads local files (web paths relative to public/) and returns the
     * kie URLs, to use as visual references in generation.
     *
     * @param  array<int,string>  $caminhos
     * @return array<int,string>
     */
    public function carregarReferencias(array $caminhos): array
    {
        $urls = [];
        foreach ($caminhos as $rel) {
            $abs = public_path($rel);
            if (is_file($abs)) {
                $urls[] = $this->upload(file_get_contents($abs), basename($rel));
            }
        }

        return $urls;
    }

    // ------------------------------------------------------------------ HTTP

    private function submeter(string $prompt, string $proporcao, array $refs): string
    {
        $input = ['prompt' => $prompt, 'output_format' => 'png', 'aspect_ratio' => $proporcao];
        $refs = array_values(array_filter($refs));
        if ($refs !== []) {
            $input['image_input'] = $refs;
        }

        $r = Http::timeout(60)->withToken($this->chave())
            ->post($this->base().'/api/v1/jobs/createTask', [
                'model' => (string) config('services.kie.text_model', 'nano-banana-pro'),
                'input' => $input,
            ]);

        // The API returns 200 even on a business error (e.g. 402 out of credits),
        // with the reason in `msg` — expose it so the message is actionable.
        $taskId = (string) $r->json('data.taskId');
        if (! $r->successful() || $taskId === '') {
            $msg = trim((string) ($r->json('msg') ?? ''));
            $code = $r->json('code');
            throw new \RuntimeException(
                'kie.ai: '.($msg !== '' ? $msg : 'submission failed ('.$r->status().').')
                .($code ? ' [code '.$code.']' : '')
            );
        }

        return $taskId;
    }

    private function sondar(string $taskId): string
    {
        for ($i = 0; $i < self::MAX_SONDAGENS; $i++) {
            try {
                $r = Http::timeout(30)->withToken($this->chave())
                    ->get($this->base().'/api/v1/jobs/recordInfo', ['taskId' => $taskId]);

                $estado = (string) $r->json('data.state');
                if ($estado === 'success') {
                    // 'resultJson' comes as a JSON STRING (not a nested object).
                    $resultJson = $r->json('data.resultJson');
                    $dados = is_string($resultJson) ? (json_decode($resultJson, true) ?: []) : (array) $resultJson;
                    $url = (string) ($dados['resultUrls'][0] ?? '');
                    if ($url === '') {
                        throw new \RuntimeException('kie.ai: success without a URL.');
                    }

                    return $url;
                }
                if ($estado === 'fail') {
                    throw new \RuntimeException('kie.ai: generation failed.');
                }
            } catch (ConnectionException) {
                // Transient network blip (DNS/timeout) — kie is still generating the
                // image; keep polling instead of aborting work it will complete.
                // Persistent failures still surface as a timeout after MAX_SONDAGENS.
            }

            usleep(2_000_000);
        }

        throw new \RuntimeException('kie.ai: polling timed out.');
    }

    /** Uploads image bytes to kie's storage; returns a public URL. */
    public function upload(string $bytes, string $nome): string
    {
        $base = rtrim((string) config('services.kie.file_base_url', 'https://kieai.redpandaai.co'), '/');

        $r = Http::timeout(60)->withToken($this->chave())
            ->post($base.'/api/file-base64-upload', [
                'base64Data' => 'data:image/png;base64,'.base64_encode($bytes),
                'uploadPath' => 'publicacoes/refs',
                'fileName' => $nome,
            ]);

        $url = (string) ($r->json('data.downloadUrl') ?? $r->json('data.url') ?? '');
        if (! $r->successful() || $url === '') {
            throw new \RuntimeException('kie.ai: reference upload failed ('.$r->status().').');
        }

        return $url;
    }

    private function chave(): string
    {
        return (string) config('services.kie.key');
    }

    private function base(): string
    {
        return rtrim((string) config('services.kie.base_url'), '/');
    }
}
