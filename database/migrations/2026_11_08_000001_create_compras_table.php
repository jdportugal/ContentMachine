<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Purchases made through Stripe Payment Links (Vídeo avulso, Pack 10, Pack 20),
// recorded from the checkout.session.completed webhook. One row per session.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('sessao')->unique();                 // Stripe checkout session id
            $table->string('payment_link')->nullable();         // plink_…
            $table->string('email')->nullable();
            $table->string('nome')->nullable();
            $table->string('nif')->nullable();                  // tax id given at checkout
            $table->unsignedInteger('total');                   // cents, VAT included
            $table->string('moeda', 3)->default('eur');
            $table->unsignedSmallInteger('videos')->default(0); // credits bought (1 / 10 / 20)
            $table->unsignedSmallInteger('videos_usados')->default(0);
            $table->unsignedBigInteger('lead_id')->nullable();  // the free video this pays for
            $table->timestamp('entregue_em')->nullable();       // clean render dispatched
            $table->json('dados')->nullable();                  // the session, for the record
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('pago_em')->nullable()->after('pronto_em');
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('pago_em'));
        Schema::dropIfExists('compras');
    }
};
