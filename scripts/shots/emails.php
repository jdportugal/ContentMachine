<?php
// CI only: the two invite emails as HTML files, to screenshot.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[1].'/email-convite.html', view('emails.convite', ['nome' => 'Ana Silva', 'link' => 'https://imocena.com/?convite=abc'])->render());
file_put_contents($argv[1].'/email-oferta.html', view('emails.convite-oferta', ['nome' => 'Ana Silva', 'video' => '#', 'descarregar' => '#'])->render());
echo DB::table('leads')->latest('id')->value('convite'), PHP_EOL;
