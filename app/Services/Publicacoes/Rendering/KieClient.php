<?php

namespace App\Services\Publicacoes\Rendering;

use Illuminate\Support\Facades\Http;

/**
 * Adaptador HTTP para a API kie.ai (modelos Nano Banana), à imagem do KieClient
 * do AdsMaker: submeter tarefa, sondar resultado, carregar imagem de referência.
 *
 *  - generate(): texto → imagem (com referências opcionais para consistência).
 *  - edit(): imagem → imagem — carrega a imagem de referência e pede uma edição.
 */
class KieClient
{
    private const MAX_SONDAGENS = 90;

    public function configurado(): bool
    {
        return filled(config('services.kie.key'));
    }

    /** Texto → imagem. $refs são URLs (kie) usadas como referência visual. */
    public function generate(string $prompt, string $proporcao, array $refs = []): string
    {
        $taskId = $this->submeter($prompt, $proporcao, $refs);

        return $this->sondar($taskId);
    }

    /**
     * Imagem → imagem: carrega os bytes da imagem de referência para o
     * armazenamento do kie e pede uma edição segundo a instrução.
     */
    public function edit(string $prompt, string $proporcao, string $refBytes, string $refNome = 'ref.png'): string
    {
        $url = $this->upload($refBytes, $refNome);

        return $this->generate($prompt, $proporcao, [$url]);
    }

    /**
     * Carrega ficheiros locais (caminhos web relativos a public/) e devolve os
     * URLs do kie, para usar como referências visuais na geração.
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

        // A API devolve 200 mesmo em erro de negócio (ex.: 402 sem créditos),
        // com o motivo em `msg` — expõe-no para a mensagem ser accionável.
        $taskId = (string) $r->json('data.taskId');
        if (! $r->successful() || $taskId === '') {
            $msg = trim((string) ($r->json('msg') ?? ''));
            $code = $r->json('code');
            throw new \RuntimeException(
                'kie.ai: '.($msg !== '' ? $msg : 'submissão falhou ('.$r->status().').')
                .($code ? ' [código '.$code.']' : '')
            );
        }

        return $taskId;
    }

    private function sondar(string $taskId): string
    {
        for ($i = 0; $i < self::MAX_SONDAGENS; $i++) {
            $r = Http::timeout(30)->withToken($this->chave())
                ->get($this->base().'/api/v1/jobs/recordInfo', ['taskId' => $taskId]);

            $estado = (string) $r->json('data.state');
            if ($estado === 'success') {
                // 'resultJson' vem como STRING JSON (não objecto aninhado).
                $resultJson = $r->json('data.resultJson');
                $dados = is_string($resultJson) ? (json_decode($resultJson, true) ?: []) : (array) $resultJson;
                $url = (string) ($dados['resultUrls'][0] ?? '');
                if ($url === '') {
                    throw new \RuntimeException('kie.ai: sucesso sem URL.');
                }

                return $url;
            }
            if ($estado === 'fail') {
                throw new \RuntimeException('kie.ai: geração falhou.');
            }

            usleep(2_000_000);
        }

        throw new \RuntimeException('kie.ai: esgotou o tempo de sondagem.');
    }

    /** Carrega bytes de imagem para o armazenamento do kie; devolve URL público. */
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
            throw new \RuntimeException('kie.ai: carregamento da referência falhou ('.$r->status().').');
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
