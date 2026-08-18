<?php

namespace App\Livewire;

use App\Services\Content\FinishedContent;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Posts Generator — turn a long video into posts/carousels.
 *
 * The pipeline already existed, buried in the Shorts Generator's video detail
 * view: ShortsPipeline::sugerirDoVideo() returns BOTH clip segments and post
 * ideas, and the ideas are stored on the source as `publicacoes_sugeridas`.
 * This page gives that half its own tab, so posts are a first-class output of a
 * long video rather than a side effect of cutting shorts.
 *
 * Opening an idea seeds session('oficina_brief') and hands off to the post
 * workshop — the same handoff Clips::abrirPublicacao() uses.
 */
#[Layout('components.layouts.app')]
#[Title('Posts Generator')]
class PostsGenerator extends Component
{
    /** Brief cap. Only the planner's prompt reads it, so it can carry the transcript. */
    private const MAX_BRIEF = 20000;

    /** Source whose suggestions are expanded, or null for all collapsed. */
    public ?string $aberta = null;

    public function alternar(string $path): void
    {
        $this->aberta = $this->aberta === $path ? null : $path;
    }

    /** Long videos available as post material. @return Collection<int,VaultNote> */
    public function getFontesProperty(): Collection
    {
        return app(VaultContract::class)->all(ShortsPipeline::FONTES)
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'clip-fonte')
            ->values();
    }

    /**
     * The post ideas stored on a source.
     *
     * @return array<int,array<string,mixed>>
     */
    public function sugestoes(VaultNote $fonte): array
    {
        $raw = json_decode((string) $fonte->get('publicacoes_sugeridas'), true);

        return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
    }

    /** Has this source been transcribed? Suggesting needs the transcript. */
    public function transcrita(VaultNote $fonte): bool
    {
        return trim((string) $fonte->get('transcricao')) !== '';
    }

    /** Ask the AI for post ideas from the video's transcript. */
    public function sugerir(string $path, ShortsPipeline $pipeline, VaultContract $vault): void
    {
        try {
            $sugestoes = $pipeline->sugerirDoVideo($path);

            // Only the post ideas here — cutting clips belongs to the Shorts tab.
            $vault->updateFrontmatter($path, [
                'publicacoes_sugeridas' => json_encode($sugestoes['publications'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            $this->aberta = $path;
            $this->dispatch('toast', message: count($sugestoes['publications'] ?? []).' post ideas suggested.', type: 'ok');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'erro');
        }
    }

    /**
     * Open one idea in the post workshop, pre-filled. $tipo picks the format
     * directly ('post', 'carrossel', …); without it the user chooses.
     */
    public function abrir(string $path, int $i, ?string $tipo, VaultContract $vault, ShortsPipeline $pipeline, FinishedContent $conteudo)
    {
        $fonte = $vault->get($path);
        $sugestao = $fonte ? ($this->sugestoes($fonte)[$i] ?? null) : null;

        if (! $sugestao) {
            $this->dispatch('toast', message: 'Suggestion not found.', type: 'erro');

            return null;
        }

        // The angle alone gives the planner nothing to write FROM — it would
        // invent the substance. Send the video's own words as the source
        // material, with the chosen angle as the instruction on top.
        $angulo = trim(((string) ($sugestao['titulo'] ?? ''))."\n\n".((string) ($sugestao['angulo'] ?? '')));
        $transcricao = $conteudo->transcriptText($pipeline->transcricao($fonte));

        $brief = $transcricao !== ''
            ? $angulo."\n\n--- Source video transcript ---\n".$transcricao
            : $angulo;

        session(['oficina_brief' => Str::limit($brief, self::MAX_BRIEF, '')]);

        return $tipo
            ? redirect()->route('publicacoes.oficina', $tipo)
            : redirect()->route('publicacoes');
    }

    public function render()
    {
        return view('livewire.posts-generator');
    }
}
