<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A tool can have the AI make both frames (drone: the aerial view and the one after orbiting): `alvo` is which frame
// the customer's change is for; `versoes` becomes {inicio: [...], fim: [...]}.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->string('alvo')->nullable()->after('pedido'));
    }

    public function down(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->dropColumn('alvo'));
    }
};
