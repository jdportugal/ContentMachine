<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Imoplanta on the same purchases as the videos: a Payment Link's metadata says what it sold — imoveis=N
// (property credits, spent one per property) or extra=planta|video|ambos (a property's add-on) — and its
// client_reference_id (imoplanta-<id>) the property it was bought from.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->unsignedSmallInteger('imoveis')->default(0)->after('videos_usados');
            $table->unsignedSmallInteger('imoveis_usados')->default(0)->after('imoveis');
            $table->unsignedBigInteger('imoplanta_id')->nullable()->after('lead_id');
            $table->string('extra')->nullable()->after('imoplanta_id');
        });
    }

    public function down(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn(['imoveis', 'imoveis_usados', 'imoplanta_id', 'extra']));
    }
};
