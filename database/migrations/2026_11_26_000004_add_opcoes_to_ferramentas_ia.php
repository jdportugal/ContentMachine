<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What the customer chose for a tool, e.g. the side move's direction: {direcao: esquerda|direita}.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->json('opcoes')->nullable()->after('ferramenta'));
    }

    public function down(): void
    {
        Schema::table('ferramentas_ia', fn (Blueprint $t) => $t->dropColumn('opcoes'));
    }
};
