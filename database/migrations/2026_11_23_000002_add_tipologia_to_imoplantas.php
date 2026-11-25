<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What kind of home the plans are for (Imoplanta::TIPOLOGIAS) and, for a flat, its floor (0 = ground floor):
// the plan reader, the 3D model (neighbours, the building around a flat) and the photo prompts use them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->string('tipologia')->default('isolada')->after('nome');
            $table->unsignedTinyInteger('piso')->nullable()->after('tipologia');
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->dropColumn(['tipologia', 'piso']));
    }
};
