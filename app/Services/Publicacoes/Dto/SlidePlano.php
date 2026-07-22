<?php

namespace App\Services\Publicacoes\Dto;

/**
 * Um cartão planeado de uma publicação (capa ou conteúdo).
 */
class SlidePlano
{
    public function __construct(
        public int $ordem,
        public string $titulo,
        public string $texto,
    ) {}

    /** @param array<string,mixed> $dados */
    public static function fromArray(array $dados, int $ordemPadrao = 1): self
    {
        return new self(
            ordem: (int) ($dados['ordem'] ?? $ordemPadrao),
            titulo: trim((string) ($dados['titulo'] ?? '')),
            texto: trim((string) ($dados['texto'] ?? '')),
        );
    }
}
