<?php

namespace App\Services\Publicacoes;

/**
 * Access to the declarative registry of post types
 * (config `contentmachine.publicacoes.tipos`). It is the single source of truth
 * about the formats; the workshop, the planner and the renderer read from here.
 */
class PublicacaoKinds
{
    /** @return array<string,array<string,mixed>> types indexed by identifier */
    public function all(): array
    {
        return (array) config('contentmachine.publicacoes.tipos', []);
    }

    /** @return array<string,mixed>|null */
    public function get(string $tipo): ?array
    {
        return $this->all()[$tipo] ?? null;
    }

    public function exists(string $tipo): bool
    {
        return array_key_exists($tipo, $this->all());
    }

    /** 'single' | 'carousel' — 'single' by default for unknown types. */
    public function formato(string $tipo): string
    {
        return $this->get($tipo)['formato'] ?? 'single';
    }

    /** @return array{min:int,max:int} the type's card limits. */
    public function cartoes(string $tipo): array
    {
        $c = $this->get($tipo)['cartoes'] ?? ['min' => 1, 'max' => 1];

        return ['min' => (int) ($c['min'] ?? 1), 'max' => (int) ($c['max'] ?? 1)];
    }
}
