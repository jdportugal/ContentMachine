<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Video credits in tenths (user, 2026-11-27: the AI photo cleanup costs 0.1 of a credit). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $t) {
            $t->decimal('videos', 8, 1)->default(0)->change();
            $t->decimal('videos_usados', 8, 1)->default(0)->change();
        });
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->decimal('creditos', 4, 1)->default(1)->change());
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $t) {
            $t->unsignedSmallInteger('videos')->default(0)->change();
            $t->unsignedSmallInteger('videos_usados')->default(0)->change();
        });
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->unsignedTinyInteger('creditos')->default(1)->change());
    }
};
