<?php

namespace App\Livewire;

use App\Services\Marketing\Canal;
use App\Services\Marketing\Funil;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin: the campaign funnel. From what Meta shows of our ads (impressions, clicks,
 * spend) to what happens on the site (visits, free videos, purchases), by ad and by channel.
 */
#[Layout('components.layouts.app')]
#[Title('Campanhas')]
class Campanhas extends Component
{
    public const PERIODOS = ['hoje' => 'Hoje', '7' => '7 dias', '30' => '30 dias', '90' => '90 dias', 'tudo' => 'Desde o início'];

    #[Url]
    public string $periodo = '30';

    private bool $fresco = false;

    public function atualizar(): void
    {
        $this->fresco = true;
    }

    public function render(Funil $funil)
    {
        if (! isset(self::PERIODOS[$this->periodo])) {
            $this->periodo = '30';
        }
        $de = match ($this->periodo) {
            'tudo' => null,
            'hoje' => now()->startOfDay(),
            default => now()->subDays((int) $this->periodo - 1)->startOfDay(),
        };

        return view('livewire.campanhas', [
            'f' => $funil->calcular($de, now(), $this->fresco),
            'periodos' => self::PERIODOS,
            'canaisNomes' => Canal::NOMES,
            'parametros' => Canal::PARAMETROS_META,
        ]);
    }
}
