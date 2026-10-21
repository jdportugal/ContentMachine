<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Projects\ProjectLanguage;
use Illuminate\Support\Facades\Log;

/**
 * Fills the inputs a custom effect needs but the plan left empty.
 *
 * A template effect (a listing card: price, title, facts, phone, agent photo…)
 * shows its sample texts in the studio preview and NOTHING in a clip unless every
 * param is filled. The planner fills them unreliably, and the intro pass hands
 * an intro effect only its `src`. So after planning, one LLM pass reads the whole
 * voiceover and the image descriptions and fills every missing param of every
 * custom-effect layer. Params already set are never touched; a failure leaves the
 * plan as it was.
 */
class EffectParamFiller
{
    use RunsClaudeCli;

    /** The layer's own on-screen text (anim.text), asked for alongside the params. */
    private const TEXTO = 'on_screen_text';

    /**
     * @param  array<string,mixed>  $plan
     * @param  array<string,mixed>  $transcript  {text, words:[{word,start,end}]}
     * @param  array<int,array<string,mixed>>  $images  clip images {id, description, video?}
     * @param  string  $notes  the clip's own instructions (listing details the voiceover may skip)
     * @param  string[]  $reviewSlugs  intro/outro effects: their texts are REVIEWED, not only filled —
     *                                 a set-but-incomplete value ("Ligue" without the number) is fixed too
     * @return array<string,mixed>
     */
    public function fill(array $plan, array $transcript, array $images, EffectLibrary $library, string $notes = '', array $reviewSlugs = []): array
    {
        $records = $library->active()->filter(fn (EffectRecord $e) => ! $library->isBuiltin($e->slug))->keyBy('slug');
        $pedidos = [];
        $conhecidos = self::factosConhecidos($plan, $records->keys()->all());

        foreach ($plan['scenes'] ?? [] as $si => $scene) {
            foreach ($scene['layers'] ?? [] as $li => $layer) {
                $rec = $records->get($layer['type'] ?? '');
                if (! $rec || ! is_array($layer)) {
                    continue;
                }
                $campos = self::inputs($rec);
                $rever = in_array($rec->slug, $reviewSlugs, true);
                $faltam = array_filter($campos, fn ($info, $k) => ($rever && $info['type'] === 'text')
                    || ! self::preenchido($layer['params'][$k] ?? null), ARRAY_FILTER_USE_BOTH);
                // Many effects take their headline from the layer's own `text`
                // (anim.text), not a param — an effect previewed with a sample text
                // is one of them, and renders titleless without it.
                if (trim((string) $rec->sample_text) !== '' && ($rever || ! self::preenchido($layer['text'] ?? null))) {
                    $faltam[self::TEXTO] = ['type' => 'text', 'example' => (string) $rec->sample_text];
                }
                if ($faltam === []) {
                    continue;
                }
                $pedidos["s{$si}l{$li}"] = [
                    'mode' => $rever ? 'review' : 'fill',
                    'effect' => $rec->slug,
                    'what_it_shows' => (string) $rec->description,
                    'missing' => $faltam,
                    'already_set' => array_filter((array) ($layer['params'] ?? []), fn ($v) => self::preenchido($v)),
                    'spoken_during_this_scene' => self::falado($transcript, (float) ($scene['start'] ?? 0), (float) ($scene['end'] ?? 0)),
                ];
            }
        }
        if ($pedidos === []) {
            return self::semLicencaInventada($plan, $notes);
        }

        $valores = $this->ask($pedidos, $transcript, $images, $notes, $conhecidos);
        $ids = array_column(array_filter($images, fn ($i) => empty($i['video'])), 'id');

        foreach ($valores as $ref => $params) {
            if (! isset($pedidos[$ref]) || ! is_array($params) || ! preg_match('/^s(\d+)l(\d+)$/', $ref, $m)) {
                continue;
            }
            $layer = &$plan['scenes'][(int) $m[1]]['layers'][(int) $m[2]];
            foreach ($pedidos[$ref]['missing'] as $k => $info) {
                $v = $params[$k] ?? null;
                if ($k === self::TEXTO) {
                    if (is_scalar($v) && trim((string) $v) !== '') {
                        $layer['text'] = trim((string) $v);
                    }

                    continue;
                }
                $ok = $info['type'] === 'image'
                    ? is_string($v) && in_array($v, $ids, true) && ! self::preenchido($layer['params'][$k] ?? null)
                    : (is_scalar($v) && trim((string) $v) !== '')
                        // a list input (buttons, items) comes back as a list
                        || (is_array($v) && $v !== [] && array_filter($v, 'is_scalar') === $v);
                if ($ok) {
                    $layer['params'][$k] = match (true) {
                        $info['type'] === 'image' => $v,
                        is_array($v) => array_values(array_map(fn ($x) => trim((string) $x), $v)),
                        default => trim((string) $v),
                    };
                }
            }
            unset($layer);
        }

        return self::semLicencaInventada($plan, $notes);
    }

    /**
     * A licence (AMI) is shown only when the author gave it — in the clip's notes,
     * which carry the listing's, the project's or the one typed on the form. An
     * effect's example licence, or one the model made up, is blanked: no licence
     * beats a wrong one.
     */
    public static function semLicencaInventada(array $plan, string $notes): array
    {
        $digits = fn (string $s) => preg_replace('/\D/', '', $s);
        preg_match_all('/\d[\d .\-]*\d|\d/', $notes, $m);
        $given = array_map($digits, $m[0]);   // each number in the notes, whole
        foreach ($plan['scenes'] ?? [] as $si => $scene) {
            foreach ($scene['layers'] ?? [] as $li => $layer) {
                foreach ((array) ($layer['params'] ?? []) as $k => $v) {
                    if (is_string($k) && preg_match('/^(ami|licen[cs]e\w*|licenca)$/i', $k) && is_scalar($v)
                        && (($d = $digits((string) $v)) === '' || ! in_array($d, $given, true))) {
                        $plan['scenes'][$si]['layers'][$li]['params'][$k] = '';
                    } elseif (is_string($v)) {
                        // …and inside a text line ("IAD PORTUGAL S.A. | AMI: 11220"), with its separator.
                        $plan['scenes'][$si]['layers'][$li]['params'][$k] = trim((string) preg_replace_callback(
                            '/\s*[|·•,–-]?\s*(?:Licen[çc]a\s+)?AMI\b[\s:nº.]*(\d[\d .]*\d|\d)/iu',
                            fn ($m) => in_array($digits($m[1]), $given, true) ? $m[0] : '',
                            $v,
                        ));
                    }
                }
            }
        }

        return $plan;
    }

    /**
     * The flat inputs an effect takes: every image-typed key in its schema and
     * every scalar key of its sample (with the sample as the format example).
     * Nested params (rows, lists) are left to the planner.
     *
     * @return array<string,array{type:string,example:string}>
     */
    public static function inputs(EffectRecord $rec): array
    {
        $schema = (string) $rec->param_schema;
        $out = [];
        preg_match_all('/["\']?(\w+)["\']?\??\s*:\s*image\b/', $schema, $m);
        // `src` is the pipeline's image key whatever type the schema gives it — an
        // outro card dropped in after planning arrives without one.
        if (preg_match('/["\']?\bsrc\b["\']?\??\s*:/', $schema)) {
            $m[1][] = 'src';
        }
        foreach (array_unique($m[1]) as $k) {
            $out[$k] = ['type' => 'image', 'example' => ''];
        }
        $amostra = (array) ($rec->sample_params ?? []);
        foreach ($amostra as $k => $v) {
            if (is_string($k) && is_scalar($v) && ! isset($out[$k])) {
                $out[$k] = ['type' => 'text', 'example' => (string) $v];
            }
        }
        // Text inputs the SCHEMA declares but the sample left out — a refined
        // effect's sample can drop a key (the agent card's phone/buttons) that
        // the component still reads. Top-level keys only: nested {…} shapes
        // (rows, items) belong to the planner.
        $topo = trim($schema);
        $topo = str_starts_with($topo, '{') ? substr($topo, 1, -1) : $topo;
        while (preg_match('/\{[^{}]*\}/', $topo)) {
            $topo = (string) preg_replace('/\{[^{}]*\}/', '', $topo);
        }
        preg_match_all('/["\']?(\w+)["\']?\??\s*:\s*([^,]+)/', $topo, $campos, PREG_SET_ORDER);
        foreach ($campos as [, $k, $tipo]) {
            if (isset($out[$k]) || preg_match('/^(src\d*|srcs)$/', $k)) {
                continue;
            }
            // A conventional image name typed as a plain string is still an image slot.
            if (preg_match('/^(avatar|logo|images?\d*|photos?\d*)$/i', $k)) {
                $out[$k] = ['type' => 'image', 'example' => ''];
            } elseif (str_contains($tipo, 'string')) {
                $ex = $amostra[$k] ?? '';
                $out[$k] = ['type' => 'text', 'example' => is_array($ex) ? implode(' | ', array_map('strval', $ex)) : (string) $ex];
            }
        }

        return $out;
    }

    /**
     * Every text value the plan's custom effects already show (the intro's phone,
     * name, price…), so a later card reuses them instead of leaving them out.
     *
     * @param  string[]  $customSlugs
     * @return array<string,list<string>>
     */
    private static function factosConhecidos(array $plan, array $customSlugs): array
    {
        $out = [];
        foreach ($plan['scenes'] ?? [] as $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                if (! is_array($layer) || ! in_array($layer['type'] ?? null, $customSlugs, true)) {
                    continue;
                }
                foreach ((array) ($layer['params'] ?? []) as $k => $v) {
                    if (is_string($v) && trim($v) !== '' && ! str_starts_with($v, 'img_') && ! in_array($v, $out[$k] ?? [], true)) {
                        $out[$k][] = trim($v);
                    }
                }
            }
        }

        return $out;
    }

    private static function preenchido(mixed $v): bool
    {
        return is_array($v) ? $v !== [] : trim((string) $v) !== '';
    }

    /** The words spoken between $start and $end. */
    private static function falado(array $transcript, float $start, float $end): string
    {
        $words = array_filter((array) ($transcript['words'] ?? []), fn ($w) => is_array($w)
            && (float) ($w['start'] ?? -1) >= $start - 0.05 && (float) ($w['start'] ?? 0) < $end);

        return implode(' ', array_map(fn ($w) => (string) ($w['word'] ?? ''), $words));
    }

    /**
     * @param  array<string,array<string,mixed>>  $pedidos
     * @return array<string,array<string,mixed>>
     */
    private function ask(array $pedidos, array $transcript, array $images, string $notes = '', array $conhecidos = []): array
    {
        $imgs = array_map(fn ($i) => ['id' => $i['id'] ?? '', 'description' => $i['description'] ?? ''],
            array_values(array_filter($images, fn ($i) => empty($i['video']))));
        $language = ProjectLanguage::name();

        $prompt = "A short video uses on-screen templates whose inputs are still empty. Fill them from the VOICEOVER.\n\n"
            ."=== VOICEOVER (the whole video) ===\n".mb_substr((string) ($transcript['text'] ?? ''), 0, 6000)."\n\n"
            .(trim($notes) !== '' ? "=== NOTES FROM THE AUTHOR (facts you may also use) ===\n".mb_substr($notes, 0, 10000)."\n\n" : '')
            .($conhecidos !== [] ? "=== FACTS ALREADY ON SCREEN IN THIS VIDEO (other templates — reuse them, they are right) ===\n"
                .json_encode($conhecidos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n" : '')
            ."=== PROVIDED IMAGES (id: description) ===\n".json_encode($imgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n"
            ."=== TEMPLATES TO FILL (ref → effect, missing inputs with a format example) ===\n"
            .json_encode($pedidos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n\n"
            ."Rules:\n"
            ."- Text inputs: take the facts from the WHOLE voiceover (a card is a summary — price, place, areas,\n"
            ."  rooms, contact can be said anywhere), in {$language}, matching the example's format, length and\n"
            ."  case (\"450.000 €\", \"LISBOA · ALVALADE\", \"120 m² · 3 quartos\"). Never copy the example's facts about\n"
            ."  the subject (price, place, areas, rooms) — only the constant lines below may be kept.\n"
            ."- Take facts from the voiceover first, then the author's notes.\n"
            ."- Constant brand and CONTACT lines in the example (company name, logo wordmark, the agent's phone\n"
            ."  and e-mail) are the author's real details: keep them exactly as in the example when the video\n"
            ."  names the same company or person; otherwise adapt them to the one named.\n"
            ."- A licence / AMI number: ONLY one written in the author's notes. None there → \"\" (never the\n"
            ."  example's, never guessed), even in review mode.\n"
            ."- `on_screen_text` is the effect's headline for THAT scene: a short title for what is said during it\n"
            ."  (spoken_during_this_scene), in the example's length and style — not a copy of the voiceover.\n"
            ."- A label/tag with no fact behind it (e.g. \"NOVO IMÓVEL\"): write a fitting one for this video.\n"
            ."- Image inputs: the id of the provided image whose description fits (avatar → the person's portrait,\n"
            ."  logo → a logo). None fits → omit the key. Never invent ids.\n"
            ."- Only omit a text input when nothing sensible can be said.\n"
            ."- mode \"review\" (the opening/closing cards): return EVERY listed input with its FINAL value. Keep a\n"
            ."  current value that is right; COMPLETE one that lacks something its example carries — a call button\n"
            ."  that in the example includes the phone number must include the REAL phone (from the facts already\n"
            ."  on screen, the voiceover or the notes); a contact line the example has must not be left out.\n\n"
            .'Return ONLY JSON, no markdown: {"<ref>": {"<input>": "<value>", ...}, ...}';

        try {
            $envelope = $this->runClaude($prompt, null, ['timeout' => 300]);
            $text = (string) ($envelope['result'] ?? '');
            if (preg_match('/\{.*\}/s', $text, $m) && is_array($data = json_decode($m[0], true))) {
                return $data;
            }
            Log::warning('Effect param fill returned no JSON: '.mb_substr($text, 0, 200));
        } catch (\Throwable $e) {
            Log::warning('Effect param fill failed: '.$e->getMessage());
        }

        return [];
    }
}
