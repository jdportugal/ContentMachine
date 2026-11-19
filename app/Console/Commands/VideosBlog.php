<?php

namespace App\Console\Commands;

use App\Jobs\Clips\TranscribeJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Guias\Guias;
use App\Services\Themes\ThemeRepository;
use Illuminate\Console\Command;

/**
 * The blog's social videos: one short per guide (resources/guias/videos/guioes.json holds each
 * one's script, opening and AI images), made as an animated clip in the "imocena-blog"
 * theme — the Imocena Demo look, with the style rules in resources/guias/videos/estilo.md —
 * opening its own way and closing on the imocena.com call to action. A finished video is
 * copied to public/media/guias/<slug>.mp4, where the guide's page shows it.
 *
 *   imocena:videos-blog luz-natural-fotografar-casa   start these
 *   imocena:videos-blog --todos --max=10              start up to 10 of those without a video
 *   imocena:videos-blog --recolher                    copy the finished ones, list the rest
 */
class VideosBlog extends Command
{
    public const TEMA = 'imocena-blog';

    public const BASE = 'demo-premium';

    /** Built-ins a blog short has no use for. */
    public const BUILTINS_FORA = ['video-intro', 'terminal', 'scatter-chart', 'line-chart', 'pie-chart', 'ambient'];

    /** The theme's listing cards: price, agent, new-listing opening. */
    public const SO_ANUNCIOS = ['preco-revelacao', 'agente-revelacao', 'imovel-novo-revelacao'];

    protected $signature = 'imocena:videos-blog {slugs?*} {--todos : todos os guias ainda sem vídeo} {--max=5 : no máximo, quantos começar} {--recolher : só guardar os prontos como rascunhos} {--refazer : começar de novo mesmo com vídeo} {--publicar=* : pôr estes rascunhos, já vistos, na página do guia}';

    protected $description = 'Faz o vídeo curto de cada guia do blog, para as redes e para a página do guia';

    public function handle(ClipStore $clips, ThemeRepository $temas, Guias $guias): int
    {
        $registo = $this->registo();
        $estado = is_file($registo) ? (array) json_decode((string) file_get_contents($registo), true) : [];
        $pasta = public_path('media/guias');
        $rascunhos = public_path('media/guias/rascunhos');
        @mkdir($rascunhos, 0775, true);

        // Approved drafts go on their guide's page.
        foreach ((array) $this->option('publicar') as $slug) {
            $feitos = glob("{$rascunhos}/{$slug}-*.mp4") ?: [];
            usort($feitos, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            if ($feitos === []) {
                $this->error("{$slug}: sem rascunho");

                continue;
            }
            copy($feitos[0], "{$pasta}/{$slug}.mp4");
            $this->info("{$slug}: publicado na página do guia");
        }
        if ((array) $this->option('publicar') !== []) {
            return self::SUCCESS;
        }

        // Collect: finished clips go to the guide's page; failed ones are reported and forgotten.
        foreach ($estado as $slug => $id) {
            $clip = $clips->find((string) $id);
            if ($clip && $clip->status === ClipRecord::STATUS_DONE && $clip->output_path && is_file($clip->output_path)) {
                // A draft first, at an unlisted address: it goes on the guide's page only
                // with --publicar, once someone has watched it.
                $nome = $slug.'-'.substr(md5($clip->id), 0, 8).'.mp4';
                copy($clip->output_path, "{$rascunhos}/{$nome}");
                $this->info("{$slug}: rascunho → ".asset("media/guias/rascunhos/{$nome}"));
                unset($estado[$slug]);
            } elseif (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
                $this->error("{$slug}: falhou".($clip?->error ? ' — '.mb_substr((string) $clip->error, 0, 200) : ''));
                unset($estado[$slug]);
            } else {
                $this->line("{$slug}: {$clip->status} (clip {$id})");
            }
        }
        if ($this->option('recolher')) {
            $this->guardar($registo, $estado);

            return self::SUCCESS;
        }

        $videos = json_decode((string) file_get_contents(resource_path('guias/videos/guioes.json')), true);
        $tema = $this->tema($temas);
        $pedidos = (array) $this->argument('slugs');
        $lista = $pedidos !== [] ? $pedidos : ($this->option('todos') ? array_keys($guias->todos()) : []);
        $comecados = 0;
        foreach ($lista as $slug) {
            if ($comecados >= max(1, (int) $this->option('max'))) {
                break;
            }
            $v = $videos[$slug] ?? null;
            if (! is_array($v) || $guias->encontrar($slug) === null) {
                $this->warn("{$slug}: sem guião em videos/guioes.json");

                continue;
            }
            $feito = is_file("{$pasta}/{$slug}.mp4") || (glob("{$rascunhos}/{$slug}-*.mp4") ?: []) !== [];
            if (isset($estado[$slug]) || ($feito && ! $this->option('refazer'))) {
                continue;   // under way, or already made
            }
            $clip = $clips->create([
                'type' => ClipRecord::TYPE_ANIMATION,
                'input_kind' => 'text',
                'title' => 'Blog · '.$guias->encontrar($slug)['titulo'],
                'source_text' => (string) $v['guiao'],
                'meta' => [
                    'theme' => $tema,
                    'auto' => true,                 // no review step: images are generated as planned
                    'abertura_propria' => true,     // no listing intro or agent card (FinalizeClipPlanJob)
                    'fewer_images' => true,
                    'sem_pesquisa' => true,         // the script's facts come from the guide, already sourced
                    'background' => 'auto',
                    'blog' => $slug,
                    'instructions' => self::instrucoes($v),
                    // The listing videos' European-Portuguese voice, not the project's default.
                    'voz' => (array) config('contentmachine.real_estate.voices.m'),
                ],
            ]);
            TranscribeJob::dispatch($clip->id);
            $estado[$slug] = $clip->id;
            $comecados++;
            $this->line("{$slug}: a fazer (clip {$clip->id})");
        }
        $this->guardar($registo, $estado);

        return self::SUCCESS;
    }

    /** The planner's direction for one video: its opening, its few AI images, and the close. */
    public static function instrucoes(array $v): string
    {
        $imagens = array_values(array_filter((array) ($v['imagens'] ?? [])));

        return trim(implode("\n", [
            'Vídeo educativo do blog Imocena para as redes sociais (Reels, TikTok, Shorts). Não é um anúncio de imóvel.',
            'ABERTURA: '.($v['abertura'] ?? 'uma pergunta em letras grandes').'. Os primeiros 2 segundos têm de prender.',
            'IMAGENS GERADAS: no máximo '.max(1, count($imagens)).', só estas, em cenas diferentes: '.implode(' | ', $imagens).'. As outras cenas são texto, números, listas ou citações sobre o fundo navy.',
            'FECHO: a última cena (pelo menos 3 s) é o cartão «Faça o vídeo do seu imóvel em minutos» com «imocena.com» em grande, sobre a frase final.',
        ]));
    }

    /** The "imocena-blog" theme: Imocena Demo's look, with the blog's own style rules. */
    private function tema(ThemeRepository $temas): string
    {
        $dir = $temas->root().'/'.self::TEMA;
        if (! is_dir($dir) && is_dir($temas->root().'/'.self::BASE)) {
            $this->copiar($temas->root().'/'.self::BASE, $dir);
        }
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/theme.json', json_encode(['name' => 'Imocena Blog'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        copy(resource_path('guias/videos/estilo.md'), $dir.'/estilo-animacao.md');

        // The listing theme it copies keeps the general built-ins off (its videos are made of
        // the listing's photos). A blog video has no photos: it needs text, lists, numbers,
        // comparisons and generated images, and none of the listing-only cards.
        @mkdir($dir.'/sfx', 0775, true);
        file_put_contents($dir.'/sfx/_disabled-builtins.json', json_encode(self::BUILTINS_FORA, JSON_PRETTY_PRINT));
        foreach (glob($dir.'/sfx/*.json') ?: [] as $f) {
            $r = json_decode((string) file_get_contents($f), true);
            if (is_array($r) && in_array($r['slug'] ?? '', self::SO_ANUNCIOS, true) && ($r['enabled'] ?? true)) {
                $r['enabled'] = false;
                file_put_contents($f, json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return self::TEMA;
    }

    private function copiar(string $de, string $para): void
    {
        @mkdir($para, 0775, true);
        foreach (scandir($de) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            is_dir("{$de}/{$f}") ? $this->copiar("{$de}/{$f}", "{$para}/{$f}") : copy("{$de}/{$f}", "{$para}/{$f}");
        }
    }

    private function registo(): string
    {
        return storage_path('app/videos-blog.json');
    }

    private function guardar(string $ficheiro, array $estado): void
    {
        file_put_contents($ficheiro, json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
