<?php

namespace App\Livewire;

use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The public site: one free video per agent. The form starts the same listing
 * import the studio uses, in the leads project (config contentmachine.leads),
 * and records the lead; `leads:enviar` emails the link once the reel is rendered.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · O vídeo do seu imóvel, grátis')]
class Landing extends Component
{
    public string $anuncio = '';

    public string $nome = '';

    public string $email = '';

    public string $telefone = '';

    public bool $consentimento = false;

    /** Honeypot: hidden from people, filled by bots. */
    public string $site = '';

    public bool $pedido = false;

    public function pedir(ListingImport $import, ProjectRepository $projects, ProjectActivator $activator, ProjectContext $context): void
    {
        if ($this->site !== '') {
            $this->pedido = true;

            return;
        }

        $chave = 'lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, config('contentmachine.leads.per_hour'))) {
            $this->addError('anuncio', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }

        $this->anuncio = trim($this->anuncio);
        $this->email = mb_strtolower(trim($this->email));
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500', fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista. Cole o link de um desses sites.')],
            'nome' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', fn ($a, $v, $fail) => DB::table('leads')->where('email', $v)->exists() ? $fail('Já fizemos um vídeo grátis para este email. Fale connosco para criar mais.') : null],
            'telefone' => ['required', 'regex:/^\+?[0-9 ()-]{9,20}$/'],
            'consentimento' => 'accepted',
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'nome.required' => 'Diga-nos o seu nome.',
            'email.required' => 'Precisamos do email para lhe enviar o vídeo.',
            'email.email' => 'Este email não parece válido.',
            'telefone.required' => 'Indique o seu telemóvel.',
            'telefone.regex' => 'Este número não parece válido.',
            'consentimento.accepted' => 'Para criarmos o vídeo, precisamos do seu consentimento.',
        ]);
        RateLimiter::hit($chave, 3600);

        $slug = config('contentmachine.leads.project');
        if ($slug !== '' && ($projeto = $projects->find($slug))) {
            $activator->activate($projeto);
        }
        $clip = $import->start($this->anuncio, ['lead' => true]);

        DB::table('leads')->insert([
            'nome' => trim($this->nome),
            'email' => $this->email,
            'telefone' => trim($this->telefone),
            'anuncio' => $this->anuncio,
            'projeto' => $context->current()->slug,
            'clip_id' => $clip->id,
            'ip' => (string) request()->ip(),
            'consentimento_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->pedido = true;
    }

    public function render()
    {
        return view('livewire.landing');
    }
}
