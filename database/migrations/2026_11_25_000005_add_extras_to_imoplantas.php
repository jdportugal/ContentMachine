<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 4's paid add-ons, by key: 'planta' (the plan video: empty plot → floors and walls → furniture, every
     * storey) and 'video' (the walkthrough made into an Imocena reel). Each: estado (a_pagar | a_fazer | pronto |
     * falhou), plus its file, error and, for the reel, the details given and the clip id.
     */
    public function up(): void
    {
        Schema::table('imoplantas', fn (Blueprint $t) => $t->json('extras')->nullable());
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $t) => $t->dropColumn('extras'));
    }
};
