<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Content\FinishedContent;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Content Repurpose — take something already finished and produce it in another
 * format. A video becomes a post or a carousel; a post or carousel becomes a
 * video.
 *
 * Every conversion is a HANDOFF, not a new pipeline: it extracts the item's text
 * (spoken words for a short, script for an animated clip, body for a post) and
 * seeds the editor that already knows how to build the target format —
 * the post workshop via session('oficina_brief'), the animated-clip creator via
 * session('animado_texto'). Nothing here plans or renders anything itself.
 */
#[Layout('components.layouts.app')]
#[Title('Content Repurpose')]
class ContentRepurpose extends Component
{
    /** Which side to show: 'video' (→ post/carousel) or 'post' (→ video). */
    public string $de = 'video';

    public function escolherOrigem(string $de): void
    {
        $this->de = in_array($de, ['video', 'post'], true) ? $de : 'video';
    }

    /**
     * Finished videos — shorts and animated clips — in one list.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function getVideosProperty(): Collection
    {
        $finished = app(FinishedContent::class);

        $shorts = $finished->shorts()->map(fn (VaultNote $n) => [
            'source' => 'short',
            'ref' => $n->path,
            'title' => $n->title(),
            'kind' => 'Short',
            'excerpt' => Str::limit(trim(strip_tags($n->html())) ?: (string) $n->get('descricao'), 140),
        ]);

        $animated = $finished->animated()->map(fn (ClipRecord $p) => [
            'source' => 'animado',
            'ref' => $p->id,
            'title' => (string) ($p->title ?: 'Animated clip'),
            'kind' => $p->type === ClipRecord::TYPE_OVERLAY ? 'Animated video' : 'Animation',
            'excerpt' => Str::limit((string) ($p->get('source_text') ?: ''), 140),
        ]);

        return $shorts->concat($animated)->values();
    }

    /**
     * Finished posts and carousels.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function getPostsProperty(): Collection
    {
        return app(FinishedContent::class)->posts()->map(function (VaultNote $n) {
            $tipo = (string) $n->get('tipo', 'post');

            return [
                'ref' => $n->path,
                'title' => $n->title(),
                'kind' => (string) config('contentmachine.publicacoes.tipos.'.$tipo.'.label', $tipo),
                'excerpt' => Str::limit(trim(strip_tags($n->html())), 140),
            ];
        })->values();
    }

    /**
     * Video → post or carousel. Seeds the post workshop with what the video
     * actually says, then opens it on the chosen format.
     */
    public function paraPublicacao(string $source, string $ref, string $tipo, FinishedContent $finished, VaultContract $vault)
    {
        if (! in_array($tipo, ['post', 'carrossel'], true)) {
            return null;
        }

        $texto = match ($source) {
            'short' => ($n = $vault->get($ref)) ? $finished->shortText($n) : '',
            'animado' => ($c = app(ClipStore::class)->find($ref)) ? $finished->animatedText($c) : '',
            default => '',
        };

        if (trim($texto) === '') {
            $this->dispatch('toast', message: 'That video has no text to work from — transcribe it first.', type: 'erro');

            return null;
        }

        session(['oficina_brief' => Str::limit($texto, 6000, '')]);

        return redirect()->route('publicacoes.oficina', $tipo);
    }

    /**
     * Post/carousel → video. Seeds the animated-clip creator's script, which
     * plans and renders it exactly as a hand-written script would be.
     */
    public function paraVideo(string $ref, FinishedContent $finished, VaultContract $vault)
    {
        $post = $vault->get($ref);
        $texto = $post ? $finished->postText($post) : '';

        if (trim($texto) === '') {
            $this->dispatch('toast', message: 'That post has no text to turn into a video.', type: 'erro');

            return null;
        }

        session(['animado_texto' => $texto]);

        return redirect()->route('clips-animados');
    }

    public function render()
    {
        return view('livewire.content-repurpose');
    }
}
