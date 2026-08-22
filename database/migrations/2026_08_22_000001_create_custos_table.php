<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ledger of provider spend, attributed to the piece of content that caused it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custos', function (Blueprint $table) {
            $table->id();
            $table->string('projeto')->index();   // project slug
            $table->string('tipo');               // clip | publicacao | geral
            $table->string('peca');               // clip id / nota slug / plan token
            $table->string('servico');            // kie.ai | anthropic | elevenlabs | …
            $table->decimal('custo', 10, 5);      // USD
            $table->string('detalhe')->default('');
            $table->timestamp('created_at');
            $table->index(['projeto', 'tipo', 'peca']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custos');
    }
};
