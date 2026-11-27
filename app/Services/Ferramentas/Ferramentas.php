<?php

namespace App\Services\Ferramentas;

use App\Jobs\Ferramentas\FerramentaFramesJob;
use App\Jobs\Ferramentas\FerramentaVideoJob;
use App\Models\FerramentaIa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The AI video tools: one photo (or a floor plan) → a start and an end frame the customer approves → one
 * Kling clip. Paid in video credits (Pagamentos\Creditos), each tool its own `creditos`. The texts here are what the landing
 * page and the customer pages show; `ai` lists the frames the AI makes, in order (any other is the customer's
 * upload, or for the plan, drawn by code); `segundos` is the clip length (it sets the worst-case cost: see the design doc).
 */
class Ferramentas
{
    /** @return array<string, array{nome:string,linha:string,texto:string,envio:string,inicio:string,fim:string,ai:list<string>,segundos:int,creditos:int,video?:bool,direcao?:bool,campo?:array{titulo:string,ajuda:string,exemplo:string},obras?:array<string,array{nome:string,exemplo:string,segundos?:int}>,exemplo?:string}> */
    public static function lista(): array
    {
        return [
            'drone' => [
                'nome' => 'Vista drone', 'linha' => 'Um drone dá a volta à casa, visto do ar.',
                'texto' => 'A partir de uma fotografia da casa, um drone sobrevoa-a e roda à sua volta, a mostrar a casa, o terreno e a envolvente vistos do ar.',
                'envio' => 'Fotografia da fachada ou do exterior', 'inicio' => 'Vista aérea', 'fim' => 'Depois de rodar', 'ai' => ['inicio', 'fim'], 'segundos' => 10, 'creditos' => 2,
                'exemplo' => 'Ex.: um pouco mais alto, mostrar também o quintal',
            ],
            'lateral' => [
                'nome' => 'Movimento lateral', 'linha' => 'A câmara desliza devagar para o lado, como um gimbal em câmara lenta.',
                'texto' => 'A partir de uma fotografia, a câmara desliza suavemente para a esquerda ou para a direita, com profundidade, como um plano de gimbal em câmara lenta. Funciona em interiores e exteriores.',
                'envio' => 'Fotografia de uma divisão ou do exterior', 'inicio' => 'A sua fotografia', 'fim' => 'Depois de deslizar', 'ai' => ['fim'], 'segundos' => 5, 'creditos' => 2, 'direcao' => true,
                'exemplo' => 'Ex.: o vidro da porta está torto, endireitar',
            ],
            'limpeza' => [
                // a photo, not a video (user, 2026-11-26): no clip, the cleaned photo is the result
                'nome' => 'Limpeza IA', 'linha' => 'A sua fotografia, sem a desarrumação e os objetos a mais.',
                'texto' => 'Caixas, roupa, carros, caixotes do lixo: tudo o que distrai sai da fotografia, e o espaço fica arrumado e pronto a mostrar. As paredes, o chão e as janelas não mudam.',
                'envio' => 'Fotografia da divisão ou do exterior', 'inicio' => 'A sua fotografia', 'fim' => 'Fotografia limpa', 'ai' => ['fim'], 'segundos' => 0, 'creditos' => 1, 'video' => false,
                'campo' => ['titulo' => 'O que quer retirar?', 'ajuda' => 'Opcional: já retiramos a desarrumação e os objetos soltos.', 'exemplo' => 'Ex.: o carro em frente à garagem, as caixas no canto'],
                'exemplo' => 'Ex.: retirar também o caixote do lixo à esquerda',
            ],
            'remodelacao' => [
                'nome' => 'Remodelação IA', 'linha' => 'Timelapse da obra, do estado atual ao acabado: uma divisão, uma casa ou uma ruína.',
                'texto' => 'Mostre o potencial de um imóvel: em timelapse, os operários renovam uma divisão ou uma fachada, ou recuperam uma ruína. Diga-nos o que quer ver construído.',
                'envio' => 'Fotografia do espaço como está hoje', 'inicio' => 'Estado atual', 'fim' => 'Depois da obra', 'ai' => ['fim'], 'segundos' => 10, 'creditos' => 3,
                // the type of works (opcoes.obra), asked first (user, 2026-11-27); a build from the ground takes 15 s
                'obras' => [
                    'interior' => ['nome' => 'Renovação interior', 'exemplo' => 'Ex.: escandinavo, madeira clara, azulejo branco'],
                    'exterior' => ['nome' => 'Fachada / casa completa', 'exemplo' => 'Ex.: reboco branco, caixilharia preta, telhado novo'],
                    'ruina' => ['nome' => 'Recuperar ruína', 'exemplo' => 'Ex.: moradia T2 de um piso, pedra à vista, telhado de telha', 'segundos' => 15],
                ],
                'campo' => ['titulo' => 'Descreva a obra', 'ajuda' => 'Opcional: o que quer construído, o tamanho, o estilo. Sem indicação, fazemos uma obra moderna e de gama alta, à medida do espaço.', 'exemplo' => 'Ex.: escandinavo, madeira clara, azulejo branco'],
                'exemplo' => 'Ex.: o chão em madeira mais escura',
            ],
            'construcao' => [
                'nome' => 'Construção IA', 'linha' => 'Num terreno vazio, os operários constroem a casa de raiz.',
                'texto' => 'Envie a fotografia de um terreno e diga-nos que casa quer: desenhamos a casa no sítio certo e, em timelapse, os operários preparam o terreno, fazem as fundações, a estrutura, as paredes, o telhado e os acabamentos.',
                'envio' => 'Fotografia do terreno', 'inicio' => 'O terreno', 'fim' => 'A casa construída', 'ai' => ['fim'], 'segundos' => 15, 'creditos' => 3,
                'campo' => ['titulo' => 'Que casa quer construir?', 'ajuda' => 'Opcional: os pisos, o tamanho, o estilo. Sem indicação, desenhamos uma moradia moderna à medida do terreno.', 'exemplo' => 'Ex.: moradia T3 de dois pisos, moderna, com piscina'],
                'exemplo' => 'Ex.: a casa mais recuada da estrada',
            ],
            'planta' => [
                // as Imoplanta's plan video (user, 2026-11-27): the plan read into a 3D model, rendered from above in the
                // customer's browser, made real by the AI; the clip builds it from the bare footprint, then furnishes it
                'nome' => 'Planta animada', 'linha' => 'A planta 2D passa a 3D, ergue-se e enche-se de mobília.',
                'texto' => 'Envie a planta de um piso. A casa aparece vista de cima: primeiro o chão e as paredes, depois a mobília a entrar no lugar, com som.',
                'envio' => 'Planta do piso (imagem)', 'inicio' => 'O terreno', 'fim' => 'Planta 3D', 'ai' => ['fim'], 'segundos' => 10, 'creditos' => 2,
                'exemplo' => 'Ex.: chão de madeira clara em toda a casa',
            ],
        ];
    }

    /** The example clip and poster a tool shows on the landing page (public/exemplos-ia/<tool>.mp4|.jpg), when made. */
    public static function exemplo(string $ferramenta): array
    {
        $f = fn (string $ext) => is_file(public_path("exemplos-ia/{$ferramenta}.{$ext}")) ? asset("exemplos-ia/{$ferramenta}.{$ext}") : null;

        return ['video' => $f('mp4'), 'poster' => $f('jpg')];
    }

    /** The tools customers can use now: those the Realizador has prompts for (the others are on the landing only). */
    public static function disponiveis(): array
    {
        return array_intersect_key(self::lista(), Realizador::PROMPTS);
    }

    /**
     * A new draft from the customer's upload, stored as a JPEG (the longest side at most 2560 px, turned upright
     * by its EXIF orientation: phone photos come sideways otherwise). Nothing is spent yet.
     */
    public function criar(User $user, string $ferramenta, UploadedFile $foto): FerramentaIa
    {
        $f = FerramentaIa::create(['user_id' => $user->id, 'ferramenta' => $ferramenta, 'estado' => FerramentaIa::RASCUNHO, 'original' => '']);
        $this->trocarFoto($f, $foto);

        return $f;
    }

    /** A draft's photo replaced (only before anything is made from it), and checked for its tool. */
    public function trocarFoto(FerramentaIa $f, UploadedFile $foto): void
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        if ($f->original) {
            $disk->delete($f->original);
        }
        $caminho = $f->pasta().'/original-'.\Illuminate\Support\Str::random(6).'.jpg';
        $disk->put($caminho, self::jpeg($foto->getRealPath()));
        $f->update(['original' => $caminho, 'analise' => ['estado' => 'a_analisar'], 'erro' => null]);
        \App\Jobs\Ferramentas\AnalisarFotoJob::dispatch($f->id, $caminho);
    }

    /** An image file as an upright JPEG, at most $lado px on its longest side. */
    public static function jpeg(string $ficheiro, int $lado = 2560): string
    {
        $im = @imagecreatefromstring((string) file_get_contents($ficheiro));
        if (! $im) {
            throw new \RuntimeException('Não conseguimos ler esta imagem.');
        }
        $orientacao = function_exists('exif_read_data') ? (int) (@exif_read_data($ficheiro)['Orientation'] ?? 1) : 1;
        $im = match ($orientacao) { 3 => imagerotate($im, 180, 0), 6 => imagerotate($im, -90, 0), 8 => imagerotate($im, 90, 0), default => $im };
        [$w, $h] = [imagesx($im), imagesy($im)];
        if (max($w, $h) > $lado) {
            $im = imagescale($im, (int) round($w * $lado / max($w, $h)), (int) round($h * $lado / max($w, $h)));
        }
        ob_start();
        imagejpeg($im, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * «Criar imagens»: spends the tool's video credits (Pagamentos\Creditos, oldest purchases first; admins and free
     * accounts spend none) and starts the frames. Once: a second call finds it no longer a draft. False when the
     * account has too few credits.
     */
    public function iniciar(FerramentaIa $f): bool
    {
        $user = $f->user;
        $n = $f->tipo()['creditos'];
        $ok = DB::transaction(function () use ($f, $user, $n) {
            $semCusto = $user->is_admin || $user->imoplantaGratis();
            if (! FerramentaIa::whereKey($f->id)->where('estado', FerramentaIa::RASCUNHO)->lockForUpdate()->exists()) {
                return false;
            }
            $compra = $semCusto ? null : app(\App\Services\Pagamentos\Creditos::class)->gastar($user, $n);
            if (! $semCusto && ! $compra) {
                return false;
            }

            return FerramentaIa::whereKey($f->id)->update(['estado' => FerramentaIa::A_ENQUADRAR, 'compra_id' => $compra,
                'creditos' => $semCusto ? 0 : $n, 'erro' => null, 'updated_at' => now()]) === 1;
        });
        if ($ok) {
            FerramentaFramesJob::dispatch($f->id, false);
        }

        return $ok;
    }

    /** The first frames failed for good: its credits go back (once) and the draft keeps the photo. */
    public function devolver(FerramentaIa $f, string $erro): void
    {
        DB::transaction(function () use ($f, $erro) {
            $f = FerramentaIa::whereKey($f->id)->lockForUpdate()->first();
            if ($f->compra_id && $f->creditos > 0) {
                app(\App\Services\Pagamentos\Creditos::class)->devolver($f->user, $f->creditos);
            }
            $f->update(['estado' => FerramentaIa::RASCUNHO, 'compra_id' => null, 'creditos' => 0, 'erro' => $erro]);
        });
    }

    /** A change to one AI frame ($qual), in the customer's words (up to editar_imagem per video; admins without limit). */
    public function alterar(FerramentaIa $f, string $qual, string $pedido): bool
    {
        $pedido = trim($pedido);
        if ($pedido === '' || ! in_array($qual, $f->quadrosIa(), true) || ($f->edicoesRestantes() < 1 && ! $f->user->is_admin)) {
            return false;
        }
        if (FerramentaIa::whereKey($f->id)->where('estado', FerramentaIa::A_APROVAR)
            ->update(['estado' => FerramentaIa::A_ENQUADRAR, 'edicoes' => DB::raw('edicoes + 1'), 'pedido' => $pedido, 'alvo' => $qual, 'erro' => null, 'updated_at' => now()]) !== 1) {
            return false;
        }
        FerramentaFramesJob::dispatch($f->id, true);

        return true;
    }

    /** A failed change: not counted, the frame stays as it was. */
    public function devolverAlteracao(FerramentaIa $f, string $erro): void
    {
        $f->update(['estado' => FerramentaIa::A_APROVAR, 'edicoes' => max(0, $f->edicoes - 1), 'erro' => $erro]);
    }

    /** Picks which version of an AI frame ($qual) goes into the video. */
    public function escolherVersao(FerramentaIa $f, string $qual, int $n): bool
    {
        $versao = ($f->versoes[$qual] ?? [])[$n] ?? null;
        if (! $versao || $f->estado !== FerramentaIa::A_APROVAR) {
            return false;
        }
        $f->update([$qual => $versao]);

        return true;
    }

    /** The side move's direction (esquerda|direita), chosen on the draft: its end frame is built for it. */
    public function direcao(FerramentaIa $f, string $direcao): bool
    {
        if (! ($f->tipo()['direcao'] ?? false) || ! in_array($direcao, ['esquerda', 'direita'], true)
            || $f->estado !== FerramentaIa::RASCUNHO) {   // the end frame is built for it: fixed once frames exist
            return false;
        }
        $f->update(['opcoes' => [...($f->opcoes ?? []), 'direcao' => $direcao]]);

        return true;
    }

    /** The remodel's type of works, chosen on the draft: the photo is checked again for it (a ruin is no bad room). */
    public function obra(FerramentaIa $f, string $obra): bool
    {
        if (! isset($f->tipo()['obras'][$obra]) || $f->estado !== FerramentaIa::RASCUNHO) {
            return false;
        }
        $mudou = $f->obra() !== $obra;
        $f->update(['opcoes' => [...($f->opcoes ?? []), 'obra' => $obra]]);
        if ($mudou && $f->original) {
            $f->update(['analise' => ['estado' => 'a_analisar']]);
            \App\Jobs\Ferramentas\AnalisarFotoJob::dispatch($f->id, $f->original);
        }

        return true;
    }

    /**
     * The plan's top-view render from the customer's browser (the 3D model read from their plan, drawn from above),
     * once: the AI frames are made from it (FerramentaFramesJob again). False when it isn't waiting for one.
     */
    public function receberRender(FerramentaIa $f, UploadedFile $png, mixed $salas = null): bool
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $ok = DB::transaction(function () use ($f, $png, $salas, $disk) {
            $atual = FerramentaIa::whereKey($f->id)->lockForUpdate()->first();
            if ($atual->ferramenta !== 'planta' || $atual->estado !== FerramentaIa::A_ENQUADRAR || empty($atual->opcoes['casa'])
                || (($r = $atual->opcoes['render'] ?? null) && $disk->exists($r))) {
                return false;
            }
            $caminho = $png->storeAs($atual->pasta(), 'render-'.\Illuminate\Support\Str::random(8).'.png', config('contentmachine.imoplanta.disk'));
            $atual->update(['opcoes' => [...$atual->opcoes, 'render' => $caminho, 'salas' => json_decode((string) \App\Services\Imoplanta\Imoplantas::salasTopo($salas), true) ?? []]]);

            return true;
        });
        if ($ok) {
            FerramentaFramesJob::dispatch($f->id, false);
        }

        return $ok;
    }

    /** The tool's own optional text (the cleanup: what to take out; the remodel: the style), on the draft: the AI frame is made with it. */
    public function texto(FerramentaIa $f, string $texto): bool
    {
        if (! isset($f->tipo()['campo']) || $f->estado !== FerramentaIa::RASCUNHO) {
            return false;
        }
        $f->update(['opcoes' => [...($f->opcoes ?? []), 'texto' => mb_substr(trim($texto), 0, 300)]]);

        return true;
    }

    /** «Aprovar e criar vídeo»: the frames are fixed and the clip starts («Aprovar fotografia»: a photo tool is done). */
    public function aprovar(FerramentaIa $f): bool
    {
        $video = $f->tipo()['video'] ?? true;   // a photo tool (the cleanup) is done once its photo is approved
        if (FerramentaIa::whereKey($f->id)->where('estado', FerramentaIa::A_APROVAR)
            ->update(['estado' => $video ? FerramentaIa::A_ANIMAR : FerramentaIa::PRONTO, 'pedido' => null, 'erro' => null, 'updated_at' => now()]) !== 1) {
            return false;
        }
        if ($video) {
            FerramentaVideoJob::dispatch($f->id, false);
        }

        return true;
    }

    /** A new version of the clip from the same frames, with the customer's note (up to refazer_video). */
    public function refazer(FerramentaIa $f, string $nota): bool
    {
        if (! ($f->tipo()['video'] ?? true) || ($f->refeitosRestantes() < 1 && ! $f->user->is_admin)) {
            return false;
        }
        if (FerramentaIa::whereKey($f->id)->where('estado', FerramentaIa::PRONTO)
            ->update(['estado' => FerramentaIa::A_ANIMAR, 'refeitos' => DB::raw('refeitos + 1'), 'pedido' => trim($nota) ?: null, 'erro' => null, 'updated_at' => now()]) !== 1) {
            return false;
        }
        FerramentaVideoJob::dispatch($f->id, true);

        return true;
    }

    /** A failed clip: the first goes back to the frames, a new version back to the video they had; neither counts. */
    public function devolverVideo(FerramentaIa $f, bool $refazer, string $erro): void
    {
        $refazer
            ? $f->update(['estado' => FerramentaIa::PRONTO, 'refeitos' => max(0, $f->refeitos - 1), 'erro' => $erro])
            : $f->update(['estado' => FerramentaIa::A_APROVAR, 'erro' => $erro]);
    }

    /**
     * A credit pack's Payment Link (stripe.links.avulso|pack10|pack20) with the buyer's email and, bought from a video, that
     * video as client_reference_id (so /obrigado brings them back to it). Without a link, an email to us.
     */
    public static function link(User $user, string $pack, ?FerramentaIa $f = null): string
    {
        $base = (string) config('contentmachine.stripe.links.'.$pack);
        if ($base === '') {
            return 'mailto:'.config('mail.from.address').'?subject='.rawurlencode('Vídeos IA · '.$pack);
        }

        return $base.'?'.http_build_query(array_filter(['client_reference_id' => $f ? 'ferramenta-'.$f->id : null, 'prefilled_email' => $user->email, 'locale' => 'pt']));
    }
}
