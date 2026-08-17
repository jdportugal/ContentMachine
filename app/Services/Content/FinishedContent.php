<?php

namespace App\Services\Content;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;

/**
 * What counts as "finished" content, in ONE place.
 *
 * The rule lives here because two features need the same answer: the Finished
 * hub (Rascunhos) lists it for publishing, and the Content Transformer's
 * repurpose tab lists it for converting between formats. Duplicating the state
 * filter would let the two drift the first time the rule changes.
 *
 * This deliberately exposes the raw notes/records, not a presentation shape —
 * each caller maps them for its own screen.
 */
class FinishedContent
{
    /** Vault states that mean "promoted to Finished". */
    public const PRONTOS = ['pronto', 'agendado', 'publicado'];

    public function __construct(private VaultContract $vault) {}

    /** Posts / carousels promoted to Finished. @return Collection<int,VaultNote> */
    public function posts(): Collection
    {
        return $this->vault->all('rascunhos')
            ->filter(fn (VaultNote $n) => in_array($n->get('estado'), self::PRONTOS, true))
            ->values();
    }

    /** Subtitled shorts cut from a long video. @return Collection<int,VaultNote> */
    public function shorts(): Collection
    {
        return $this->vault->all('clips')
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'clip' && in_array($n->get('estado'), self::PRONTOS, true))
            ->values();
    }

    /** Animated clips explicitly promoted (finished = true). @return Collection<int,ClipRecord> */
    public function animated(): Collection
    {
        return app(ClipStore::class)->all()
            ->filter(fn (ClipRecord $p) => $p->status === ClipRecord::STATUS_DONE && (bool) $p->get('finished'))
            ->values();
    }

    // ── text extraction (what a repurpose seeds the target editor with) ──

    /**
     * Everything actually SAID in a short, from its shifted subtitle data.
     *
     * subtitle_data is an array of SEGMENTS — {start, end, text, words[]} — so the
     * spoken text is each segment's `text`, joined. Repurposing must hand the AI
     * this, not just the title: a one-line title gives it nothing to write from.
     * Falls back to the description when the clip has no subtitles yet.
     */
    public function shortText(VaultNote $short): string
    {
        $segmentos = json_decode((string) $short->get('subtitle_data'), true);

        $falado = is_array($segmentos)
            ? collect($segmentos)
                ->map(fn ($s) => is_array($s)
                    // A segment carries `text`; tolerate a bare word list too.
                    ? trim((string) ($s['text'] ?? $s['word'] ?? ''))
                    : trim((string) $s))
                ->filter()
                ->implode(' ')
            : '';

        return $this->compose(
            $short->title(),
            trim($falado) !== '' ? trim($falado) : (string) $short->get('descricao')
        );
    }

    /** An animated clip's script — what it was generated from. */
    public function animatedText(ClipRecord $clip): string
    {
        return $this->compose(
            (string) ($clip->title ?: 'Animated clip'),
            (string) ($clip->get('source_text') ?: '')
        );
    }

    /** A post's body, as plain text, for seeding a video script. */
    public function postText(VaultNote $post): string
    {
        return $this->compose($post->title(), trim(strip_tags($post->html())));
    }

    /**
     * A long video's full transcript as continuous prose, for seeding a post
     * brief with the actual source material.
     *
     * @param  array<int,array<string,mixed>>  $transcricao  segments from ShortsPipeline::transcricao()
     */
    public function transcriptText(array $transcricao): string
    {
        return trim(collect($transcricao)
            ->map(fn ($s) => is_array($s) ? trim((string) ($s['text'] ?? '')) : trim((string) $s))
            ->filter()
            ->implode(' '));
    }

    /** Title + body. Callers cap it for their own target. */
    private function compose(string $title, string $body): string
    {
        return trim($title."\n\n".trim($body));
    }
}
