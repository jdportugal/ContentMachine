<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per visit to the public site (the first page of a session), with where it
 * came from. No IP and no cookie of its own: it is the middle of the campaign funnel
 * (ad click → visit → video asked for → purchase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->string('canal', 20)->index();      // Canal::de() of the origin
            $table->string('pagina')->default('/');
            $table->text('origem')->nullable();        // Origem JSON (utm tags, click id, referrer)
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas');
    }
};
