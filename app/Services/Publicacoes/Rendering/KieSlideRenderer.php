<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Driver de produção: gera cada cartão com a API kie.ai (modelos Nano Banana),
 * à imagem do KieClient do AdsMaker — submete uma tarefa por cartão e sonda o
 * resultado. Devolve URLs de imagem.
 *
 * Requer KIE_API_KEY. Sem chave, a app usa o SvgSlideRenderer (ver
 * AppServiceProvider), por isso este driver não é exercido offline.
 */
class KieSlideRenderer implements SlideRenderer
{
    private const MAX_SONDAGENS = 90;

    public function render(PublicacaoPlan $plan, array $kind): array
    {
        $chave = (string) config('services.kie.key');
        if ($chave === '') {
            throw DriverNotConfiguredException::for('kie', 'KIE_API_KEY');
        }

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $total = count($plan->slides);
        $urls = [];
        $ancora = null; // 1.º cartão serve de referência aos seguintes (consistência visual).

        foreach ($plan->slides as $i => $slide) {
            $prompt = $this->prompt($slide, $kind, $i === 0, $i + 1, $total);
            $taskId = $this->submeter($chave, $prompt, $proporcao, $ancora);
            $url = $this->sondar($chave, $taskId);
            $urls[] = $url;
            $ancora ??= $url;
        }

        return $urls;
    }

    /**
     * Prompt para o modelo com texto (nano-banana-pro): cartão editorial da
     * IATECA com o texto EXACTO, legível e correctamente escrito.
     */
    private function prompt(SlidePlano $s, array $kind, bool $capa, int $ordem, int $total): string
    {
        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $papel = $capa
            ? 'É a CAPA do carrossel: título grande e central, muito legível.'
            : 'É um cartão de CONTEÚDO ('.$ordem.' de '.$total.'): título no topo e o texto por baixo, com respiração.';

        $texto = $s->texto !== '' ? 'TEXTO DE APOIO (exacto): «'.$s->texto.'»' : '';

        return trim(<<<PROMPT
        Cartão para redes sociais da IATECA — uma biblioteca para a era das máquinas que pensam.
        Estética: página de livro antigo / gravura. Fundo de pergaminho creme, tinta castanho-escura,
        filete e ornamentos discretos a ouro velho, tipografia serifada elegante e centrada.
        Sóbrio e culto. SEM pessoas, SEM logótipos de marcas, SEM emojis, SEM molduras de fotografia.
        Proporção {$proporcao}. {$papel}

        Compõe o seguinte texto, escrito EXACTAMENTE assim, em português europeu:
        TÍTULO: «{$s->titulo}»
        {$texto}

        Regra de texto: renderiza TODO o texto como letras reais, nítidas, bem espaçadas e SEM erros
        ortográficos. Não inventes, traduzas nem alteres palavras. O texto é o elemento central do cartão.
        PROMPT);
    }

    private function submeter(string $chave, string $prompt, string $proporcao, ?string $ancora): string
    {
        $input = [
            'prompt' => $prompt,
            'output_format' => 'png',
            'aspect_ratio' => $proporcao,
        ];
        if ($ancora !== null) {
            $input['image_input'] = [$ancora];
        }

        $r = Http::timeout(60)
            ->withToken($chave)
            ->post(rtrim((string) config('services.kie.base_url'), '/').'/api/v1/jobs/createTask', [
                // Modelo com texto (nano-banana-pro): cartões são texto-intensivos.
                'model' => (string) config('services.kie.text_model', 'nano-banana-pro'),
                'input' => $input,
            ]);

        $taskId = (string) $r->json('data.taskId');
        if (! $r->successful() || $taskId === '') {
            throw new \RuntimeException('kie.ai: submissão falhou.');
        }

        return $taskId;
    }

    private function sondar(string $chave, string $taskId): string
    {
        $base = rtrim((string) config('services.kie.base_url'), '/');

        for ($i = 0; $i < self::MAX_SONDAGENS; $i++) {
            $r = Http::timeout(30)->withToken($chave)
                ->get($base.'/api/v1/jobs/recordInfo', ['taskId' => $taskId]);

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
}
