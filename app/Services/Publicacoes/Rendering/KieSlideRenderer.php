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
        $urls = [];
        $ancora = null; // 1.º cartão serve de referência aos seguintes (consistência visual).

        foreach ($plan->slides as $slide) {
            $taskId = $this->submeter($chave, $this->prompt($slide, $kind), $proporcao, $ancora);
            $url = $this->sondar($chave, $taskId);
            $urls[] = $url;
            $ancora ??= $url;
        }

        return $urls;
    }

    private function prompt(SlidePlano $s, array $kind): string
    {
        $gabarito = (string) ($kind['gabarito'] ?? 'quadrado');
        $regraTexto = 'Renderiza TODO o texto na imagem como letras reais, nítidas e correctamente escritas em português. Sem emojis.';

        return trim(<<<PROMPT
        Cartão editorial da IATECA, estética de biblioteca antiga (pergaminho, tinta,
        filete a ouro), gabarito «{$gabarito}». Título: «{$s->titulo}». Texto: «{$s->texto}».
        {$regraTexto}
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
                'model' => (string) config('services.kie.image_model'),
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
                $url = (string) ($r->json('data.resultJson.resultUrls.0') ?? '');
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
