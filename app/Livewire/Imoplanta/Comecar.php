<?php

namespace App\Livewire\Imoplanta;

use App\Models\Imoplanta;
use App\Models\User;
use App\Services\Auth\LinkEntrada;
use App\Services\Billing\Stripe;
use App\Services\Imoplanta\Imoplantas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The public plans page: anyone goes from house plans to photos and a walkthrough video, in three steps on one
 * page — the email (an account without a password or an email check, like the demo video), the plans, the payment. The plans are kept as a
 * property not paid for (a_pagar) and nothing is read until Stripe confirms the payment, which starts it
 * (Stripe::cumprir → Imoplantas::iniciar). Then the customer works in the same editor as everyone else
 * (3D model, cameras, AI changes within the limits, images, video). An account with credit left skips paying.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · Das plantas ao vídeo do imóvel')]
class Comecar extends Component
{
    use WithFileUploads;

    /** The pack the final step starts on: one property. */
    public const PLANO = 'imoplanta1';

    /** The pack chosen on the final step (1, 10 or 20 properties, as on «Os meus vídeos»). */
    public string $plano = self::PLANO;

    public string $email = '';

    public bool $consentimento = false;

    /** An email that already had an account: a sign-in link went to it and the page says so. */
    public ?string $enviadoPara = null;

    /** Honeypot: hidden from people, filled by bots. */
    public string $site = '';

    public string $nome = '';

    public string $tipologia = 'isolada';

    public ?int $piso = null;

    /** @var array<int,TemporaryUploadedFile> */
    public array $plantas = [];

    /** @var array<int,TemporaryUploadedFile> */
    public array $referencias = [];

    /** Optional: the listing or project link, and anything else about the home. */
    public string $anuncio = '';

    public string $notas = '';

    public ?string $aviso = null;

    public function mount(Stripe $stripe)
    {
        if (! Auth::check()) {
            session(['url.intended' => route('plantas')]);   // an existing account signs in and comes back here
        }
        // Back from Checkout: whichever of this and the webhook comes first records the payment and starts the property.
        $id = (string) request()->query('pago', '');
        if (Auth::check() && preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
            $sessao = rescue(fn () => $stripe->sessao($id), [], report: true);
            if (($sessao['client_reference_id'] ?? null) === (string) Auth::id()) {
                $stripe->cumprir($sessao);
                $p = Imoplanta::where('user_id', Auth::id())->find($sessao['metadata']['imoplanta'] ?? null);
                if ($p && $p->estado !== Imoplanta::A_PAGAR) {
                    return $this->redirectRoute('imoplanta.imovel', $p->id);
                }
                $this->aviso = 'Assim que o pagamento for confirmado, começamos a ler as plantas. Pode voltar a esta página mais tarde.';
            }
        }

        return null;
    }

    /** Step 1: the email, and straight on to the plans (see below for admin accounts). */
    public function continuar(LinkEntrada $links): void
    {
        if ($this->site !== '') {
            return;
        }
        $chave = 'plantas:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, config('contentmachine.leads.per_hour'))) {
            $this->addError('email', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }
        $this->email = Str::lower(trim($this->email));
        $this->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'consentimento' => 'accepted',
        ], [
            'email.required' => 'Indique o seu email.',
            'email.email' => 'Indique um email válido.',
            'consentimento.accepted' => 'Para criarmos o seu imóvel, precisamos do seu consentimento.',
        ]);
        RateLimiter::hit($chave, 3600);

        // Like the demo video: no email check. The email is the account — a new one is made, an existing one is
        // signed straight in (the owner's choice, knowing anyone who types an address gets into that account).
        // Admin accounts are the exception: they hold everything, so they still get the emailed link.
        $user = User::porEmail($this->email);   // its own address, or one it is shared with
        if ($user?->is_admin) {
            $links->enviar($user, '/plantas', $this->email);
            $this->enviadoPara = $this->email;

            return;
        }
        $user ??= User::create(['name' => Str::before($this->email, '@'), 'email' => $this->email, 'password' => Str::random(40)]);
        Auth::login($user, true);
        session()->regenerate();
    }

    public function outroEmail(): void
    {
        $this->reset('email', 'enviadoPara');
    }

    /** Step 2: the plans. With credit left it starts at once; otherwise they wait for the payment. */
    public function enviar(Imoplantas $imoplantas)
    {
        abort_unless(Auth::check(), 403);
        $this->nome = trim($this->nome);
        $this->anuncio = trim($this->anuncio);
        $this->notas = trim($this->notas);
        [$regras, $mensagens] = Lista::regras();
        $this->validate($regras + [
            'anuncio' => 'nullable|url:https,http|max:500',
            'notas' => 'nullable|string|max:2000',
        ], $mensagens + [
            'anuncio.*' => 'O link tem de começar por https:// (copie-o da barra de endereço).',
            'notas.max' => 'A descrição pode ter até 2000 caracteres.',
        ]);
        $user = Auth::user();
        $info = ['anuncio' => $this->anuncio, 'notas' => $this->notas];

        if ($user->is_admin || $user->imoveisImoplantaDisponiveis() > 0) {
            $p = $imoplantas->criar($user, $this->nome, $this->plantas, $this->tipologia, $this->piso, $this->referencias, $info);
            if ($p) {
                return $this->redirectRoute('imoplanta.imovel', $p->id);
            }
        }
        $imoplantas->guardar($user, $this->nome, $this->plantas, $this->tipologia, $this->piso, $this->referencias, $info);
        $this->reset('plantas', 'referencias');

        return null;
    }

    /** Step 3: to Stripe, for this property. */
    public function pagar(Stripe $stripe)
    {
        abort_unless(Auth::check(), 403);
        $p = $this->pendente();
        if (! $p) {
            return null;
        }
        if (Auth::user()->imoplantaGratis()) {   // 0 €: no Stripe, it starts now
            app(Imoplantas::class)->iniciar($p);

            return $this->redirectRoute('imoplanta.imovel', $p->id);
        }

        $plano = isset(config('contentmachine.imoplanta.planos')[$this->plano]) ? $this->plano : self::PLANO;

        return redirect()->away($stripe->checkout(Auth::user(), $plano, $p));
    }

    /** Back to step 2: the plans waiting for payment are deleted. */
    public function trocarPlantas(Imoplantas $imoplantas): void
    {
        if ($p = $this->pendente()) {
            $this->fill(['nome' => $p->nome, 'tipologia' => $p->tipologia, 'piso' => $p->piso, 'anuncio' => (string) $p->anuncio, 'notas' => (string) $p->notas]);
            $imoplantas->descartar($p);
        }
    }

    private function pendente(): ?Imoplanta
    {
        return Auth::check() ? Imoplanta::where('user_id', Auth::id())->where('estado', Imoplanta::A_PAGAR)->latest('id')->first() : null;
    }

    public function render()
    {
        $pendente = $this->pendente();

        return view('livewire.imoplanta.comecar', [
            'passo' => ! Auth::check() ? ($this->enviadoPara ? 'enviado' : 'conta') : ($pendente ? 'confirmar' : 'plantas'),
            'pendente' => $pendente,
            'gratis' => (bool) Auth::user()?->imoplantaGratis(),
            'planos' => config('contentmachine.imoplanta.planos'),
            'disponiveis' => Auth::check() && ! Auth::user()->is_admin && ! Auth::user()->imoplantaGratis() ? Auth::user()->imoveisImoplantaDisponiveis() : null,
            'imoveis' => Auth::check() ? Imoplanta::where('user_id', Auth::id())->where('estado', '!=', Imoplanta::A_PAGAR)->count() : 0,
        ]);
    }
}
