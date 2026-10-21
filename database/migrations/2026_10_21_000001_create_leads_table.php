<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Free-video requests from the public landing page: the listing, the clip made for
// it (in `projeto`), and the agent's name/phone read from the listing for follow-up.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('anuncio')->unique();          // listing URL — one free video each
            $table->string('nome')->nullable();           // agent, from the listing
            $table->string('telefone')->nullable();       // agent, from the listing
            $table->string('projeto');                    // project slug the clip lives in
            $table->string('clip_id')->nullable();
            $table->string('ip', 45)->default('');
            $table->timestamp('consentimento_em');        // RGPD consent
            $table->timestamp('pronto_em')->nullable();   // reel rendered
            $table->timestamp('falhou_em')->nullable();   // pipeline failed: needs a human
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
