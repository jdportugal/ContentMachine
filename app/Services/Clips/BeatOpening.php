<?php

namespace App\Services\Clips;

use Symfony\Component\Process\Process;

/**
 * Where a music-only opening cuts: the track's beat grid (tempo by
 * autocorrelation of the onset strength, phase by the best-fitting comb), then
 * its bars — every change lands on a bar's first beat, the accent the ear
 * expects a cut on. $count clips, one bar each; the reel starts on the next.
 */
class BeatOpening
{
    private const SR = 11025;

    private const HOP = 128;

    /**
     * @return list<float> the boundaries in seconds: [0, cut…, end] — $count + 1 of them
     */
    public static function cuts(string $music, int $count = 3): array
    {
        $p = new Process(['ffmpeg', '-v', 'error', '-i', $music, '-t', '24', '-ac', '1', '-ar', (string) self::SR, '-f', 's16le', '-']);
        $p->setTimeout(60)->mustRun();

        return self::fromSamples(array_values(unpack('s*', $p->getOutput()) ?: []), $count);
    }

    /**
     * @param  list<int>  $samples  mono, 11025 Hz
     * @return list<float>
     */
    public static function fromSamples(array $samples, int $count = 3): array
    {
        $count = max(1, $count);
        $full = self::envelope($samples);
        $n = count($full);
        $flux = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $flux[] = max(0.0, $full[$i] - $full[$i - 1]);
        }
        $sec = self::HOP / self::SR;
        $beats = self::beats($flux, $sec);
        if (count($beats) < 8) {
            // No pulse to follow: even cuts.
            return array_map(fn ($i) => round($i * 2.5, 3), range(0, $count));
        }

        // The bar's first beat: of the four places in the bar, the one the music hits hardest.
        $hit = fn (float $t) => $flux[(int) round($t / $sec)] ?? 0.0;
        $weight = array_fill(0, 4, 0.0);
        foreach ($beats as $k => $t) {
            $weight[$k % 4] += $hit($t);
        }
        $first = array_search(max($weight), $weight, true);
        $downbeats = array_values(array_filter($beats, fn ($t, $k) => $k % 4 === $first && $t > 1.5, ARRAY_FILTER_USE_BOTH));

        $out = [0.0];
        foreach (array_slice($downbeats, 0, $count) as $t) {
            $out[] = $t;
        }
        while (count($out) <= $count) {
            $out[] = end($out) + 4 * ($beats[1] - $beats[0]);
        }

        return array_map(fn ($t) => round($t, 3), $out);
    }

    /**
     * Onset envelope per hop: the RMS of the high-passed signal (every hit).
     *
     * @return list<float>
     */
    private static function envelope(array $s): array
    {
        $out = [];
        $prev = 0;
        for ($i = 0, $n = count($s); $i + self::HOP <= $n; $i += self::HOP) {
            $sum = 0.0;
            for ($j = $i; $j < $i + self::HOP; $j++) {
                $d = $s[$j] - $prev;
                $prev = $s[$j];
                $sum += $d * $d;
            }
            $out[] = sqrt($sum / self::HOP);
        }

        return $out;
    }

    /**
     * Beat times: the period (0.33–0.75 s) the onsets repeat at most, refined
     * with the phase whose comb catches the most onset strength, each beat then
     * snapped to the strongest onset within 50 ms.
     *
     * @param  list<float>  $flux
     * @return list<float>
     */
    private static function beats(array $flux, float $sec): array
    {
        $n = count($flux);
        $mean = $n ? array_sum($flux) / $n : 0.0;
        // Smoothed over ~60 ms for the tempo: a sharp onset train misses its own
        // multiples by a hop of rounding and the bar-length check would fail.
        $x = [];
        for ($i = 0; $i < $n; $i++) {
            $w = array_slice($flux, max(0, $i - 2), 5);
            $x[] = array_sum($w) / count($w) - $mean;
        }
        $ac = function (int $lag) use ($x, $n): float {
            $score = 0.0;
            for ($i = 0; $i + $lag < $n; $i++) {
                $score += $x[$i] * $x[$i + $lag];
            }

            return $lag < $n ? $score / ($n - $lag) : 0.0;
        };
        // A beat repeats at the half bar and the bar too: the lag counts with its multiples, so a
        // busy off-beat figure (hi-hats, an arpeggio) does not win over the pulse.
        [$bestLag, $bestScore] = [0, -INF];
        for ($lag = (int) ceil(0.33 / $sec); $lag <= (int) floor(0.75 / $sec); $lag++) {
            $score = $ac($lag) + $ac(2 * $lag) + $ac(4 * $lag);
            if ($score > $bestScore) {
                [$bestLag, $bestScore] = [$lag, $score];
            }
        }
        if ($bestLag === 0) {
            return [];
        }
        // Period (to a fraction of a hop: whole hops drift a beat off within a few
        // bars) and phase together: the comb that lands on the most onset strength.
        [$period, $phase, $best] = [(float) $bestLag, 0.0, -INF];
        for ($per = $bestLag - 1.0; $per <= $bestLag + 1.0; $per += 0.05) {
            for ($ph = 0; $ph < $bestLag; $ph++) {
                $sum = 0.0;
                for ($i = $ph; $i < $n; $i += $per) {
                    $sum += $flux[(int) round($i)] ?? 0.0;
                }
                if ($sum > $best) {
                    [$period, $phase, $best] = [$per, (float) $ph, $sum];
                }
            }
        }
        $win = (int) round(0.05 / $sec);
        $beats = [];
        for ($f = $phase; $f < $n; $f += $period) {
            $i = (int) round($f);
            $at = min($n - 1, $i);
            for ($j = max(0, $i - $win); $j <= min($n - 1, $i + $win); $j++) {
                if ($flux[$j] > $flux[$at]) {
                    $at = $j;
                }
            }
            $beats[] = $at * $sec;
        }

        return $beats;
    }
}
