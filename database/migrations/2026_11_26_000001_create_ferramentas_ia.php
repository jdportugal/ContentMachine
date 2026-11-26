<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ferramentas IA: packs of "vídeos IA" on the same purchases (a Payment Link's metadata videos_ia=N), and one row
// per video: the customer's upload, the start and end frames they approve, the clip (design: docs/superpowers/specs/2026-11-26-ferramentas-ia-design.md).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->unsignedSmallInteger('videos_ia')->default(0)->after('imoveis_usados');
            $table->unsignedSmallInteger('videos_ia_usados')->default(0)->after('videos_ia');
        });
        Schema::create('ferramentas_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('compra_id')->nullable();   // the purchase its video came from (null: not spent, or free)
            $table->string('ferramenta');                           // drone | limpeza | remodelacao | construcao | planta
            $table->string('estado');
            $table->string('original');                             // the customer's photo (or plan), never changed
            $table->string('inicio')->nullable();                   // the approved / current start frame
            $table->string('fim')->nullable();                      // the approved / current end frame
            $table->json('versoes')->nullable();                    // every AI frame made, oldest first
            $table->unsignedTinyInteger('edicoes')->default(0);     // changes to the AI frame used
            $table->string('video')->nullable();
            $table->unsignedTinyInteger('refeitos')->default(0);    // new versions of the clip used
            $table->text('pedido')->nullable();                     // the last change / note asked for
            $table->string('erro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ferramentas_ia');
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn(['videos_ia', 'videos_ia_usados']));
    }
};
