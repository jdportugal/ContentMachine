<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The "convide um colega e fica com o vídeo limpo" reminder, sent once a couple of days after a free video. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->timestamp('lembrete_convite_em')->nullable());
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('lembrete_convite_em'));
    }
};
