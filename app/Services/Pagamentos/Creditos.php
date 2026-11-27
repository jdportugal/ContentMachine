<?php

namespace App\Services\Pagamentos;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One balance of video credits (user, 2026-11-27): an Imocena video costs 1, an Imoplanta property 5 (shown as its
 * 50 €), each AI tool its own number (Ferramentas::lista()[tool]['creditos']). 1 credit = 10 € at the pack price.
 * The credits are `compras.videos` / `videos_usados` (an Imocena video was always 1 of them), in tenths: the AI
 * photo cleanup costs 0.1.
 */
class Creditos
{
    /** Imoplanta paid in credits (its 50 € at 10 € a credit); and what an old AI video (25 €) became, rounded up. */
    public const IMOPLANTA = 5;

    public const VIDEO_IA_ANTIGO = 3;

    /**
     * Spends $n credits from the oldest purchases with any left, all or nothing; call it inside the caller's
     * transaction (the rows are locked). Returns the first purchase's id, or null when the account has fewer.
     */
    public function gastar(User $user, float $n): ?int
    {
        $n = round($n, 1);
        $compras = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')
            ->orderBy('id')->lockForUpdate()->get(['id', 'videos', 'videos_usados']);
        if ($n <= 0 || round($compras->sum(fn ($c) => $c->videos - $c->videos_usados), 1) < $n) {
            return null;
        }
        $falta = $n;
        foreach ($compras as $c) {
            $tirar = round(min($falta, $c->videos - $c->videos_usados), 1);
            DB::table('compras')->where('id', $c->id)->update(['videos_usados' => round($c->videos_usados + $tirar, 1), 'updated_at' => now()]);
            if (($falta = round($falta - $tirar, 1)) <= 0) {
                break;
            }
        }

        return $compras->first()->id;
    }

    /** Gives $n credits back (a refund), to the newest purchases that spent them. */
    public function devolver(User $user, float $n): void
    {
        DB::transaction(function () use ($user, $n) {
            $n = round($n, 1);
            foreach (DB::table('compras')->where('user_id', $user->id)->where('videos_usados', '>', 0)->orderByDesc('id')->lockForUpdate()->get() as $c) {
                if ($n <= 0) {
                    break;
                }
                $dar = round(min($n, $c->videos_usados), 1);
                DB::table('compras')->where('id', $c->id)->update(['videos_usados' => round($c->videos_usados - $dar, 1), 'updated_at' => now()]);
                $n = round($n - $dar, 1);
            }
        });
    }

    /** A number of credits as the pages show it: «42», «41,9», «0,1». */
    public static function txt(float $n): string
    {
        return rtrim(rtrim(number_format(round($n, 1), 1, ',', ' '), '0'), ',');
    }

    /** «0,1 crédito de vídeo», «1 crédito de vídeo», «2,5 créditos de vídeo» (with $de false, no «de vídeo»). */
    public static function rotulo(float $n, bool $de = true): string
    {
        return self::txt($n).' '.(round($n, 1) <= 1 ? 'crédito' : 'créditos').($de ? ' de vídeo' : '');
    }
}
