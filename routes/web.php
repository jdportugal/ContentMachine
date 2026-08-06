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
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\Store\ClipStore;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// ── Authentication ───────────────────────────────────────────────────────────
// Everything else in this file requires a signed-in user (Authenticate is
// appended to the web group in bootstrap/app.php); these two are the way in and
// out, so they opt out of it explicitly.
Route::livewire('/login', \App\Livewire\Auth\Login::class)
    ->name('login')
    ->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class)
    ->middleware('guest');

Route::livewire('/register', \App\Livewire\Auth\Register::class)
    ->name('register')
    ->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class)
    ->middleware('guest');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

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
Route::livewire('/clips-animados/sfx', \App\Livewire\ClipsAnimadosSfx::class)->name('clips-animados.sfx');
// Per-effect detail page (custom effect id or built-in slug). One segment, so it
// never collides with the /sfx/{slug}/preview and /sfx/{slug}/audio asset routes.
Route::livewire('/clips-animados/sfx/{key}', \App\Livewire\ClipsAnimadosSfx::class)->name('clips-animados.sfx.detail');

// Serve uma imagem carregada (miniatura), pelo nome de ficheiro (aleatório).
Route::get('/clips-animados/upload/{name}', function (string $name) {
    abort_unless((bool) preg_match('/^[A-Za-z0-9]+\.[A-Za-z0-9]+$/', $name), 404);
    $disk = Storage::disk(config('contentmachine.clips.disk'));
    abort_unless($disk->exists("clips/uploads/{$name}"), 404);

    return response()->file($disk->path("clips/uploads/{$name}"));
})->name('clips-animados.upload');

// Serve an image-library thumbnail (per-project vault), by id.
Route::get('/clips-animados/library-image/{id}', function (string $id) {
    $img = app(\App\Services\Clips\ImageLibrary::class)->find($id);
    abort_unless($img && is_file($img['path']), 404);

    return response()->file($img['path']);
})->name('clips-animados.library-image');

// Serve any image belonging to a clip (uploads or generated), by clip id + image id.
Route::get('/clips-animados/{id}/image/{imageId}', function (string $id, string $imageId) {
    $clip = app(ClipStore::class)->find($id);
    abort_unless($clip !== null, 404);
    $img = collect($clip->images ?? [])->firstWhere('id', $imageId);
    $disk = Storage::disk(config('contentmachine.clips.disk'));
    abort_unless($img && ! empty($img['path']) && $disk->exists($img['path']), 404);

    return response()->file($disk->path($img['path']));
})->name('clips-animados.clip-image');

// Serve/download a clip's final file (vault-backed, resolved for the active project).
Route::get('/clips-animados/{id}/media', function (string $id) {
    $clip = app(ClipStore::class)->find($id);
    abort_unless($clip && $clip->output_path && is_file($clip->output_path), 404);

    return request()->boolean('download')
        ? response()->download($clip->output_path)
        : response()->file($clip->output_path);
})->name('clips-animados.media');

// Download a custom SFX (or every one, id = 'all') as a self-contained JSON file
// — component source, metadata and its sound — for backup or moving between installs.
Route::get('/clips-animados/sfx/{id}/export', function (string $id) {
    abort_unless((bool) preg_match('/^[a-z0-9-]+$/i', $id), 404);
    $payload = app(\App\Services\Clips\EffectPortability::class)->export($id);
    abort_if($payload === null, 404);

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $name = $id === 'all' ? 'brand-machine-sfx.json' : 'sfx-'.$id.'.json';

    return response()->streamDownload(fn () => print($json), $name, [
        'Content-Type' => 'application/json',
    ]);
})->name('clips-animados.sfx-export');

// Download a background (or every one, id = 'all') as a self-contained JSON file —
// component source or mp4, plus metadata — for backup or moving between installs.
Route::get('/clips-animados/background/{id}/export', function (string $id) {
    abort_unless((bool) preg_match('/^[a-z0-9-]+$/i', $id), 404);
    $payload = app(\App\Services\Clips\BackgroundPortability::class)->export($id);
    abort_if($payload === null, 404);

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $name = $id === 'all' ? 'brand-machine-backgrounds.json' : 'background-'.$id.'.json';

    return response()->streamDownload(fn () => print($json), $name, [
        'Content-Type' => 'application/json',
    ]);
})->name('clips-animados.background-export');

// Serve the cached showcase preview of an SFX (built-in or custom), for the
// current design system. 404 until the sample has been rendered.
Route::get('/clips-animados/sfx/{slug}/preview', function (string $slug) {
    abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*$/', $slug), 404);
    $path = app(EffectLibrary::class)->previewPath($slug);
    abort_unless(is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.sfx-preview');

// Serve the sound attached to an effect (sfx-audio/<slug>.*), for playback in the
// SFX studio. 404 when the effect has no sound.
Route::get('/clips-animados/sfx/{slug}/audio', function (string $slug) {
    abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*$/', $slug), 404);
    $path = app(\App\Services\Clips\Store\EffectStore::class)->audioPath($slug);
    abort_unless($path !== null && is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.sfx-audio');

// Serve a custom background's preview: a code background's cached render (design
// aware) or a video background's mp4 file. 404 until ready. Keyed by record id.
Route::get('/clips-animados/background/{id}/preview', function (string $id) {
    $bg = app(\App\Services\Clips\Store\BackgroundStore::class)->find($id);
    abort_unless($bg !== null, 404);
    $path = app(\App\Services\Clips\BackgroundLibrary::class)->previewFileFor($bg);
    abort_unless($path !== null && is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.background-preview');

// Serve the cached backgrounds reel (one video cycling through every background)
// for the current design system + background set. 404 until it has been rendered.
Route::get('/clips-animados/background-reel', function () {
    $path = app(\App\Services\Clips\BackgroundLibrary::class)->reelPath();
    abort_unless(is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.background-reel');

// Serve the cached SFX showreel (one video cycling through every effect) for the
// current design system + effect set. 404 until it has been rendered.
Route::get('/clips-animados/showreel', function () {
    $path = app(EffectLibrary::class)->showreelPath();
    abort_unless(is_file($path), 404);

    return response()->file($path);
})->name('clips-animados.showreel');

Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
Route::livewire('/publicacoes/{tipo}', Oficina::class)->name('publicacoes.oficina');

Route::livewire('/finished', Rascunhos::class)->name('finished');
Route::livewire('/noticias', Noticias::class)->name('noticias');
Route::livewire('/design-system', DesignSystem::class)->name('design-system');
Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
