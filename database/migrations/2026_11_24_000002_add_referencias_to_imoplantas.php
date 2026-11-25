<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The customer's other drawings and pictures of the property (elevations, sections, photos of the house or site,
// material samples), uploaded beside the plans: read with them into the model and sent with every photo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->json('referencias')->nullable()->after('plantas'));
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->dropColumn('referencias'));
    }
};
