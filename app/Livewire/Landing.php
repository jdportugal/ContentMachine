<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Leads\LeadVideo;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The public site: one free video per listing. The agent only pastes the link —
 * name and phone come from the listing (leads:atualizar). The form starts the
 * studio's listing import in the app's one project,
 * then turns into the reel's progress and, once rendered, the video itself.
 * The lead is kept in the session so a reload finds it again.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · O vídeo do seu imóvel, grátis')]
class Landing extends Component
{
    public string $anuncio = '';

    public bool $consentimento = false;

    /** Honeypot: hidden from people, filled by bots. */
    public string $site = '';

    public ?int $leadId = null;

    public bool $bot = false;

    public function mount(): void
    {
        $this->leadId = session('lead_id');
    }

    public function pedir(ListingImport $import, ProjectContext $context): void
    {
        if ($this->site !== '') {
            $this->bot = true;

            return;
        }

        $chave = 'lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, config('contentmachine.leads.per_hour'))) {
            $this->addError('anuncio', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }

        $this->anuncio = trim($this->anuncio);
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista. Cole o link de um desses sites.'),
                fn ($a, $v, $fail) => DB::table('leads')->where('anuncio', $v)->exists() ? $fail('Já fizemos o vídeo grátis deste anúncio. Fale connosco para a versão limpa.') : null],
            'consentimento' => 'accepted',
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'consentimento.accepted' => 'Para criarmos o vídeo, precisamos do seu consentimento.',
        ]);
        RateLimiter::hit($chave, 3600);

        // Free videos always use the default theme, not whatever the studio is on.
        $clip = $import->start($this->anuncio, ['free_lead' => true, 'theme' => app(ThemeRepository::class)->default()->slug]);

        $this->leadId = DB::table('leads')->insertGetId([
            'anuncio' => $this->anuncio,
            'projeto' => $context->current()->slug,
            'clip_id' => $clip->id,
            'ip' => (string) request()->ip(),
            'consentimento_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session(['lead_id' => $this->leadId]);
    }

    /** Ask for another listing's video (the session forgets the current one). */
    public function outro(): void
    {
        session()->forget('lead_id');
        $this->reset('anuncio', 'consentimento', 'leadId');
    }

    /** The current lead's reel, as the hero card shows it. */
    private function pedido(LeadVideo $videos): ?array
    {
        $lead = $this->leadId ? $videos->lead($this->leadId) : null;
        if (! $lead) {
            return null;
        }
        $clip = $videos->clip($lead);
        if ($videos->ficheiro($lead)) {
            return ['estado' => 'pronto',
                'video' => URL::signedRoute('lead.video', ['lead' => $lead->id]),
                'descarregar' => URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1])];
        }
        if (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
            return ['estado' => 'falhou'];
        }
        [$etapa, $pct] = Imoveis::ESTADOS[$clip->status] ?? ['A preparar', 10];

        return ['estado' => 'a-fazer', 'etapa' => $etapa, 'pct' => $pct];
    }

    public function render(LeadVideo $videos)
    {
        return view('livewire.landing', ['pedido' => $this->bot ? ['estado' => 'a-fazer', 'etapa' => 'A ler o anúncio', 'pct' => 15] : $this->pedido($videos)]);
    }
}
