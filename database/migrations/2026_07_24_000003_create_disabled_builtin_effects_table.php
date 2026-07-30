<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Presence = the built-in effect is disallowed for the planner. Absence
        // = allowed (the default). Custom effects use clip_effects.enabled instead.
        Schema::create('disabled_builtin_effects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disabled_builtin_effects');
    }
};
