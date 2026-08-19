<?php

namespace App\Livewire;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Publishing\BlotatoClient;
use App\Services\Publishing\ZernioClient;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * The "Finished" hub: content explicitly promoted from the editors, organized
 * into four tabs — unpublished, scheduled, a calendar of scheduled posts, and
 * previously posted. Publishing/scheduling goes through Blotato.
 */
#[Layout('components.layouts.app')]
#[Title('Finished')]
class Rascunhos extends Component
{
    /** Platforms we can post to (must have an account id in Settings). */
    public const PLATAFORMAS = ['youtube', 'instagram', 'tiktok', 'linkedin', 'threads'];

    /** Active tab: unpublished|scheduled|calendar|posted. */
    public string $aba = 'unpublished';

    /** Selected platforms per item id. @var array<string,array<int,string>> */
    public array $plataformas = [];

    /** Schedule mode per item id: now|slot|time. @var array<string,string> */
    public array $quando = [];

    /** datetime-local value per item id (when quando=time). @var array<string,string> */
    public array $datas = [];

    /**
     * "DM for link" per item id: ativo, keyword, link, cta, mensagem, resposta.
     * Blank copy fields fall back to the defaults below, so the placeholders the
     * user sees are exactly what gets sent.
     *
     * @var array<string,array<string,mixed>>
     */
    public array $dm = [];

    /** Calendar month cursor, "YYYY-MM". */
    public string $mes = '';

    public ?string $aviso = null;

    public function mount(): void
    {
        $this->mes = now()->format('Y-m');
    }

    /** Stable id for an item (safe for wire:model — no dots or colons). */
    private function idDe(string $source, string $ref): string
    {
        return $source.'_'.md5($ref);
    }

    // ── Actions ──────────────────────────────────────────────────────────

    /** Publish or schedule an item to the selected platforms via Blotato. */
    public function publicar(string $source, string $ref, VaultContract $vault, SettingsRepository $settings): void
    {
        $this->aviso = null;
        $id = $this->idDe($source, $ref);
        $plats = array_values(array_filter($this->plataformas[$id] ?? []));

        if ($plats === []) {
            $this->addError('plataformas.'.$id, 'Pick at least one platform.');

            return;
        }

        if ($this->dmLigado($id)) {
            if ($this->dmCampo($id, 'keyword') === '') {
                $this->addError('dm.'.$id.'.keyword', 'Pick the word people will DM you.');

                return;
            }
            if (! filter_var($this->dmCampo($id, 'link'), FILTER_VALIDATE_URL)) {
                $this->addError('dm.'.$id.'.link', 'The link people get must be a full URL.');

                return;
            }
        }

        $modo = $this->quando[$id] ?? 'now';
        $scheduledTime = null;
        $slot = false;
        $scheduledFor = null; // what we store to drive the tabs

        if ($modo === 'time') {
            $dt = $this->datas[$id] ?? null;
            if (blank($dt)) {
                $this->addError('datas.'.$id, 'Choose a date & time.');

                return;
            }
            $when = Carbon::parse($dt);
            $scheduledTime = $when->toIso8601String();
            $scheduledFor = $when->toIso8601String();
        } elseif ($modo === 'slot') {
            $slot = true; // Blotato picks the next free slot; we don't know the exact time.
        }

        $item = $this->itens($vault)->firstWhere('id', $id);
        if (! $item) {
            $this->aviso = 'Item not found.';

            return;
        }

        // Upload media once, reuse across platforms.
        $blotato = app(BlotatoClient::class);
        try {
            $urls = array_values(array_map(fn ($m) => $blotato->uploadMedia($m), $this->midiaFor($item, $vault)));
        } catch (Throwable $e) {
            $this->aviso = 'Media upload failed: '.$e->getMessage();

            return;
        }

        $accounts = (array) $settings->get('blotato', []);
        $texto = $this->legenda($item, $id);

        $ids = [];
        $erros = [];
        foreach ($plats as $p) {
            $acc = trim((string) ($accounts[$p] ?? ''));
            if ($acc === '') {
                $erros[] = ucfirst($p).': no account id in Settings';

                continue;
            }
            try {
                $r = $blotato->publish($acc, $p, $texto, $urls, $scheduledTime, $slot, (string) $item['title']);
                $ids[$p] = $r['id'] ?? ($r['submissionId'] ?? true);
            } catch (Throwable $e) {
                $erros[] = ucfirst($p).': '.$e->getMessage();
            }
        }

        if ($ids === []) {
            $this->aviso = 'Nothing published — '.implode('; ', $erros);

            return;
        }

        $automacoes = $this->registarDm($id, $item, array_keys($ids), $settings, $erros);

        $posted = ! $scheduledTime && ! $slot; // immediate
        if ($posted) {
            $scheduledFor = now()->toIso8601String(); // stamp the moment it went out
        }
        $this->guardarEstado(
            $source, $ref, $vault,
            $posted ? 'posted' : 'scheduled',
            $scheduledFor, array_keys($ids), $ids,
            $automacoes === [] ? [] : ['dm_automations' => $automacoes],
        );

        $this->aviso = ($posted ? 'Posted' : 'Scheduled').' to '.implode(', ', array_keys($ids)).'.';
        if ($automacoes !== []) {
            $this->aviso .= ' DM automation live for «'.$this->dmCampo($id, 'keyword').'».';
        }
        if ($erros !== []) {
            $this->aviso .= ' Some failed — '.implode('; ', $erros);
        }
        $this->aba = $posted ? 'posted' : 'scheduled';
    }

    // ── DM for link (Zernio) ─────────────────────────────────────────────

    /** Is "DM for link" switched on for this item? */
    private function dmLigado(string $id): bool
    {
        return (bool) ($this->dm[$id]['ativo'] ?? false);
    }

    /** One typed DM field, trimmed. */
    private function dmCampo(string $id, string $campo): string
    {
        return trim((string) ($this->dm[$id][$campo] ?? ''));
    }

    /**
     * Default copy for a DM campaign — shown as the field's placeholder AND used
     * when the field is left blank, so what the user reads is what gets sent.
     */
    public function dmPadrao(string $id, string $campo): string
    {
        $palavra = $this->dmCampo($id, 'keyword') ?: 'LINK';

        return match ($campo) {
            'cta' => "📬 DM «{$palavra}» and I'll send you the link.",
            'mensagem' => "Here's the link you asked for 👇",
            'resposta' => 'Sent you a DM 📩',
            default => '',
        };
    }

    /** A typed DM field, or its default when left blank. */
    private function dmTexto(string $id, string $campo): string
    {
        return $this->dmCampo($id, $campo) ?: $this->dmPadrao($id, $campo);
    }

    /**
     * Registers the comment/DM automation with Zernio after a successful publish.
     *
     * Instagram-only: it's the one platform we publish to that Zernio can watch.
     * Anything that goes wrong lands in $erros next to the publishing errors —
     * the post is already out, so a failed automation must not read as a failure
     * to publish.
     *
     * @param  array<int,string>  $publicadas  platforms the post actually went to
     * @param  array<int,string>  $erros  by reference, appended to
     * @return array<string,string>  platform => automation id
     */
    private function registarDm(string $id, array $item, array $publicadas, SettingsRepository $settings, array &$erros): array
    {
        if (! $this->dmLigado($id)) {
            return [];
        }

        if (! in_array('instagram', $publicadas, true)) {
            $erros[] = 'DM automation skipped: Zernio only watches Instagram.';

            return [];
        }

        $zernio = (array) $settings->get('zernio', []);
        $perfil = trim((string) ($zernio['profile'] ?? ''));
        $conta = trim((string) ($zernio['instagram'] ?? ''));
        if ($perfil === '' || $conta === '') {
            $erros[] = 'DM automation skipped: no Zernio profile/account id in Settings.';

            return [];
        }

        try {
            $r = app(ZernioClient::class)->commentToDm(
                profileId: $perfil,
                accountId: $conta,
                name: Str::limit((string) $item['title'], 60, ''),
                dmMessage: $this->dmTexto($id, 'mensagem'),
                keywords: [$this->dmCampo($id, 'keyword')],
                linkUrl: $this->dmCampo($id, 'link'),
                linkLabel: 'Open the link',
                commentReply: $this->dmTexto($id, 'resposta'),
            );

            return ['instagram' => (string) ($r['automation']['id'] ?? '')];
        } catch (Throwable $e) {
            $erros[] = 'DM automation: '.$e->getMessage();

            return [];
        }
    }

    /**
     * The caption Blotato posts: the item's own description (or body), the DM
     * call to action when one is set, then its tags as hashtags. Falls back to
     * the title when there's no text.
     */
    private function legenda(array $item, ?string $id = null): string
    {
        $texto = trim((string) ($item['caption'] ?? '')) ?: (string) $item['title'];

        if ($id !== null && $this->dmLigado($id)) {
            $texto .= "\n\n".$this->dmTexto($id, 'cta');
        }

        $hashtags = collect($item['tags'] ?? [])
            ->map(fn ($t) => preg_replace('/\s+/u', '', ltrim(trim((string) $t), '#')))
            ->filter()
            ->unique()
            ->map(fn ($t) => '#'.$t)
            ->implode(' ');

        return $hashtags === '' ? $texto : $texto."\n\n".$hashtags;
    }

    /** Removes an item from a schedule (local view only). */
    public function desagendar(string $source, string $ref, VaultContract $vault): void
    {
        // ponytail: resets our view only — a post already handed to Blotato stays
        // scheduled there. Cancel it on the Blotato dashboard too if needed.
        $this->guardarEstado($source, $ref, $vault, 'unpublished', null, [], []);
    }

    public function remover(string $source, string $ref, VaultContract $vault): void
    {
        if ($source === 'animado') {
            app(ClipStore::class)->find($ref)?->delete();

            return;
        }

        $vault->delete($ref);
    }

    /**
     * Writes publish state back to the item's store (vault note or clip record).
     *
     * @param  array<string,mixed>  $extra  merged in as-is (e.g. dm_automations)
     */
    private function guardarEstado(string $source, string $ref, VaultContract $vault, string $estado, ?string $scheduledFor, array $plats, array $ids, array $extra = []): void
    {
        if ($source === 'animado') {
            app(ClipStore::class)->find($ref)?->update([
                'publish_state' => $estado === 'unpublished' ? null : $estado,
                'scheduled_for' => $scheduledFor,
                'plataformas' => $plats,
                'blotato_ids' => $ids,
            ] + $extra);

            return;
        }

        $vault->updateFrontmatter($ref, $extra + [
            'estado' => match ($estado) {
                'posted' => 'publicado',
                'scheduled' => 'agendado',
                default => 'pronto',
            },
            'agendado_para' => $scheduledFor,
            'plataformas' => $plats,
            'blotato_ids' => $ids,
        ]);
    }

    // ── Media resolution ─────────────────────────────────────────────────

    /** Local file paths / http URLs of an item's media, ready for Blotato. */
    private function midiaFor(array $item, VaultContract $vault): array
    {
        return match ($item['source']) {
            'animado' => array_values(array_filter([
                app(ClipStore::class)->find($item['ref'])?->output_path,
            ], fn ($p) => $p && is_file($p))),
            'clip' => array_values(array_filter([$this->shortVideo($item['ref'])])),
            default => $this->postImages($item['ref'], $vault), // 'post'
        };
    }

    /** Best available rendered short for a clip note (final music > legendado > raw). */
    private function shortVideo(string $notePath): ?string
    {
        $slug = basename($notePath, '.md');
        $dir = storage_path('app/shorts/'.$slug);
        foreach (['final-music.mp4', 'final.mp4', 'raw.mp4'] as $f) {
            if (is_file($dir.'/'.$f)) {
                return $dir.'/'.$f;
            }
        }

        return null;
    }

    /** A post's images as local files (public/) or passthrough http URLs. */
    private function postImages(string $notePath, VaultContract $vault): array
    {
        $imagens = (array) ($vault->get($notePath)?->get('imagens', []) ?? []);

        return collect($imagens)
            ->map(fn ($rel) => Str::startsWith((string) $rel, 'http') ? (string) $rel : public_path((string) $rel))
            ->filter(fn ($p) => Str::startsWith($p, 'http') || is_file($p))
            ->values()
            ->all();
    }

    // ── Item collection & normalization ──────────────────────────────────

    private function estadoDe(?string $publishState, ?string $scheduledFor): string
    {
        if ($publishState === 'posted') {
            return 'posted';
        }
        if ($publishState === 'scheduled') {
            return $scheduledFor && Carbon::parse($scheduledFor)->isPast() ? 'posted' : 'scheduled';
        }

        return 'unpublished';
    }

    /**
     * The caption text of a note: its `descricao` when it has one, otherwise the
     * body's first section as plain text — a carousel's body is every card,
     * split by `---`, and only the cover card reads like a caption.
     */
    private function textoDaNota(VaultNote $n): string
    {
        $descricao = trim((string) $n->get('descricao', ''));
        if ($descricao !== '') {
            return $descricao;
        }

        $primeira = preg_split('/^\s*---\s*$/m', trim($n->body))[0] ?? '';

        return trim(html_entity_decode(strip_tags(Str::markdown($primeira))));
    }

    /** Normalizes a vault note (post or short) into the common shape. */
    private function deNota(VaultNote $n, string $source, string $kind): array
    {
        $publishState = match ((string) $n->get('estado')) {
            'publicado' => 'posted',
            'agendado' => 'scheduled',
            default => null,
        };
        $scheduledFor = $n->get('agendado_para') ? (string) $n->get('agendado_para') : null;

        $imagens = (array) $n->get('imagens', []);
        $capa = $imagens[0] ?? null;
        if ($capa && ! Str::startsWith($capa, 'http')) {
            $capa = asset($capa);
        }

        return [
            'id' => $this->idDe($source, $n->path),
            'source' => $source,
            'ref' => $n->path,
            'kind' => $kind,
            'title' => $n->title(),
            'caption' => $this->textoDaNota($n),
            'tags' => array_values((array) $n->get('tags', [])),
            'cover' => $capa,
            'excerpt' => Str::limit(strip_tags($n->html()), 160),
            'scheduled_for' => $scheduledFor,
            'plataformas' => array_values((array) $n->get('plataformas', [])),
            'state' => $this->estadoDe($publishState, $scheduledFor),
        ];
    }

    /** All promoted items across the three sources. @return Collection<int,array<string,mixed>> */
    private function itens(VaultContract $vault): Collection
    {
        // Which items count as finished lives in FinishedContent — the repurpose
        // tab reads the same rule, so it must not be restated here.
        $finished = app(\App\Services\Content\FinishedContent::class);

        $posts = $finished->posts()
            ->map(function (VaultNote $n) {
                $tipo = (string) $n->get('tipo', 'post');
                $kind = config('contentmachine.publicacoes.tipos.'.$tipo.'.label', $tipo);

                return $this->deNota($n, 'post', $kind);
            });

        $clips = $finished->shorts()
            ->map(fn (VaultNote $n) => $this->deNota($n, 'clip', 'Short'));

        $animados = $finished->animated()
            ->map(fn (ClipRecord $p) => [
                'id' => $this->idDe('animado', $p->id),
                'source' => 'animado',
                'ref' => $p->id,
                'kind' => $p->type === ClipRecord::TYPE_OVERLAY ? 'Animated video' : 'Animation',
                'title' => (string) ($p->title ?: 'Animated clip'),
                // An animated clip's caption/tags are the planner's suggestion.
                'caption' => trim((string) (((array) $p->get('meta', []))['suggested']['description'] ?? '')),
                'tags' => array_values((array) (((array) $p->get('meta', []))['suggested']['tags'] ?? [])),
                'cover' => null,
                'excerpt' => '',
                'scheduled_for' => $p->get('scheduled_for') ?: null,
                'plataformas' => array_values((array) $p->get('plataformas', [])),
                'state' => $this->estadoDe($p->get('publish_state'), $p->get('scheduled_for') ?: null),
            ]);

        return $posts->values()->concat($clips->values())->concat($animados->values());
    }

    /** Scheduled items grouped by day (Y-m-d) for the calendar month. */
    private function calendario(Collection $agendados): array
    {
        return $agendados
            ->filter(fn ($i) => $i['scheduled_for'] && Str::startsWith(Carbon::parse($i['scheduled_for'])->format('Y-m'), $this->mes))
            ->groupBy(fn ($i) => Carbon::parse($i['scheduled_for'])->format('Y-m-d'))
            ->map(fn ($grp) => $grp->values()->all())
            ->all();
    }

    public function mesAnterior(): void
    {
        $this->mes = Carbon::parse($this->mes.'-01')->subMonth()->format('Y-m');
    }

    public function mesSeguinte(): void
    {
        $this->mes = Carbon::parse($this->mes.'-01')->addMonth()->format('Y-m');
    }

    public function render(VaultContract $vault)
    {
        $todos = $this->itens($vault);
        $porEstado = $todos->groupBy('state');

        $agendados = ($porEstado->get('scheduled') ?? collect())->values();
        $porPublicar = ($porEstado->get('unpublished') ?? collect())->values();

        // Seed each item's platform selection as an ARRAY before it's rendered.
        // The platform checkboxes bind to plataformas.<id>; if that key isn't an
        // array, Livewire treats a lone checkbox as a boolean and the next
        // render's in_array() throws a 500. Seeding from the saved platforms also
        // pre-checks what was chosen before.
        foreach ($porPublicar as $item) {
            if (! is_array($this->plataformas[$item['id']] ?? null)) {
                $this->plataformas[$item['id']] = array_values((array) ($item['plataformas'] ?? []));
            }
            // Same reason as above: the DM block binds to dm.<id>.<field>, so the
            // key has to exist as an array before the checkbox renders.
            if (! is_array($this->dm[$item['id']] ?? null)) {
                $this->dm[$item['id']] = ['ativo' => false, 'keyword' => '', 'link' => '', 'cta' => '', 'mensagem' => '', 'resposta' => ''];
            }
        }

        return view('livewire.rascunhos', [
            'aba' => $this->aba,
            'unpublished' => $porPublicar,
            'scheduled' => $agendados,
            'posted' => ($porEstado->get('posted') ?? collect())->values(),
            'calendario' => $this->calendario($agendados),
            'diasDoMes' => $this->diasDoMes(),
            'blotatoReady' => filled(config('services.blotato.key')),
            'zernioReady' => filled(config('services.zernio.key')),
            'contagem' => [
                'unpublished' => ($porEstado->get('unpublished') ?? collect())->count(),
                'scheduled' => $agendados->count(),
                'posted' => ($porEstado->get('posted') ?? collect())->count(),
            ],
        ]);
    }

    /** The month's day cells, aligned to a Monday-start grid (null = padding). */
    private function diasDoMes(): array
    {
        $inicio = Carbon::parse($this->mes.'-01');
        $pad = (int) $inicio->dayOfWeekIso - 1; // Mon=1
        $dias = array_fill(0, $pad, null);
        for ($d = 1; $d <= $inicio->daysInMonth; $d++) {
            $dias[] = $inicio->copy()->day($d)->format('Y-m-d');
        }

        return $dias;
    }
}
