<?php

namespace App\Livewire\Ferramentas;

use App\Models\FerramentaIa;
use App\Services\Ferramentas\Ferramentas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One AI video, in three steps: the photo (a new one: /ferramentas/nova/{ferramenta}), the frames to change or
 * approve, the video to download or make again. Polls while a job runs. Owners only (and admins).
 */
#[Layout('components.layouts.app')]
#[Title('Ferramentas IA')]
class Video extends Component
{
    use WithFileUploads;

    public ?FerramentaIa $video = null;

    public string $ferramenta = '';

    public $foto = null;

    public string $pedido = '';

    /** The side move's direction: esquerda | direita. */
    public string $direcao = 'direita';

    /** The cleanup's optional list of things to take out. */
    public string $retirar = '';

    /** Which AI frame the change is for (the last one by default). */
    public string $alvo = '';

    public string $nota = '';

    public function mount(?string $ferramenta = null, ?FerramentaIa $video = null): void
    {
        if ($video?->exists) {
            abort_unless($video->user_id === auth()->id() || auth()->user()->is_admin, 403);
            $this->video = $video;
            $this->ferramenta = $video->ferramenta;
            $this->direcao = $video->direcao();
            $this->retirar = (string) ($video->opcoes['retirar'] ?? '');
        } else {
            abort_unless(isset(Ferramentas::disponiveis()[$ferramenta]), 404);
            $this->ferramenta = $ferramenta;
        }
    }

    /** A photo chosen: kept as the draft at once (a new one gets its page) and checked for the tool while they look. */
    public function updatedFoto(Ferramentas $ferramentas)
    {
        $this->validate(['foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:15360'], [
            'foto.required' => 'Escolha uma fotografia.',
            'foto.*' => 'A fotografia tem de ser uma imagem JPG, PNG ou WebP com menos de 15 MB.',
        ]);
        if ($this->video) {
            abort_unless($this->video->estado === FerramentaIa::RASCUNHO, 409);
            $ferramentas->trocarFoto($this->video, $this->foto);
            $this->foto = null;
            $this->video->refresh();

            return null;
        }
        $this->video = $ferramentas->criar(auth()->user(), $this->ferramenta, $this->foto);
        $ferramentas->direcao($this->video, $this->direcao);
        $ferramentas->retirar($this->video, $this->retirar);
        $this->foto = null;

        return $this->redirectRoute('ferramentas.video', $this->video->id, navigate: false);
    }

    public function updatedDirecao(Ferramentas $ferramentas): void
    {
        if ($this->video) {
            $ferramentas->direcao($this->video, $this->direcao);
            $this->video->refresh();
        }
    }

    /** «Criar imagens»: one video spent and the frames start (none left: the draft stays and its page shows the packs). */
    public function criar(Ferramentas $ferramentas): void
    {
        if (! $this->video) {
            $this->addError('foto', 'Escolha uma fotografia.');

            return;
        }
        $ferramentas->retirar($this->video, $this->retirar);   // what they typed, even if the field never lost focus
        $ferramentas->iniciar($this->video);
        $this->video->refresh();
    }

    public function alterar(Ferramentas $ferramentas): void
    {
        $this->validate(['pedido' => 'required|string|max:500'], ['pedido.*' => 'Escreva o que quer mudar (até 500 caracteres).']);
        if ($ferramentas->alterar($this->video, $this->alvo ?: last($this->video->quadrosIa()), $this->pedido)) {
            $this->pedido = '';
        } else {
            $this->addError('pedido', 'Já não é possível alterar esta imagem.');
        }
        $this->video->refresh();
    }

    public function escolher(string $qual, int $n, Ferramentas $ferramentas): void
    {
        $ferramentas->escolherVersao($this->video, $qual, $n);
        $this->video->refresh();
    }

    public function aprovar(Ferramentas $ferramentas): void
    {
        $ferramentas->aprovar($this->video);
        $this->video->refresh();
    }

    public function refazer(Ferramentas $ferramentas): void
    {
        $this->validate(['nota' => 'nullable|string|max:500'], ['nota.*' => 'A nota pode ter até 500 caracteres.']);
        if ($ferramentas->refazer($this->video, $this->nota)) {
            $this->nota = '';
        } else {
            $this->addError('nota', 'Já não é possível fazer outra versão deste vídeo.');
        }
        $this->video->refresh();
    }

    public function render()
    {
        $this->video?->refresh();
        $user = auth()->user();

        return view('livewire.ferramentas.video', [
            'tipo' => Ferramentas::lista()[$this->ferramenta],
            'disponiveis' => $user->is_admin || $user->imoplantaGratis() ? null : $user->videosIaDisponiveis(),
            'packs' => config('contentmachine.ferramentas.packs'),
        ]);
    }
}
