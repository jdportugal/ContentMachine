<?php

namespace App\Jobs;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\Rendering\SlideRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Desenha os cartões de uma peça fora do ciclo do pedido web.
 *
 * O renderizador SVG é instantâneo, mas o kie.ai (nano-banana-pro) submete e
 * sonda uma tarefa por cartão — lento demais para um pedido web. Corre aqui,
 * numa fila, à imagem do GeneratePostImagesJob do AdsMaker. As imagens ficam
 * em public/media/publicacoes/{token}/ e os caminhos ficam em cache para a
 * oficina os ler por sondagem.
 */
class GerarImagensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    /** @param array<int,array{titulo?:string,texto?:string}> $slides */
    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $plataforma,
        public string $legenda,
        public array $slides,
        public string $token,
        public string $proporcao = '',
        public array $referencias = [],
        public string $notaSlug = '', // se definido, persiste as imagens na nota
    ) {}

    public function handle(SlideRenderer $renderer, PublicacaoKinds $kinds): void
    {
        try {
            $kind = $kinds->get($this->tipo) ?? [];
            if ($this->proporcao !== '') {
                $kind['proporcao'] = $this->proporcao;
            }
            $kind['_refs'] = $this->referencias;
            $cor = (string) (config('contentmachine.plataformas_meta.'.$this->plataforma.'.cor') ?? '#1f7a7a');

            $plano = PublicacaoPlan::daOficina(
                $kinds->formato($this->tipo) === 'carousel',
                $this->titulo,
                $this->legenda,
                $this->slides,
                [$this->tipo, $this->plataforma],
            );

            if ($plano->slides === []) {
                Cache::put(self::key($this->token), ['imagens' => []], now()->addMinutes(30));

                return;
            }

            $paths = $this->escrever($renderer->render($plano, array_merge($kind, ['_cor' => $cor])));
            Cache::put(self::key($this->token), ['imagens' => $paths], now()->addMinutes(30));

            // Persiste na nota (para o painel refletir as imagens sem a oficina aberta).
            if ($this->notaSlug !== '' && $paths !== []) {
                $this->persistirNota($paths);
            }
        } finally {
            $this->limparFlag();
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true, 'msg' => $e->getMessage()], now()->addMinutes(30));
        $this->limparFlag();
    }

    private function persistirNota(array $paths): void
    {
        try {
            $vault = app(\App\Services\Vault\VaultContract::class);
            $nota = $vault->get('rascunhos/'.$this->notaSlug.'.md');
            if ($nota) {
                $vault->updateFrontmatter($nota->path, ['imagens' => $paths]);
            }
        } catch (\Throwable) {
            // não bloqueia a geração se a persistência falhar
        }
    }

    private function limparFlag(): void
    {
        if ($this->notaSlug !== '') {
            Cache::forget(self::notaKey($this->notaSlug));
        }
    }

    /** Chave de cache que marca uma publicação como «a gerar» (para o painel). */
    public static function notaKey(string $slug): string
    {
        return 'publicacao.gerando.'.$slug;
    }

    /**
     * Persiste cada artefacto de forma durável: SVG inline → ficheiro .svg;
     * URL (kie.ai) → descarrega o PNG para .png (as URLs do kie expiram).
     *
     * @param  array<int,string>  $artefactos
     * @return array<int,string>  caminhos web relativos
     */
    private function escrever(array $artefactos): array
    {
        $rel = 'media/publicacoes/'.$this->token;
        $dir = public_path($rel);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $caminhos = [];
        foreach ($artefactos as $i => $arte) {
            $n = $i + 1;

            if (str_starts_with(ltrim($arte), '<svg')) {
                $p = $rel.'/'.$n.'.svg';
                file_put_contents(public_path($p), $arte);
                $caminhos[] = $p;

                continue;
            }

            // URL de imagem (kie.ai) — descarrega para ficheiro durável.
            try {
                $bytes = Http::timeout(60)->get($arte)->body();
                $p = $rel.'/'.$n.'.png';
                file_put_contents(public_path($p), $bytes);
                $caminhos[] = $p;
            } catch (\Throwable) {
                $caminhos[] = $arte; // guarda o URL como último recurso
            }
        }

        return $caminhos;
    }

    public static function key(string $token): string
    {
        return 'publicacao.imagens.'.$token;
    }
}
