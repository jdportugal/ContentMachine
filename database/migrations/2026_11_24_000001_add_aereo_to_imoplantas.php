<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The overhead reference: the browser's top-view render of the 3D model with the roof off (aereo_render) and the
// photoreal 3D floor plan made from it and the finishes board (aereo_img), made once and sent with every photo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->string('aereo_render')->nullable()->after('acabamentos_img');
            $table->string('aereo_img')->nullable()->after('aereo_render');
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->dropColumn(['aereo_render', 'aereo_img']));
    }
};
