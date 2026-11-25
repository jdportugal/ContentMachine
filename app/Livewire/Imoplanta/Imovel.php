<?php

namespace App\Livewire\Imoplanta;

use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use App\Services\Imoplanta\ImoplantaCasa;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Imoplanta\Imoplantas;
use App\Services\RealEstate\ListingScraper;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * One Imoplanta property, in four steps: 1. the 3D model (built in the browser with three.js,
 * resources/js/imoplanta.js) with its AI change requests and the shot planner with a preview of every shot;
 * 2. the finishes board and each storey's realistic 3D floor plan, each changeable a few times; 3. the AI
 * photos with a change per image; 4. the walkthrough video. The browser renders (top views, shots) go to the
 * `imoplanta.aereo` / `imoplanta.render` routes. Earlier steps can be looked at (?passo=n), read-only.
 * Re-do counters are shown; the limits are enforced in Imoplantas. Polls while anything is being worked on.
 */
#[Layout('components.layouts.app')]
class Imovel extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $imovelId;

    public string $pedido = '';

    /** The step being looked at (an earlier one, read-only); null: the current one. */
    #[Url(as: 'passo')]
    public ?int $ver = null;

    /** @var array<string,string> 'q' (the finishes board) or a storey number → the change asked for */
    public array $pedidosRef = [];

    /** @var array<int,string> image id → the change asked for */
    public array $pedidosImagem = [];

    /** The Imocena reel add-on's details, all optional: a listing link, or the facts for the text. */
    public array $reel = ['anuncio' => '', 'preco' => '', 'local' => '', 'notas' => '', 'nome' => '', 'telefone' => '', 'email' => ''];

    /** The reel's contact photo and logo (optional uploads, after payment). */
    public $fotoContacto = null;

    public $logoContacto = null;

    /** Back from paying (an add-on; /obrigado sends them here with it): what the customer reads. */
    public ?string $aviso = null;

    public function mount(Imoplanta $imovel)
    {
        $this->imovelId = $imovel->id;
        $p = $this->imovel();
        if ($p->estado === Imoplanta::A_PAGAR) {   // plans waiting for payment: pay first
            return $this->redirectRoute('plantas');
        }
        $this->reel = array_merge($this->reel, array_map('strval', (array) $p->extra('video', 'dados')));
        if ($this->reel['anuncio'] === '' && $p->anuncio && ListingScraper::supports($p->anuncio)) {   // the link given with the plans
            $this->reel['anuncio'] = $p->anuncio;
        }
        $this->reel['notas'] = $this->reel['notas'] ?: (string) $p->notas;
        if (session('pago')) {   // back from a Payment Link (/obrigado): the webhook has recorded it, or soon will
            $this->aviso = $p->extra('video') === 'dados'
                ? 'Pagamento recebido. Falta só um passo: os dados do vídeo, abaixo (tudo opcional).'
                : 'Pagamento recebido. Se o vídeo ainda não começou, começa assim que o pagamento for confirmado.';
            $this->ver = 4;
        }

        return null;
    }

    /** The property, if this user may see it (re-checked on every request). */
    private function imovel(): Imoplanta
    {
        $p = Imoplanta::with('imagens')->find($this->imovelId);
        abort_unless($p && $p->visivelPara(auth()->user()), 404);

        return $p;
    }

    public function alterarModelo(Imoplantas $imoplantas): void
    {
        $this->pedido = trim($this->pedido);
        $this->validate(['pedido' => 'required|string|min:5|max:1000'], [
            'pedido.required' => 'Descreva o que quer mudar no modelo.',
            'pedido.min' => 'Descreva o que quer mudar no modelo.',
        ]);
        if (! $imoplantas->pedirAlteracao($this->imovel(), $this->pedido)) {
            $this->addError('pedido', 'Já usou as alterações ao modelo 3D deste imóvel (ou o modelo ainda está a ser feito).');

            return;
        }
        $this->reset('pedido');
    }

    /** @return bool whether the plan was saved (the planner only offers "Gerar" after a save that worked) */
    public function guardarCameras(array $cameras, Imoplantas $imoplantas): bool
    {
        $this->resetErrorBag('cameras');
        try {
            $ok = $imoplantas->guardarCameras($this->imovel(), $cameras);
        } catch (ValidationException $e) {
            $this->addError('cameras', collect($e->errors())->flatten()->first());

            return false;
        }
        if (! $ok) {
            $this->addError('cameras', 'As câmaras já não podem mudar.');
        }

        return $ok;
    }

    public function gerarImagens(Imoplantas $imoplantas): void
    {
        if (! $imoplantas->gerarImagens($this->imovel())) {
            $this->addError('cameras', 'Os acabamentos e as plantas 3D ainda não estão prontos.');

            return;
        }
        $this->ver = null;
    }

    /** Step 1 → 2: saves the cameras and fixes them and the model. @return bool whether it moved on */
    public function avancar(array $cameras, Imoplantas $imoplantas): bool
    {
        $this->resetErrorBag('cameras');
        try {
            $ok = $imoplantas->confirmarCameras($this->imovel(), $cameras);
        } catch (ValidationException $e) {
            $this->addError('cameras', collect($e->errors())->flatten()->first());

            return false;
        }
        if (! $ok) {
            $this->addError('cameras', 'As câmaras já não podem mudar.');
        }
        $this->ver = null;

        return $ok;
    }

    /** Step 2: a change to the finishes board ($nivel null) or to a storey's 3D floor plan. */
    public function alterarReferencia(?int $nivel, Imoplantas $imoplantas): void
    {
        $chave = $nivel === null ? 'q' : (string) $nivel;
        $this->resetErrorBag('pedidosRef.'.$chave);
        $texto = trim((string) ($this->pedidosRef[$chave] ?? ''));
        if (mb_strlen($texto) < 5 || mb_strlen($texto) > 1000) {
            $this->addError('pedidosRef.'.$chave, 'Descreva o que quer mudar.');

            return;
        }
        if (! $imoplantas->alterarReferencia($this->imovel(), $nivel, $texto, auth()->user())) {
            $this->addError('pedidosRef.'.$chave, 'Já usou as alterações (ou ainda há uma a ser feita).');

            return;
        }
        unset($this->pedidosRef[$chave]);
    }

    public function tentarReferencias(Imoplantas $imoplantas): void
    {
        $imoplantas->tentarReferencias($this->imovel());
    }

    /** Where the property is: 1 model and cameras, 2 materials and 3D floor plans, 3 images, 4 video. */
    public static function passoAtual(Imoplanta $p): int
    {
        return match (true) {
            $p->estado === Imoplanta::REFERENCIAS => 2,
            $p->estado === Imoplanta::IMAGENS => $p->video || $p->video_estado ? 4 : 3,
            default => 1,
        };
    }

    public function criarVideo(Imoplantas $imoplantas): void
    {
        $p = $this->imovel();
        abort_unless($p->user_id === auth()->id() || auth()->user()->is_admin, 403);
        if (! $imoplantas->pedirVideo($p, auth()->user())) {
            $this->addError('video', 'O vídeo só pode ser criado quando todas as imagens estiverem prontas.');
        }
    }

    /** Step 4: an add-on ('planta', 'video' or 'ambos', the two cheaper): started at once when free, else off to Stripe Checkout. */
    public function comprarExtra(string $chave, Imoplantas $imoplantas)
    {
        abort_unless(in_array($chave, ['planta', 'video', 'ambos'], true), 404);
        $p = $this->imovel();
        abort_unless($p->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $this->resetErrorBag();
        $r = $imoplantas->pedirExtra($p, $chave, auth()->user());
        if ($r === false) {
            $this->addError('extra.'.$chave, 'Este vídeo já foi pago, feito ou está a ser feito.');

            return null;
        }

        return $r === 'pagar' ? redirect()->away(Imoplantas::link(auth()->user(), 'extra_'.$chave, $p)) : null;
    }

    /** After paying for the reel: its details and contact, all optional; then it is made. */
    public function enviarDadosReel(Imoplantas $imoplantas): void
    {
        $p = $this->imovel();
        abort_unless($p->user_id === auth()->id() || auth()->user()->is_admin, 403);
        $this->reel = array_map(fn ($v) => trim((string) $v), $this->reel);
        $this->validate([
            'reel.anuncio' => ['nullable', 'url:https,http', 'max:500', fn ($a, $v, $fail) => ListingScraper::supports($v) ? null
                : $fail('Deste site ainda não lemos anúncios (lemos RE/MAX, iad e idealista). Apague o link e escreva os dados abaixo.')],
            'reel.preco' => 'nullable|string|max:40',
            'reel.local' => 'nullable|string|max:120',
            'reel.notas' => 'nullable|string|max:3000',
            'reel.nome' => 'nullable|string|max:80',
            'reel.telefone' => 'nullable|string|max:30',
            'reel.email' => 'nullable|email|max:120',
            'fotoContacto' => 'nullable|image|max:10240',
            'logoContacto' => 'nullable|image|max:10240',
        ], [
            'reel.anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
            'reel.email.email' => 'Este email não parece certo.',
            'fotoContacto.image' => 'A foto tem de ser uma imagem (JPG ou PNG).',
            'logoContacto.image' => 'O logótipo tem de ser uma imagem (JPG ou PNG).',
            '*.max' => 'Este ficheiro é demasiado grande (máximo 10 MB).',
        ]);
        $disco = config('contentmachine.clips.disk');
        $dados = Arr::only($this->reel, ['anuncio', 'preco', 'local', 'notas', 'nome', 'telefone', 'email']) + [
            'foto' => $this->fotoContacto?->storeAs('clips/uploads', Str::random(40).'.'.$this->fotoContacto->extension(), $disco),
            'logo' => $this->logoContacto?->storeAs('clips/uploads', Str::random(40).'.'.$this->logoContacto->extension(), $disco),
        ];
        if (! $imoplantas->enviarDadosReel($p, $dados)) {
            $this->addError('extra.video', 'Este vídeo já está a ser feito.');

            return;
        }
        $this->reset('fotoContacto', 'logoContacto');
    }

    /** Admins, for now: drop the images and go back to the cameras to take them again. */
    public function refazerFotografias(Imoplantas $imoplantas): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        if (! $imoplantas->refazerFotografias($this->imovel())) {
            $this->addError('refazer', 'Espere que as imagens em curso terminem e tente de novo.');
        }
    }

    public function alterarImagem(int $imagem, Imoplantas $imoplantas): void
    {
        $img = $this->imovel()->imagens->firstWhere('id', $imagem) ?? abort(404);
        $texto = trim((string) ($this->pedidosImagem[$imagem] ?? ''));
        if (mb_strlen($texto) < 5 || mb_strlen($texto) > 1000) {
            $this->addError("pedidosImagem.$imagem", 'Descreva o que quer mudar nesta imagem.');

            return;
        }
        if (! $imoplantas->alterarImagem($img, $texto)) {
            $this->addError("pedidosImagem.$imagem", 'Já usou as alterações desta imagem (ou ainda está a ser feita).');

            return;
        }
        unset($this->pedidosImagem[$imagem]);
    }

    public function gerarImagemDeNovo(int $imagem, Imoplantas $imoplantas): void
    {
        $img = $this->imovel()->imagens->firstWhere('id', $imagem) ?? abort(404);
        if ($img->estado === ImoplantaImagem::FALHOU) {   // a failed one: free retry
            $imoplantas->repetirImagem($img);

            return;
        }
        if (! $imoplantas->gerarImagemDeNovo($img, (bool) auth()->user()->is_admin)) {
            $this->addError("pedidosImagem.$imagem", 'Já usou as alterações desta imagem (ou ainda está a ser feita).');
        }
    }

    public function tentarDeNovo(Imoplantas $imoplantas): void
    {
        $imoplantas->tentarDeNovo($this->imovel());
    }

    public function render()
    {
        $p = $this->imovel();
        // the reel add-on is made by the listing pipeline: its clip says when it is done
        $reel = ($id = $p->extra('video', 'clip')) ? app(ClipStore::class)->find($id) : null;
        if ($p->extra('video') === 'a_fazer' && $reel && in_array($reel->status, [ClipRecord::STATUS_DONE, ClipRecord::STATUS_FAILED], true)) {
            app(Imoplantas::class)->reelTerminou($p, $reel->status === ClipRecord::STATUS_DONE);
        }
        $atual = self::passoAtual($p);
        // the video step opens once every image is ready (it becomes the current one when the video is asked for)
        $alcance = $atual === 3 && $p->imagens->isNotEmpty() && $p->imagens->every(fn ($i) => $i->estado === ImoplantaImagem::PRONTA && $i->foto) ? 4 : $atual;
        $dono = $p->user_id === auth()->id();   // only the owner's browser uploads renders

        return view('livewire.imoplanta.imovel', [
            'p' => $p,
            'reelClip' => $reel,
            // video credits the reel add-on can use instead of paying (free accounts and admins don't need them)
            'creditos' => auth()->user()->is_admin || auth()->user()->imoplantaGratis() ? 0 : auth()->user()->creditos(),
            'precos' => collect(config('contentmachine.imoplanta.extras'))->map(fn ($e) => auth()->user()->is_admin || auth()->user()->imoplantaGratis() ? 0 : $e['preco']),
            'atual' => $atual,
            'alcance' => $alcance,
            'passo' => $this->ver && $this->ver >= 1 && $this->ver <= $alcance ? $this->ver : $atual,
            // the renders this browser still has to make and send: each storey's top view (step 2 on), then each shot
            'envios' => $p->casa && $dono ? [
                'casa' => $p->casaParaVer(),
                'csrf' => csrf_token(),
                'pendentes' => [
                    ...(in_array($p->estado, [Imoplanta::REFERENCIAS, Imoplanta::IMAGENS], true)
                        ? array_values(array_map(fn (int $n) => ['id' => 'topo-'.$n, 'numero' => 0, 'topo' => $n, 'envio' => route('imoplanta.aereo', [$p->id, $n])],
                            array_filter($p->niveisComPlanta(), fn (int $n) => ! $p->aereo($n, 'render')))) : []),
                    ...$p->imagens->where('estado', ImoplantaImagem::A_RENDERIZAR)->map(fn (ImoplantaImagem $i) => [
                        'id' => $i->id, 'numero' => $i->numero, 'camera' => $i->camera, 'envio' => route('imoplanta.render', [$p->id, $i->id]),
                    ])->values()->all(),
                ],
            ] : null,
            // the browser builds and renders it all from these (wire:ignore'd; re-made when versao/estado change)
            'tres' => $p->casa ? [
                'casa' => $p->casaParaVer(),
                'cameras' => $p->cameras ?? [],
                'vista' => ImoplantaCasa::vista($p->casa),
                'max' => (int) config('contentmachine.imoplanta.max_cameras'),
                'editavel' => $p->editavel(),
                'fov' => ImoplantaCasa::FOV,
            ] : null,
        ])->title($p->nome.' · Imoplanta');
    }
}
