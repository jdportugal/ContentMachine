<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_effects', function (Blueprint $table) {
            $table->id();
            $table->text('prompt');               // the user's plain-language request
            $table->string('slug')->unique();     // layer `type` used in plans
            $table->string('display_name');
            $table->text('description');          // one line for the planner vocabulary
            $table->text('param_schema');         // schema line appended to the planner prompt
            $table->text('sample_text')->nullable();
            $table->json('sample_params')->nullable();
            $table->longText('tsx');              // generated component source (source of truth)
            $table->string('status')->default('pending'); // pending | active | failed
            $table->text('error')->nullable();
            $table->string('preview_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clip_effects');
    }
};
