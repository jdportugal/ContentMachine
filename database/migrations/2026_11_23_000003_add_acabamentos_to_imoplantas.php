<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Each property's finishes (Acabamentos::PALETAS key) and their board image, made once before the photos and sent
// with every one of them, so all photos share the same floor wood, tiles, doors and window frames.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->string('acabamentos')->nullable()->after('piso');
            $table->string('acabamentos_img')->nullable()->after('acabamentos');
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->dropColumn(['acabamentos', 'acabamentos_img']));
    }
};
