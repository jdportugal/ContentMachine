<?php

namespace App\Livewire;

use App\Services\Billing\Stripe;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeContext;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The agent's portal: paste a listing, get a reel. The studio's "From a listing"
 * form trimmed to what an agent needs (link, notes, own photo), in pt-PT, over
 * the same import pipeline; plus their listing reels with status and download.
 *
 * A customer spends one bought video per reel (from the purchase that expires
 * first) and sees only their own; an admin makes them freely and sees them all.
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

    /** After Stripe Checkout: what the customer reads about their payment. */
    public ?string $pagamento = null;

    public function mount(ThemeContext $themes, Stripe $stripe): void
    {
        $this->tema = $themes->current()->slug;

        // Back from Checkout. The webhook records the purchase too; whichever comes
        // first does it, so the videos are here even before the webhook arrives.
        $id = (string) request()->query('pago', '');
        if (preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
            $sessao = rescue(fn () => $stripe->sessao($id), [], report: true);
            if (($sessao['client_reference_id'] ?? null) === (string) auth()->id()) {
                $this->pagamento = $stripe->cumprir($sessao)
                    ? 'Pagamento recebido. Os seus vídeos já estão disponíveis.'
                    : 'Assim que o pagamento for confirmado, os vídeos ficam disponíveis aqui.';
            }
        }
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

        $user = auth()->user();
        $semVideos = DB::transaction(function () use ($import, $user) {
            $compra = $user->is_admin ? null : DB::table('compras')->where('user_id', $user->id)->where('tipo', 'videos')
                ->where('restantes', '>', 0)->where('expira_em', '>', now())
                ->orderBy('expira_em')->lockForUpdate()->first();
            if (! $user->is_admin && ! $compra) {
                return true;
            }

            $clip = $import->start($this->anuncio, [
                'agent_photo' => $this->foto?->store('clips/uploads'),
                'script_notes' => trim($this->notas),
                'theme' => $this->tema,
            ]);
            if ($compra) {
                DB::table('compras')->where('id', $compra->id)->decrement('restantes', 1, ['updated_at' => now()]);
                DB::table('videos')->insert(['user_id' => $user->id, 'compra_id' => $compra->id, 'projeto' => app(ProjectContext::class)->current()->slug, 'clip_id' => $clip->id,
                    'anuncio' => $this->anuncio, 'created_at' => now(), 'updated_at' => now()]);
            }

            return false;
        });
        if ($semVideos) {
            $this->addError('anuncio', 'Não tem vídeos disponíveis. Compre um vídeo ou um pack abaixo.');

            return;
        }

        $this->reset('anuncio', 'notas', 'foto');   // the theme stays as chosen
        unset($this->videos);
    }

    /** The listing reels, newest first: every one for an admin, a customer's own otherwise. */
    #[Computed]
    public function videos()
    {
        $user = auth()->user();
        $meus = $user->is_admin ? null : DB::table('videos')->where('user_id', $user->id)->pluck('clip_id')->all();

        return app(ClipStore::class)->all()
            ->filter(fn (ClipRecord $c) => ! empty($c->meta['listing']) && ($meus === null || in_array($c->id, $meus, true)))
            ->values();
    }

    public function render()
    {
        return view('livewire.imoveis', [
            'aDecorrer' => $this->videos->contains(fn (ClipRecord $c) => $c->isActive()),
            'temas' => collect(app(ThemeRepository::class)->all())->mapWithKeys(fn ($t) => [$t->slug => $t->name]),
            'disponiveis' => auth()->user()->is_admin ? null : auth()->user()->videosDisponiveis(),
            'planos' => config('contentmachine.planos'),
        ]);
    }
}
