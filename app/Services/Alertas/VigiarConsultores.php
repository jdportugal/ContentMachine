<?php

namespace App\Services\Alertas;

use App\Services\RealEstate\ListingScraper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * New-listing alerts for agents who asked for them (the box on the free-video form):
 *
 *  1. each consenting lead becomes a watched agent (their page on the portal);
 *  2. every hour the page is read; the first reading only records what is there;
 *  3. a listing that was not there before, and has no Imocena video yet, is emailed
 *     to the agent with a link that starts its video. One email a day at most.
 *
 * Only leads with marketing_em set and marketing_cancelado_em null are ever emailed,
 * and every email carries the one-click unsubscribe link.
 */
class VigiarConsultores
{
    /** More "new" listings than this in one reading is the page changing, not the agent publishing. */
    private const MAX_NOVOS = 5;

    private const HORAS_ENTRE_AVISOS = 20;

    private const MAX_CONHECIDOS = 400;

    public function __construct(private PaginaDoConsultor $paginas) {}

    /** Consenting leads not yet watched (and pages not found before, a few more times). */
    public function sincronizar(): int
    {
        $novos = 0;
        $leads = DB::table('leads')->whereNotNull('marketing_em')->whereNull('marketing_cancelado_em')->whereNotNull('email')
            ->whereNotIn('id', DB::table('consultores_vigiados')->where(fn ($q) => $q->where('estado', '!=', 'sem-pagina')->orWhere('falhas', '>=', 3))->select('lead_id'))
            ->orderBy('id')->limit(200)->get(['id', 'email', 'anuncio']);

        foreach ($leads as $lead) {
            $portal = ListingScraper::portal($lead->anuncio);
            if (! in_array($portal, ['remax', 'iad', 'exp'], true)) {
                continue;
            }
            $pagina = rescue(fn () => $this->paginas->de($lead->anuncio), null, report: false);
            $email = mb_strtolower(trim($lead->email));
            $estado = match (true) {
                ! $pagina => 'sem-pagina',
                DB::table('consultores_vigiados')->where('email', $email)->where('chave', $pagina['chave'])->where('estado', 'ativo')->where('lead_id', '!=', $lead->id)->exists() => 'duplicado',
                default => 'ativo',
            };
            $existe = DB::table('consultores_vigiados')->where('lead_id', $lead->id)->first();
            $dados = ['email' => $email, 'portal' => $portal, 'estado' => $estado, 'chave' => $pagina['chave'] ?? null, 'pagina' => $pagina['url'] ?? null,
                'falhas' => $pagina ? 0 : ($existe->falhas ?? 0) + 1, 'updated_at' => now()];
            $existe
                ? DB::table('consultores_vigiados')->where('id', $existe->id)->update($dados)
                : DB::table('consultores_vigiados')->insert($dados + ['lead_id' => $lead->id, 'created_at' => now()]);
            $novos += $estado === 'ativo' ? 1 : 0;
        }

        return $novos;
    }

    /** Read the pages due for a check. @return array{lidos: int, avisos: int, falhas: int} */
    public function verificar(int $limite = 200): array
    {
        $r = ['lidos' => 0, 'avisos' => 0, 'falhas' => 0];
        $consultores = DB::table('consultores_vigiados as c')->join('leads as l', 'l.id', '=', 'c.lead_id')
            ->where('c.estado', 'ativo')->whereNotNull('l.marketing_em')->whereNull('l.marketing_cancelado_em')
            ->where(fn ($q) => $q->whereNull('c.avisado_em')->orWhere('c.avisado_em', '<', now()->subHours(self::HORAS_ENTRE_AVISOS)))
            ->orderByRaw('c.verificado_em is not null')->orderBy('c.verificado_em')->limit($limite)
            ->get(['c.*', 'l.nome']);

        foreach ($consultores as $i => $c) {
            if ($i > 0 && ($pausa = (int) config('contentmachine.alertas.pausa_ms', 1500)) > 0) {
                usleep($pausa * 1000);   // one page at a time, unhurried
            }
            $anuncios = rescue(fn () => $this->paginas->anuncios($c->portal, $c->pagina), [], report: false);
            if ($anuncios === []) {
                DB::table('consultores_vigiados')->where('id', $c->id)->update(['falhas' => $c->falhas + 1, 'verificado_em' => now(), 'updated_at' => now()]);
                $r['falhas']++;

                continue;
            }
            $r['lidos']++;
            $ids = array_column($anuncios, 'id');
            $conhecidos = $c->conhecidos === null ? null : (array) json_decode($c->conhecidos, true);
            $novos = $conhecidos === null ? [] : array_values(array_filter($anuncios, fn ($a) => ! in_array($a['id'], $conhecidos, true)));
            $muda = ['conhecidos' => json_encode(array_slice(array_values(array_unique(array_merge($ids, $conhecidos ?? []))), 0, self::MAX_CONHECIDOS)),
                'falhas' => 0, 'verificado_em' => now(), 'updated_at' => now()];

            // Not worth an email: a listing that already has its video here.
            $porAvisar = array_values(array_filter($novos, fn ($a) => ! $a['url'] || ! DB::table('leads')->where('anuncio', $a['url'])->exists()));
            if (count($novos) > self::MAX_NOVOS) {
                Log::warning('Alertas: demasiados anúncios novos de uma vez, a página deve ter mudado', ['consultor' => $c->id, 'novos' => count($novos)]);
                $porAvisar = [];
            }
            if ($porAvisar !== [] && $this->avisar($c, $porAvisar)) {
                $muda['avisado_em'] = now();
                $r['avisos']++;
                DB::table('avisos_imoveis')->insert(array_map(fn ($a) => ['consultor_id' => $c->id, 'anuncio_id' => $a['id'],
                    'titulo' => mb_substr($a['titulo'], 0, 250), 'url' => $a['url'], 'created_at' => now()], $porAvisar));
            } elseif ($porAvisar !== []) {
                // The email did not go out: leave these as unseen, to try again next hour.
                $muda['conhecidos'] = json_encode(array_values(array_diff((array) json_decode($muda['conhecidos'], true), array_column($porAvisar, 'id'))));
            }
            DB::table('consultores_vigiados')->where('id', $c->id)->update($muda);
        }

        return $r;
    }

    /** One email with the new listings (three at most), each with the link that starts its video. */
    private function avisar(object $c, array $anuncios): bool
    {
        $base = rtrim((string) config('app.url'), '/').'/?utm_source=imocena&utm_medium=email&utm_campaign=imovel-novo';
        $itens = array_map(fn ($a) => ['titulo' => $a['titulo'] !== '' ? $a['titulo'] : 'Imóvel novo',
            'link' => $base.($a['url'] ? '&anuncio='.urlencode($a['url']) : '').'#pedido'], array_slice($anuncios, 0, 3));
        $cancelar = URL::signedRoute('lead.emails.cancelar', ['lead' => $c->lead_id]);
        $assunto = count($anuncios) === 1 ? 'Publicou um imóvel novo. Quer o vídeo dele?' : 'Publicou '.count($anuncios).' imóveis novos. Quer os vídeos?';

        try {
            Mail::html(view('emails.imovel-novo', ['nome' => trim((string) strtok((string) $c->nome, ' ')), 'itens' => $itens,
                'mais' => max(0, count($anuncios) - 3), 'cancelar' => $cancelar])->render(), function ($m) use ($c, $assunto, $cancelar) {
                    $m->to($c->email)->subject($assunto);
                    $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$cancelar.'>');
                    $m->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Aviso de imóvel novo não enviado', ['consultor' => $c->id, 'erro' => $e->getMessage()]);

            return false;
        }
    }
}
