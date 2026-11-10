<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Attribution: where each lead and purchase came from (UTM tags, click ids, referrer),
// and when a purchase was reported to the ad platforms (so it is reported once).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', fn (Blueprint $t) => $t->text('origem')->nullable());
        Schema::table('compras', function (Blueprint $t) {
            $t->text('origem')->nullable();
            $t->timestamp('medido_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn('origem'));
        Schema::table('compras', fn (Blueprint $t) => $t->dropColumn(['origem', 'medido_em']));
    }
};
