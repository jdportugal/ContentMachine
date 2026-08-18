<?php

namespace App\Livewire;

use App\Jobs\Editor\AnalyseEditJob;
use App\Jobs\Editor\GenerateEditSfxJob;
use App\Jobs\Editor\RenderEditJob;
use App\Services\Editor\CutPlan;
use App\Services\Editor\EditorStore;
use App\Services\Editor\EditRecord;
use App\Services\Editor\Removal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * AI Video Editor — cuts a raw take down to what you meant to say, keeping the
 * camera and screen-feed tracks in sync, then offers effects for the screen.
 *
 * The transcript IS the timeline: struck segments are the removals, and toggling
 * one edits the cut. Nothing renders until you approve.
 */
#[Layout('components.layouts.app')]
#[Title('Video Editor')]
class VideoEditor extends Component
{
    use WithFileUploads;

    /** Open edit's id, or null for the list. */
    public ?string $aberto = null;

    public string $titulo = '';

    public $camera = null;

    public $screen = null;

    public string $lingua = 'pt';

    public float $limiteSilencio = 0.7;

    private function store(): EditorStore
    {
        return app(EditorStore::class);
    }

    // ── creating ─────────────────────────────────────────────────────────

    public function criar(): void
    {
        $this->validate([
            'camera' => 'required|file|max:4194304',   // 4 GB, as the clips uploads allow
            'screen' => 'nullable|file|max:4194304',
            'titulo' => 'nullable|string|max:120',
            'limiteSilencio' => 'numeric|min:0.2|max:5',
        ], [
            'camera.required' => 'Choose the camera recording.',
        ]);

        $edit = $this->store()->create([
            'title' => trim($this->titulo) !== '' ? trim($this->titulo) : 'Take '.now()->format('d/m H:i'),
            'language' => $this->lingua,
            'silence_threshold' => (float) $this->limiteSilencio,
        ]);

        $paths = ['camera_path' => $this->guardar($edit->id(), $this->camera, 'camera')];
        if ($this->screen) {
            $paths['screen_path'] = $this->guardar($edit->id(), $this->screen, 'screen');
        }

        $edit->update($paths);

        AnalyseEditJob::dispatch($edit->id());

        $this->reset(['camera', 'screen', 'titulo']);
        $this->aberto = $edit->id();
    }

    /**
     * Move an upload next to its edit record, keeping its extension. Copied
     * rather than streamed through a disk: the sources live in the vault beside
     * the record, so the whole edit travels together.
     */
    private function guardar(string $id, mixed $upload, string $role): string
    {
        $destino = $this->store()->filePath($id, $role, $upload->getClientOriginalExtension() ?: 'mp4');
        @mkdir(dirname($destino), 0775, true);

        if (! @copy($upload->getRealPath(), $destino)) {
            throw new \RuntimeException("Could not store the {$role} file.");
        }

        return $destino;
    }

    // ── navigation ───────────────────────────────────────────────────────

    public function abrir(string $id): void
    {
        $this->aberto = $id;
    }

    public function voltar(): void
    {
        $this->aberto = null;
    }

    public function apagar(string $id): void
    {
        $this->store()->deleteById($id);
        if ($this->aberto === $id) {
            $this->aberto = null;
        }
    }

    // ── editing the cut ──────────────────────────────────────────────────

    /**
     * Toggle one transcript segment: struck becomes kept, kept becomes struck.
     * A manual removal is the same kind of object as a detected one, so undoing
     * an AI proposal is just dropping the removal that covers this segment.
     */
    public function alternarSegmento(int $i): void
    {
        $edit = $this->edicao;
        if (! $edit) {
            return;
        }

        $segmentos = $edit->transcript();
        if (! isset($segmentos[$i]) || ! is_array($segmentos[$i])) {
            return;
        }

        $inicio = (float) ($segmentos[$i]['start'] ?? 0);
        $fim = (float) ($segmentos[$i]['end'] ?? 0);
        $meio = $inicio + max(0.0, ($fim - $inicio) / 2);

        $restantes = [];
        $estavaRemovido = false;

        foreach ($edit->removals() as $r) {
            // Drop any removal covering this segment's midpoint — that is the one
            // striking it out, whatever proposed it.
            if ($meio >= $r->start && $meio < $r->end) {
                $estavaRemovido = true;

                continue;
            }
            $restantes[] = $r;
        }

        if (! $estavaRemovido) {
            $restantes[] = new Removal($inicio, $fim, Removal::MANUAL, Str::limit((string) ($segmentos[$i]['text'] ?? ''), 60));
        }

        $edit->setRemovals($restantes);
    }

    /** Drop every proposed cut and start from the raw take. */
    public function limparCortes(): void
    {
        $this->edicao?->setRemovals([]);
    }

    public function reanalisar(): void
    {
        if ($edit = $this->edicao) {
            $edit->update(['status' => EditorStore::PENDING]);
            AnalyseEditJob::dispatch($edit->id());
        }
    }

    public function aprovar(): void
    {
        if ($edit = $this->edicao) {
            // Status BEFORE dispatch: on the sync queue the job runs inline, and
            // updating this (stale) instance afterwards would clobber the result
            // the job just saved — the edit then shows "rendering" forever.
            $edit->update(['status' => EditorStore::RENDERING]);
            RenderEditJob::dispatch($edit->id());
        }
    }

    public function gerarSfx(): void
    {
        if ($edit = $this->edicao) {
            $edit->update(['sfx_status' => 'working']);
            GenerateEditSfxJob::dispatch($edit->id());
        }
    }

    // ── view data ────────────────────────────────────────────────────────

    public function getEdicaoProperty(): ?EditRecord
    {
        return $this->aberto ? $this->store()->find($this->aberto) : null;
    }

    /** @return Collection<int,EditRecord> */
    public function getEdicoesProperty(): Collection
    {
        return $this->store()->all();
    }

    /**
     * The transcript with each segment marked kept or removed, plus the silence
     * dropped just before it — a silence removes no words, so it shows as a gap
     * marker rather than struck text.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLinhasProperty(): array
    {
        $edit = $this->edicao;
        if (! $edit) {
            return [];
        }

        $removals = $edit->removals();
        $linhas = [];
        $anterior = 0.0;

        foreach ($edit->transcript() as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $inicio = (float) ($seg['start'] ?? 0);
            $fim = (float) ($seg['end'] ?? 0);
            $meio = $inicio + max(0.0, ($fim - $inicio) / 2);

            $cobertura = null;
            foreach ($removals as $r) {
                if ($meio >= $r->start && $meio < $r->end) {
                    $cobertura = $r;
                    break;
                }
            }

            // Silence removed in the gap since the previous segment.
            $silencio = 0.0;
            foreach ($removals as $r) {
                if ($r->reason === Removal::SILENCE && $r->start >= $anterior - 0.01 && $r->end <= $inicio + 0.01) {
                    $silencio += $r->duration();
                }
            }

            $linhas[] = [
                'i' => $i,
                'text' => trim((string) ($seg['text'] ?? '')),
                'start' => $inicio,
                'removed' => $cobertura !== null,
                'reason' => $cobertura?->reason,
                'gap' => round($silencio, 1),
            ];
            $anterior = $fim;
        }

        return $linhas;
    }

    public function getResumoProperty(): array
    {
        $edit = $this->edicao;
        if (! $edit) {
            return ['total' => 0.0, 'kept' => 0.0, 'cut' => 0.0];
        }

        $total = (float) $edit->get('duration', 0);
        $kept = app(CutPlan::class)->keptDuration($edit->removals(), $total);

        return ['total' => $total, 'kept' => $kept, 'cut' => max(0.0, $total - $kept)];
    }

    public function getOcupadoProperty(): bool
    {
        $edit = $this->edicao;
        $estados = [EditorStore::PENDING, EditorStore::ANALYSING, EditorStore::RENDERING];

        return $edit
            ? in_array((string) $edit->status, $estados, true) || $edit->get('sfx_status') === 'working'
            : $this->edicoes->contains(fn (EditRecord $e) => in_array((string) $e->status, $estados, true));
    }

    public function render()
    {
        return view('livewire.video-editor');
    }
}
