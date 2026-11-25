<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Step 2 (materials and 3D floor plans): the finishes board's progress, and the customer's changes to it. */
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $t) {
            $t->string('quadro_estado')->nullable()->after('acabamentos_img');       // a_fazer | falhou | null (done or not asked)
            $t->string('quadro_erro')->nullable()->after('quadro_estado');
            $t->unsignedTinyInteger('acabamentos_refeitos')->default(0)->after('quadro_erro');
            $t->json('acabamentos_pedidos')->nullable()->after('acabamentos_refeitos');   // the changes asked, in order
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $t) => $t->dropColumn(['quadro_estado', 'quadro_erro', 'acabamentos_refeitos', 'acabamentos_pedidos']));
    }
};
