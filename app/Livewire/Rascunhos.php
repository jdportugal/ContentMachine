<?php

namespace App\Livewire;

use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rascunhos e Agendamento')]
class Rascunhos extends Component
{
    public string $filtro = 'todos';

    /** @var array<string,string> data de agendamento por slug de nota (sem pontos p/ o wire:model) */
    public array $datas = [];

    public function agendar(string $path, VaultContract $vault): void
    {
        $slug = pathinfo($path, PATHINFO_FILENAME);
        $data = $this->datas[$slug] ?? null;

        if (blank($data)) {
            $this->addError('datas.'.$slug, 'Escolha uma data.');

            return;
        }

        $vault->updateFrontmatter($path, [
            'estado' => 'agendado',
            'agendado_para' => $data,
        ]);
    }

    public function desagendar(string $path, VaultContract $vault): void
    {
        $vault->updateFrontmatter($path, [
            'estado' => 'rascunho',
            'agendado_para' => null,
        ]);
    }

    public function remover(string $path, VaultContract $vault): void
    {
        $vault->delete($path);
    }

    public function render(VaultContract $vault)
    {
        $rascunhos = $vault->all('rascunhos');

        if ($this->filtro !== 'todos') {
            $rascunhos = $rascunhos->filter(fn ($n) => $n->get('estado') === $this->filtro)->values();
        }

        return view('livewire.rascunhos', [
            'rascunhos' => $rascunhos,
            'contagem' => [
                'todos' => $vault->all('rascunhos')->count(),
                'rascunho' => $vault->all('rascunhos')->filter(fn ($n) => $n->get('estado') === 'rascunho')->count(),
                'agendado' => $vault->all('rascunhos')->filter(fn ($n) => $n->get('estado') === 'agendado')->count(),
            ],
        ]);
    }
}
