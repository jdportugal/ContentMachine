<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Imoplanta: house plans → 3D model (three.js, in the browser) → planned shots → photoreal images.
// A purchase (`compras`) now has a `tipo`: 'videos' (the reels, as before) or
// 'imoplanta' (one credit = one property). Each property is a row in
// `imoplantas`, spending one credit from the purchase that expires first; its
// images are rows in `imoplanta_imagens`. `custos` gains the columns the owner
// needs to price Imoplanta: the step, the model, tokens, units and currency.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('tipo')->default('videos')->after('plano')->index();
        });

        Schema::create('imoplantas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();   // null: an admin's
            $table->string('nome');
            $table->string('estado')->index();
            $table->string('erro')->nullable();
            $table->json('plantas');                    // stored plan image paths (local disk)
            $table->json('casa')->nullable();           // the house description (ImoplantaCasa schema)
            $table->json('cameras')->nullable();        // planned shots (ImoplantaCasa::cameras)
            $table->unsignedInteger('versao')->default(0);           // house descriptions so far (the page rebuilds the 3D view)
            $table->unsignedTinyInteger('refeitos_3d')->default(0);  // AI changes to the 3D model used
            $table->json('pedidos')->nullable();        // the change requests, in order
            $table->timestamps();
        });

        Schema::create('imoplanta_imagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imoplanta_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('numero');      // the camera's number in the shot plan
            $table->json('camera');
            $table->string('estado');
            $table->string('render')->nullable();       // the browser's three.js render (1600×900 PNG)
            $table->string('foto')->nullable();         // current photoreal image path
            $table->unsignedTinyInteger('refeitos')->default(0);    // AI re-dos used
            $table->text('pedido')->nullable();         // the last change asked for
            $table->string('erro')->nullable();
            $table->timestamps();
        });

        Schema::table('custos', function (Blueprint $table) {
            $table->string('passo')->nullable();                     // extract | ai_change_3d | image_generate | image_redo | stripe_fee
            $table->string('modelo')->nullable();
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_saida')->nullable();
            $table->unsignedInteger('tokens_cache')->nullable();
            $table->decimal('unidades', 12, 3)->nullable();          // images, seconds, …
            $table->string('moeda', 3)->default('USD');              // provider prices are USD; Stripe fees EUR
        });
    }

    public function down(): void
    {
        Schema::table('custos', fn (Blueprint $table) => $table->dropColumn(['passo', 'modelo', 'tokens_entrada', 'tokens_saida', 'tokens_cache', 'unidades', 'moeda']));
        Schema::dropIfExists('imoplanta_imagens');
        Schema::dropIfExists('imoplantas');
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn('tipo');
        });
    }
};
