<?php

use App\Livewire\Ativos;
use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\Definicoes;
use App\Livewire\Monitorizacao;
use App\Livewire\Noticias;
use App\Livewire\Painel;
use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Painel::class)->name('painel');
Route::livewire('/monitorizacao', Monitorizacao::class)->name('monitorizacao');
Route::livewire('/ativos', Ativos::class)->name('ativos');
Route::livewire('/clips', Clips::class)->name('clips');

// Serve o vídeo do clip para pré-visualização. Devolve o melhor disponível:
// short final (com música > legendado) ou, se ainda só foi cortado, o corte cru.
// ?v=raw força o corte cru; ?v=final força o short legendado.
Route::get('/clips/{slug}/video', function (string $slug) {
    $dir = storage_path('app/shorts/'.basename($slug));

    $ordem = match (request('v')) {
        'raw' => ['raw.mp4'],
        'final' => ['final-music.mp4', 'final.mp4'],
        default => ['final-music.mp4', 'final.mp4', 'raw.mp4'],
    };

    $path = collect($ordem)
        ->map(fn ($f) => $dir.'/'.$f)
        ->first(fn ($p) => is_file($p));

    abort_unless($path !== null, 404);

    return response()->file($path);
})->name('clips.video');

// Serve uma faixa da biblioteca de música (storage/app/shorts/musicas) para pré-visualização.
Route::get('/clips/musica/{name}', function (string $name) {
    $path = app(\App\Services\Shorts\MusicLibrary::class)->pathFor($name);
    abort_unless($path !== null, 404);

    return response()->file($path);
})->name('clips.musica');
Route::livewire('/clips-animados', ClipsAnimados::class)->name('clips-animados');

Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
Route::livewire('/publicacoes/{tipo}', Oficina::class)->name('publicacoes.oficina');

Route::livewire('/rascunhos', Rascunhos::class)->name('rascunhos');
Route::livewire('/noticias', Noticias::class)->name('noticias');
Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
