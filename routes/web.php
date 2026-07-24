<?php

use App\Livewire\Ativos;
use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\Definicoes;
use App\Livewire\DesignSystem;
use App\Livewire\Monitorizacao;
use App\Livewire\Noticias;
use App\Livewire\Painel;
use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use App\Models\ClipProject;
use App\Services\Clips\EffectLibrary;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
    $path = app(MusicLibrary::class)->pathFor($name);
    abort_unless($path !== null, 404);

    return response()->file($path);
})->name('clips.musica');
Route::livewire('/clips-animados', ClipsAnimados::class)->name('clips-animados');

// Serve uma imagem carregada (miniatura), pelo nome de ficheiro (aleatório).
Route::get('/clips-animados/upload/{name}', function (string $name) {
    abort_unless((bool) preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+$/', $name), 404);
    $disk = Storage::disk(config('contentmachine.clips.disk'));
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

// Serve the cached showcase preview of an SFX (built-in or custom), for the
// current design system. 404 until the sample has been rendered.
Route::get('/clips-animados/sfx/{slug}/preview', function (string $slug) {
    abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*$/', $slug), 404);
    $path = app(EffectLibrary::class)->previewPath($slug);
    abort_unless(is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.sfx-preview');

Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
Route::livewire('/publicacoes/{tipo}', Oficina::class)->name('publicacoes.oficina');

Route::livewire('/rascunhos', Rascunhos::class)->name('rascunhos');
Route::livewire('/noticias', Noticias::class)->name('noticias');
Route::livewire('/design-system', DesignSystem::class)->name('design-system');
Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
