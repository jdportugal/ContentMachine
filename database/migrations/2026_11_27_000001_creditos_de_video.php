<?php

use App\Services\Pagamentos\Creditos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One balance of video credits (compras.videos): what was left of Imoplanta properties (5 credits each) and of
 * AI videos (3 each) is folded in, and those columns are marked spent. An AI video remembers what it cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->unsignedTinyInteger('creditos')->default(1)->after('compra_id'));
        DB::table('compras')->whereColumn('imoveis_usados', '<', 'imoveis')->orWhereColumn('videos_ia_usados', '<', 'videos_ia')
            ->orderBy('id')->get()->each(function ($c) {
                DB::table('compras')->where('id', $c->id)->update([
                    'videos' => $c->videos + ($c->imoveis - $c->imoveis_usados) * Creditos::IMOPLANTA + ($c->videos_ia - $c->videos_ia_usados) * Creditos::VIDEO_IA_ANTIGO,
                    'imoveis_usados' => $c->imoveis, 'videos_ia_usados' => $c->videos_ia,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->dropColumn('creditos'));
    }
};
