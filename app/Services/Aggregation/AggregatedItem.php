<?php

namespace App\Services\Aggregation;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Um item de conteúdo normalizado, independente da plataforma de origem.
 * É o formato comum produzido pelos drivers e consumido pelo agregador.
 */
class AggregatedItem implements Arrayable
{
    /**
     * @param  array<int,string>  $tags
     * @param  array<int,string>  $fontes  URLs mencionadas no conteúdo
     */
    public function __construct(
        public readonly string $id,
        public readonly string $plataforma,
        public readonly string $titulo,
        public readonly string $canal,
        public readonly string $data,        // YYYY-MM-DD
        public readonly string $url,
        public readonly string $thumbnail = '',
        public readonly string $descricao = '',
        public readonly string $transcricao = '',
        public readonly array $tags = [],
        public readonly array $fontes = [],
    ) {}

    /** Dia de arquivo (YYYY-MM-DD) usado para organizar o vault. */
    public function dia(): string
    {
        return $this->data !== '' ? $this->data : now()->toDateString();
    }

    /** Caminho relativo da nota no vault. */
    public function caminho(): string
    {
        return "noticias/{$this->dia()}/{$this->plataforma}-{$this->id}.md";
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plataforma' => $this->plataforma,
            'titulo' => $this->titulo,
            'canal' => $this->canal,
            'data' => $this->data,
            'url' => $this->url,
            'thumbnail' => $this->thumbnail,
            'descricao' => $this->descricao,
            'transcricao' => $this->transcricao,
            'tags' => $this->tags,
            'fontes' => $this->fontes,
        ];
    }
}
