<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A listing can have more than one video: an agent who comes back for the same listing (or a
 * colleague with it) gets a new free video instead of a dead end. Keep a plain index for lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['anuncio']);
            $table->index('anuncio');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['anuncio']);
            $table->unique('anuncio');
        });
    }
};
