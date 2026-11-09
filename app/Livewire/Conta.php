<?php

namespace App\Livewire;

use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Contas\TextosDoVideo;
use App\Services\Shorts\MusicLibrary;
use App\Services\Contas\LinkDeAcesso;
use App\Services\Leads\LeadVideo;
use App\Services\Pagamentos\Compras;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A customer's area: their videos (ready to download, clean once paid), their
 * credits from packs, and a form that spends a credit on a new listing.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · A minha conta')]
class Conta extends Component
{
    public string $anuncio = '';

    /** The lead whose video is being edited (null: no editor open). */
    public ?int $editar = null;

    public string $musica = '';

    public float $volume = 0.1;

    public string $legendas = 'bottom';

    /** @var array<int,string> on-screen texts, in TextosDoVideo::extrair() order */
    public array $textos = [];

    /** Re-renders a customer may ask for, per video. */
    public const EDICOES = 10;

    /** The customer's own lead and its clip, or null. */
    private function meuClip(int $leadId): ?array
    {
        $lead = DB::table('leads')->where('id', $leadId)->where('user_id', Auth::id())->first();
        $clip = $lead ? app(LeadVideo::class)->clip($lead) : null;

        return $clip ? [$lead, $clip] : null;
    }

    public function abrirEditor(int $leadId): void
    {
        [, $clip] = $this->meuClip($leadId) ?? [null, null];
        if (! $clip) {
            return;
        }
        $this->editar = $leadId;
        $this->musica = (string) ($clip->meta['musica'] ?? '');
        $this->volume = (float) ($clip->meta['musica_volume'] ?? 0.1);
        $this->legendas = in_array($clip->meta['captions'] ?? 'bottom', ClipRecord::CAPTIONS, true) ? $clip->meta['captions'] ?? 'bottom' : 'bottom';
        $this->textos = array_column(TextosDoVideo::extrair((array) $clip->plan), 'valor');
        $this->resetErrorBag();
    }

    public function fecharEditor(): void
    {
        $this->reset('editar', 'musica', 'volume', 'legendas', 'textos');
    }

    /** Save the changes and render the video again (same narration; ~3 minutes). */
    public function guardarEdicao(MusicLibrary $biblioteca): void
    {
        [$lead, $clip] = $this->meuClip((int) $this->editar) ?? [null, null];
        if (! $clip) {
            return;
        }
        if ($clip->isActive()) {
            $this->addError('edicao', 'Este vídeo ainda está a ser feito. Espere que termine.');

            return;
        }
        $feitas = (int) ($clip->meta['edicoes'] ?? 0);
        if ($feitas >= self::EDICOES) {
            $this->addError('edicao', 'Já editou este vídeo '.self::EDICOES.' vezes. Fale connosco para mais alterações.');

            return;
        }
        $nomes = array_column($biblioteca->all(), 'name');
        $this->validate([
            'musica' => ['nullable', fn ($a, $v, $fail) => $v === '' || in_array($v, ['aleatoria', 'nenhuma'], true) || in_array($v, $nomes, true) ? null : $fail('Escolha uma das músicas.')],
            'volume' => 'numeric|min:0|max:0.4',
            'legendas' => 'in:'.implode(',', ClipRecord::CAPTIONS),
            'textos.*' => 'nullable|string|max:160',
        ], ['textos.*.max' => 'Cada texto pode ter até 160 caracteres.']);

        $meta = array_merge((array) $clip->meta, [
            'musica' => $this->musica === '' ? 'aleatoria' : $this->musica,
            'musica_volume' => round((float) $this->volume, 2),
            'captions' => $this->legendas,
            'edicoes' => $feitas + 1,
        ]);
        unset($meta['limpo_path']);   // a free video's clean copy is made again from the edited plan
        $caminhos = array_column(TextosDoVideo::extrair((array) $clip->plan), 'caminho');
        $novos = [];
        foreach ($caminhos as $i => $caminho) {
            if (isset($this->textos[$i])) {
                $novos[$caminho] = (string) $this->textos[$i];
            }
        }
        $clip->update(['plan' => TextosDoVideo::aplicar((array) $clip->plan, $novos), 'meta' => $meta, 'status' => ClipRecord::STATUS_RENDERING]);
        DB::table('leads')->where('id', $lead->id)->update(['pronto_em' => null, 'updated_at' => now()]);
        RenderJob::dispatch($clip->id);
        $this->fecharEditor();
    }

    public function usarCredito(ListingImport $import, ProjectContext $context, Compras $compras): void
    {
        $user = Auth::user();
        $this->anuncio = trim($this->anuncio);
        $this->validate([
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail('Por agora lemos anúncios da RE/MAX, iad e idealista.')],
        ], [
            'anuncio.required' => 'Cole o link do anúncio.',
            'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
        ]);
        if ($user->creditos() < 1) {
            $this->addError('anuncio', 'Já não tem vídeos disponíveis. Compre um vídeo ou um pack.');

            return;
        }
        $existente = DB::table('leads')->where('anuncio', $this->anuncio)->first();
        if ($existente && $existente->user_id && (int) $existente->user_id !== (int) $user->id) {
            $this->addError('anuncio', 'Este anúncio já tem um vídeo feito por outra conta. Fale connosco.');

            return;
        }
        if ($existente && $existente->pago_em) {
            $this->addError('anuncio', 'Este anúncio já tem o vídeo limpo na sua conta.');

            return;
        }
        $compra = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')->orderBy('id')->first();

        // A free video already made for this listing (by them, or before they had an account): deliver it clean.
        if ($existente && $existente->falhou_em === null && $existente->clip_id) {
            DB::table('leads')->where('id', $existente->id)->update(['user_id' => $user->id, 'updated_at' => now()]);
            $compras->entregarVideoLimpo($compra->id, $existente->id);
            $this->reset('anuncio');

            return;
        }

        $clip = $import->start($this->anuncio, ['theme' => app(ThemeRepository::class)->forPortal(ListingScraper::portal($this->anuncio))->slug]);
        $dados = ['user_id' => $user->id, 'projeto' => $context->current()->slug, 'clip_id' => $clip->id, 'ip' => (string) request()->ip(),
            'consentimento_em' => now(), 'falhou_em' => null, 'pronto_em' => null, 'pago_em' => now(), 'updated_at' => now()];
        if ($existente) {
            DB::table('leads')->where('id', $existente->id)->update($dados);
        } else {
            DB::table('leads')->insert($dados + ['anuncio' => $this->anuncio, 'created_at' => now()]);
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'updated_at' => now()]);
        $this->reset('anuncio');
    }

    public function render(LeadVideo $videos)
    {
        $user = Auth::user();
        $lista = LinkDeAcesso::leads($user)->map(function ($lead) use ($videos) {
            $clip = $videos->clip($lead);
            $pronto = (bool) $videos->ficheiro($lead);
            [$etapa, $pct] = Imoveis::ESTADOS[$clip?->status] ?? ['A preparar', 10];

            return (object) [
                'id' => $lead->id,
                'edicoes' => (int) ($clip?->meta['edicoes'] ?? 0),
                'anuncio' => $lead->anuncio,
                'titulo' => $clip?->title ?: $lead->anuncio,
                'estado' => $pronto ? 'pronto' : (! $clip || $clip->status === ClipRecord::STATUS_FAILED ? 'falhou' : 'a-fazer'),
                'limpo' => $pronto && empty($clip?->meta['free_lead']),
                'etapa' => $etapa, 'pct' => $pct,
                'video' => $pronto ? URL::signedRoute('lead.video', ['lead' => $lead->id]) : null,
                'descarregar' => $pronto ? URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]) : null,
            ];
        });

        $link = fn (string $plano, array $extra = []) => (string) config("contentmachine.stripe.links.$plano") !== ''
            ? config("contentmachine.stripe.links.$plano").'?'.http_build_query($extra + ['prefilled_email' => $user->email, 'locale' => 'pt'])
            : 'mailto:'.config('mail.from.address');
        [$editarLead, $editarClip] = $this->editar ? ($this->meuClip($this->editar) ?? [null, null]) : [null, null];

        return view('livewire.conta', [
            'link' => $link,
            'edicao' => $editarClip ? ['titulo' => $editarClip->title ?: $editarLead->anuncio, 'restantes' => self::EDICOES - (int) ($editarClip->meta['edicoes'] ?? 0)] : null,
            'musicas' => $editarClip ? array_column(app(MusicLibrary::class)->all(), 'name') : [],
            'campos' => $editarClip ? TextosDoVideo::extrair((array) $editarClip->plan) : [],
            'user' => $user,
            'creditos' => $user->creditos(),
            'videos' => $lista,
            'aDecorrer' => $lista->contains(fn ($v) => $v->estado === 'a-fazer'),
        ]);
    }
}
