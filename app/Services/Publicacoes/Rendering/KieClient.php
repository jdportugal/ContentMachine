<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Costs\CostLedger;
use App\Services\Settings\StepKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    /**
     * Which pipeline step this instance renders for — decides which kie.ai key it
     * bills to (Settings → Steps). Post cards by default; ClipImageGenerator
     * switches its own copy to the clip-images step.
     */
    public string $passo = 'publicacoes_cartoes';

    public function configurado(): bool
    {
        return filled($this->chave());
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
     * Image → VIDEO: animates the given start image according to the prompt
     * (kie's kling image-to-video — 5s, silent) and returns the video URL.
     * `$imageUrl` must be a kie file URL (see upload()).
     */
    public function generateVideo(string $prompt, string $imageUrl): string
    {
        $r = Http::timeout(60)->withToken($this->chave())
            ->post($this->base().'/api/v1/jobs/createTask', [
                'model' => (string) config('services.kie.video_model', 'kling-2.6/image-to-video'),
                'input' => [
                    'prompt' => Str::limit($prompt, 990, ''),
                    'image_urls' => [$imageUrl],
                    'sound' => false,
                    'duration' => '5',
                ],
            ]);

        // Videos take minutes, not seconds — poll a longer window, price as video.
        return $this->sondar(
            $this->taskIdDe($r),
            custo: (float) config('contentmachine.custos.kie_video', 0.40),
            tentativas: 300,
        );
    }

    /**
     * Start frame → end frame VIDEO: the camera move between two stills, with
     * whatever changes between them animated in. Kling 3.0 (default) takes both
     * frames as image_urls and 3–15 s; Kling 2.x takes image_url + tail_image_url
     * and 5/10 s. Returns the taskId so several rooms can be submitted before any
     * is polled (see pollVideo()). Both URLs must be kie file URLs (see upload()).
     */
    public function submitFramesVideo(string $prompt, string $startUrl, string $endUrl, string $negative = '', int $seconds = 5): string
    {
        $model = (string) config('contentmachine.real_estate.property.video_model', 'kling-3.0/video');
        $prompt = Str::limit($prompt, 2400, '');
        $input = str_starts_with($model, 'kling-3')
            ? ['prompt' => $prompt, 'image_urls' => [$startUrl, $endUrl], 'sound' => (bool) config('contentmachine.real_estate.property.sound', true), 'duration' => (string) max(3, min(15, $seconds)), 'mode' => 'pro', 'multi_shots' => false]
            : array_filter(['prompt' => $prompt, 'image_url' => $startUrl, 'tail_image_url' => $endUrl, 'duration' => $seconds > 7 ? '10' : '5', 'negative_prompt' => $negative]);

        $r = Http::timeout(60)->withToken($this->chave())
            ->post($this->base().'/api/v1/jobs/createTask', ['model' => $model, 'input' => $input]);

        return $this->taskIdDe($r);
    }

    /**
     * Image → image EDIT that keeps the photo's framing (Google's Nano Banana
     * Edit): measured on a real room it moves the frame <1% where Nano Banana Pro,
     * Seedream and Qwen re-compose it by 20%+. Returns the taskId (poll()).
     */
    public function submitExactEdit(string $prompt, string $imageUrl, string $aspect): string
    {
        $r = Http::timeout(60)->withToken($this->chave())
            ->post($this->base().'/api/v1/jobs/createTask', [
                'model' => (string) config('services.kie.exact_edit_model', 'google/nano-banana-edit'),
                'input' => ['prompt' => $prompt, 'image_urls' => [$imageUrl], 'output_format' => 'png', 'image_size' => $aspect],
            ]);

        return $this->taskIdDe($r);
    }

    /** Poll a submitted video task until it finishes; returns the video URL. */
    public function pollVideo(string $taskId): string
    {
        return $this->sondar($taskId, custo: (float) config('contentmachine.custos.kie_video', 0.40), tentativas: 450);
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

        return $this->taskIdDe($r);
    }

    /** The submitted task's id — or a readable failure (kie returns 200 with the
     * business error in `msg`, e.g. 402 out of credits). */
    private function taskIdDe(Response $r): string
    {
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

    private function sondar(string $taskId, ?float $custo = null, ?int $tentativas = null): string
    {
        for ($i = 0; $i < ($tentativas ?? self::MAX_SONDAGENS); $i++) {
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
                    app(CostLedger::class)->registar(
                        'kie.ai',
                        $custo ?? (float) config('contentmachine.custos.kie_imagem', 0.04),
                        $custo !== null ? 'video' : 'image',
                    );

                    return $url;
                }
                if ($estado === 'fail') {
                    $why = trim((string) ($r->json('data.failMsg') ?? ''));
                    throw new \RuntimeException('kie.ai: generation failed'.($why !== '' ? " ({$why})" : '').'.');
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
                // Unique per upload: kie stores by path + name, so two jobs uploading
                // "end.png" at once got each other's image (rooms morphing into the
                // wrong room).
                'fileName' => Str::random(12).'-'.$nome,
            ]);

        $url = (string) ($r->json('data.downloadUrl') ?? $r->json('data.url') ?? '');
        if (! $r->successful() || $url === '') {
            throw new \RuntimeException('kie.ai: reference upload failed ('.$r->status().').');
        }

        return $url;
    }

    private function chave(): string
    {
        return StepKey::key($this->passo, 'kie');
    }

    private function base(): string
    {
        return rtrim((string) config('services.kie.base_url'), '/');
    }
}
