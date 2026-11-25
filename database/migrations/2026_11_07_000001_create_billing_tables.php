<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Paid videos. Sign-up now makes a customer, not an admin: `is_admin` keeps the
// studio (and every stored API key) to the accounts that existed before — all of
// them admins. A Stripe purchase is a row in `compras` holding a number of videos
// that expire together; each video a customer makes is a row in `videos`, spending
// one from the purchase that expires first.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });
        DB::table('users')->update(['is_admin' => true]);

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plano');
            $table->unsignedInteger('videos');
            $table->unsignedInteger('restantes');
            $table->unsignedInteger('valor');                  // cents, VAT included
            $table->string('stripe_sessao')->unique();         // Checkout Session: fulfilled once
            $table->string('stripe_pagamento')->nullable()->index();   // PaymentIntent, for refunds
            $table->string('nif')->nullable();                 // tax id given at checkout, for the invoice
            $table->timestamp('expira_em');
            $table->timestamp('reembolsado_em')->nullable();
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->string('projeto');                         // project slug the clip lives in
            $table->string('clip_id')->index();
            $table->string('anuncio');
            $table->timestamp('terminado_em')->nullable();     // rendered or failed: no longer watched
            $table->timestamp('devolvido_em')->nullable();     // failed: the video went back to the purchase
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
        Schema::dropIfExists('compras');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
