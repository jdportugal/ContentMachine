<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What visitors do on the landing page before (or instead of) asking for a video:
 * the agency they pick, a link we could not read, an email left to get the link
 * later or to be told when their agency is supported. The agency is also kept on
 * the lead, so requests can be split by agency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->index();        // escolha | link-recusado | lembrete | aviso-agencia
            $table->string('agencia', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('anuncio', 500)->nullable();
            $table->string('nota')->nullable();         // the agency's name, typed, when it is "outra"
            $table->text('origem')->nullable();         // Origem JSON
            $table->timestamp('created_at')->index();
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->string('agencia', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('agencia'));
        Schema::dropIfExists('landing_eventos');
    }
};
