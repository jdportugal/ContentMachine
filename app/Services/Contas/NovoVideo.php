<?php

namespace App\Services\Contas;

use App\Models\User;
use App\Services\Pagamentos\Compras;
use App\Services\Projects\ProjectContext;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Support\Facades\DB;

/**
 * A customer spends one of their videos on a listing. With $opcoes (the "Novo vídeo"
 * page): extra photos, their own voiceover text and the music, carried to the import.
 */
class NovoVideo
{
    public function __construct(private ListingImport $import, private ProjectContext $context, private Compras $compras) {}

    /**
     * @param  array{fotos_extra?:array<int,string>,narracao_propria?:string,musica?:string,musica_ficheiro?:string}  $opcoes
     * @return string|null an error for the link field, or null when the video is on its way
     */
    public function criar(User $user, string $anuncio, array $opcoes = []): ?string
    {
        if ($user->creditos() < 1) {
            return 'Já não tem vídeos disponíveis. Compre um vídeo ou um pack.';
        }
        // This account's own video of the listing first, else a free one nobody has claimed.
        // Another account's video of the same listing does not stand in the way: a new one is made.
        $existente = DB::table('leads')->where('anuncio', $anuncio)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->orderByRaw('user_id is null')->latest('id')->first();
        if ($existente && $existente->pago_em && $opcoes === []) {
            return 'Este anúncio já tem o vídeo limpo na sua conta.';
        }
        if ($existente && $existente->pago_em) {
            $existente = null;   // asked again with changes: a new video, the old one stays
        }
        $compra = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')->orderBy('id')->first();

        // A free video already made for this listing, with nothing asked of it: deliver it clean.
        if ($opcoes === [] && $existente && $existente->falhou_em === null && $existente->clip_id) {
            DB::table('leads')->where('id', $existente->id)->update(['user_id' => $user->id, 'updated_at' => now()]);
            $this->compras->entregarVideoLimpo($compra->id, $existente->id);

            return null;
        }

        $clip = $this->import->start($anuncio, array_filter($opcoes, fn ($v) => $v !== null && $v !== '' && $v !== [])
            + ['theme' => app(ThemeRepository::class)->forPortal(ListingScraper::portal($anuncio))->slug]);
        $dados = ['user_id' => $user->id, 'projeto' => $this->context->current()->slug, 'clip_id' => $clip->id, 'ip' => (string) request()->ip(),
            'consentimento_em' => now(), 'falhou_em' => null, 'pronto_em' => null, 'pago_em' => now(), 'updated_at' => now()];
        if ($existente) {
            DB::table('leads')->where('id', $existente->id)->update($dados);
        } else {
            DB::table('leads')->insert($dados + ['anuncio' => $anuncio, 'origem' => \App\Services\Marketing\Origem::json(), 'created_at' => now(),
                'consultor' => empty($opcoes['listing_manual'])
                    ? (rescue(fn () => app(\App\Services\Alertas\PaginaDoConsultor::class)->de($anuncio, 8), null, report: false)['chave'] ?? null) : null]);
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'updated_at' => now()]);

        return null;
    }

    /** The "link" a video made from uploaded files is filed under (unique; no portal reads it). */
    public static function anuncioCarregado(): string
    {
        return 'https://imocena.com/carregado/'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(16));
    }

    public static function eCarregado(string $anuncio): bool
    {
        return str_starts_with($anuncio, 'https://imocena.com/carregado/');
    }

    /** The theme for a video without a link: the agency's (RE/MAX, Century 21…) when it is one we have, else the default. */
    public static function portalDaAgencia(string $agencia): ?string
    {
        $a = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($agencia));
        $mapa = ['/\bre\s*\/?\s*max\b/' => 'remax', '/\bcentury\s*21\b/' => 'century21', '/\biad\b/' => 'iad', '/\bexp\b/' => 'exp',
            '/\b(kw|keller williams)\b/' => 'kw', '/\bpredimed\b/' => 'predimed', '/\bera\b/' => 'era', '/\bzome\b/' => 'zome',
            '/\bbussola\s*haus\b/' => 'bussolahaus', '/\bmy\s*best\s*houses\b/' => 'mybesthouses'];
        foreach ($mapa as $padrao => $portal) {
            if (preg_match($padrao, $a)) {
                return $portal;
            }
        }

        return null;
    }

    /** The link rules shared by the account's quick form and the "Novo vídeo" page. */
    public static function regrasDoAnuncio(): array
    {
        return [
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail(ListingScraper::MENSAGEM),
                fn ($a, $v, $fail) => ListingScraper::naoEImovel($v) ? $fail(ListingScraper::NAO_E_IMOVEL) : null],
        ];
    }

    public const MENSAGENS_DO_ANUNCIO = [
        'anuncio.required' => 'Cole o link do anúncio.',
        'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
    ];
}
