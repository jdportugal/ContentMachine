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
#[Title('Imocena · O vídeo do seu imóvel, pronto em minutos. Grátis.')]
class Landing extends Component
{
    /**
     * The promotional-email opt-in, word for word. Stored with each consent as proof
     * of what was agreed to, so change the wording here, never in the view.
     */
    public const MARKETING = 'Quero receber emails da Imocena com novidades, dicas e promoções. É opcional e pode cancelar a qualquer momento, com um clique em qualquer email.';

    public string $anuncio = '';

    public bool $consentimento = false;

    /** Optional and never pre-ticked: the free video doesn't depend on it. */
    public bool $marketing = false;

    public string $email = '';

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
        $this->email = trim($this->email);
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista. Cole o link de um desses sites.'),
                // A failed attempt does not use up the free video: only leads that did not fail count.
                fn ($a, $v, $fail) => DB::table('leads')->where('anuncio', $v)->whereNull('falhou_em')->exists() ? $fail('Já fizemos o vídeo grátis deste anúncio. Fale connosco para a versão limpa.') : null],
            'consentimento' => 'accepted',
            'email' => ['exclude_unless:marketing,true', 'required', 'email:rfc', 'max:255'],
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'consentimento.accepted' => 'Para criarmos o vídeo, precisamos do seu consentimento.',
            'email.required' => 'Indique o email onde quer receber as novidades.',
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
            'email' => $this->marketing ? $this->email : null,
            'marketing_em' => $this->marketing ? now() : null,
            'marketing_texto' => $this->marketing ? self::MARKETING : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session(['lead_id' => $this->leadId]);
        $this->dispatch('lead-criado', id: $this->leadId);
    }

    /** Ask for another listing's video (the session forgets the current one). */
    public function outro(): void
    {
        session()->forget('lead_id');
        $this->reset('anuncio', 'consentimento', 'marketing', 'email', 'leadId');
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
