<?php

namespace App\Livewire\Imoplanta;

use App\Models\Imoplanta;
use App\Services\Imoplanta\Imoplantas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Imoplanta's start page: the customer's credits (and the packs to buy more),
 * the form that starts a property from its plan images — spending one credit
 * right away — and their properties. Customers see only their own.
 */
#[Layout('components.layouts.app')]
#[Title('Imoplanta')]
class Lista extends Component
{
    use WithFileUploads;

    public string $nome = '';

    public string $tipologia = 'isolada';

    public ?int $piso = null;

    /** @var array<int,TemporaryUploadedFile> */
    public array $plantas = [];

    /** @var array<int,TemporaryUploadedFile> elevations, sections, photos, material samples (optional) */
    public array $referencias = [];

    public ?string $pagamento = null;

    public function mount(): void
    {
        if (session('pago')) {   // back from a pack's Payment Link (/obrigado)
            $this->pagamento = 'Pagamento recebido. Os imóveis ficam disponíveis aqui assim que o pagamento for confirmado.';
        }
    }

    public function criar(Imoplantas $imoplantas)
    {
        $this->nome = trim($this->nome);
        $this->validate(...self::regras());

        $p = $imoplantas->criar(auth()->user(), $this->nome, $this->plantas, $this->tipologia, $this->piso, $this->referencias);
        if (! $p) {
            $this->addError('plantas', 'Não tem imóveis disponíveis. Compre um imóvel ou um pack abaixo.');

            return null;
        }

        return $this->redirectRoute('imoplanta.imovel', $p->id);
    }

    /**
     * The new-property form's rules and messages (here and on the public plans page).
     *
     * @return array{0: array<string,mixed>, 1: array<string,string>}
     */
    public static function regras(): array
    {
        return [[
            'nome' => 'required|string|max:120',
            'tipologia' => 'required|in:'.implode(',', array_keys(Imoplanta::TIPOLOGIAS)),
            'piso' => 'nullable|required_if:tipologia,apartamento|integer|min:0|max:60',
            'plantas' => 'required|array|min:1|max:'.config('contentmachine.imoplanta.max_plantas'),
            'plantas.*' => 'image|mimes:jpg,jpeg,png,webp|max:15360',
            'referencias' => 'array|max:'.config('contentmachine.imoplanta.max_plantas'),
            'referencias.*' => 'image|mimes:jpg,jpeg,png,webp|max:15360',
        ], [
            'nome.required' => 'Dê um nome ao imóvel (ex.: a morada).',
            'tipologia.*' => 'Escolha o tipo de imóvel.',
            'piso.required_if' => 'Indique o andar do apartamento (0 = rés-do-chão).',
            'piso.*' => 'Indique o andar do apartamento (0 = rés-do-chão).',
            'plantas.required' => 'Carregue pelo menos uma imagem das plantas.',
            'plantas.max' => 'No máximo :max imagens por imóvel.',
            'plantas.*.image' => 'As plantas têm de ser imagens (JPG, PNG ou WebP).',
            'plantas.*.mimes' => 'As plantas têm de ser imagens (JPG, PNG ou WebP).',
            'plantas.*.max' => 'Cada imagem tem de ter menos de 15 MB.',
            'referencias.max' => 'No máximo :max imagens de referência.',
            'referencias.*' => 'As referências têm de ser imagens (JPG, PNG ou WebP) com menos de 15 MB.',
        ]];
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.imoplanta.lista', [
            'imoveis' => Imoplanta::query()->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))->with('imagens')->latest('id')->get(),
            'disponiveis' => $user->is_admin ? null : $user->imoveisImoplantaDisponiveis(),
            'planos' => config('contentmachine.imoplanta.planos'),
            'gratis' => $user->imoplantaGratis(),
        ]);
    }
}
