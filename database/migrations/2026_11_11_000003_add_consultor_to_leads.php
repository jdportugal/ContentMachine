<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who the agent of each lead's listing is on their portal (remax:125411383, exp:brunocouto,
 * iad:ana.silva): the free video is one per agent, and an agent we already know is
 * offered a paid video instead. Existing RE/MAX and eXp leads are filled in from their link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->string('consultor')->nullable()->index());

        foreach (DB::table('leads')->get(['id', 'anuncio']) as $lead) {
            $host = strtolower((string) parse_url($lead->anuncio, PHP_URL_HOST));
            $chave = match (true) {
                (bool) preg_match('#(^|\.)remax\.pt$#', $host) && preg_match('#/(\d{5,})-\d+/?$#', (string) parse_url($lead->anuncio, PHP_URL_PATH), $m) => 'remax:'.$m[1],
                (bool) preg_match('#^([a-z0-9-]+)\.expportugal\.com$#', $host, $m) && $m[1] !== 'www' => 'exp:'.$m[1],
                default => null,
            };
            if ($chave) {
                DB::table('leads')->where('id', $lead->id)->update(['consultor' => $chave]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('consultor'));
    }
};
