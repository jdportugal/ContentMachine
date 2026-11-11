<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New-listing alerts. An agent who ticked the box on the free-video form is watched:
 * their public page on the portal is read every hour, and when a listing appears that
 * was not there before they get an email suggesting its video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultores_vigiados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->unique();   // the lead whose consent this is
            $table->string('email')->index();
            $table->string('portal', 10);                      // remax | iad | exp
            $table->string('estado', 12)->default('ativo');    // ativo | sem-pagina | duplicado
            $table->string('chave')->nullable();               // the agent on the portal, e.g. remax:125411383
            $table->string('pagina')->nullable();              // their public page
            $table->json('conhecidos')->nullable();            // listing ids already seen (null = not read yet)
            $table->unsignedSmallInteger('falhas')->default(0);
            $table->timestamp('verificado_em')->nullable();
            $table->timestamp('avisado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('avisos_imoveis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultor_id')->index();
            $table->string('anuncio_id');
            $table->string('titulo')->default('');
            $table->string('url', 600)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_imoveis');
        Schema::dropIfExists('consultores_vigiados');
    }
};
