<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Contas\EdicaoDeVideo;
use App\Services\Contas\LinkDeAcesso;
use App\Services\Contas\NovoVideo;
use App\Services\Leads\LeadVideo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A customer's area: their videos (ready to download, clean once paid), their
 * credits from packs, and a form that spends a credit on a new listing.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · A minha conta')]
class Conta extends Component
{
    public string $anuncio = '';

    public function usarCredito(NovoVideo $novo): void
    {
        $this->anuncio = trim($this->anuncio);
        $this->validate(NovoVideo::regrasDoAnuncio(), NovoVideo::MENSAGENS_DO_ANUNCIO);
        if ($erro = $novo->criar(Auth::user(), $this->anuncio)) {
            $this->addError('anuncio', $erro);

            return;
        }
        $this->reset('anuncio');
    }

    public function render(LeadVideo $videos)
    {
        $user = Auth::user();
        $lista = LinkDeAcesso::leads($user)->map(function ($lead) use ($videos) {
            $clip = $videos->clip($lead);
            $pronto = (bool) $videos->ficheiro($lead);
            [$etapa, $pct] = Imoveis::ESTADOS[$clip?->status] ?? ['A preparar', 10];

            return (object) [
                'id' => $lead->id,
                'edicoesRestantes' => $clip ? max(0, EdicaoDeVideo::permitidas($clip) - EdicaoDeVideo::feitas($clip)) : 0,
                'anuncio' => $lead->anuncio,
                'titulo' => $clip?->title ?: $lead->anuncio,
                'estado' => $pronto ? 'pronto' : (! $clip || $clip->status === ClipRecord::STATUS_FAILED ? 'falhou' : 'a-fazer'),
                'limpo' => $pronto && empty($clip?->meta['free_lead']),
                'etapa' => $etapa, 'pct' => $pct,
                'video' => $pronto ? URL::signedRoute('lead.video', ['lead' => $lead->id]) : null,
                'descarregar' => $pronto ? URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]) : null,
            ];
        });

        $link = fn (string $plano, array $extra = []) => (string) config("contentmachine.stripe.links.$plano") !== ''
            ? config("contentmachine.stripe.links.$plano").'?'.http_build_query($extra + ['prefilled_email' => $user->email, 'locale' => 'pt'])
            : 'mailto:'.config('mail.from.address');

        return view('livewire.conta', [
            'link' => $link,
            'user' => $user,
            'creditos' => $user->creditos(),
            'videos' => $lista,
            'aDecorrer' => $lista->contains(fn ($v) => $v->estado === 'a-fazer'),
        ]);
    }
}
