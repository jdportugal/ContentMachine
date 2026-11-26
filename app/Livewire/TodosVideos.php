<?php

namespace App\Livewire;

use App\Services\Leads\LeadVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url as NoEndereco;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin: every customer's video (free and paid, every account and none), newest first, with the same permanent
 * signed links the customer got by email (watch and download), to send them again.
 */
#[Layout('components.layouts.app')]
#[Title('Todos os vídeos')]
class TodosVideos extends Component
{
    use WithPagination;

    #[NoEndereco(as: 'q', except: '')]
    public string $procura = '';

    #[NoEndereco(except: 'todos')]
    public string $estado = 'todos';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(LeadVideo $videos)
    {
        $q = Str::lower(trim($this->procura));
        $leads = DB::table('leads')->leftJoin('users', 'users.id', '=', 'leads.user_id')
            ->select('leads.*', 'users.email as conta')
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w->where('users.email', 'like', "%$q%")->orWhere('leads.email', 'like', "%$q%")
                ->orWhere('leads.email_video', 'like', "%$q%")->orWhere('leads.nome', 'like', "%$q%")->orWhere('leads.consultor', 'like', "%$q%")
                ->orWhere('leads.anuncio', 'like', "%$q%")))
            ->when($this->estado === 'prontos', fn ($w) => $w->whereNotNull('leads.pronto_em'))
            ->when($this->estado === 'a-fazer', fn ($w) => $w->whereNull('leads.pronto_em')->whereNull('leads.falhou_em'))
            ->when($this->estado === 'falhados', fn ($w) => $w->whereNotNull('leads.falhou_em')->whereNull('leads.pronto_em'))
            ->orderByDesc('leads.id')->paginate(30);

        $linhas = collect($leads->items())->map(function ($l) use ($videos) {
            $clip = rescue(fn () => $videos->clip($l), null, report: false);
            $pronto = $l->pronto_em !== null || (bool) $videos->ficheiro($l);

            return (object) [
                'id' => $l->id,
                'titulo' => $clip?->title ?: $l->anuncio,
                'anuncio' => $l->anuncio,
                'quem' => $l->conta ?: ($l->email_video ?: ($l->email ?: '')),
                'nome' => $l->nome ?: '',
                'conta' => (bool) $l->conta,
                'limpo' => $l->pago_em || $l->oferta_em || ($clip && empty($clip->meta['free_lead'])),
                'estado' => $pronto ? 'pronto' : ($l->falhou_em ? 'falhou' : 'a-fazer'),
                'quando' => $l->created_at,
                'ver' => $pronto ? URL::signedRoute('lead.video', ['lead' => $l->id]) : null,
                'descarregar' => $pronto ? URL::signedRoute('lead.video', ['lead' => $l->id, 'download' => 1]) : null,
            ];
        });

        return view('livewire.todos-videos', ['linhas' => $linhas, 'leads' => $leads]);
    }
}
