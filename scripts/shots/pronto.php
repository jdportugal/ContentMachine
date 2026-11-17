<?php
// CI only: marks the newest lead's video as made (a placeholder MP4), so the ready card shows.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$mp4 = $argv[1];
$l = DB::table('leads')->latest('id')->first();
$c = app(App\Services\Leads\LeadVideo::class)->clip($l);
$c->update(['status' => App\Services\Clips\Store\ClipRecord::STATUS_DONE, 'output_path' => $mp4, 'meta' => array_merge((array) $c->meta, ['limpo_path' => $mp4])]);
DB::table('leads')->where('id', $l->id)->update(['pronto_em' => now(), 'nome' => $argv[2] ?? null]);
echo $l->id, PHP_EOL;
