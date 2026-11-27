<?php

namespace App\Services\Pagamentos;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One balance of video credits (user, 2026-11-27): an Imocena video costs 1, an Imoplanta property 5 (shown as its
 * 50 €), each AI tool its own number (Ferramentas::lista()[tool]['creditos']). 1 credit = 10 € at the pack price.
 * The credits are `compras.videos` / `videos_usados` (an Imocena video was always 1 of them).
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
    public function gastar(User $user, int $n): ?int
    {
        $compras = DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')
            ->orderBy('id')->lockForUpdate()->get(['id', 'videos', 'videos_usados']);
        if ($n < 1 || $compras->sum(fn ($c) => $c->videos - $c->videos_usados) < $n) {
            return null;
        }
        $falta = $n;
        foreach ($compras as $c) {
            $tirar = min($falta, $c->videos - $c->videos_usados);
            DB::table('compras')->where('id', $c->id)->increment('videos_usados', $tirar, ['updated_at' => now()]);
            if (($falta -= $tirar) === 0) {
                break;
            }
        }

        return $compras->first()->id;
    }

    /** Gives $n credits back (a refund), to the newest purchases that spent them. */
    public function devolver(User $user, int $n): void
    {
        DB::transaction(function () use ($user, $n) {
            foreach (DB::table('compras')->where('user_id', $user->id)->where('videos_usados', '>', 0)->orderByDesc('id')->lockForUpdate()->get() as $c) {
                if ($n < 1) {
                    break;
                }
                $dar = min($n, $c->videos_usados);
                DB::table('compras')->where('id', $c->id)->decrement('videos_usados', $dar, ['updated_at' => now()]);
                $n -= $dar;
            }
        });
    }
}
