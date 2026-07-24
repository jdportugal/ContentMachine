<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the animated-clip pipeline off the database: each clip_projects row
 * becomes a JSON record in the default project's vault (clips-animados/<id>.json),
 * matching the new ClipStore format, then the table is dropped. The clip's own
 * numeric id is preserved as the record id so existing render output paths keep
 * resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clip_projects')) {
            return;
        }

        $dir = rtrim((string) config('contentmachine.projects.default_vault', base_path('vault')), '/').'/clips-animados';
        @mkdir($dir, 0775, true);

        foreach (DB::table('clip_projects')->get() as $row) {
            $attrs = [
                'id' => (string) $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'input_kind' => $row->input_kind,
                'title' => $row->title,
                'source_text' => $row->source_text,
                'source_path' => $row->source_path,
                'audio_path' => $row->audio_path,
                'transcript' => json_decode($row->transcript ?? 'null', true),
                'plan' => json_decode($row->plan ?? 'null', true),
                'images' => json_decode($row->images ?? 'null', true),
                'meta' => json_decode($row->meta ?? 'null', true),
                'output_path' => $row->output_path,
                'error' => $row->error,
                'scheduled_for' => $row->scheduled_for,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
            file_put_contents(
                $dir.'/'.$row->id.'.json',
                json_encode($attrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        Schema::dropIfExists('clip_projects');
    }

    public function down(): void
    {
        // Irreversible data migration — records now live in the vault as JSON.
    }
};
