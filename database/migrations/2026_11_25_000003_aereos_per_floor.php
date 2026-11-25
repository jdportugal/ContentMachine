<?php

use App\Services\Imoplanta\ImoplantaCasa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One realistic top view per storey: {"0": {"render": path, "img": path}, "1": …}, replacing the single one. */
    public function up(): void
    {
        Schema::table('imoplantas', fn (Blueprint $t) => $t->json('aereos')->nullable()->after('acabamentos_img'));
        foreach (DB::table('imoplantas')->whereNotNull('aereo_render')->get() as $p) {
            $casa = json_decode((string) $p->casa, true) ?: [];
            $n = (string) ImoplantaCasa::nivelPrincipal($casa);   // the single top view was the main storey's
            DB::table('imoplantas')->where('id', $p->id)->update(['aereos' => json_encode([$n => array_filter(['render' => $p->aereo_render, 'img' => $p->aereo_img])])]);
        }
        Schema::table('imoplantas', fn (Blueprint $t) => $t->dropColumn(['aereo_render', 'aereo_img']));
    }

    public function down(): void
    {
        Schema::table('imoplantas', function (Blueprint $t) {
            $t->string('aereo_render')->nullable();
            $t->string('aereo_img')->nullable();
        });
        foreach (DB::table('imoplantas')->whereNotNull('aereos')->get() as $p) {
            $a = collect(json_decode((string) $p->aereos, true) ?: [])->first() ?? [];
            DB::table('imoplantas')->where('id', $p->id)->update(['aereo_render' => $a['render'] ?? null, 'aereo_img' => $a['img'] ?? null]);
        }
        Schema::table('imoplantas', fn (Blueprint $t) => $t->dropColumn('aereos'));
    }
};
