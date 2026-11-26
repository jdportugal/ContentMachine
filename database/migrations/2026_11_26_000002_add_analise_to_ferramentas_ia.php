<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The check of the customer's photo for its tool (AnalisarFotoJob): {estado: a_analisar|feito, adequada, problemas, dica}.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->json('analise')->nullable()->after('original'));
    }

    public function down(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->dropColumn('analise'));
    }
};
