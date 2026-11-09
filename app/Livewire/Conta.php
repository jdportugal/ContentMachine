<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Contas\EdicaoDeVideo;
use App\Services\Contas\LinkDeAcesso;
use App\Services\Leads\LeadVideo;
use App\Services\Pagamentos\Compras;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function usarCredito(ListingImport $import, ProjectContext $context, Compras $compras): void
    {
        $user = Auth::user();
        $this->anuncio = trim($this->anuncio);
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista.')],
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
        ]);
        if ($user->creditos() < 1) {
            $this->addError('anuncio', 'Já não tem vídeos disponíveis. Compre um vídeo ou um pack.');

            return;
        }
        $existente = DB::table('leads')->where('anuncio', $this->anuncio)->first();
        if ($existente && $existente->user_id && (int) $existente->user_id !== (int) $user->id) {
            $this->addError('anuncio', 'Este anúncio já tem um vídeo feito por outra conta. Fale connosco.');

            return;
        }
        if ($existente && $existente->pago_em) {
            $this->addError('anuncio', 'Este anúncio já tem o vídeo limpo na sua conta.');

            return;
        }
        $compra = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')->orderBy('id')->first();

        // A free video already made for this listing (by them, or before they had an account): deliver it clean.
        if ($existente && $existente->falhou_em === null && $existente->clip_id) {
            DB::table('leads')->where('id', $existente->id)->update(['user_id' => $user->id, 'updated_at' => now()]);
            $compras->entregarVideoLimpo($compra->id, $existente->id);
            $this->reset('anuncio');

            return;
        }

        $clip = $import->start($this->anuncio, ['theme' => app(ThemeRepository::class)->forPortal(ListingScraper::portal($this->anuncio))->slug]);
        $dados = ['user_id' => $user->id, 'projeto' => $context->current()->slug, 'clip_id' => $clip->id, 'ip' => (string) request()->ip(),
            'consentimento_em' => now(), 'falhou_em' => null, 'pronto_em' => null, 'pago_em' => now(), 'updated_at' => now()];
        if ($existente) {
            DB::table('leads')->where('id', $existente->id)->update($dados);
        } else {
            DB::table('leads')->insert($dados + ['anuncio' => $this->anuncio, 'created_at' => now()]);
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'updated_at' => now()]);
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
