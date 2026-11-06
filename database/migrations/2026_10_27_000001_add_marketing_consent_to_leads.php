<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional consent to promotional emails (RGPD art. 7 + Lei 41/2004 art. 13.º-A):
// separate from the free-video consent, never pre-ticked, and kept as proof —
// when, the exact wording agreed to, and when it was withdrawn.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('email')->nullable()->after('telefone');
            $table->timestamp('marketing_em')->nullable()->after('consentimento_em');
            $table->text('marketing_texto')->nullable()->after('marketing_em');
            $table->timestamp('marketing_cancelado_em')->nullable()->after('marketing_texto');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['email', 'marketing_em', 'marketing_texto', 'marketing_cancelado_em']);
        });
    }
};
