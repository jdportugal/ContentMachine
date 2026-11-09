<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Customer accounts. Until now every account was the studio's operator; buyers now
// get an account too (created at checkout, signed in by email link), so the studio
// is gated on is_admin and customers see only their own videos and credits.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
        });
        // Every account that exists today is the operator's.
        DB::table('users')->update(['is_admin' => true]);

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
        });
        Schema::table('compras', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $table->timestamp('login_em')->nullable()->after('entregue_em');   // the thank-you page's one sign-in
        });
    }

    public function down(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn(['user_id', 'login_em']));
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('user_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
