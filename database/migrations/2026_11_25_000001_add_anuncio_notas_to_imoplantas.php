<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->string('anuncio', 500)->nullable()->after('referencias');   // the customer's listing or project link
            $table->text('notas')->nullable()->after('anuncio');               // what the customer told us about the home
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->dropColumn(['anuncio', 'notas']);
        });
    }
};
