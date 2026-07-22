<?php

namespace App\Services\Publicacoes\Dto;

/**
 * Plano estruturado de uma publicação: título, legenda, etiquetas e cartões.
 * É o resultado do planeamento (IA ou heurística) e a fonte para o corpo
 * Markdown gravado no vault e para o desenho das imagens.
 */
class PublicacaoPlan
{
    /** @param array<int,SlidePlano> $slides */
    public function __construct(
        public string $titulo,
        public string $legenda,
        public array $tags,
        public array $slides,
    ) {}

    /**
     * Constrói um plano a partir do estado do formulário da oficina.
     *
     * @param  array<int,array{titulo?:string,texto?:string}>  $slides
     * @param  array<int,string>  $tags
     */
    public static function daOficina(bool $carrossel, string $titulo, string $legenda, array $slides, array $tags): self
    {
        if ($carrossel) {
            $out = [];
            foreach ($slides as $s) {
                $t = trim((string) ($s['titulo'] ?? ''));
                $x = trim((string) ($s['texto'] ?? ''));
                if ($t === '' && $x === '') {
                    continue;
                }
                $out[] = new SlidePlano(count($out) + 1, $t !== '' ? $t : 'Cartão '.(count($out) + 1), $x);
            }

            return new self($titulo, '', $tags, $out);
        }

        return new self($titulo, $legenda, $tags, [
            new SlidePlano(1, $titulo !== '' ? $titulo : 'Peça', $legenda),
        ]);
    }

    /** @param array<string,mixed> $dados */
    public static function fromArray(array $dados): self
    {
        $slidesBrutos = is_array($dados['slides'] ?? null) ? $dados['slides'] : [];

        $slides = [];
        foreach (array_values($slidesBrutos) as $i => $slide) {
            if (is_array($slide)) {
                $slides[] = SlidePlano::fromArray($slide, $i + 1);
            }
        }

        // Normaliza a ordem para 1..N sequencial e estável.
        usort($slides, fn (SlidePlano $a, SlidePlano $b) => $a->ordem <=> $b->ordem);
        foreach ($slides as $i => $slide) {
            $slide->ordem = $i + 1;
        }

        $tags = array_values(array_filter(array_map(
            fn ($t) => trim((string) $t),
            is_array($dados['tags'] ?? null) ? $dados['tags'] : [],
        )));

        return new self(
            titulo: trim((string) ($dados['titulo'] ?? '')),
            legenda: trim((string) ($dados['legenda'] ?? '')),
            tags: $tags,
            slides: $slides,
        );
    }

    /**
     * Serializa o plano no corpo Markdown do vault.
     *  - single: a legenda (ou o texto do único cartão).
     *  - carousel: «## Cartão N» separados por «---», preservando a convenção
     *    já usada nos carrosséis existentes.
     */
    public function toBody(string $formato): string
    {
        if ($formato === 'single') {
            $corpo = $this->legenda;

            if ($corpo === '' && $this->slides !== []) {
                $corpo = trim($this->slides[0]->titulo."\n\n".$this->slides[0]->texto);
            }

            return trim($corpo);
        }

        return collect($this->slides)
            ->map(function (SlidePlano $s) {
                $titulo = $s->titulo !== '' ? $s->titulo : 'Cartão '.$s->ordem;
                $bloco = '## '.$titulo;

                return $s->texto !== '' ? $bloco."\n\n".$s->texto : $bloco;
            })
            ->implode("\n\n---\n\n");
    }
}
