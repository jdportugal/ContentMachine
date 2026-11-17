<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invite a colleague: an agent with a free (watermarked) video shares their link; when a
 * colleague arriving by it gets their own free video made, the agent's video is unlocked clean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('convite', 16)->nullable()->unique();            // this agent's invite code (?convite=…)
            $table->unsignedBigInteger('convidado_por')->nullable()->index(); // the lead whose invite brought this one
            $table->string('convite_email')->nullable();                     // where to send the clean video (not for marketing)
            $table->timestamp('oferta_em')->nullable();                      // clean video unlocked by an invite
        });

        // Emails we sent to colleagues on an agent's behalf: one per address per agent.
        Schema::create('convites_enviados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->index();
            $table->string('email');
            $table->timestamp('created_at')->nullable();
            $table->unique(['lead_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites_enviados');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['convite']);
            $table->dropIndex(['convidado_por']);
        });
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['convite', 'convidado_por', 'convite_email', 'oferta_em']));
    }
};
