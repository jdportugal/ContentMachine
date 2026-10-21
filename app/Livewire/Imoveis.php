<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeContext;
use App\Services\Themes\ThemeRepository;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The agent's portal: paste a listing, get a reel. The studio's "From a listing"
 * form trimmed to what an agent needs (link, notes, own photo), in pt-PT, over
 * the same import pipeline; plus their listing reels with status and download.
 */
#[Layout('components.layouts.app')]
#[Title('Os meus vídeos')]
class Imoveis extends Component
{
    use WithFileUploads;

    public string $anuncio = '';

    public string $notas = '';

    public $foto = null;

    /** The theme this video is made in (defaults to the studio's current one). */
    public string $tema = '';

    /** Status → what the agent reads, and how far along the bar is. */
    public const ESTADOS = [
        ClipRecord::STATUS_IMPORTING => ['A ler o anúncio', 15],
        ClipRecord::STATUS_CHOOSING_VOICE => ['A preparar a narração', 25],
        ClipRecord::STATUS_TRANSCRIBING => ['A gravar a narração', 35],
        ClipRecord::STATUS_PLANNING => ['A montar as cenas', 60],
        ClipRecord::STATUS_COLLECTING => ['A montar as cenas', 60],
        ClipRecord::STATUS_RENDERING => ['A exportar o vídeo', 85],
    ];

    public function mount(ThemeContext $themes): void
    {
        $this->tema = $themes->current()->slug;
    }

    public function criar(ListingImport $import, ThemeRepository $themes): void
    {
        $this->anuncio = trim($this->anuncio);
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500', fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista.')],
            'notas' => 'nullable|string|max:3000',
            'foto' => 'nullable|image|max:10240',
            'tema' => ['required', fn ($a, $v, $fail) => $themes->find($v) ? null : $fail('Escolha um dos temas.')],
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'foto.image' => 'A foto tem de ser uma imagem (JPG ou PNG).',
            'foto.max' => 'A foto tem de ter menos de 10 MB.',
        ]);

        $import->start($this->anuncio, [
            'agent_photo' => $this->foto?->store('clips/uploads'),
            'script_notes' => trim($this->notas),
            'theme' => $this->tema,
        ]);
        $this->reset('anuncio', 'notas', 'foto');   // the theme stays as chosen
        unset($this->videos);
    }

    /** The listing reels, newest first. */
    #[Computed]
    public function videos()
    {
        return app(ClipStore::class)->all()->filter(fn (ClipRecord $c) => ! empty($c->meta['listing']))->values();
    }

    public function render()
    {
        return view('livewire.imoveis', [
            'aDecorrer' => $this->videos->contains(fn (ClipRecord $c) => $c->isActive()),
            'temas' => collect(app(ThemeRepository::class)->all())->mapWithKeys(fn ($t) => [$t->slug => $t->name]),
        ]);
    }
}
