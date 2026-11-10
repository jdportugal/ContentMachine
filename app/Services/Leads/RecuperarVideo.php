<?php

namespace App\Services\Leads;

use App\Models\User;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Contas\LinkDeAcesso;
use App\Services\RealEstate\ListingImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The fallback when a customer's video does not come out. Nobody should be left
 * looking at a spinner or lose what they paid for:
 *
 *  - stuck: a clip that has not moved for STUCK_MINUTES counts as failed;
 *  - an edit that fails keeps the video they had, and the edit is given back;
 *  - a new video is made again from scratch once, automatically;
 *  - if that fails too, the video they spent goes back to their account,
 *    they get an email saying so, and the admins get one with the details.
 */
class RecuperarVideo
{
    public const STUCK_MINUTES = 40;

    /** The options a video was asked with, carried to the second attempt. */
    private const OPCOES = ['fotos_extra', 'narracao_propria', 'musica', 'musica_ficheiro', 'theme', 'free_lead'];

    public function __construct(private ListingImport $import, private LinkDeAcesso $links) {}

    /** A clip that is still "in progress" but has not changed for a long time. */
    public static function preso(?ClipRecord $clip): bool
    {
        if (! $clip || ! $clip->isActive() || ! $clip->updated_at) {
            return false;
        }

        return Carbon::parse($clip->updated_at)->lt(now()->subMinutes(self::STUCK_MINUTES));
    }

    /** Handle a lead whose clip failed, vanished or got stuck. Returns what was done. */
    public function tratar(object $lead, ?ClipRecord $clip): string
    {
        $erro = $clip?->error ?: ($clip ? 'Parado há mais de '.self::STUCK_MINUTES.' minutos ('.$clip->status.')' : 'O clip desapareceu');
        if ($clip && $clip->status !== ClipRecord::STATUS_FAILED) {
            $clip->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $erro]);
        }

        // An edit that failed: the video they had is still there. Keep it, give the edit back.
        $meta = (array) ($clip?->meta ?? []);
        if ($clip && (int) ($meta['edicoes'] ?? 0) > 0 && $clip->output_path && is_file($clip->output_path)) {
            $clip->update(['status' => ClipRecord::STATUS_DONE, 'error' => null,
                'meta' => array_merge($meta, ['edicoes' => (int) $meta['edicoes'] - 1, 'edicao_falhou_em' => now()->toIso8601String()])]);
            $this->avisarCliente($lead, 'A sua edição não foi aplicada',
                'Não conseguimos aplicar a última edição ao vídeo do seu imóvel. O vídeo que tinha continua na sua conta e a edição foi-lhe devolvida, por isso pode tentar outra vez sem custo.');
            $this->avisarAdmins($lead, $erro, 'Edição falhou: vídeo anterior reposto e edição devolvida.');

            return 'edicao-reposta';
        }

        // First failure of a new video: make it again from scratch, once.
        if ((int) ($meta['tentativa'] ?? 1) < 2) {
            $opcoes = array_intersect_key($meta, array_flip(self::OPCOES));
            $novo = $this->import->start($lead->anuncio, $opcoes + ['tentativa' => 2]);
            DB::table('leads')->where('id', $lead->id)->update(['clip_id' => $novo->id, 'updated_at' => now()]);
            Log::warning('Lead video failed, retrying once', ['lead' => $lead->id, 'erro' => $erro]);

            return 'nova-tentativa';
        }

        // Second failure: give the video back and tell everyone.
        $devolvido = $this->devolverVideo($lead);
        DB::table('leads')->where('id', $lead->id)->update(['falhou_em' => now(), 'updated_at' => now()]);
        $this->avisarCliente($lead, 'Não conseguimos fazer o vídeo do seu imóvel',
            'Tentámos duas vezes, mas não conseguimos fazer o vídeo do anúncio '.$lead->anuncio.'. '
            .($devolvido ? 'O vídeo que usou já voltou para a sua conta. ' : '')
            .'Pode tentar outra vez na sua conta ou responder a este email e tratamos disso.');
        $this->avisarAdmins($lead, $erro, $devolvido ? 'Falhou duas vezes: vídeo devolvido à conta do cliente.' : 'Falhou duas vezes (vídeo grátis).');

        return 'falhou';
    }

    /** A paid video goes back to the account it came from (once). */
    private function devolverVideo(object $lead): bool
    {
        if (! $lead->user_id || ! $lead->pago_em) {
            return false;
        }
        $compra = DB::table('compras')->where('user_id', $lead->user_id)->where('videos_usados', '>', 0)->orderByDesc('id')->first();
        if (! $compra) {
            return false;
        }
        DB::table('compras')->where('id', $compra->id)->update(['videos_usados' => DB::raw('videos_usados - 1'), 'updated_at' => now()]);
        DB::table('leads')->where('id', $lead->id)->update(['pago_em' => null, 'devolvido_em' => now()]);

        return true;
    }

    private function avisarCliente(object $lead, string $assunto, string $texto): void
    {
        $user = $lead->user_id ? User::find($lead->user_id) : null;
        if ($user && ! $user->isAdmin()) {
            $this->links->enviar($user, $assunto, $texto);
        }
    }

    private function avisarAdmins(object $lead, string $erro, string $resumo): void
    {
        $admins = User::where('is_admin', true)->pluck('email')->all();
        if ($admins === []) {
            return;
        }
        $html = '<p><strong>'.e($resumo).'</strong></p>'
            .'<p>Pedido #'.$lead->id.'<br>Anúncio: <a href="'.e($lead->anuncio).'">'.e($lead->anuncio).'</a><br>'
            .'Cliente: '.e($lead->user_id ? (User::find($lead->user_id)?->email ?? '#'.$lead->user_id) : ($lead->email ?: 'vídeo grátis, sem conta')).'</p>'
            .'<p>Erro: '.e(mb_strimwidth($erro, 0, 600, '…')).'</p>';
        try {
            Mail::html($html, fn ($m) => $m->to($admins)->subject('Imocena · vídeo com problema (#'.$lead->id.')'));
        } catch (\Throwable $e) {
            Log::warning('Aviso de vídeo falhado não enviado', ['lead' => $lead->id, 'erro' => $e->getMessage()]);
        }
    }
}
