<?php

use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\Definicoes;
use App\Livewire\Monitorizacao;
use App\Livewire\Noticias;
use App\Livewire\Painel;
use App\Livewire\Publicacoes\Carrosseis;
use App\Livewire\Publicacoes\Posts;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use App\Models\ClipProject;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Painel::class)->name('painel');
Route::livewire('/monitorizacao', Monitorizacao::class)->name('monitorizacao');
Route::livewire('/clips', Clips::class)->name('clips');
Route::livewire('/clips-animados', ClipsAnimados::class)->name('clips-animados');

// Serve uma imagem carregada (miniatura), pelo nome de ficheiro (aleatório).
Route::get('/clips-animados/upload/{name}', function (string $name) {
    abort_unless((bool) preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+$/', $name), 404);
    $disk = \Illuminate\Support\Facades\Storage::disk(config('contentmachine.clips.disk'));
    abort_unless($disk->exists("clips/uploads/{$name}"), 404);

    return response()->file($disk->path("clips/uploads/{$name}"));
})->name('clips-animados.upload');

// Serve/descarrega o ficheiro final de um clip.
Route::get('/clips-animados/{project}/media', function (ClipProject $project) {
    abort_unless($project->output_path && is_file($project->output_path), 404);

    return request()->boolean('download')
        ? response()->download($project->output_path)
        : response()->file($project->output_path);
})->name('clips-animados.media');

Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
Route::livewire('/publicacoes/posts', Posts::class)->name('publicacoes.posts');
Route::livewire('/publicacoes/carrosseis', Carrosseis::class)->name('publicacoes.carrosseis');

Route::livewire('/rascunhos', Rascunhos::class)->name('rascunhos');
Route::livewire('/noticias', Noticias::class)->name('noticias');
Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
