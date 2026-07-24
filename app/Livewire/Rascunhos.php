<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Drafts and Scheduling')]
class Rascunhos extends Component
{
    public string $filtro = 'todos';

    /** @var array<string,string> scheduling date per item id (no dots for wire:model) */
    public array $datas = [];

    /** Stable id for an item (safe for wire:model — no dots or colons). */
    private function idDe(string $source, string $ref): string
    {
        return $source.'_'.md5($ref);
    }

    public function agendar(string $source, string $ref, VaultContract $vault): void
    {
        $id = $this->idDe($source, $ref);
        $data = $this->datas[$id] ?? null;

        if (blank($data)) {
            $this->addError('datas.'.$id, 'Choose a date.');

            return;
        }

        if ($source === 'animado') {
            app(ClipStore::class)->find($ref)?->update(['scheduled_for' => $data]);

            return;
        }

        $vault->updateFrontmatter($ref, [
            'estado' => 'agendado',
            'agendado_para' => $data,
        ]);
    }

    public function desagendar(string $source, string $ref, VaultContract $vault): void
    {
        if ($source === 'animado') {
            app(ClipStore::class)->find($ref)?->update(['scheduled_for' => null]);

            return;
        }

        $vault->updateFrontmatter($ref, [
            'estado' => 'pronto',
            'agendado_para' => null,
        ]);
    }

    public function remover(string $source, string $ref, VaultContract $vault): void
    {
        if ($source === 'animado') {
            app(ClipStore::class)->find($ref)?->delete();

            return;
        }

        $vault->delete($ref);
    }

    /** Normalizes a vault note (post or clip) into the common shape. */
    private function deNota(VaultNote $n, string $source, string $kind): array
    {
        $agendadoPara = $n->get('estado') === 'agendado' ? (string) $n->get('agendado_para') : null;
        $imagens = (array) $n->get('imagens', []);
        $capa = $imagens[0] ?? null;
        if ($capa && ! Str::startsWith($capa, 'http')) {
            $capa = asset($capa);
        }

        return [
            'id' => $this->idDe($source, $n->path),
            'source' => $source,
            'ref' => $n->path,
            'kind' => $kind,
            'title' => $n->title(),
            'cover' => $capa,
            'excerpt' => Str::limit(strip_tags($n->html()), 160),
            'scheduled' => $agendadoPara ?: null,
        ];
    }

    /** @return Collection<int,array<string,mixed>> */
    private function itens(VaultContract $vault): Collection
    {
        $prontos = ['pronto', 'agendado'];

        // 1. Posts marked as ready.
        $posts = $vault->all('rascunhos')
            ->filter(fn (VaultNote $n) => in_array($n->get('estado'), $prontos, true))
            ->map(function (VaultNote $n) {
                $tipo = (string) $n->get('tipo', 'post');
                $kind = config('contentmachine.publicacoes.tipos.'.$tipo.'.label', $tipo);

                return $this->deNota($n, 'post', $kind);
            });

        // 2. Shorts (clips) already rendered.
        $clips = $vault->all('clips')
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'clip' && in_array($n->get('estado'), $prontos, true))
            ->map(fn (VaultNote $n) => $this->deNota($n, 'clip', 'Short'));

        // 3. Completed animated clips (vault, per-project).
        $animados = app(ClipStore::class)->all()
            ->filter(fn (ClipRecord $p) => $p->status === ClipRecord::STATUS_DONE)
            ->map(fn (ClipRecord $p) => [
                'id' => $this->idDe('animado', $p->id),
                'source' => 'animado',
                'ref' => $p->id,
                'kind' => $p->type === ClipRecord::TYPE_OVERLAY ? 'Animated video' : 'Animation',
                'title' => (string) ($p->title ?: 'Animated clip'),
                'cover' => null,
                'excerpt' => '',
                'scheduled' => $p->scheduled_for ?: null,
            ]);

        return $posts->values()
            ->concat($clips->values())
            ->concat($animados->values());
    }

    public function render(VaultContract $vault)
    {
        $todos = $this->itens($vault);

        $itens = match ($this->filtro) {
            'agendado' => $todos->filter(fn ($i) => $i['scheduled'] !== null),
            'pronto' => $todos->filter(fn ($i) => $i['scheduled'] === null),
            default => $todos,
        };

        return view('livewire.rascunhos', [
            'itens' => $itens->values(),
            'contagem' => [
                'todos' => $todos->count(),
                'pronto' => $todos->filter(fn ($i) => $i['scheduled'] === null)->count(),
                'agendado' => $todos->filter(fn ($i) => $i['scheduled'] !== null)->count(),
            ],
        ]);
    }
}
