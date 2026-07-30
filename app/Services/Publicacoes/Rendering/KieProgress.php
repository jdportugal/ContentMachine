<?php

namespace App\Services\Publicacoes\Rendering;

use Illuminate\Support\Facades\Cache;

/**
 * Per-card generation progress for one piece, keyed by the GerarImagensJob token
 * and persisted in the cache so a RETRIED job resumes instead of regenerating the
 * whole carousel:
 *
 *  - a card already finished (has `url`) is reused as-is — no kie call at all;
 *  - a card mid-flight (has `taskId` but no `url`) is re-polled by its taskId — no
 *    new createTask, so kie does NOT generate a duplicate;
 *  - only a never-submitted card is generated.
 *
 * So a retry only fetches/finishes the card that failed, not the ones kie already
 * produced. Cleared when the piece finishes (or is abandoned after max attempts).
 */
class KieProgress
{
    public function __construct(private string $token) {}

    private function key(): string
    {
        return 'publicacao.kie-progress.'.$this->token;
    }

    /** @return array<int,array{taskId?:string,url?:string}> */
    public function all(): array
    {
        return (array) Cache::get($this->key(), []);
    }

    /** @return array{taskId?:string,url?:string} */
    public function card(int $i): array
    {
        return $this->all()[$i] ?? [];
    }

    /** @param array{taskId?:string,url?:string} $data */
    public function save(int $i, array $data): void
    {
        $all = $this->all();
        $all[$i] = array_merge($all[$i] ?? [], $data);
        Cache::put($this->key(), $all, now()->addHours(2));
    }

    public function clear(): void
    {
        Cache::forget($this->key());
    }
}
