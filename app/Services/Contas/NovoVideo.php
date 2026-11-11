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
        $existente = DB::table('leads')->where('anuncio', $anuncio)->first();
        if ($existente && $existente->user_id && (int) $existente->user_id !== (int) $user->id) {
            return 'Este anúncio já tem um vídeo feito por outra conta. Fale connosco.';
        }
        if ($existente && $existente->pago_em) {
            return 'Este anúncio já tem o vídeo limpo na sua conta.';
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
                'consultor' => rescue(fn () => app(\App\Services\Alertas\PaginaDoConsultor::class)->de($anuncio, 8), null, report: false)['chave'] ?? null]);
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'updated_at' => now()]);

        return null;
    }

    /** The link rules shared by the account's quick form and the "Novo vídeo" page. */
    public static function regrasDoAnuncio(): array
    {
        return [
            'anuncio' => ['required', 'url:https,http', 'max:500',
                fn ($a, $v, $fail) => ListingScraper::supports($v) ? null : $fail(ListingScraper::MENSAGEM)],
        ];
    }

    public const MENSAGENS_DO_ANUNCIO = [
        'anuncio.required' => 'Cole o link do anúncio.',
        'anuncio.url' => 'Isto não parece um link. Copie-o da barra de endereço do anúncio.',
    ];
}
