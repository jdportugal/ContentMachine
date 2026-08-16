<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clip_effects', function (Blueprint $table) {
            // Allowed for the AI planner in generated videos. Disallowed effects
            // stay in the library (registered, previewable, editable) but the
            // planner never uses them.
            $table->boolean('enabled')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clip_effects', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
