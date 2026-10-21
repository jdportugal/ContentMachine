<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Free-video requests from the public landing page: who asked, for which listing,
// and the clip made for them (in `projeto`), until the link is emailed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->index();
            $table->string('telefone');
            $table->string('anuncio');                    // listing URL
            $table->string('projeto');                    // project slug the clip lives in
            $table->string('clip_id')->nullable();
            $table->string('ip', 45)->default('');
            $table->timestamp('consentimento_em');        // RGPD consent
            $table->timestamp('enviado_em')->nullable();  // video link emailed
            $table->timestamp('falhou_em')->nullable();   // pipeline failed: needs a human
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
