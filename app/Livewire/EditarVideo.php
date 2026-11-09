<?php

namespace App\Livewire;

use App\Jobs\Clips\RegenerateVoiceoverJob;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Contas\AmostrasDeEfeitos;
use App\Services\Contas\EdicaoDeVideo;
use App\Services\Leads\LeadVideo;
use App\Services\Themes\ThemeContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A customer edits one of their reels: the voiceover (spoken again), the picture of each
 * scene, their contact on the cards, and the music (their own file). Two re-renders are
 * free; a credit buys two more.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · Editar vídeo')]
class EditarVideo extends Component
{
    use WithFileUploads;

    public int $lead;

    public string $narracao = '';

    /** @var array<int,array<string,string>> scene => [layer.param => image id] */
    public array $fotos = [];

    /** @var array<string,mixed> "c{scene}_{layer}_{param}" => uploaded file (no dots: Livewire nests on them) */
    public array $novasFotos = [];

    public string $nome = '';

    public string $telefone = '';

    public string $licenca = '';

    public $logo = null;

    public $musica = null;

    public bool $semMusica = false;

    public float $volume = 0.1;

    /** The scene whose picture is being swapped (its picker open). */
    public ?string $aTrocar = null;

    /** @var array<int,string> scene => effect slug (changed ones are applied on save) */
    public array $efeitos = [];

    /** The scene whose effect list is open. */
    public ?int $aEscolherEfeito = null;

    public function mount(int $lead): void
    {
        $this->lead = $lead;
        $clip = $this->clip() ?? abort(404);
        $listing = (array) ($clip->meta['listing'] ?? []);
        $this->narracao = (string) ($listing['voiceover'] ?? $clip->transcript['text'] ?? '');
        $cenas = collect(EdicaoDeVideo::cenas($clip));
        $this->fotos = $cenas->mapWithKeys(fn ($c) => [$c['indice'] => $c['fotos']])->all();
        $this->efeitos = $cenas->mapWithKeys(fn ($c) => [$c['indice'] => $c['slug']])->all();
        $this->nome = (string) ($listing['agent'] ?? '');
        $this->telefone = (string) ($listing['phone'] ?? '');
        $this->licenca = preg_replace('/\D/', '', (string) ($listing['licence'] ?? ''));
        $this->semMusica = ($clip->meta['musica'] ?? '') === 'nenhuma';
        $this->volume = (float) ($clip->meta['musica_volume'] ?? 0.1);
    }

    /** Only the owner's lead, and only once it has a clip. */
    private function clip(): ?ClipRecord
    {
        $lead = DB::table('leads')->where('id', $this->lead)->where('user_id', Auth::id())->first();

        $clip = $lead ? app(LeadVideo::class)->clip($lead) : null;
        // The clip's own theme: its effects (names, which can be swapped) live there.
        if ($clip) {
            app(ThemeContext::class)->use((string) ($clip->meta['theme'] ?? ''));
        }

        return $clip;
    }

    /** The listing's photos a customer can pick (not the logo, not the agent's portrait). @return array<int,string> */
    private function galeria(ClipRecord $clip): array
    {
        return collect(EdicaoDeVideo::imagens($clip))
            ->reject(fn ($i) => str_starts_with((string) ($i['description'] ?? ''), 'Logótipo') || str_starts_with((string) ($i['description'] ?? ''), 'Retrato'))
            ->keys()->all();
    }

    /**
     * Another picture effect for scene $cena. Its pictures carry over in order; an effect
     * that shows more takes the listing's next unused photos (the customer can swap them).
     */
    public function escolherEfeito(int $cena, string $slug): void
    {
        $clip = $this->clip();
        $catalogo = EdicaoDeVideo::efeitosDeFoto();
        $atual = $clip ? collect(EdicaoDeVideo::cenas($clip))->firstWhere('indice', $cena) : null;
        $this->aEscolherEfeito = null;
        if (! $clip || ! isset($catalogo[$slug]) || ! $atual || ! $atual['trocavel']) {
            return;
        }
        $daCena = collect($this->fotos[$cena] ?? [])->filter(fn ($id, $onde) => str_starts_with((string) $onde, '0.'));
        $usadas = $daCena->values()->all();
        $fila = array_values(array_unique(array_merge($usadas, array_diff($this->galeria($clip), $usadas))));
        $novas = [];
        foreach ($catalogo[$slug]['imagens'] as $n => $chave) {
            $novas["0.$chave"] = $fila[$n] ?? $fila[0] ?? '';
        }
        $this->fotos[$cena] = array_merge(collect($this->fotos[$cena] ?? [])->reject(fn ($id, $onde) => str_starts_with((string) $onde, '0.'))->all(), $novas);
        $this->efeitos[$cena] = $slug;
        foreach (array_keys($this->novasFotos) as $chave) {
            if (str_starts_with((string) $chave, 'c'.$cena.'_0_')) {
                unset($this->novasFotos[$chave]);
            }
        }
        $this->aTrocar = null;
    }

    /** The upload slot for scene $cena's picture at "layer.param". */
    public static function chave(int $cena, string $onde): string
    {
        return 'c'.$cena.'_'.str_replace('.', '_', $onde);
    }

    public function escolherFoto(int $cena, string $onde, string $imagem): void
    {
        $clip = $this->clip();
        if ($clip && isset(EdicaoDeVideo::imagens($clip)[$imagem], $this->fotos[$cena][$onde])) {
            $this->fotos[$cena][$onde] = $imagem;
            unset($this->novasFotos[self::chave($cena, $onde)]);
        }
        $this->aTrocar = null;
    }

    /**
     * Each effect's sample URL in the clip's theme (null while it renders; asked for then).
     *
     * @param  array<int,string>  $slugs
     * @return array<string,?string>
     */
    private function amostras(array $slugs): array
    {
        $tema = app(ThemeContext::class)->current()->slug;
        $out = [];
        foreach ($slugs as $slug) {
            $ficheiro = AmostrasDeEfeitos::caminho($slug);
            if (is_file($ficheiro)) {
                $out[$slug] = route('conta.amostra', ['tema' => $tema, 'efeito' => $slug, 'v' => filemtime($ficheiro)]);
            } else {
                AmostrasDeEfeitos::pedir($slug);
                $out[$slug] = null;
            }
        }

        return $out;
    }

    /** Spend one of the account's videos on two more edits of this one. */
    public function comprarEdicoes(): void
    {
        $clip = $this->clip();
        $compra = DB::table('compras')->where('user_id', Auth::id())->whereColumn('videos_usados', '<', 'videos')->orderBy('id')->first();
        if (! $clip || ! $compra) {
            return;
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'updated_at' => now()]);
        $clip->update(['meta' => array_merge((array) $clip->meta, ['edicoes_compradas' => (int) ($clip->meta['edicoes_compradas'] ?? 0) + EdicaoDeVideo::GRATIS])]);
    }

    public function guardar(): void
    {
        $clip = $this->clip() ?? abort(404);
        if ($clip->isActive()) {
            $this->addError('guardar', 'Este vídeo ainda está a ser feito. Espere que termine.');

            return;
        }
        if (EdicaoDeVideo::feitas($clip) >= EdicaoDeVideo::permitidas($clip)) {
            $this->addError('guardar', 'Já usou as edições deste vídeo.');

            return;
        }
        $this->narracao = trim(preg_replace("/[ \t]+/", ' ', $this->narracao));
        $this->validate([
            'narracao' => 'required|string|min:40|max:1500',
            'nome' => 'required|string|max:60',
            'telefone' => ['required', 'string', 'max:25', 'regex:/^[+0-9 ()]{9,}$/'],
            'licenca' => 'nullable|digits_between:3,8',
            'logo' => 'nullable|image|max:5120',
            'musica' => 'nullable|file|mimes:mp3,m4a,wav,aac,mpga|max:20480',
            'novasFotos.*' => 'nullable|image|max:15360',
            'volume' => 'numeric|min:0|max:0.4',
        ], [
            'narracao.required' => 'Escreva o texto da narração.',
            'narracao.min' => 'A narração está demasiado curta.',
            'narracao.max' => 'A narração tem de ter até 1500 caracteres (cerca de 1 minuto).',
            'nome.required' => 'Indique o seu nome.',
            'telefone.regex' => 'Indique um telefone válido.',
            'licenca.digits_between' => 'A licença AMI é só o número (ex.: 11220).',
            'logo.image' => 'O logótipo tem de ser uma imagem (PNG ou JPG).',
            'musica.mimes' => 'A música tem de ser MP3, M4A ou WAV.',
            'musica.max' => 'A música tem de ter menos de 20 MB.',
            'novasFotos.*.image' => 'As fotos têm de ser imagens (JPG ou PNG).',
        ]);

        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $plan = (array) $clip->plan;
        $imagens = (array) ($clip->images ?? []);

        // Pictures: picked from the listing's, or uploaded for a scene.
        foreach ($this->novasFotos as $chave => $ficheiro) {
            if (! preg_match('/^c(\d+)_(\d+)_(.+)$/', (string) $chave, $m)) {
                continue;
            }
            [$cena, $onde] = [(int) $m[1], $m[2].'.'.$m[3]];
            if (! $ficheiro || ! isset($this->fotos[(int) $cena][$onde])) {
                continue;
            }
            $id = 'img_'.Str::lower(Str::random(8));
            $imagens[] = ['id' => $id, 'path' => $ficheiro->store('clips/uploads', config('contentmachine.clips.disk')), 'description' => 'Foto enviada pelo consultor.'];
            $this->fotos[(int) $cena][$onde] = $id;
        }
        // A scene's new effect first: it decides which picture params the layer has.
        foreach ($this->efeitos as $cena => $slug) {
            if ($slug !== (string) ($plan['scenes'][(int) $cena]['layers'][0]['type'] ?? '')) {
                $daCena = collect($this->fotos[(int) $cena] ?? [])->filter(fn ($id, $onde) => str_starts_with((string) $onde, '0.'))->values()->all();
                [$plan] = EdicaoDeVideo::trocarEfeito($plan, (int) $cena, (string) $slug, $daCena);
            }
        }
        foreach ($this->fotos as $cena => $lugares) {
            foreach ($lugares as $onde => $id) {
                $plan = EdicaoDeVideo::trocarFoto($plan, (int) $cena, (string) $onde, (string) $id);
            }
        }

        // The agent's contact on the cards; a new logo replaces the logo picture everywhere.
        $listing = (array) ($clip->meta['listing'] ?? []);
        $antes = ['name' => (string) ($listing['agent'] ?? ''), 'phone' => (string) ($listing['phone'] ?? ''), 'licence' => preg_replace('/\D/', '', (string) ($listing['licence'] ?? ''))];
        $depois = ['name' => trim($this->nome), 'phone' => trim($this->telefone), 'licence' => trim($this->licenca)];
        $plan = EdicaoDeVideo::aplicarAgente($plan, $antes, $depois);
        if ($this->logo) {
            $caminho = $this->logo->store('clips/uploads', config('contentmachine.clips.disk'));
            $trocado = false;
            foreach ($imagens as &$img) {
                if (str_starts_with((string) ($img['description'] ?? ''), 'Logótipo')) {
                    $img['path'] = $caminho;
                    $img['transparent'] = false;
                    $trocado = true;
                }
            }
            unset($img);
            if (! $trocado) {
                $imagens[] = ['id' => 'img_'.Str::lower(Str::random(8)), 'path' => $caminho, 'description' => 'Logótipo da agência.'];
            }
        }

        $meta = array_merge((array) $clip->meta, [
            'listing' => array_merge($listing, ['agent' => $depois['name'], 'phone' => $depois['phone'], 'licence' => $depois['licence'] !== '' ? 'AMI '.$depois['licence'] : '']),
            'musica_volume' => round((float) $this->volume, 2),
            'edicoes' => EdicaoDeVideo::feitas($clip) + 1,
        ]);
        if ($this->semMusica) {
            $meta['musica'] = 'nenhuma';
            unset($meta['musica_ficheiro']);
        } elseif ($this->musica) {
            $meta['musica_ficheiro'] = $disk->path($this->musica->store('clips/uploads', config('contentmachine.clips.disk')));
            $meta['musica'] = 'propria';
        } elseif (($meta['musica'] ?? '') === 'nenhuma') {
            $meta['musica'] = 'aleatoria';
        }
        unset($meta['limpo_path'], $meta['musica_usada']);   // a free video's clean copy is made again from the edit

        $voz = trim((string) ($listing['voiceover'] ?? '')) !== $this->narracao;
        $clip->update(['plan' => $plan, 'images' => $imagens, 'meta' => $meta, 'status' => $voz ? ClipRecord::STATUS_TRANSCRIBING : ClipRecord::STATUS_RENDERING, 'error' => null]);
        DB::table('leads')->where('id', $this->lead)->update(['pronto_em' => null, 'updated_at' => now()]);
        // A new voiceover is spoken and the scenes retimed to it; otherwise only rendered again.
        $voz ? RegenerateVoiceoverJob::dispatch($clip->id, $this->narracao) : RenderJob::dispatch($clip->id);

        session()->flash('editado', 'A refazer o vídeo com as suas alterações. Fica pronto em poucos minutos.');
        $this->redirect(route('conta'), navigate: false);
    }

    public function render()
    {
        $clip = $this->clip() ?? abort(404);
        $imagens = EdicaoDeVideo::imagens($clip);
        $url = fn (string $id) => route('conta.imagem', ['lead' => $this->lead, 'imagem' => $id]);

        return view('livewire.editar-video', [
            'clip' => $clip,
            'cenas' => EdicaoDeVideo::cenas($clip),
            'galeria' => $this->galeria($clip),
            'catalogo' => $catalogo = EdicaoDeVideo::efeitosDeFoto(),
            'amostras' => $amostras = $this->amostras(array_keys($catalogo)),
            'aPrepararAmostras' => in_array(null, $amostras, true),
            // The rendered video, for each scene to play its own seconds (the effect in use).
            'filme' => ! $clip->isActive() && $clip->output_path && is_file($clip->output_path) ? URL::signedRoute('lead.video', ['lead' => $this->lead]) : null,
            'url' => $url,
            'logoAtual' => collect($imagens)->first(fn ($i) => str_starts_with((string) ($i['description'] ?? ''), 'Logótipo'))['id'] ?? null,
            'feitas' => EdicaoDeVideo::feitas($clip),
            'permitidas' => EdicaoDeVideo::permitidas($clip),
            'creditos' => Auth::user()->creditos(),
            'aFazer' => $clip->isActive(),
            'musicaAtual' => ($clip->meta['musica'] ?? '') === 'propria' ? 'A sua música' : (($clip->meta['musica'] ?? '') === 'nenhuma' ? 'Sem música' : 'Música da Imocena'),
            'comprar' => (string) config('contentmachine.stripe.links.avulso') !== ''
                ? config('contentmachine.stripe.links.avulso').'?'.http_build_query(['prefilled_email' => Auth::user()->email, 'locale' => 'pt'])
                : 'mailto:'.config('mail.from.address'),
        ]);
    }
}
