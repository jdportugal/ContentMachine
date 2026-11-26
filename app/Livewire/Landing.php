<?php

namespace App\Livewire;

use App\Services\Alertas\PaginaDoConsultor;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Leads\AvisoPronto;
use App\Services\Leads\Convites;
use App\Services\Leads\LeadVideo;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

    /** Photos sent instead of a link ("Carregar fotos"). */
    public const MAX_FOTOS = 30;

    /** Videos of the property sent with (or instead of) the photos: cut per room, like in the account. */
    public const MAX_VIDEOS = 3;

    /**
     * The only box on the form, optional and never pre-ticked: new-listing alerts and
     * news by email (RGPD / Lei 41/2004). Stored word for word with each consent as
     * proof of what was agreed to, so change the wording here, never in the view.
     */
    public const MARKETING = 'Avisem-me por email quando eu publicar um imóvel novo, para fazer o vídeo dele (a Imocena consulta os meus anúncios públicos), e enviem-me novidades. Cancelo quando quiser, com um clique.';

    /**
     * The agencies the page asks about first. `lemos`: we read its listings (a key of
     * ListingScraper::PORTAIS, so the free video comes out in '<key>-premium'); the
     * others can leave an email to be told when we do. `site` is where the link comes from.
     */
    public const AGENCIAS = [
        'remax' => ['nome' => 'RE/MAX', 'da' => 'da RE/MAX', 'site' => 'remax.pt', 'lemos' => true],
        'iad' => ['nome' => 'iad Portugal', 'da' => 'da iad', 'site' => 'iadportugal.pt', 'lemos' => true],
        'exp' => ['nome' => 'eXp Portugal', 'da' => 'da eXp', 'site' => 'expportugal.com', 'lemos' => true],
        'kw' => ['nome' => 'KW Portugal', 'da' => 'da KW', 'site' => 'kwportugal.pt', 'lemos' => true],
        'era' => ['nome' => 'ERA', 'da' => 'da ERA', 'site' => 'era.pt', 'lemos' => false],
        'century21' => ['nome' => 'Century 21', 'da' => 'da Century 21', 'site' => 'century21.pt', 'lemos' => true],
        'predimed' => ['nome' => 'Predimed', 'da' => 'da Predimed', 'site' => 'predimed.pt', 'lemos' => true],
        'zome' => ['nome' => 'Zome', 'da' => 'da Zome', 'site' => 'zome.pt', 'lemos' => false],
        // "Mais agências": a second screen, behind a tile on the first.
        'bussolahaus' => ['nome' => 'Bússola Haus', 'da' => 'da Bússola Haus', 'site' => 'bussolatchaus.com', 'lemos' => true, 'mais' => true],
        'homewize' => ['nome' => 'Homewize', 'da' => 'da Homewize', 'site' => 'homewize.pt', 'lemos' => false, 'mais' => true],
        'mybesthouses' => ['nome' => 'My Best Houses', 'da' => 'da My Best Houses', 'site' => 'mybesthouses.com', 'lemos' => true, 'mais' => true],
        'lisrenda' => ['nome' => 'Lisrenda', 'da' => 'da Lisrenda', 'site' => 'lisrenda.pt', 'lemos' => false, 'mais' => true],
        'outra' => ['nome' => 'Outra agência', 'da' => 'da sua agência', 'site' => '', 'lemos' => false, 'mais' => true],
    ];

    /** The visitor's agency (a key of AGENCIAS), asked before anything else; '' until they pick. */
    public string $agencia = '';

    /** The email left to get the link later, or to be told when the agency is supported. */
    public string $contacto = '';

    /** "Outra agência": its name, as typed (optional). */
    public string $nomeAgencia = '';

    /** What the visitor was just told after leaving their email: 'lembrete' | 'aviso' | ''. */
    public string $enviado = '';

    public string $anuncio = '';

    /** Kept for old callers; the form now shows a notice under the button instead of a box (delivering the requested video is RGPD art. 6.º, n.º 1, b)). */
    public bool $consentimento = false;

    /** Optional and never pre-ticked: the free video doesn't depend on it. */
    public bool $marketing = false;

    public string $email = '';

    /** Optional: where to send "o seu vídeo está pronto" (a service email, not marketing). */
    public string $emailVideo = '';

    /** Honeypot: hidden from people, filled by bots. */
    public string $site = '';

    #[\Livewire\Attributes\Locked]
    public ?int $leadId = null;

    /** This listing's agent already had their free video: the card suggests a paid one instead of the form. */
    public bool $jaTeve = false;

    public bool $bot = false;

    /** "Convide um colega" (Convites): the agent's name and email, then a colleague's email to send the invite to. */
    public string $meuNome = '';

    public string $meuEmail = '';

    public string $emailColega = '';

    /** The colleague the invite email just went to ('' when none). */
    public string $conviteEnviado = '';

    /**
     * Step 2, one question at a time: '' = "Qual é o imóvel?" (the four answers), then
     * 'link' (paste it), 'nome' (find it by their name, RE/MAX only), 'fotos' (upload) or 'depois' (email me).
     */
    public string $modo = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $fotos = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $videos = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $novasFotos = [];

    /** One line with the photos ("T2 em Alvalade, 320 000 €"): what the narration says. */
    public string $sobreFotos = '';

    /** A video from uploaded photos has no contact at the end: asked for with the clean version. */
    public string $nomeContacto = '';

    public string $telefoneContacto = '';

    public $fotoContacto = null;

    public bool $pedirContacto = false;

    public string $nomeConsultor = '';

    /** @var list<array{id: string, nome: string, agencia: string}> */
    public array $consultores = [];

    /** The agent picked from the search (their RE/MAX number), and their newest listings. */
    public string $consultorEscolhido = '';

    /** @var list<array{id: string, titulo: string, url: ?string, foto: ?string, preco: ?int}> */
    public array $imoveis = [];

    /** The listing picked from their list, shown above the button. */
    public string $imovelEscolhido = '';

    /** 'sem-resultados' | 'erro' | '' after a search or a listing read. */
    public string $avisoProcura = '';

    public function mount(Convites $convites): void
    {
        // "Fazer vídeo grátis" on the home page (?escolher=1): always the question, whatever was picked or asked before.
        if (request()->query('escolher')) {
            session()->forget(['agencia', 'lead_id']);
        }
        $this->leadId = session('lead_id');
        // Arrived by a colleague's invite link: remember whose, for the video they ask for.
        if (($codigo = request()->query('convite')) && ($quem = $convites->convidante((string) $codigo))) {
            if (session('convite') !== $quem->convite) {
                session(['convite' => $quem->convite]);
                Convites::evento('convite-visita', $quem, ['nota' => 'convite '.$quem->convite]);
            }
            if (! request()->query('agencia') && ! session('agencia') && isset(self::AGENCIAS[(string) $quem->agencia])) {
                session(['agencia' => $quem->agencia]);
            }
        }
        // ?agencia=remax (an ad or our own email can skip the question), else what they picked before.
        $agencia = (string) request()->query('agencia', session('agencia', ''));
        $this->agencia = isset(self::AGENCIAS[$agencia]) ? $agencia : '';
        // Someone whose video is under way (or done) came by a link: their agency is the listing's.
        if ($this->agencia === '' && $this->leadId && ($lead = DB::table('leads')->find($this->leadId))) {
            $this->agencia = isset(self::AGENCIAS[$portal = (string) ($lead->agencia ?? ListingScraper::portal((string) $lead->anuncio))]) ? $portal : '';
        }
        // A link from a new-listing alert arrives with the listing already filled in.
        $anuncio = ListingScraper::limpar((string) request()->query('anuncio', ''));
        if ($anuncio !== '' && mb_strlen($anuncio) <= 500 && ListingScraper::supports($anuncio)) {
            $this->anuncio = $anuncio;
            $this->modo = 'link';
            $this->agencia = isset(self::AGENCIAS[$portal = (string) ListingScraper::portal($anuncio)]) ? $portal : $this->agencia;
            $this->jaTeve = self::jaTeveVideo($this->agente()['chave'] ?? null, $anuncio);
        }
    }

    public function pedir(ListingImport $import, ProjectContext $context): void
    {
        $this->modo = 'link';   // whatever went wrong is shown by the link box
        if ($this->site !== '') {
            $this->bot = true;

            return;
        }

        $chave = 'lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, config('contentmachine.leads.per_hour'))) {
            $this->addError('anuncio', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }

        // A link pasted in the wrong box (the email one) still counts as the link.
        if (trim($this->anuncio) === '' && self::pareceLink($this->emailVideo)) {
            [$this->anuncio, $this->emailVideo] = [trim($this->emailVideo), ''];
        }
        if (trim($this->anuncio) === '') {
            // Most people tap the coral button first: send them to the box, ready to paste.
            $this->addError('anuncio', 'Primeiro cole aqui o link do anúncio ↑');
            $this->dispatch('focar-link');

            return;
        }
        $this->anuncio = ListingScraper::limpar(ListingScraper::abrirPartilha($this->anuncio));
        $this->email = trim($this->email);
        $this->emailVideo = mb_strtolower(trim($this->emailVideo));
        if ($this->marketing && $this->email === '' && $this->emailVideo !== '') {
            $this->email = $this->emailVideo;   // one address typed, both uses ticked
        }
        // Who the agent is on the portal, and (for the alerts) the email they publish there:
        // nothing to type when the listing has it.
        $agente = ListingScraper::supports($this->anuncio) ? $this->agente() : null;
        // A link from a site we do not read: kept (no personal data), so we know which sites people try.
        if (filter_var($this->anuncio, FILTER_VALIDATE_URL) && ! ListingScraper::supports($this->anuncio)) {
            $this->registar('link-recusado', ['anuncio' => mb_substr($this->anuncio, 0, 500)]);
        }
        // An agent's own page or a search instead of one property (see ListingScraper::naoEImovel).
        if (ListingScraper::naoEImovel($this->anuncio)) {
            $this->registar('link-nao-imovel', ['anuncio' => mb_substr($this->anuncio, 0, 500)]);
        }
        if ($this->marketing && $this->email === '' && $agente) {
            $this->email = (string) rescue(fn () => app(PaginaDoConsultor::class)->email($agente, 8), '', report: false);
        }
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail(ListingScraper::MENSAGEM),
                fn ($a, $v, $fail) => ListingScraper::naoEImovel($v) ? $fail(ListingScraper::NAO_E_IMOVEL) : null],
            'email' => ['exclude_unless:marketing,true', 'required', 'email:rfc', 'max:255'],
            'emailVideo' => ['nullable', 'email:rfc', 'max:255'],
        ], [
            'emailVideo.email' => 'Este email não parece estar certo.',
            'anuncio.required' => 'Primeiro cole aqui o link do anúncio ↑',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'email.required' => 'Não encontrámos o seu email no anúncio. Escreva-o aqui para receber os avisos.',
        ]);
        if ($this->agencia === '' && isset(self::AGENCIAS[$portal = (string) ListingScraper::portal($this->anuncio)])) {
            $this->agencia = $portal;   // a link pasted without answering the question answers it
        }
        // The free video is one per agent: someone we already made a video for is shown how to get this one.
        if (self::jaTeveVideo($agente['chave'] ?? null, $this->anuncio)) {
            $this->jaTeve = true;

            return;
        }
        RateLimiter::hit($chave, 3600);

        // Free videos use the portal's premium theme (iad-premium, remax-premium…),
        // falling back to the default; never whatever the studio happens to be on.
        $clip = $import->start($this->anuncio, ['free_lead' => true, 'theme' => app(ThemeRepository::class)->forPortal(ListingScraper::portal($this->anuncio))->slug]);

        $dados = [
            'projeto' => $context->current()->slug,
            'clip_id' => $clip->id,
            'ip' => (string) request()->ip(),
            'consentimento_em' => now(),
            'email' => $this->marketing ? $this->email : null,
            'marketing_em' => $this->marketing ? now() : null,
            'marketing_texto' => $this->marketing ? self::MARKETING : null,
            'origem' => \App\Services\Marketing\Origem::json(),
            'consultor' => $agente['chave'] ?? null,
            // The listing's own portal says it best; the answer to the question otherwise (a demo listing).
            'agencia' => isset(self::AGENCIAS[$portal = (string) ListingScraper::portal($this->anuncio)]) ? $portal : ($this->agencia ?: null),
            'convidado_por' => $this->convidadoPor($agente['chave'] ?? null),
            'email_video' => $this->emailVideo !== '' ? $this->emailVideo : null,
            'convite_email' => $this->emailVideo !== '' ? $this->emailVideo : null,
            'updated_at' => now(),
        ];
        // The same listing may be asked for again (a new free video each time). A retry after a
        // failed attempt still reuses that row (failure cleared, new clip attached).
        $falhado = DB::table('leads')->where('anuncio', $this->anuncio)->whereNotNull('falhou_em')->whereNull('user_id')->latest('id')->first();
        if ($falhado) {
            DB::table('leads')->where('id', $falhado->id)->update($dados + ['falhou_em' => null, 'pronto_em' => null]);
            $this->leadId = $falhado->id;
            // A retry of our own failure is not the visitor's doing: give the attempt back.
            RateLimiter::decrement($chave);
        } else {
            $this->leadId = DB::table('leads')->insertGetId($dados + ['anuncio' => $this->anuncio, 'created_at' => now()]);
        }
        session(['lead_id' => $this->leadId]);
        $this->dispatch('lead-criado', id: $this->leadId);
        // The same Lead from the server (with the browser's event id, so Meta counts it once).
        app(\App\Services\Marketing\MetaConversoes::class)->enviar('Lead', 'lead-'.$this->leadId, [], $this->marketing ? $this->email : null, \App\Services\Marketing\Origem::atual());
    }

    /** The lead whose invite brought this visitor (kept in the session), unless it is the visitor's own. */
    private function convidadoPor(?string $consultor): ?int
    {
        $quem = app(Convites::class)->convidante(session('convite'));
        if (! $quem || ($consultor !== null && $quem->consultor === $consultor) || $quem->anuncio === $this->anuncio) {
            return null;
        }

        return (int) $quem->id;
    }

    /**
     * Step 1 of "convide um colega": where to send the clean video, and the name the
     * colleague will see. Then the link to share appears (WhatsApp, copy, or our email).
     */
    public function ativarConvite(): void
    {
        $lead = $this->leadId ? DB::table('leads')->find($this->leadId) : null;
        if (! $lead || ! Convites::porDesbloquear($lead) || $this->site !== '') {
            return;
        }
        $this->meuNome = trim(preg_replace('/\s+/', ' ', $this->meuNome));
        $this->meuEmail = trim($this->meuEmail);
        $this->validate([
            'meuNome' => ['required', 'string', 'min:2', 'max:60', 'not_regex:/(https?:|www\.|@|\/|\\\\)/i'],
            'meuEmail' => ['required', 'email:rfc', 'max:255'],
        ], [
            'meuNome.required' => 'Escreva o seu nome: é o que o seu colega vê no convite.',
            'meuNome.not_regex' => 'Escreva só o seu nome.',
            'meuEmail.required' => 'Escreva o seu email: é para lá que enviamos a versão limpa.',
            'meuEmail.email' => 'Este email não parece estar certo.',
        ]);
        DB::table('leads')->where('id', $lead->id)->update([
            'convite_email' => $this->meuEmail,
            'nome' => $lead->nome ?: $this->meuNome,
            'updated_at' => now(),
        ]);
        session(['convite_nome' => $this->meuNome]);
        app(Convites::class)->codigo($lead);
        Convites::evento('convite-ativado', $lead, ['email' => $this->meuEmail]);
    }

    /** The agent shared the link (WhatsApp, or copied it): counted, nothing else. */
    public function partilhou(string $como): void
    {
        $lead = $this->leadId ? DB::table('leads')->find($this->leadId) : null;
        if ($lead && in_array($como, ['whatsapp', 'copiado'], true)) {
            Convites::evento('convite-'.$como, $lead);
        }
    }

    /** Step 2, optional: we email the invite to a colleague, in the agent's name. */
    public function convidar(): void
    {
        $lead = $this->leadId ? DB::table('leads')->find($this->leadId) : null;
        if (! $lead || empty($lead->convite) || $this->site !== '') {
            return;
        }
        $chave = 'convite:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, 5) || DB::table('convites_enviados')->where('lead_id', $lead->id)->count() >= Convites::MAX_EMAILS) {
            $this->addError('emailColega', 'Já enviámos muitos convites seus. Partilhe o link por WhatsApp.');

            return;
        }
        $this->emailColega = mb_strtolower(trim($this->emailColega));
        $this->validate(['emailColega' => ['required', 'email:rfc', 'max:255',
            fn ($a, $v, $fail) => $v === mb_strtolower((string) $lead->convite_email) ? $fail('Este é o seu email. Escreva o do seu colega.') : null,
            fn ($a, $v, $fail) => DB::table('convites_enviados')->where('lead_id', $lead->id)->where('email', $v)->exists() ? $fail('Já enviámos o convite a este colega.') : null,
        ]], ['emailColega.required' => 'Escreva o email do seu colega.', 'emailColega.email' => 'Este email não parece estar certo.']);
        RateLimiter::hit($chave, 3600);

        try {
            app(Convites::class)->enviarEmail($lead, $this->emailColega, (string) (session('convite_nome') ?: $lead->nome ?: 'Um colega'));
        } catch (\Throwable $e) {
            Log::warning('Convite não enviado', ['lead' => $lead->id, 'erro' => $e->getMessage()]);
            $this->addError('emailColega', 'Não conseguimos enviar o email agora. Tente outra vez daqui a pouco.');

            return;
        }
        Convites::evento('convite-email', $lead);
        $this->conviteEnviado = $this->emailColega;
        $this->reset('emailColega');
    }

    /** The first answer: the visitor's agency. The page that follows is theirs (its name, its template's video). */
    public function escolher(string $agencia): void
    {
        if (! isset(self::AGENCIAS[$agencia])) {
            return;
        }
        $this->agencia = $agencia;
        $this->reset('enviado', 'contacto', 'nomeAgencia');
        $this->resetErrorBag();
        session(['agencia' => $agencia]);
        $this->registar('escolha');
        $this->dispatch('agencia-escolhida', agencia: $agencia);
    }

    /** RE/MAX: switch between pasting the link and finding the listing by name. */
    public function mudarModo(string $modo): void
    {
        $this->modo = match (true) {
            $modo === 'nome' && \App\Services\RealEstate\ConsultoresPorNome::para($this->agencia) => 'nome',
            in_array($modo, ['link', 'fotos', 'depois', ''], true) => $modo,
            default => 'link',
        };
        $this->resetErrorBag();
        $this->avisoProcura = '';
    }

    /** The agency's own agent search (RE/MAX, iad, Century 21: ConsultoresPorNome), by the name typed. */
    public function procurarConsultor(): void
    {
        $rede = \App\Services\RealEstate\ConsultoresPorNome::para($this->agencia);
        if (! $rede) {
            return;
        }
        // A listing link pasted in the name box: use it as the link.
        if (self::pareceLink($this->nomeConsultor)) {
            [$this->anuncio, $this->nomeConsultor, $this->modo] = [trim($this->nomeConsultor), '', 'link'];
            $this->resetErrorBag();
            app()->call([$this, 'pedir']);

            return;
        }
        $this->validate(['nomeConsultor' => 'required|string|min:3|max:80'], [
            'nomeConsultor.required' => 'Escreva o seu nome.',
            'nomeConsultor.min' => 'Escreva pelo menos o primeiro e o último nome.',
        ]);
        $this->reset('consultores', 'consultorEscolhido', 'imoveis', 'avisoProcura');
        if (! RateLimiter::attempt('procura-nome:'.request()->ip(), 30, fn () => true, 3600)) {
            $this->avisoProcura = 'erro';

            return;
        }
        try {
            $this->consultores = $rede->procurar($this->nomeConsultor);
        } catch (\Throwable $e) {
            report($e);
            $this->avisoProcura = 'erro';

            return;
        }
        $this->avisoProcura = $this->consultores === [] ? 'sem-resultados' : '';
        $this->registar('procura-nome', ['nota' => count($this->consultores).' resultados']);
    }

    /** The agent picked themselves: their newest listings. */
    public function escolherConsultor(string $id): void
    {
        $rede = \App\Services\RealEstate\ConsultoresPorNome::para($this->agencia);
        if (! $rede || ! in_array($id, array_column($this->consultores, 'id'), true)) {
            return;
        }
        $this->consultorEscolhido = $id;
        $this->avisoProcura = '';
        try {
            $this->imoveis = $rede->imoveis($id);
        } catch (\Throwable $e) {
            report($e);
            $this->imoveis = [];
            $this->avisoProcura = 'erro';

            return;
        }
        $this->registar('escolha-consultor', ['nota' => $id.' · '.count($this->imoveis).' imóveis']);
    }

    /** A listing picked from the agent's list: it becomes the link, ready for "Criar vídeo grátis". */
    public function escolherImovel(string $ref): void
    {
        foreach ($this->imoveis as $i) {
            if ($i['id'] === $ref && ! empty($i['url'])) {
                $this->anuncio = $i['url'];
                $this->imovelEscolhido = $i['titulo'].(! empty($i['preco']) ? ' · '.number_format((int) $i['preco'], 0, ',', ' ').' €' : '');
                $this->modo = 'link';
                $this->resetErrorBag();
                $this->registar('escolha-imovel', ['anuncio' => $i['url']]);
                app()->call([$this, 'pedir']);   // one tap: the listing is the answer

                return;
            }
        }
    }

    /** Back to the question. */
    public function trocar(): void
    {
        session()->forget('agencia');
        $this->reset('agencia', 'enviado', 'contacto', 'nomeAgencia', 'anuncio', 'jaTeve', 'modo', 'nomeConsultor', 'consultores', 'consultorEscolhido', 'imoveis', 'imovelEscolhido', 'avisoProcura');
        $this->resetErrorBag();
    }

    /**
     * An email instead of a link. An agency we read: the visitor has no link at hand, so we
     * send them this page once, to do it later. One we do not read yet: we keep the email
     * to tell them when we do. Asked for by them, used for that only.
     */
    public function lembrar(): void
    {
        if ($this->site !== '' || ! isset(self::AGENCIAS[$this->agencia])) {
            $this->enviado = (self::AGENCIAS[$this->agencia]['lemos'] ?? true) ? 'lembrete' : 'aviso';   // a bot is told it worked

            return;
        }
        $chave = 'lembrete:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $this->addError('contacto', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }
        $this->contacto = trim($this->contacto);
        $this->validate(['contacto' => ['required', 'email:rfc', 'max:255'], 'nomeAgencia' => ['nullable', 'string', 'max:120']],
            ['contacto.required' => 'Escreva o seu email.', 'contacto.email' => 'Este email não parece estar certo.']);
        RateLimiter::hit($chave, 3600);

        $a = self::AGENCIAS[$this->agencia];
        $this->registar($a['lemos'] ? 'lembrete' : 'aviso-agencia', ['email' => $this->contacto, 'nota' => trim($this->nomeAgencia) ?: null]);
        if ($a['lemos']) {
            $link = rtrim((string) config('app.url'), '/').'/video-gratis?agencia='.$this->agencia.'&utm_source=imocena&utm_medium=email&utm_campaign=lembrete#pedido';
            try {
                Mail::html(view('emails.lembrete', ['agencia' => $a['nome'], 'link' => $link])->render(),
                    fn ($m) => $m->to($this->contacto)->subject('O link para fazer o vídeo grátis do seu imóvel'));
            } catch (\Throwable $e) {
                Log::warning('Lembrete não enviado', ['erro' => $e->getMessage()]);
                $this->addError('contacto', 'Não conseguimos enviar o email agora. Tente outra vez daqui a pouco.');

                return;
            }
        }
        $this->enviado = $a['lemos'] ? 'lembrete' : 'aviso';
        $this->reset('contacto', 'nomeAgencia');
    }

    /** One row of what happened on the page; never in the way of the visitor. */
    private function registar(string $tipo, array $dados = []): void
    {
        rescue(fn () => DB::table('landing_eventos')->insert($dados + [
            'tipo' => $tipo,
            'agencia' => $this->agencia !== '' ? $this->agencia : null,
            'origem' => \App\Services\Marketing\Origem::json(),
            'created_at' => now(),
        ]), report: false);
    }

    /** The demo reel in the agency's own template when we have rendered one (imocena:demo-reels), else the general one. */
    public static function demoReel(string $agencia, string $slug = 'moradia-lajeosa'): array
    {
        foreach (array_filter([$agencia !== '' ? "reel-{$agencia}.mp4" : null, 'reel.mp4']) as $ficheiro) {
            if (is_file(public_path("media/demo/{$slug}/{$ficheiro}"))) {
                return ['url' => asset("media/demo/{$slug}/{$ficheiro}"), 'proprio' => $ficheiro !== 'reel.mp4'];
            }
        }

        return ['url' => null, 'proprio' => false];
    }

    /** The agent's page on the portal for the listing in the form, or null (unknown, or the portal did not answer). */
    private function agente(): ?array
    {
        return rescue(fn () => app(PaginaDoConsultor::class)->de($this->anuncio, 8), null, report: false);
    }

    /** Has this agent a video (free or paid, not failed) of another listing? */
    public static function jaTeveVideo(?string $consultor, string $anuncio): bool
    {
        return $consultor !== null && DB::table('leads')->where('consultor', $consultor)->whereNull('falhou_em')->where('anuncio', '!=', $anuncio)->exists();
    }

    /** Ask for another listing's video (the session forgets the current one). */
    public function outro(): void
    {
        session()->forget('lead_id');
        $this->reset('anuncio', 'consentimento', 'marketing', 'email', 'emailVideo', 'leadId', 'jaTeve', 'enviado', 'modo', 'fotos', 'videos', 'sobreFotos', 'imovelEscolhido', 'pedirContacto');
    }

    /** The current lead's reel, as the hero card shows it. */
    /** Photos chosen (added to those already there, up to MAX_FOTOS). */
    public function updatedNovasFotos(): void
    {
        $this->validate(['novasFotos.*' => 'file|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime|max:512000'], [
            'novasFotos.*.mimetypes' => 'Só fotos (JPG, PNG, WebP) e vídeos (MP4 ou MOV).',
            'novasFotos.*.max' => 'Cada vídeo tem de ter menos de 500 MB.',
        ]);
        foreach ($this->novasFotos as $f) {
            if (str_starts_with((string) $f->getMimeType(), 'video/')) {
                if (count($this->videos) < self::MAX_VIDEOS) {
                    $this->videos[] = $f;
                }
            } elseif ($f->getSize() <= 15360 * 1024) {
                $this->fotos[] = $f;
            } else {
                $this->addError('novasFotos', 'Cada foto tem de ter menos de 15 MB.');
            }
        }
        $this->fotos = array_slice($this->fotos, 0, self::MAX_FOTOS);
        $this->novasFotos = [];
    }

    public function tirarVideo(int $i): void
    {
        unset($this->videos[$i]);
        $this->videos = array_values($this->videos);
    }

    public function tirarFoto(int $i): void
    {
        unset($this->fotos[$i]);
        $this->fotos = array_values($this->fotos);
    }

    /** "Carregar fotos": a free video from the agent's own photos (no listing, so no contact at the end yet). */
    public function criarComFotos(ListingImport $import, ProjectContext $context): void
    {
        if ($this->site !== '') {
            $this->bot = true;

            return;
        }
        if (count($this->fotos) < 3 && $this->videos === []) {
            $this->addError('fotos', 'Escolha pelo menos 3 fotos ou um vídeo.');

            return;
        }
        $this->sobreFotos = trim(preg_replace('/\s+/', ' ', $this->sobreFotos));
        $this->validate(['sobreFotos' => ['required', 'string', 'min:4', 'max:300']], [
            'sobreFotos.required' => 'Diga-nos o que é e onde fica.', 'sobreFotos.min' => 'Diga-nos o que é e onde fica.']);
        $preco = preg_match('/(\d{1,3}(?:[ .]\d{3})+|\d{4,})\s*(?:€|eur)/iu', $this->sobreFotos, $m) ? (int) preg_replace('/\D/', '', $m[1]) : null;
        $chave = 'lead:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, config('contentmachine.leads.per_hour'))) {
            $this->addError('fotos', 'Recebemos muitos pedidos deste endereço. Tente novamente daqui a uma hora.');

            return;
        }
        RateLimiter::hit($chave, 3600);
        $disco = (string) config('contentmachine.clips.disk');
        $caminhos = array_map(fn ($f) => $f->store('clips/uploads', $disco), $this->fotos);
        $filmes = array_map(fn ($f) => $f->store('clips/uploads', $disco), $this->videos);
        $anuncio = \App\Services\Contas\NovoVideo::anuncioCarregado();
        $portal = ListingScraper::portal('https://'.(self::AGENCIAS[$this->agencia]['site'] ?? '').'/') ?? $this->agencia;
        $clip = $import->start($anuncio, ['free_lead' => true,
            'listing_manual' => ['description' => $this->sobreFotos, 'price' => $preco, 'photos' => $caminhos, 'agent' => [], 'agency' => (string) (self::AGENCIAS[$this->agencia]['nome'] ?? '')],
            'theme' => app(ThemeRepository::class)->forPortal((string) $portal)->slug]
            // The videos are cut per room; with photos too, both are mixed (as in NovoVideoPagina).
            + array_filter(['listing_video' => $filmes[0] ?? null, 'videos_carregados' => array_slice($filmes, 1), 'mix_photos' => $filmes !== [] && $caminhos !== []]));
        $this->leadId = DB::table('leads')->insertGetId([
            'anuncio' => $anuncio, 'projeto' => $context->current()->slug, 'clip_id' => $clip->id, 'ip' => (string) request()->ip(),
            'consentimento_em' => now(), 'origem' => \App\Services\Marketing\Origem::json(), 'agencia' => $this->agencia ?: null,
            'convidado_por' => $this->convidadoPor(null), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reset('fotos', 'videos', 'sobreFotos');
        session(['lead_id' => $this->leadId]);
        $this->registar('fotos-carregadas', ['nota' => count($caminhos).' fotos'.($filmes ? ', '.count($filmes).' vídeos' : '')]);
        $this->dispatch('lead-criado', id: $this->leadId);
        app(\App\Services\Marketing\MetaConversoes::class)->enviar('Lead', 'lead-'.$this->leadId, [], null, \App\Services\Marketing\Origem::atual());
    }

    /**
     * A photos video and "Adicionar o meu contacto": the video is made again with the agent's
     * name and phone at the end, then the clean version is paid for (the payment cleans this new one).
     */
    public function guardarContacto(ListingImport $import)
    {
        $lead = $this->leadId ? DB::table('leads')->find($this->leadId) : null;
        $clip = $lead?->clip_id ? app(\App\Services\Clips\Store\ClipStore::class)->find($lead->clip_id) : null;
        $meta = (array) ($clip?->meta ?? []);
        if (! $lead || empty($meta['listing_manual'])) {
            return null;
        }
        $this->nomeContacto = trim(preg_replace('/\s+/', ' ', $this->nomeContacto));
        $this->telefoneContacto = trim($this->telefoneContacto);
        $this->validate([
            'nomeContacto' => ['required', 'string', 'min:2', 'max:60'],
            'telefoneContacto' => ['required', 'string', 'max:30', 'regex:/^[+\d][\d\s().-]{7,}$/'],
            'fotoContacto' => ['nullable', 'image', 'max:15360'],
        ], [
            'nomeContacto.required' => 'Escreva o seu nome.',
            'telefoneContacto.required' => 'Escreva o seu telefone.',
            'telefoneContacto.regex' => 'Este telefone não parece estar certo.',
            'fotoContacto.image' => 'A foto tem de ser uma imagem.',
        ]);
        $manual = (array) $meta['listing_manual'];
        $manual['agent'] = ['name' => $this->nomeContacto, 'phone' => $this->telefoneContacto];
        $opcoes = array_filter(['free_lead' => true, 'listing_manual' => $manual, 'theme' => $meta['theme'] ?? null,
            'listing_video' => $meta['listing_video'] ?? null, 'videos_carregados' => $meta['videos_carregados'] ?? null, 'mix_photos' => $meta['mix_photos'] ?? null,
            'agent_photo' => $this->fotoContacto?->store('clips/uploads', (string) config('contentmachine.clips.disk'))]);
        $novo = $import->start($lead->anuncio, $opcoes);
        DB::table('leads')->where('id', $lead->id)->update(['clip_id' => $novo->id, 'nome' => $this->nomeContacto, 'telefone' => $this->telefoneContacto,
            'pronto_em' => null, 'updated_at' => now()]);
        $this->pedirContacto = false;
        $link = (string) config('contentmachine.stripe.links.primeiro');
        if ($link === '') {
            return null;
        }

        return redirect()->away($link.'?'.http_build_query(['client_reference_id' => 'lead-'.$lead->id, 'locale' => 'pt']));
    }

    /** Something that looks like a web address rather than a name or an email. */
    public static function pareceLink(string $texto): bool
    {
        $t = trim($texto);

        return $t !== '' && ! str_contains($t, ' ') && ! str_contains($t, '@')
            && (bool) preg_match('#^(https?://|www\.)|\.(pt|com|net|google)(/|$)#i', $t);
    }

    /** "Enviamos-lhe o vídeo": the email asked for while the video is being made. */
    public function guardarEmailVideo(): void
    {
        $this->emailVideo = mb_strtolower(trim($this->emailVideo));
        $this->validate(['emailVideo' => ['required', 'email:rfc', 'max:255']], [
            'emailVideo.required' => 'Escreva o seu email.',
            'emailVideo.email' => 'Este email não parece estar certo.',
        ]);
        if ($this->leadId) {
            $lead = DB::table('leads')->where('id', $this->leadId)->first(['convite_email']);
            // The one optional box sits here now: ticked, the same email gets the new-listing alerts.
            $alertas = $this->marketing ? ['email' => $this->emailVideo, 'marketing_em' => now(), 'marketing_texto' => self::MARKETING] : [];
            DB::table('leads')->where('id', $this->leadId)->update($alertas + ['email_video' => $this->emailVideo,
                'convite_email' => $lead?->convite_email ?: $this->emailVideo, 'updated_at' => now()]);
        }
    }

    private function pedido(LeadVideo $videos): ?array
    {
        $lead = $this->leadId ? $videos->lead($this->leadId) : null;
        if (! $lead) {
            return null;
        }
        $clip = $videos->clip($lead);
        if ($videos->ficheiro($lead)) {
            $limpo = (! empty($lead->pago_em) || ! empty($lead->oferta_em)) && empty($clip?->meta['free_lead']);

            $manual = (array) ($clip?->meta['listing_manual'] ?? []);

            return ['estado' => 'pronto', 'pago' => $limpo, 'oferta' => $limpo && empty($lead->pago_em),
                'semContacto' => $manual !== [] && trim((string) ($manual['agent']['phone'] ?? '')) === '',
                'video' => URL::signedRoute('lead.video', ['lead' => $lead->id]),
                'descarregar' => URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]),
                // "Convide um colega": only while there is a watermark to take off.
                'convite' => Convites::porDesbloquear($lead) ? [
                    'ativo' => ! empty($lead->convite) && ! empty($lead->convite_email),
                    'email' => $lead->convite_email,
                    'link' => ! empty($lead->convite) ? app(Convites::class)->link($lead, 'link') : null,
                    'whatsapp' => ! empty($lead->convite) ? 'https://wa.me/?text='.rawurlencode('Fiz o vídeo de um imóvel meu na Imocena em minutos, com narração e o meu contacto no fim. O primeiro é grátis, experimenta: '.app(Convites::class)->link($lead, 'whatsapp')) : null,
                ] : null];
        }
        if ($lead->falhou_em) {
            return ['estado' => 'falhou'];
        }
        // A failed clip is made again automatically once (RecuperarVideo) before it counts as failed.
        if (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
            return ['estado' => 'a-fazer', 'etapa' => 'A tentar outra vez', 'pct' => 10];
        }
        [$etapa, $pct] = Imoveis::ESTADOS[$clip->status] ?? ['A preparar', 10];

        return ['estado' => 'a-fazer', 'etapa' => $etapa, 'pct' => $pct, 'email' => AvisoPronto::destino($lead)];
    }

    public function render(LeadVideo $videos)
    {
        // Arrived by an invite and not yet asked for a video: whose invite it is, for the offer bar.
        $quem = $this->leadId === null ? app(Convites::class)->convidante(session('convite')) : null;
        $convidadoPor = $quem ? trim((string) ($quem->nome ?: '')) : null;

        return view('livewire.landing', ['convidadoPor' => $quem ? ($convidadoPor !== '' ? $convidadoPor : 'Um colega') : null, 'ag' => self::AGENCIAS[$this->agencia] ?? null, 'pedido' => $this->bot ? ['estado' => 'a-fazer', 'etapa' => 'A ler o anúncio', 'pct' => 15] : $this->pedido($videos)]);
    }
}
