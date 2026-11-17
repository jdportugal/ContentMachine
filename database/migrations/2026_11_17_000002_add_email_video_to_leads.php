<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "O vídeo do seu imóvel está pronto": the email the agent leaves on the form to get their
 * video (a service email, not marketing), and when that email was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('email_video')->nullable();
            $table->timestamp('aviso_pronto_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn(['email_video', 'aviso_pronto_em']));
    }
};
