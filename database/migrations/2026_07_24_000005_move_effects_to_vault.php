<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves custom SFX off the database into the default project's vault: each
 * clip_effects row becomes sfx/fx-<id>.json (the EffectStore format) and the
 * disallowed built-ins become sfx/_disabled-builtins.json. Then both tables are
 * dropped. SFX are now per-project (they follow the active project's vault).
 */
return new class extends Migration
{
    public function up(): void
    {
        $dir = rtrim((string) config('contentmachine.projects.default_vault', base_path('vault')), '/').'/sfx';
        @mkdir($dir, 0775, true);

        if (Schema::hasTable('clip_effects')) {
            foreach (DB::table('clip_effects')->get() as $row) {
                $id = 'fx-'.$row->id;
                $attrs = [
                    'id' => $id,
                    'prompt' => $row->prompt,
                    'slug' => $row->slug,
                    'display_name' => $row->display_name,
                    'description' => $row->description,
                    'param_schema' => $row->param_schema,
                    'sample_text' => $row->sample_text,
                    'sample_params' => json_decode($row->sample_params ?? 'null', true),
                    'tsx' => $row->tsx,
                    'status' => $row->status,
                    'error' => $row->error,
                    'preview_path' => $row->preview_path,
                    'enabled' => (bool) ($row->enabled ?? true),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
                file_put_contents($dir.'/'.$id.'.json', json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            Schema::dropIfExists('clip_effects');
        }

        if (Schema::hasTable('disabled_builtin_effects')) {
            $slugs = DB::table('disabled_builtin_effects')->pluck('slug')->all();
            if ($slugs !== []) {
                file_put_contents($dir.'/_disabled-builtins.json', json_encode(array_values($slugs), JSON_PRETTY_PRINT));
            }
            Schema::dropIfExists('disabled_builtin_effects');
        }
    }

    public function down(): void
    {
        // Irreversible data migration — SFX now live in the vault as JSON.
    }
};
