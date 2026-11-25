<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The walkthrough video: one short AI camera move per photo (Kling image-to-video), joined with crossfades.
// video_clips keeps each finished clip by image id, so a retry never pays for a clip twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imoplantas', function (Blueprint $table) {
            $table->string('video')->nullable()->after('acabamentos_img');
            $table->string('video_estado')->nullable()->after('video');   // a_fazer | pronto | falhou
            $table->string('video_erro')->nullable()->after('video_estado');
            $table->json('video_clips')->nullable()->after('video_erro');
        });
    }

    public function down(): void
    {
        Schema::table('imoplantas', fn (Blueprint $table) => $table->dropColumn(['video', 'video_estado', 'video_erro', 'video_clips']));
    }
};
