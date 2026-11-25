<?php

namespace App\Services\Imoplanta;

use App\Jobs\Imoplanta\AereoJob;
use App\Jobs\Imoplanta\AlterarModeloJob;
use App\Jobs\Imoplanta\AlterarReferenciaJob;
use App\Jobs\Imoplanta\PlantaVideoJob;
use App\Jobs\Imoplanta\QuadroJob;
use App\Jobs\Imoplanta\FotografarJob;
use App\Jobs\Imoplanta\LerPlantasJob;
use App\Jobs\Imoplanta\VideoJob;
use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use App\Models\User;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\ListingImport;
use App\Services\RealEstate\ListingScraper;
use App\Services\Themes\ThemeRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * What a customer can do with an Imoplanta property, with the money rules
 * enforced here (server side), not in the page:
 *
 *  - criar(): spends ONE credit, right away — before any AI work —
 *    from the purchase that expires first. No credit, no property. Admins don't
 *    spend credits.
 *  - pedirAlteracao(): one of the property's 3 AI changes to the 3D model.
 *  - alterarImagem(): one of an image's 2 AI changes.
 *  Both counters move with a conditional UPDATE (… WHERE refeitos < limit), so
 *  two clicks at once can't overspend; a change that fails is given back.
 *  - The shot plan and the 3D model are fixed once the images are made.
 *  - receberRender(): the browser's render of a shot, accepted once per image
 *    (a conditional UPDATE again), so a re-upload — a second tab, a page that
 *    resumed — never queues a second photo. Uploads cost nothing; the paid
 *    photo step starts only from the one accepted render.
 */
class Imoplantas
{
    public function __construct(private ImoplantaCustos $custos) {}

    /**
     * A property started with a credit: saved, then started at once (iniciar).
     *
     * @param  list<UploadedFile>  $plantas
     * @param  list<UploadedFile>  $referencias  elevations, sections, photos, material samples
     * @param  array{anuncio?: ?string, notas?: ?string}  $info  the customer's listing link and notes
     * @return Imoplanta|null null when the customer has no credit left (nothing is kept)
     */
    public function criar(User $user, string $nome, array $plantas, string $tipologia = 'isolada', ?int $piso = null, array $referencias = [], array $info = []): ?Imoplanta
    {
        $p = $this->guardar($user, $nome, $plantas, $tipologia, $piso, $referencias, $info);
        if ($this->iniciar($p)) {
            return $p->refresh();
        }
        $this->descartar($p);

        return null;
    }

    /** Deletes a property not paid for (a_pagar) and its files; false for any other. */
    public function descartar(Imoplanta $p): bool
    {
        if (Imoplanta::whereKey($p->id)->where('estado', Imoplanta::A_PAGAR)->delete() !== 1) {
            return false;
        }
        Storage::disk(config('contentmachine.imoplanta.disk'))->deleteDirectory($p->pasta());

        return true;
    }

    /**
     * The plans of a property not paid for yet (a_pagar): nothing is read and no AI money is spent until
     * iniciar(). A customer has one such property at most: a new one replaces it.
     */
    public function guardar(User $user, string $nome, array $plantas, string $tipologia = 'isolada', ?int $piso = null, array $referencias = [], array $info = []): Imoplanta
    {
        Imoplanta::where('user_id', $user->id)->where('estado', Imoplanta::A_PAGAR)->get()->each(fn (Imoplanta $velho) => $this->descartar($velho));
        $p = Imoplanta::create(['user_id' => $user->id, 'nome' => $nome, 'tipologia' => $tipologia, 'piso' => $tipologia === 'apartamento' ? $piso : null, 'estado' => Imoplanta::A_PAGAR, 'plantas' => [],
            'anuncio' => ($info['anuncio'] ?? null) ?: null, 'notas' => ($info['notas'] ?? null) ?: null]);
        $guardar = fn (array $fs, string $pasta) => array_map(fn (UploadedFile $f) => $f->store($p->pasta().'/'.$pasta, config('contentmachine.imoplanta.disk')), $fs);
        $p->update(['plantas' => $guardar($plantas, 'plantas'), 'referencias' => $guardar($referencias, 'referencias') ?: null]);

        return $p;
    }

    /**
     * Spends ONE property credit on a saved property — from their oldest purchase with one left
     * (compras.imoveis); admins and free accounts (User::imoplantaGratis) spend none — and starts reading its
     * plans. Once: a second call finds it already started. False when there is no credit.
     */
    public function iniciar(Imoplanta $p): bool
    {
        $user = $p->user;
        $ok = DB::transaction(function () use ($p, $user) {
            $semCusto = $user->is_admin || $user->imoplantaGratis();
            $compra = $semCusto ? null : DB::table('compras')->where('user_id', $user->id)
                ->whereColumn('imoveis_usados', '<', 'imoveis')->orderBy('id')->lockForUpdate()->first();
            if (! $semCusto && ! $compra) {
                return false;
            }
            if (Imoplanta::whereKey($p->id)->where('estado', Imoplanta::A_PAGAR)->update(['estado' => Imoplanta::A_LER, 'compra_id' => $compra?->id]) !== 1) {
                return false;
            }
            if ($compra) {
                DB::table('compras')->where('id', $compra->id)->increment('imoveis_usados', 1, ['updated_at' => now()]);
            }

            return true;
        });
        if ($ok) {
            $this->custos->stripe($p->refresh());
            LerPlantasJob::dispatch($p->id);
        }

        return $ok;
    }

    /**
     * A paid Stripe session (Compras::registar, from the webhook) for Imoplanta: the property it was bought from
     * starts (its credit is spent by iniciar), or that property's add-on does. A pack bought from the list
     * (no property) just leaves its credits on the account.
     */
    public function pago(object $compra, User $user): void
    {
        $p = $compra->imoplanta_id ? Imoplanta::where('user_id', $user->id)->find($compra->imoplanta_id) : null;
        if (! $p) {
            return;
        }
        if ($compra->extra) {
            $livre = fn (string $k) => in_array($p->extra($k), [null, 'a_pagar', 'falhou'], true);
            if ($compra->extra === 'ambos' ? $livre('planta') && $livre('video') : $livre($compra->extra)) {
                $this->iniciarExtra($p, $compra->extra);
            }

            return;
        }
        if ($p->estado === Imoplanta::A_PAGAR) {
            $this->iniciar($p);
        }
    }

    /**
     * Where a buy button goes: the Payment Link for $plano (stripe.links: imoplanta1|10|20, extra_planta|video|ambos)
     * with the property as client_reference_id (so the webhook knows what it paid for) and their email filled in.
     * Without the link configured, an email to us (as the video buttons do).
     */
    public static function link(User $user, string $plano, ?Imoplanta $p = null): string
    {
        $base = (string) config('contentmachine.stripe.links.'.$plano);
        if ($base === '') {
            return 'mailto:'.config('mail.from.address').'?subject='.rawurlencode('Imoplanta · '.$plano.($p ? ' · imóvel '.$p->id : ''));
        }

        return $base.'?'.http_build_query(array_filter(['client_reference_id' => $p ? 'imoplanta-'.$p->id : null, 'prefilled_email' => $user->email, 'locale' => 'pt']));
    }

    /** Uses one 3D re-do; false when none is left or the model can't change now. */
    public function pedirAlteracao(Imoplanta $p, string $pedido): bool
    {
        $ok = Imoplanta::whereKey($p->id)->where('estado', Imoplanta::MODELO)
            ->where('refeitos_3d', '<', (int) config('contentmachine.imoplanta.refazer_3d'))
            ->update(['refeitos_3d' => DB::raw('refeitos_3d + 1'), 'estado' => Imoplanta::A_ALTERAR, 'erro' => null, 'updated_at' => now()]) === 1;
        if ($ok) {
            $p->refresh()->update(['pedidos' => [...($p->pedidos ?? []), $pedido]]);
            AlterarModeloJob::dispatch($p->id, $pedido);
        }

        return $ok;
    }

    /** Gives back a 3D re-do whose change could not be made. */
    public function devolverAlteracao(Imoplanta $p, string $erro): void
    {
        Imoplanta::whereKey($p->id)->where('refeitos_3d', '>', 0)->decrement('refeitos_3d');
        $p->refresh()->update(['estado' => Imoplanta::MODELO, 'erro' => $erro]);
    }

    public function guardarCameras(Imoplanta $p, mixed $cameras): bool
    {
        if (! $p->editavel() || ! $p->casa) {
            return false;
        }
        $p->update(['cameras' => ImoplantaCasa::validarCameras($cameras, $p->casa)]);

        return true;
    }

    /**
     * Fixes the plan and makes one image per planned shot, waiting for its
     * browser render (receberRender), which then goes to the photographer.
     * Once per property.
     */
    public function gerarImagens(Imoplanta $p): bool
    {
        if (! in_array($p->estado, [Imoplanta::MODELO, Imoplanta::REFERENCIAS], true) || empty($p->cameras)
            || ($p->estado === Imoplanta::REFERENCIAS && ! $p->referenciasProntas() && ! $p->referenciasFalharam())) {
            return false;
        }
        $imagens = DB::transaction(function () use ($p) {
            if (Imoplanta::whereKey($p->id)->whereIn('estado', [Imoplanta::MODELO, Imoplanta::REFERENCIAS])->update(['estado' => Imoplanta::IMAGENS, 'erro' => null]) !== 1) {
                return null;
            }

            return array_map(fn ($c, $i) => $p->imagens()->create(['numero' => $i + 1, 'camera' => $c, 'estado' => ImoplantaImagem::A_RENDERIZAR]),
                array_values($p->cameras), array_keys(array_values($p->cameras)));
        });

        return $imagens !== null;
    }

    /**
     * Admins only, for testing: back to the model and its cameras, the images and their files gone, to move the
     * cameras and generate again. Refused while an image is still with the AI (its job would write to a deleted row).
     * The money already spent stays in the cost ledger.
     */
    public function refazerFotografias(Imoplanta $p): bool
    {
        if ($p->estado !== Imoplanta::IMAGENS || $p->imagens()->whereIn('estado', [ImoplantaImagem::A_FOTOGRAFAR, ImoplantaImagem::A_ALTERAR])->exists()) {
            return false;
        }
        $disco = Storage::disk(config('contentmachine.imoplanta.disk'));
        $ficheiros = $p->imagens()->get(['render', 'foto'])->flatMap(fn ($i) => [$i->render, $i->foto])->filter()->all();
        $ok = DB::transaction(function () use ($p) {
            if (Imoplanta::whereKey($p->id)->where('estado', Imoplanta::IMAGENS)->update(['estado' => Imoplanta::MODELO, 'erro' => null]) !== 1) {
                return false;
            }
            $p->imagens()->delete();

            return true;
        });
        if ($ok) {
            $disco->delete($ficheiros);
        }

        return $ok;
    }

    /**
     * Starts the walkthrough video once every photo is done. Once per property; an admin may make it again
     * (from new clips). False when it can't start now.
     */
    public function pedirVideo(Imoplanta $p, User $user): bool
    {
        $p->loadMissing('imagens');
        $prontas = $p->imagens->isNotEmpty() && $p->imagens->every(fn (ImoplantaImagem $i) => $i->estado === ImoplantaImagem::PRONTA && $i->foto);
        if (! $prontas || $p->video_estado === 'a_fazer' || ($p->video && ! $user->is_admin)) {
            return false;
        }
        $ok = Imoplanta::whereKey($p->id)->where(fn ($q) => $q->whereNull('video_estado')->orWhere('video_estado', '!=', 'a_fazer'))
            ->update(['video_estado' => 'a_fazer', 'video_erro' => null] + ($p->video ? ['video_clips' => null] : [])) === 1;
        if ($ok) {
            VideoJob::dispatch($p->id);
        }

        return $ok;
    }

    /**
     * Step 1 → 2: saves the cameras (validated), fixes the model and them, and starts the finishes board; each
     * storey's 3D floor plan starts when the browser uploads its top-view render (receberAereo).
     */
    public function confirmarCameras(Imoplanta $p, mixed $cameras): bool
    {
        if (! $this->guardarCameras($p, $cameras)) {
            return false;
        }
        if (Imoplanta::whereKey($p->id)->where('estado', Imoplanta::MODELO)
            ->update(['estado' => Imoplanta::REFERENCIAS, 'erro' => null, 'quadro_estado' => $p->acabamentos_img ? null : 'a_fazer']) !== 1) {
            return false;
        }
        if (! $p->acabamentos_img) {
            QuadroJob::dispatch($p->id);
        }

        return true;
    }

    /**
     * Step 2: the owner's change to the finishes board ($nivel null) or to one storey's 3D floor plan — up to
     * refazer_referencias each (admins: no limit), one at a time. The counter moves first (given back on failure).
     */
    public function alterarReferencia(Imoplanta $p, ?int $nivel, string $pedido, User $user): bool
    {
        $p->refresh();
        $ocupado = $p->quadro_estado === 'a_fazer' || collect($p->aereos ?? [])->contains(fn ($a) => ($a['estado'] ?? null) === 'a_fazer');
        if ($p->estado !== Imoplanta::REFERENCIAS || $ocupado || ($nivel === null ? ! $p->acabamentos_img : ! $p->aereo($nivel))
            || (! $user->is_admin && $p->refeitosReferencia($nivel) === 0)) {
            return false;
        }
        if ($nivel === null) {
            $p->update(['acabamentos_refeitos' => $p->acabamentos_refeitos + 1, 'quadro_estado' => 'a_fazer', 'quadro_erro' => null]);
        } else {
            $p->guardarAereo($nivel, 'refeitos', (int) (($p->aereos[$nivel] ?? [])['refeitos'] ?? 0) + 1);
            $p->guardarAereo($nivel, 'erro', null);
            $p->guardarAereo($nivel, 'estado', 'a_fazer');
        }
        AlterarReferenciaJob::dispatch($p->id, $nivel, $pedido);

        return true;
    }

    /** Step 2, free: makes again the board and the floor plans that failed. */
    public function tentarReferencias(Imoplanta $p): void
    {
        if ($p->quadro_estado === 'falhou') {
            $p->update(['quadro_estado' => 'a_fazer', 'quadro_erro' => null]);
            QuadroJob::dispatch($p->id);
        }
        foreach ($p->niveisComPlanta() as $n) {
            if ((($p->aereos ?? [])[$n]['estado'] ?? null) === 'falhou') {
                $p->guardarAereo($n, 'estado', 'a_fazer');
                AereoJob::dispatch($p->id, $n);
            }
        }
    }

    /**
     * Step 4's add-ons ('planta': the plan video; 'video': the Imocena reel; 'ambos'). Payment comes first,
     * before any question: free for admins and free accounts, and again (no charge) after one failed; the reel
     * may spend a video credit; otherwise Stripe. Once paid, the plan video starts and the reel asks for its
     * details (estado 'dados', enviarDadosReel). Returns 'iniciado' (paid: started or asking), 'pagar' (send
     * them to Checkout) or false (not now: not ready yet, paid already, being made, or made — admins may redo).
     */
    public function pedirExtra(Imoplanta $p, string $chave, User $user): string|false
    {
        $p->refresh();
        if ($chave === 'ambos') {   // the two together, cheaper: only while neither has been asked for
            $livres = collect(['planta', 'video'])->every(fn ($k) => in_array($p->extra($k), [null, 'a_pagar'], true));
            if (! $livres || $p->niveisComPlanta() === [] || ! collect($p->niveisComPlanta())->every(fn ($n) => $p->aereo($n)) || ! $p->video) {
                return false;
            }
            if ($user->is_admin || $user->imoplantaGratis()) {
                $this->iniciarExtra($p, 'ambos');

                return 'iniciado';
            }
            $p->guardarExtra('planta', 'estado', 'a_pagar');
            $p->guardarExtra('video', 'estado', 'a_pagar');

            return 'pagar';
        }
        $estado = $p->extra($chave);
        $pronto = match ($chave) {
            'planta' => $p->niveisComPlanta() !== [] && collect($p->niveisComPlanta())->every(fn ($n) => $p->aereo($n)),
            'video' => (bool) $p->video,
            default => false,
        };
        if (! $pronto || in_array($estado, ['a_fazer', 'dados'], true) || ($estado === 'pronto' && ! $user->is_admin)) {
            return false;
        }
        $pagoComCredito = $chave === 'video' && $p->extra('video', 'compra');   // a failed reel's credit went back: spend one again
        if (($estado === 'falhou' && ! $pagoComCredito) || $user->is_admin || $user->imoplantaGratis()) {
            $this->iniciarExtra($p, $chave);

            return 'iniciado';
        }
        // the reel: one of the account's video credits («Os meus vídeos»), when it has any
        if ($chave === 'video') {
            $compra = DB::transaction(function () use ($user) {
                $c = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')
                    ->orderBy('id')->lockForUpdate()->first();
                if ($c) {
                    DB::table('compras')->where('id', $c->id)->increment('videos_usados', 1, ['updated_at' => now()]);
                }

                return $c?->id;
            });
            $p->guardarExtra('video', 'compra', $compra);
            $p->guardarExtra('video', 'devolvido', null);
            if ($compra) {
                $this->iniciarExtra($p, 'video');

                return 'iniciado';
            }
        }
        $p->guardarExtra($chave, 'estado', 'a_pagar');

        return 'pagar';
    }

    /**
     * Starts an add-on (paid, free, or a retry): the plan video's job, or the reel through the listing pipeline;
     * 'ambos' starts both. The reel uses the plan video when there is one: while it is being made, the reel
     * waits for it (PlantaVideoJob calls continuarReel when it ends, made or not).
     */
    public function iniciarExtra(Imoplanta $p, string $chave): void
    {
        if ($chave === 'ambos') {
            $this->iniciarExtra($p, 'planta');
            $this->iniciarExtra($p->refresh(), 'video');

            return;
        }
        if ($chave === 'video') {   // paid: now its details (the form; what they gave before stays filled in)
            $p->guardarExtra('video', 'estado', 'dados');
            $p->guardarExtra('video', 'erro', null);

            return;
        }
        $p->guardarExtra($chave, 'estado', 'a_fazer');
        $p->guardarExtra($chave, 'erro', null);
        if ($chave === 'planta') {
            PlantaVideoJob::dispatch($p->id);

            return;
        }
    }

    /**
     * The paid reel's details (all optional: listing link or facts, and the contact for the end card — name,
     * phone, email, photo, logo): saved, and the reel starts — after the plan video if that is being made.
     */
    public function enviarDadosReel(Imoplanta $p, array $dados): bool
    {
        if ($p->refresh()->extra('video') !== 'dados') {
            return false;
        }
        $p->guardarExtra('video', 'dados', self::dadosReel($dados));
        $p->guardarExtra('video', 'estado', 'a_fazer');
        if ($p->refresh()->extra('planta') === 'a_fazer') {
            $p->guardarExtra('video', 'espera', true);

            return true;
        }
        $this->continuarReel($p);

        return true;
    }

    /** Starts a reel that was waiting for the plan video (or one not waiting). */
    public function continuarReel(Imoplanta $p): void
    {
        $p->guardarExtra('video', 'espera', null);
        try {
            $clip = $this->adotarPrevia($p->refresh()) ?? $this->comecarReel($p);
            $p->guardarExtra('video', 'clip', $clip);
        } catch (\Throwable $e) {
            report($e);
            $this->reelFalhou($p);
            $p->guardarExtra('video', 'erro', Str::limit($e->getMessage(), 250));
        }
    }

    /**
     * The reel's watermarked preview, once, as soon as the walkthrough is done (the plan video's preview is one
     * demo GIF for everyone, public/imoplanta-demo/demo-planta.gif: nothing to make): the reel from what we know (the listing link given with the plans,
     * their notes). Each only if not bought already.
     */
    public function fazerPrevias(Imoplanta $p): void
    {
        if (! config('contentmachine.imoplanta.previas') || ! $p->video) {
            return;
        }
        if ($p->extra('video', 'previa_clip') === null && in_array($p->extra('video'), [null, 'a_pagar'], true)) {
            try {
                $dados = self::dadosReel(['anuncio' => $p->anuncio && ListingScraper::supports($p->anuncio) ? $p->anuncio : '', 'notas' => (string) $p->notas]);
                $p->guardarExtra('video', 'previa_dados', $dados);
                $p->guardarExtra('video', 'previa_clip', $this->comecarReel($p, $dados, true));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * One of the real estate tracks in the music library (Recursos → Música; files named «real-estate…»), at
     * random; else the listing reels' default track. '' when the library has neither (the render then picks any).
     */
    public static function musicaImobiliaria(): string
    {
        $musicas = app(\App\Services\Shorts\MusicLibrary::class);
        $faixas = array_values(array_filter(array_column($musicas->all(), 'name'), fn ($n) => str_starts_with(Str::lower($n), 'real-estate')));

        return $faixas !== [] ? $faixas[array_rand($faixas)] : ($musicas->pathFor((string) config('contentmachine.real_estate.music')) ? (string) config('contentmachine.real_estate.music') : '');
    }

    /** The reel's details as compared: trimmed, empty ones dropped. */
    public static function dadosReel(array $d): array
    {
        return array_filter(array_map(fn ($v) => trim((string) $v), $d), fn ($v) => $v !== '');
    }

    /**
     * The reel bought as previewed (same details, and no plan video to add): its preview, rendered again without
     * the mark — no new script, voice or AI. Null when it can't be (then a new reel is made).
     */
    private function adotarPrevia(Imoplanta $p): ?string
    {
        $id = $p->extra('video', 'previa_clip');
        $clip = $id ? app(ClipStore::class)->find($id) : null;
        $comPlanta = $p->extra('planta') === 'pronto';
        if (! $clip || $clip->status !== ClipRecord::STATUS_DONE || $comPlanta
            || self::dadosReel((array) $p->extra('video', 'dados')) != (array) $p->extra('video', 'previa_dados')) {
            return null;
        }
        $clip->update(['status' => ClipRecord::STATUS_RENDERING, 'meta' => array_merge($clip->meta ?? [], ['watermark' => null, 'watermark_grande' => null])]);
        RenderJob::dispatch($clip->id);

        return $clip->id;
    }

    /** The reel is done ($ok) or failed: a failed one paid with a credit gives the credit back now (once). */
    public function reelTerminou(Imoplanta $p, bool $ok): void
    {
        $ok ? $p->guardarExtra('video', 'estado', 'pronto') : $this->reelFalhou($p);
    }

    private function reelFalhou(Imoplanta $p): void
    {
        $p->guardarExtra('video', 'estado', 'falhou');
        $compra = $p->extra('video', 'compra');
        if (! $compra || $p->extra('video', 'devolvido')) {
            return;
        }
        $p->guardarExtra('video', 'devolvido', true);   // once, however often the failure is seen
        DB::table('compras')->where('id', $compra)->where('videos_usados', '>', 0)->decrement('videos_usados', 1, ['updated_at' => now()]);
    }

    /**
     * The walkthrough made into an Imocena reel: the same pipeline as «Os meus vídeos» (ListingImport), with the
     * walkthrough as the listing video (cut per room). A supported listing link gives the facts; otherwise the
     * details typed here (all optional) and what the plans say. No studio agent, photo or logo goes in it.
     * Returns the clip id.
     */
    private function comecarReel(Imoplanta $p, ?array $dados = null, bool $previa = false): string
    {
        $d = array_map(fn ($v) => trim((string) $v), $dados ?? (array) $p->extra('video', 'dados'));
        $video = 'clips/uploads/'.Str::random(40).'.mp4';
        Storage::disk(config('contentmachine.clips.disk'))->put($video, Storage::disk(config('contentmachine.imoplanta.disk'))->get($p->video));
        $link = ($d['anuncio'] ?? '') !== '' && ListingScraper::supports($d['anuncio']) ? $d['anuncio'] : '';
        $semConsultor = ($d['nome'] ?? '') === '' && $link === '';
        $notas = implode("\n", array_filter([
            $d['notas'] ?? '',
            'As imagens são 3D, geradas a partir das plantas: legenda «Imagem 3D do projeto».',
            $semConsultor ? 'Não há consultor nem agência: não termine com «fale com…»; termine com o preço, se houver'
                .(($c = implode(' / ', array_filter([$d['telefone'] ?? '', $d['email'] ?? '']))) !== '' ? ', e o contacto '.$c : '').'.' : '',
        ]));
        $meta = ['listing_video' => $video, 'theme' => app(ThemeRepository::class)->default()->slug, 'script_notes' => $notas, 'imoplanta' => $p->id,
            // their contact for the cards (over the listing's agent, when a link is given); photo and logo are clips-disk uploads
            'contacto' => array_filter(['name' => $d['nome'] ?? '', 'phone' => $d['telefone'] ?? '', 'email' => $d['email'] ?? '']),
            'agent_photo' => $d['foto'] ?? null, 'agency_logo' => $d['logo'] ?? null];
        $meta['musica'] = self::musicaImobiliaria();
        if ($previa) {   // a big mark across it, until bought
            $meta += ['watermark' => (string) config('contentmachine.imoplanta.marca_previa'), 'watermark_grande' => true];
        }
        $planta = ! $previa && $p->extra('planta') === 'pronto' ? $p->extra('planta', 'video') : null;
        if ($planta && Storage::disk(config('contentmachine.imoplanta.disk'))->exists($planta)) {   // the plan video add-on goes in the reel too
            $meta['imoplanta_planta'] = 'clips/uploads/'.Str::random(40).'.mp4';
            Storage::disk(config('contentmachine.clips.disk'))->put($meta['imoplanta_planta'], Storage::disk(config('contentmachine.imoplanta.disk'))->get($planta));
        }
        if ($link === '') {
            $preco = preg_replace('/\D/', '', $d['preco'] ?? '');
            $divisoes = collect($p->casa['niveis'] ?? [])->flatMap(fn ($n) => array_column($n['divisoes'] ?? [], 'nome'))->filter()->implode(', ');
            $meta['listing_manual'] = [
                'url' => '',
                'title' => $p->nome,
                'description' => $d['notas'] ?? '',
                'price' => $preco !== '' ? (int) $preco : null,
                'details' => array_filter([
                    'Tipologia' => $p->tipologiaRotulo(),
                    'Localização' => $d['local'] ?? '',
                    'Divisões' => $divisoes,
                ]),
                'photos' => [],
                'agent' => ['name' => $d['nome'] ?? '', 'phone' => $d['telefone'] ?? '', 'email' => $d['email'] ?? '', 'photo' => null],
                'agency' => '',
                'ami' => '',
            ];
        }

        return app(ListingImport::class)->start($link, $meta)->id;
    }

    /**
     * Stores the browser's top-view render of one storey (its realistic 3D floor plan's starting point), once,
     * and queues that floor plan straight away, so it is ready (or being made) before the photos need it.
     */
    public function receberAereo(Imoplanta $p, UploadedFile $png, int $nivel): bool
    {
        $disco = config('contentmachine.imoplanta.disk');
        if (! in_array($p->estado, [Imoplanta::REFERENCIAS, Imoplanta::IMAGENS], true) || ! in_array($nivel, $p->niveisComPlanta(), true)) {
            return false;
        }
        $caminho = $png->storeAs($p->pasta().'/renders', 'aereo-'.$nivel.'-'.Str::random(8).'.png', $disco);
        $ok = DB::transaction(function () use ($p, $nivel, $caminho, $disco) {
            $atual = Imoplanta::whereKey($p->id)->lockForUpdate()->first();
            if (($r = $atual->aereo($nivel, 'render')) && Storage::disk($disco)->exists($r)) {
                return false;
            }
            $atual->guardarAereo($nivel, 'render', $caminho);
            $atual->guardarAereo($nivel, 'estado', 'a_fazer');

            return true;
        });
        if (! $ok) {
            Storage::disk($disco)->delete($caminho);

            return false;
        }
        AereoJob::dispatch($p->id, $nivel);

        return true;
    }

    /** Stores a shot's browser render and queues its photo; false when it already has one (or isn't waiting). */
    public function receberRender(ImoplantaImagem $img, UploadedFile $png): bool
    {
        if ($img->estado !== ImoplantaImagem::A_RENDERIZAR) {
            return false;
        }
        $disco = config('contentmachine.imoplanta.disk');
        $caminho = $png->storeAs($img->imoplanta->pasta().'/renders', $img->numero.'-'.Str::random(8).'.png', $disco);
        $ok = ImoplantaImagem::whereKey($img->id)->where('estado', ImoplantaImagem::A_RENDERIZAR)
            ->update(['render' => $caminho, 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'erro' => null, 'updated_at' => now()]) === 1;
        if (! $ok) {
            Storage::disk($disco)->delete($caminho);   // another upload won the race

            return false;
        }
        FotografarJob::dispatch($img->id, false);

        return true;
    }

    /** Uses one of the image's re-dos; false when none is left or it is busy. */
    public function alterarImagem(ImoplantaImagem $img, string $pedido): bool
    {
        $ok = ImoplantaImagem::whereKey($img->id)->where('estado', ImoplantaImagem::PRONTA)
            ->where('refeitos', '<', (int) config('contentmachine.imoplanta.refazer_imagem'))
            ->update(['refeitos' => DB::raw('refeitos + 1'), 'estado' => ImoplantaImagem::A_ALTERAR, 'pedido' => $pedido, 'erro' => null, 'updated_at' => now()]) === 1;
        if ($ok) {
            FotografarJob::dispatch($img->id, true);
        }

        return $ok;
    }

    /**
     * A fresh photo from the same render, for a result that came out wrong. Uses one of the image's changes
     * (admins: unlimited). If it fails, the old photo stays and "Tentar de novo" retries for free.
     */
    public function gerarImagemDeNovo(ImoplantaImagem $img, bool $admin = false): bool
    {
        $q = ImoplantaImagem::whereKey($img->id)->where('estado', ImoplantaImagem::PRONTA)->whereNotNull('render');
        $ok = ($admin ? $q : $q->where('refeitos', '<', (int) config('contentmachine.imoplanta.refazer_imagem')))
            ->update(['refeitos' => DB::raw($admin ? 'refeitos' : 'refeitos + 1'), 'estado' => ImoplantaImagem::A_FOTOGRAFAR, 'erro' => null, 'updated_at' => now()]) === 1;
        if ($ok) {
            FotografarJob::dispatch($img->id, false, true);
        }

        return $ok;
    }

    public function devolverAlteracaoImagem(ImoplantaImagem $img, string $erro): void
    {
        ImoplantaImagem::whereKey($img->id)->where('refeitos', '>', 0)->decrement('refeitos');
        $img->refresh()->update(['estado' => ImoplantaImagem::PRONTA, 'erro' => $erro]);
    }

    /** After a failure, start again from the last good point — free. */
    public function tentarDeNovo(Imoplanta $p): void
    {
        if ($p->estado === Imoplanta::FALHOU) {
            $p->update(['estado' => $p->casa ? Imoplanta::MODELO : Imoplanta::A_LER, 'erro' => null]);
            if (! $p->casa) {
                LerPlantasJob::dispatch($p->id);
            }

            return;
        }
        foreach ($p->imagens()->where('estado', ImoplantaImagem::FALHOU)->get() as $img) {
            $this->repetirImagem($img);
        }
    }

    /** One failed image, again — free. No render yet: back to waiting for the browser's (the page renders it again). */
    public function repetirImagem(ImoplantaImagem $img): bool
    {
        $estado = $img->render ? ImoplantaImagem::A_FOTOGRAFAR : ImoplantaImagem::A_RENDERIZAR;
        $ok = ImoplantaImagem::whereKey($img->id)->where('estado', ImoplantaImagem::FALHOU)
            ->update(['estado' => $estado, 'erro' => null, 'updated_at' => now()]) === 1;
        if ($ok && $img->render) {
            FotografarJob::dispatch($img->id, false);
        }

        return $ok;
    }
}
