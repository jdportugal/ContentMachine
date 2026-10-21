<?php

use App\Livewire\Ativos;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\AvatarGen;
use App\Livewire\Clips;
use App\Livewire\ClipsAnimados;
use App\Livewire\ClipsAnimadosSfx;
use App\Livewire\ClipsAnimadosVfx;
use App\Livewire\ContentRepurpose;
use App\Livewire\Definicoes;
use App\Livewire\DesignSystem;
use App\Livewire\Monitorizacao;
use App\Livewire\Noticias;
use App\Livewire\Painel;
use App\Livewire\PostsGenerator;
use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use App\Services\Avatars\AvatarStore;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\BackgroundPortability;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\EffectPortability;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Clips\Store\VfxStore;
use App\Services\Projects\ProjectResources;
use App\Services\RealEstate\PropertyVideoEditor;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// ── Authentication ───────────────────────────────────────────────────────────
// The way in and out. Everything else lives in the `auth` group below.
Route::livewire('/login', Login::class)
    ->name('login')
    ->middleware('guest');

Route::livewire('/register', Register::class)
    ->name('register')
    ->middleware('guest');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// ── The application ──────────────────────────────────────────────────────────
// Every route below needs a signed-in user — pages AND the file-serving routes,
// which hand out rendered media. Applied here rather than to the `web` group:
// Livewire registers its own /livewire/update endpoint with `web`, so gating that
// group logs guests out of the sign-in and sign-up forms themselves (their form
// submissions are Livewire requests). Livewire re-applies Authenticate to update
// requests for these components on its own — it is persistent middleware.
Route::middleware('auth')->group(function () {
    Route::livewire('/', Painel::class)->name('painel');
    Route::livewire('/monitorizacao', Monitorizacao::class)->name('monitorizacao');
    Route::livewire('/ativos', Ativos::class)->name('ativos');
    Route::livewire('/clips', Clips::class)->name('clips');
    // Content Transformer subtabs. Registered before '/clips/{slug}/video' so the
    // literal segments are never swallowed by that wildcard.
    Route::livewire('/clips/posts', PostsGenerator::class)->name('clips.posts');
    Route::livewire('/clips/repurpose', ContentRepurpose::class)->name('clips.repurpose');

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
    Route::livewire('/clips-animados/sfx', ClipsAnimadosSfx::class)->name('clips-animados.sfx');
    Route::livewire('/clips-animados/vfx', ClipsAnimadosVfx::class)->name('clips-animados.vfx');
    // Backgrounds live inside the Animated Clips component, but they belong to the
    // Effects Studio's tab strip — which links, so the view needs a URL of its own.
    Route::livewire('/clips-animados/backgrounds', ClipsAnimados::class)
        ->defaults('view', 'backgrounds')
        ->name('clips-animados.backgrounds');

    // Serve a VFX Lab render: inline for the preview player, ?download=1 to save it.
    // 404 until the render finishes (or forever, if it failed).
    Route::get('/clips-animados/vfx/{id}/media', function (string $id) {
        abort_unless((bool) preg_match('/^[a-z0-9-]+$/', $id), 404);
        $store = app(VfxStore::class);
        $vfx = $store->find($id);
        abort_unless($vfx !== null, 404);
        $path = $store->videoFor($vfx);
        abort_unless($path !== null, 404);

        return request()->boolean('download')
            ? response()->download($path)
            : response()->file($path);
    })->name('clips-animados.vfx-media');
    // Per-effect detail page (custom effect id or built-in slug). One segment, so it
    // never collides with the /sfx/{slug}/preview and /sfx/{slug}/audio asset routes.
    Route::livewire('/clips-animados/sfx/{key}', ClipsAnimadosSfx::class)->name('clips-animados.sfx.detail');

    // Serve uma imagem carregada OU gerada (miniatura), pelo nome de ficheiro.
    // Generated images live in clips/generated — without that fallback the review
    // page showed «AI image — ready» with an invisible (404) thumbnail.
    Route::get('/clips-animados/upload/{name}', function (string $name) {
        abort_unless((bool) preg_match('/^[A-Za-z0-9-]+\.[A-Za-z0-9]+$/', $name), 404);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        foreach (["clips/uploads/{$name}", "clips/generated/{$name}"] as $path) {
            if ($disk->exists($path)) {
                return response()->file($disk->path($path));
            }
        }
        abort(404);
    })->name('clips-animados.upload');

    // Serve an image-library thumbnail (per-project vault), by id.
    Route::get('/clips-animados/library-image/{id}', function (string $id) {
        $img = app(ImageLibrary::class)->find($id);
        abort_unless($img && is_file($img['path']), 404);

        return response()->file($img['path']);
    })->name('clips-animados.library-image');

    // Serve a project resource image (Design System → Project resources): agent-photo, logo or an extra image's file name.
    Route::get('/project-resources/{name}', function (string $name) {
        $r = app(ProjectResources::class);
        $path = match ($name) {
            'agent-photo' => $r->agentPhotoPath(),
            'logo' => $r->logoPath(),
            default => collect($r->images())->firstWhere('file', $name)['path'] ?? null,
        };
        abort_unless($path && is_file($path), 404);

        return response()->file($path);
    })->name('project-resources.image');

    // Serve any image belonging to a clip (uploads or generated), by clip id + image id.
    Route::get('/clips-animados/{id}/image/{imageId}', function (string $id, string $imageId) {
        $clip = app(ClipStore::class)->find($id);
        abort_unless($clip !== null, 404);
        $img = collect($clip->images ?? [])->firstWhere('id', $imageId);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        abort_unless($img && ! empty($img['path']) && $disk->exists($img['path']), 404);

        return response()->file($disk->path($img['path']));
    })->name('clips-animados.clip-image');

    // One of a clip's voiceover takes, for the "pick the voice" player.
    Route::get('/clips-animados/{id}/take/{n}', function (string $id, int $n) {
        $path = app(ClipStore::class)->find($id)?->meta['voice_takes'][$n] ?? null;
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        abort_unless(is_string($path) && $disk->exists($path), 404);

        return response()->file($disk->path($path));
    })->whereNumber('n')->name('clips-animados.take');

    // A property walkthrough's cleaned-up footage or its filmstrip, for the section picker.
    Route::get('/clips-animados/{id}/property/{what}', function (string $id, string $what) {
        $path = app(ClipStore::class)->find($id)?->meta['property'][$what === 'strip' ? 'strip' : 'preview'] ?? null;
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        abort_unless(is_string($path) && $disk->exists($path), 404);

        return response()->file($disk->path($path));
    })->whereIn('what', ['footage', 'strip'])->name('clips-animados.property');

    // A listing clip's property video (a light preview) or its filmstrip, for the editor's moment picker.
    // Made on first use and kept beside the clip's uploads.
    Route::get('/clips-animados/{id}/listing-video/{what}', function (string $id, string $what) {
        $source = app(ClipStore::class)->find($id)?->meta['listing_video'] ?? null;
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        abort_unless(is_string($source) && $disk->exists($source), 404);
        $out = 'clips/listing-preview/'.$id.($what === 'strip' ? '-strip.jpg' : '.mp4');
        if (! $disk->exists($out)) {
            $editor = app(PropertyVideoEditor::class);
            $what === 'strip' ? $editor->filmstrip($disk->path($source), $disk->path($out)) : $editor->preview($disk->path($source), $disk->path($out));
        }

        return response()->file($disk->path($out));
    })->whereIn('what', ['footage', 'strip'])->name('clips-animados.listing-video');

    // A property room's real start frame or its staged still, for the review card.
    Route::get('/clips-animados/{id}/room/{i}/{which}', function (string $id, int $i, string $which) {
        $anim = app(ClipStore::class)->find($id)?->meta['property']['rooms'][$i]['anim'] ?? [];
        $path = str_starts_with($which, 'c')
            ? ($anim['candidates'][(int) substr($which, 1)]['path'] ?? null)   // one of the modernised images to choose from
            : ($anim[['start' => 'start_frame', 'end' => 'end_frame', 'staged' => 'staged'][$which] ?? ''] ?? null);
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        abort_unless(is_string($path) && $disk->exists($path), 404);

        return response()->file($disk->path($path));
    })->whereNumber('i')->where('which', 'start|end|staged|c\d+')->name('clips-animados.room');

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
        $payload = app(EffectPortability::class)->export($id);
        abort_if($payload === null, 404);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = $id === 'all' ? 'brand-machine-sfx.json' : 'sfx-'.$id.'.json';

        return response()->streamDownload(fn () => print ($json), $name, [
            'Content-Type' => 'application/json',
        ]);
    })->name('clips-animados.sfx-export');

    // Download a background (or every one, id = 'all') as a self-contained JSON file —
    // component source or mp4, plus metadata — for backup or moving between installs.
    Route::get('/clips-animados/background/{id}/export', function (string $id) {
        abort_unless((bool) preg_match('/^[a-z0-9-]+$/i', $id), 404);
        $payload = app(BackgroundPortability::class)->export($id);
        abort_if($payload === null, 404);

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $name = $id === 'all' ? 'brand-machine-backgrounds.json' : 'background-'.$id.'.json';

        return response()->streamDownload(fn () => print ($json), $name, [
            'Content-Type' => 'application/json',
        ]);
    })->name('clips-animados.background-export');

    // Serve the cached showcase preview of an SFX (built-in or custom), for the
    // current design system. 404 until the sample has been rendered.
    Route::get('/clips-animados/sfx/{slug}/preview', function (string $slug, Request $request) {
        abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*$/', $slug), 404);
        // ?format=half|landscape — the same effect previewed in another box.
        $format = (string) $request->query('format', EffectLibrary::FORMAT_PORTRAIT);
        abort_unless(array_key_exists($format, EffectLibrary::FORMATS), 404);
        $path = app(EffectLibrary::class)->previewPath($slug, $format);
        abort_unless(is_file($path), 404);

        return response()->file($path);
    })->name('clips-animados.sfx-preview');

    // Serve the sound attached to an effect (sfx-audio/<slug>.*), for playback in the
    // SFX studio. 404 when the effect has no sound.
    Route::get('/clips-animados/sfx/{slug}/audio', function (string $slug) {
        abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*$/', $slug), 404);
        $path = app(EffectStore::class)->audioPath($slug);
        abort_unless($path !== null && is_file($path), 404);

        return response()->file($path);
    })->name('clips-animados.sfx-audio');

    // Serve an effect rendered with the user's own inputs ("use this effect").
    Route::get('/clips-animados/sfx-use/{file}', function (string $file) {
        abort_unless((bool) preg_match('/^[a-z][a-z0-9-]*\.mp4$/', $file), 404);
        $path = app(EffectLibrary::class)->previewDir().'/uses/'.$file;
        abort_unless(is_file($path), 404);

        return response()->download($path, $file, [], request()->boolean('dl') ? 'attachment' : 'inline');
    })->name('clips-animados.sfx-use');

    // Serve a custom background's preview: a code background's cached render (design
    // aware) or a video background's mp4 file. 404 until ready. Keyed by record id.
    Route::get('/clips-animados/background/{id}/preview', function (string $id) {
        $bg = app(BackgroundStore::class)->find($id);
        abort_unless($bg !== null, 404);
        $path = app(BackgroundLibrary::class)->previewFileFor($bg);
        abort_unless($path !== null && is_file($path), 404);

        return response()->file($path);
    })->name('clips-animados.background-preview');

    // Serve the cached backgrounds reel (one video cycling through every background)
    // for the current design system + background set. 404 until it has been rendered.
    Route::get('/clips-animados/background-reel', function () {
        $path = app(BackgroundLibrary::class)->reelPath();
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

    // Avatar Gen — fal.ai lip-sync. Deliberately not in the side menu yet.
    Route::livewire('/avatar-gen', AvatarGen::class)->name('avatar-gen');

    // Serve an Avatar Gen result: inline for the preview player, ?download=1 to save it.
    // 404 until the lip-sync finishes (or forever, if it failed).
    Route::get('/avatar-gen/{id}/media', function (string $id) {
        abort_unless((bool) preg_match('/^[a-z0-9-]+$/', $id), 404);
        $store = app(AvatarStore::class);
        $avatar = $store->find($id);
        abort_unless($avatar !== null, 404);
        $path = $store->videoFor($avatar);
        abort_unless($path !== null, 404);

        return request()->boolean('download')
            ? response()->download($path)
            : response()->file($path);
    })->name('avatar-gen.media');

    Route::livewire('/publicacoes', Publicacoes::class)->name('publicacoes');
    Route::livewire('/publicacoes/{tipo}', Oficina::class)->name('publicacoes.oficina');

    Route::livewire('/finished', Rascunhos::class)->name('finished');
    Route::livewire('/noticias', Noticias::class)->name('noticias');
    Route::livewire('/design-system', DesignSystem::class)->name('design-system');
    Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
});
