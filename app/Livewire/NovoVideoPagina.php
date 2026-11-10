<?php

namespace App\Livewire;

use App\Services\Contas\NovoVideo;
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
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · Novo vídeo')]
class NovoVideoPagina extends Component
{
    use WithFileUploads;

    public const MAX_FOTOS = 15;

    public string $anuncio = '';

    /** @var array<int,mixed> the photos added so far (uploaded files) */
    public array $fotos = [];

    /** @var array<int,mixed> the batch being uploaded (dropped or picked), merged into $fotos */
    public array $novas = [];

    /** 'nos' (we write it from the listing) or 'eu' (their own text). */
    public string $narrador = 'nos';

    public string $narracao = '';

    /** 'aleatoria', a library track's name, 'propria' or 'nenhuma'. */
    public string $musica = 'aleatoria';

    public $ficheiroMusica = null;

    public function mount(): void
    {
        $this->anuncio = (string) request()->query('anuncio', '');
    }

    public function updatedNovas(): void
    {
        $this->validate(['novas.*' => 'image|max:15360'], ['novas.*.image' => 'As fotos têm de ser imagens (JPG, PNG ou WebP).', 'novas.*.max' => 'Cada foto tem de ter menos de 15 MB.']);
        $this->fotos = array_slice(array_merge($this->fotos, $this->novas), 0, self::MAX_FOTOS);
        $this->novas = [];
    }

    public function tirarFoto(int $i): void
    {
        unset($this->fotos[$i]);
        $this->fotos = array_values($this->fotos);
    }

    public function updatedFicheiroMusica(): void
    {
        $this->musica = 'propria';
    }

    public function criar(NovoVideo $novo, MusicLibrary $biblioteca): void
    {
        $this->anuncio = trim($this->anuncio);
        $this->narracao = trim(preg_replace("/[ \t]+/", ' ', $this->narracao));
        $faixas = array_column($biblioteca->all(), 'name');
        $this->validate(NovoVideo::regrasDoAnuncio() + [
            'fotos' => 'array|max:'.self::MAX_FOTOS,
            'fotos.*' => 'image|max:15360',
            'narrador' => 'in:nos,eu',
            'narracao' => $this->narrador === 'eu' ? 'required|string|min:40|max:1500' : 'nullable',
            'musica' => ['required', fn ($a, $v, $fail) => in_array($v, ['aleatoria', 'propria', 'nenhuma'], true) || in_array($v, $faixas, true) ? null : $fail('Escolha a música.')],
            'ficheiroMusica' => $this->musica === 'propria' ? 'required|file|mimes:mp3,m4a,wav,aac,mpga|max:20480' : 'nullable',
        ], NovoVideo::MENSAGENS_DO_ANUNCIO + [
            'fotos.max' => 'No máximo '.self::MAX_FOTOS.' fotos adicionais.',
            'narracao.required' => 'Escreva o texto da narração, ou deixe-nos escrevê-lo.',
            'narracao.min' => 'A narração está demasiado curta.',
            'narracao.max' => 'A narração tem de ter até 1500 caracteres (cerca de 1 minuto).',
            'ficheiroMusica.required' => 'Escolha o ficheiro de música.',
            'ficheiroMusica.mimes' => 'A música tem de ser MP3, M4A ou WAV.',
            'ficheiroMusica.max' => 'A música tem de ter menos de 20 MB.',
        ]);

        $disco = (string) config('contentmachine.clips.disk');
        $opcoes = [
            'fotos_extra' => array_map(fn ($f) => $f->store('clips/uploads', $disco), $this->fotos),
            'narracao_propria' => $this->narrador === 'eu' ? $this->narracao : null,
            'musica' => $this->musica,
        ];
        if ($this->musica === 'propria') {
            $opcoes['musica_ficheiro'] = \Illuminate\Support\Facades\Storage::disk($disco)->path($this->ficheiroMusica->store('clips/uploads', $disco));
        }
        if ($erro = $novo->criar(Auth::user(), $this->anuncio, $opcoes)) {
            $this->addError('anuncio', $erro);

            return;
        }
        session()->flash('editado', 'A fazer o seu vídeo. Fica pronto em poucos minutos, aqui nesta página.');
        $this->redirect(route('conta'), navigate: false);
    }

    public function render(MusicLibrary $biblioteca)
    {
        return view('livewire.novo-video', [
            'creditos' => Auth::user()->creditos(),
            'faixas' => EditarVideo::faixas($biblioteca),
        ]);
    }
}
