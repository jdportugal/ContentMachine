<?php

namespace App\Jobs;

use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\Rendering\SlideRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Regenera a imagem de UM cartão. Se houver imagem de referência + instrução,
 * faz edição imagem→imagem (kie.ai); caso contrário compõe de novo a partir do
 * texto. Espelha o EditSlideImageJob do AdsMaker. Corre na fila 'media'.
 */
class RegenerarCartaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $tipo,
        public string $plataforma,
        public int $index,
        public string $titulo,
        public string $texto,
        public string $instrucao,
        public ?string $refImagem,   // caminho web da imagem actual (ou null)
        public int $ordem,
        public int $total,
        public string $token,
        public string $proporcao = '',
        public array $referencias = [],
    ) {}

    public function handle(SlideRenderer $renderer, PublicacaoKinds $kinds): void
    {
        $kind = $kinds->get($this->tipo) ?? [];
        $cor = (string) (config('contentmachine.plataformas_meta.'.$this->plataforma.'.cor') ?? '#1f7a7a');
        $kind = array_merge($kind, ['_cor' => $cor, '_refs' => $this->referencias]);
        if ($this->proporcao !== '') {
            $kind['proporcao'] = $this->proporcao;
        }

        $slide = new SlidePlano($this->ordem, $this->titulo, $this->texto);

        // Bytes da referência (para edição imagem→imagem), se existir e houver instrução.
        $refBytes = null;
        if ($this->refImagem !== null && trim($this->instrucao) !== '' && is_file(public_path($this->refImagem))) {
            $refBytes = file_get_contents(public_path($this->refImagem));
        }

        $artefacto = $renderer->renderCartao($slide, $kind, $refBytes, $this->instrucao, $this->ordem, $this->total);

        Cache::put(self::key($this->token), [
            'index' => $this->index,
            'imagem' => $this->escrever($artefacto),
        ], now()->addMinutes(30));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['index' => $this->index, 'erro' => true], now()->addMinutes(30));
    }

    private function escrever(string $artefacto): string
    {
        $rel = 'media/publicacoes/'.$this->token;
        if (! is_dir(public_path($rel))) {
            @mkdir(public_path($rel), 0775, true);
        }

        if (str_starts_with(ltrim($artefacto), '<svg')) {
            $p = $rel.'/'.($this->index + 1).'.svg';
            file_put_contents(public_path($p), $artefacto);

            return $p;
        }

        $p = $rel.'/'.($this->index + 1).'.png';
        file_put_contents(public_path($p), Http::timeout(60)->get($artefacto)->body());

        return $p;
    }

    public static function key(string $token): string
    {
        return 'publicacao.cartao.'.$token;
    }
}
