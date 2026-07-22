<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_projects', function (Blueprint $table) {
            $table->id();
            $table->string('type');            // animation | overlay
            $table->string('status')->default('draft');
            $table->string('input_kind');      // text | audio | video
            $table->string('title')->nullable();
            $table->text('source_text')->nullable();
            $table->string('source_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->json('transcript')->nullable();
            $table->json('plan')->nullable();
            $table->string('output_path')->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_projects');
    }
};
