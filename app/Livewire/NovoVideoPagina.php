<?php

namespace App\Livewire;

use App\Services\Contas\NovoVideo;
use App\Services\Contas\PerfilDoConsultor;
use App\Services\RealEstate\ListingScraper;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Novo vídeo": a listing link, plus what the customer wants in it before it is made —
 * photos that are not in the listing (dragged in), their own voiceover text, the music.
 * Or no link at all: their own photos and videos, the description and the agent's
 * details (prefilled from the account or their last video).
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · Novo vídeo')]
class NovoVideoPagina extends Component
{
    use WithFileUploads;

    public const MAX_FOTOS = 15;

    /** Without a link: the whole gallery is theirs. */
    public const MAX_FOTOS_SEM_LINK = 30;

    public const MAX_VIDEOS = 5;

    /** 'link' (a listing we read) or 'ficheiros' (their own photos, videos and description). */
    public string $modo = 'link';

    public string $anuncio = '';

    /** @var array<int,mixed> without a link: the videos added so far */
    public array $videos = [];

    public string $descricao = '';

    public string $preco = '';

    /** The agent on the end card (without a link), prefilled from the account or their last video. */
    public string $nome = '';

    public string $telefone = '';

    public string $email = '';

    public string $ami = '';

    public string $agencia = '';

    public $fotoConsultor = null;

    /** The photo already known (a URL to show it) and its file, used when no new one is uploaded. */
    public ?string $fotoAtual = null;

    #[\Livewire\Attributes\Locked]
    public ?string $fotoAtualCaminho = null;

    /** @var array<int,mixed> the photos added so far (uploaded files) */
    public array $fotos = [];

    /** @var array<int,mixed> the batch being uploaded (dropped or picked), merged into $fotos */
    public array $novas = [];

    /** 'nos' (we write it from the listing), 'eu' (their own text, read by the voice) or 'gravo' (their own voice, recorded or uploaded). */
    public string $narrador = 'nos';

    /** Their text: read by the voice ('eu'), or what they read when recording ('gravo', optional). */
    public string $narracao = '';

    /** Their own voice: a recording made on the page, or an audio file. */
    public $vozGravada = null;

    /** 'aleatoria', a library track's name, 'propria' or 'nenhuma'. */
    public string $musica = 'aleatoria';

    public $ficheiroMusica = null;

    public function mount(PerfilDoConsultor $perfis): void
    {
        $this->anuncio = (string) request()->query('anuncio', '');
        $this->modo = request()->query('modo') === 'ficheiros' ? 'ficheiros' : 'link';
        $perfil = $perfis->de(Auth::user());
        foreach (PerfilDoConsultor::CAMPOS as $campo) {
            $this->{$campo} = $perfil[$campo];
        }
        $this->fotoAtual = $perfil['foto'];
        $this->fotoAtualCaminho = $perfil['fotoCaminho'];
    }

    public function mudarModo(string $modo): void
    {
        $this->modo = $modo === 'ficheiros' ? 'ficheiros' : 'link';
        $this->resetErrorBag();
        $this->fotos = array_slice($this->fotos, 0, $this->maxFotos());
    }

    private function maxFotos(): int
    {
        return $this->modo === 'ficheiros' ? self::MAX_FOTOS_SEM_LINK : self::MAX_FOTOS;
    }

    /** Photos (and, without a link, videos) dropped or picked: each goes to its own list. */
    public function updatedNovas(): void
    {
        $videos = $this->modo === 'ficheiros';
        $this->validate(['novas.*' => $videos ? 'file|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime|max:512000' : 'image|max:15360'], [
            'novas.*.image' => 'As fotos têm de ser imagens (JPG, PNG ou WebP).',
            'novas.*.mimetypes' => 'Só fotos (JPG, PNG, WebP) e vídeos (MP4 ou MOV).',
            'novas.*.max' => $videos ? 'Cada vídeo tem de ter menos de 500 MB.' : 'Cada foto tem de ter menos de 15 MB.',
        ]);
        foreach ($this->novas as $f) {
            if (str_starts_with((string) $f->getMimeType(), 'video/')) {
                if (count($this->videos) < self::MAX_VIDEOS) {
                    $this->videos[] = $f;
                }
            } elseif ($f->getSize() <= 15360 * 1024) {
                $this->fotos[] = $f;
            } else {
                $this->addError('novas', 'Cada foto tem de ter menos de 15 MB.');
            }
        }
        $this->fotos = array_slice($this->fotos, 0, $this->maxFotos());
        $this->novas = [];
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

    /**
     * Our text, written now, for them to read when recording (or to edit and have the voice
     * read): from the description typed here, or from the listing at the link.
     */
    public function sugerirTexto(ListingScraper $scraper, \App\Services\RealEstate\ListingScriptWriter $writer): void
    {
        $this->resetErrorBag('narracao');
        $chave = 'sugerir-texto:'.Auth::id();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($chave, 10)) {
            $this->addError('narracao', 'Já pediu vários textos. Tente outra vez daqui a pouco.');

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($chave, 3600);
        if ($this->modo === 'ficheiros') {
            if (mb_strlen(trim($this->descricao)) < 80) {
                $this->addError('narracao', 'Escreva primeiro a descrição do imóvel (passo 2): o texto é feito a partir dela.');

                return;
            }
            $listing = \App\Jobs\Clips\ImportListingJob::listingManual(['description' => $this->descricao, 'price' => (int) preg_replace('/\D/', '', $this->preco) ?: null,
                'agent' => ['name' => $this->nome, 'phone' => $this->telefone, 'email' => $this->email], 'agency' => $this->agencia, 'ami' => $this->ami]);
        } else {
            $this->anuncio = ListingScraper::limpar($this->anuncio);
            $this->validate(NovoVideo::regrasDoAnuncio(), NovoVideo::MENSAGENS_DO_ANUNCIO);
            $listing = rescue(fn () => $scraper->scrape($this->anuncio), null, report: false);
            if (! $listing) {
                $this->addError('narracao', 'Não conseguimos ler o anúncio agora. Escreva o texto, ou tente outra vez.');

                return;
            }
        }
        $texto = trim((string) (rescue(fn () => $writer->write($listing), [], report: false)['voiceover'] ?? ''));
        if ($texto === '') {
            $this->addError('narracao', 'Não conseguimos escrever o texto agora. Tente outra vez.');

            return;
        }
        $this->narracao = mb_substr($texto, 0, 1500);
    }

    public function updatedFicheiroMusica(): void
    {
        $this->musica = 'propria';
    }

    public function criar(NovoVideo $novo, MusicLibrary $biblioteca, PerfilDoConsultor $perfis): void
    {
        $semLink = $this->modo === 'ficheiros';
        $this->anuncio = $semLink ? '' : ListingScraper::limpar($this->anuncio);
        $this->narracao = trim(preg_replace("/[ \t]+/", ' ', $this->narracao));
        $faixas = array_column($biblioteca->all(), 'name');
        if ($semLink && $this->fotos === [] && $this->videos === []) {
            $this->addError('ficheiros', 'Junte as fotos e/ou os vídeos do imóvel.');

            return;
        }
        $this->validate(($semLink ? $this->regrasSemLink() : NovoVideo::regrasDoAnuncio()) + [
            'fotos' => 'array|max:'.$this->maxFotos(),
            'fotos.*' => 'image|max:15360',
            'narrador' => 'in:nos,eu,gravo',
            'narracao' => $this->narrador === 'eu' ? 'required|string|min:40|max:1500' : 'nullable|string|max:1500',
            'vozGravada' => $this->narrador === 'gravo' ? 'required|file|mimes:mp3,mpga,m4a,mp4,wav,aac,weba,webm,ogg,oga,opus,caf,mov,qt|max:51200' : 'nullable',
            'musica' => ['required', fn ($a, $v, $fail) => in_array($v, ['aleatoria', 'propria', 'nenhuma'], true) || in_array($v, $faixas, true) ? null : $fail('Escolha a música.')],
            'ficheiroMusica' => $this->musica === 'propria' ? 'required|file|mimes:mp3,m4a,wav,aac,mpga|max:20480' : 'nullable',
        ], NovoVideo::MENSAGENS_DO_ANUNCIO + self::MENSAGENS_SEM_LINK + [
            'fotos.max' => 'No máximo '.$this->maxFotos().' fotos.',
            'narracao.required' => 'Escreva o texto da narração, ou deixe-nos escrevê-lo.',
            'narracao.min' => 'A narração está demasiado curta.',
            'narracao.max' => 'A narração tem de ter até 1500 caracteres (cerca de 1 minuto).',
            'vozGravada.required' => 'Grave a sua voz ou escolha um ficheiro de áudio.',
            'vozGravada.mimes' => 'A gravação tem de ser um ficheiro de áudio (MP3, M4A, WAV ou WebM).',
            'vozGravada.max' => 'A gravação tem de ter menos de 50 MB.',
            'ficheiroMusica.required' => 'Escolha o ficheiro de música.',
            'ficheiroMusica.mimes' => 'A música tem de ser MP3, M4A ou WAV.',
            'ficheiroMusica.max' => 'A música tem de ter menos de 20 MB.',
        ]);

        $disco = (string) config('contentmachine.clips.disk');
        $fotos = array_map(fn ($f) => $f->store('clips/uploads', $disco), $this->fotos);
        $opcoes = [
            'fotos_extra' => $semLink ? [] : $fotos,
            'narracao_propria' => $this->narrador !== 'nos' && $this->narracao !== '' ? $this->narracao : null,
            'voz_propria' => $this->narrador === 'gravo' ? $this->vozGravada->store('clips/uploads', $disco) : null,
            'musica' => $this->musica,
        ];
        if ($this->musica === 'propria') {
            $opcoes['musica_ficheiro'] = \Illuminate\Support\Facades\Storage::disk($disco)->path($this->ficheiroMusica->store('clips/uploads', $disco));
        }
        if ($semLink) {
            $opcoes += $this->opcoesSemLink($fotos, $disco, $perfis);
            $this->anuncio = NovoVideo::anuncioCarregado();
        }
        if ($erro = $novo->criar(Auth::user(), $this->anuncio, $opcoes)) {
            $this->addError($semLink ? 'ficheiros' : 'anuncio', $erro);
            $this->anuncio = $semLink ? '' : $this->anuncio;

            return;
        }
        session()->flash('editado', 'A fazer o seu vídeo. Fica pronto em poucos minutos, aqui nesta página.');
        $this->redirect(route('conta'), navigate: false);
    }

    /** Without a link: at least one photo or video, the description, and who the agent is. */
    private function regrasSemLink(): array
    {
        return [
            'videos' => 'array|max:'.self::MAX_VIDEOS,
            'videos.*' => 'file|mimetypes:video/mp4,video/quicktime|max:512000',
            'descricao' => 'required|string|min:80|max:6000',
            'preco' => ['nullable', 'regex:/^[\d\s.]{1,15}$/'],
            'nome' => 'required|string|max:80',
            'telefone' => 'required|string|max:40',
            'email' => 'nullable|email|max:120',
            'ami' => 'nullable|string|max:30',
            'agencia' => 'required|string|max:100',
            'fotoConsultor' => 'nullable|image|max:10240',
        ];
    }

    public const MENSAGENS_SEM_LINK = [
        'descricao.required' => 'Escreva a descrição do imóvel.',
        'descricao.min' => 'A descrição está demasiado curta: conte-nos o tipo, a zona, as divisões e o que o imóvel tem de especial.',
        'descricao.max' => 'A descrição tem de ter até 6000 caracteres.',
        'preco.regex' => 'Escreva só o número, por exemplo 350000.',
        'nome.required' => 'Escreva o nome do consultor.',
        'telefone.required' => 'Escreva o telefone do consultor.',
        'email.email' => 'Este email não parece válido.',
        'agencia.required' => 'Escreva a agência e a loja (ex.: RE/MAX Gold).',
        'fotoConsultor.image' => 'A foto do consultor tem de ser uma imagem (JPG ou PNG).',
        'fotoConsultor.max' => 'A foto do consultor tem de ter menos de 10 MB.',
        'videos.max' => 'No máximo 5 vídeos.',
        'videos.*.max' => 'Cada vídeo tem de ter menos de 500 MB.',
    ];

    /**
     * The listing as typed, the uploaded files and the agent, for the import; the agent's
     * details are kept on the account for the next video.
     */
    private function opcoesSemLink(array $fotos, string $disco, PerfilDoConsultor $perfis): array
    {
        $videos = array_map(fn ($f) => $f->store('clips/uploads', $disco), $this->videos);
        $foto = $this->fotoConsultor?->store('clips/uploads', $disco) ?? $this->fotoAtualCaminho;
        $dados = ['nome' => $this->nome, 'telefone' => $this->telefone, 'email' => $this->email, 'ami' => $this->ami, 'agencia' => $this->agencia];
        $perfis->guardar(Auth::user(), $dados, $foto);
        $portal = NovoVideo::portalDaAgencia($this->agencia);

        return array_filter([
            'listing_manual' => [
                'description' => trim($this->descricao),
                'price' => (int) preg_replace('/\D/', '', $this->preco) ?: null,
                'photos' => $fotos,
                'agent' => ['name' => trim($this->nome), 'phone' => trim($this->telefone), 'email' => trim($this->email)],
                'agency' => trim($this->agencia),
                'ami' => trim($this->ami),
            ],
            // The videos are cut per room; with photos too, both are mixed.
            'listing_video' => $videos[0] ?? null,
            'videos_carregados' => array_slice($videos, 1),
            'mix_photos' => $videos !== [] && $fotos !== [],
            // A copy: the video's own files may be dropped by an edit, the account's photo stays.
            'agent_photo' => $foto ? $perfis->copiar($foto) : null,
            'licence' => trim($this->ami),
            'theme' => app(\App\Services\Themes\ThemeRepository::class)->forPortal($portal)->slug,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '' && $v !== false);
    }

    public function render(MusicLibrary $biblioteca)
    {
        return view('livewire.novo-video', [
            'creditos' => Auth::user()->creditos(),
            'faixas' => EditarVideo::faixas($biblioteca),
        ]);
    }
}
