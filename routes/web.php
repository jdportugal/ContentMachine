<?php

use App\Livewire\Ativos;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\AvatarGen;
use App\Livewire\ClipsAnimados;
use App\Livewire\Conta;
use App\Livewire\EditarVideo;
use App\Livewire\ClipsAnimadosSfx;
use App\Livewire\ClipsAnimadosVfx;
use App\Livewire\Definicoes;
use App\Livewire\DesignSystem;
use App\Livewire\Imoplanta\Comecar as ImoplantaComecar;
use App\Livewire\Imoplanta\Custos as ImoplantaCustos;
use App\Livewire\Imoplanta\Imovel as ImoplantaImovel;
use App\Livewire\Imoplanta\Lista as ImoplantaLista;
use App\Livewire\Ferramentas\Lista as FerramentasLista;
use App\Livewire\Ferramentas\Video as FerramentasVideo;
use App\Livewire\Imoveis;
use App\Livewire\Landing;
use App\Models\Imoplanta;
use App\Models\ImoplantaImagem;
use App\Services\Avatars\AvatarStore;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\BackgroundPortability;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\EffectPortability;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Clips\Store\VfxStore;
use App\Services\Imoplanta\ImoplantaCasa;
use App\Services\Imoplanta\Imoplantas;
use App\Services\Leads\LeadVideo;
use App\Services\Projects\ProjectResources;
use App\Http\Controllers\StripeWebhookController;
use App\Services\Contas\LinkDeAcesso;
use App\Services\RealEstate\DemoListingScraper;
use App\Services\RealEstate\PropertyVideoEditor;
use App\Services\Shorts\MusicLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// ── Public site ──────────────────────────────────────────────────────────────
// The free-video landing page, and the signed link to the finished reel it shows:
// a lead has no account, so the signature is the key and LeadVideo activates their project.
// The free-video form (the page the ads, emails, invites and guides send people to) lives at
// /video-gratis; the home page presents Imocena and its blog. Anyone who reaches the home page
// with a link meant for the form (ad tags, ?agencia=, ?convite=, ?anuncio=…) or with a video
// under way is sent on to the form with the same query, so old links and live ads keep working.
Route::livewire('/video-gratis', Landing::class)->name('video');
Route::get('/', function (Request $request, \App\Services\Guias\Guias $guias) {
    $query = (string) $request->server('QUERY_STRING', '');   // as sent: same order and encoding
    if ($query !== '' || session()->has('lead_id')) {
        return redirect()->to(route('video').($query !== '' ? '?'.$query : ''));
    }

    return view('inicio', ['guias' => $guias->todos(), 'temas' => $guias->porTema(), 'reel' => Landing::demoReel('')['url']]);
})->name('home');
Route::redirect('/blog', '/guias', 301);

// Imocena's own demo listings (fictional, generated photos): the page the
// landing's example points at, and a link anyone can paste into the form.
Route::get('/'.DemoListingScraper::PATH.'/{slug}', function (string $slug, DemoListingScraper $demo) {
    abort_unless(isset(DemoListingScraper::listings()[$slug]), 404);
    $reel = is_file(public_path("media/demo/{$slug}/reel.mp4")) ? asset("media/demo/{$slug}/reel.mp4") : null;

    return view('demo-listing', ['listing' => $demo->scrape(url(DemoListingScraper::PATH.'/'.$slug)), 'reel' => $reel]);
})->name('demo');

// The demo video made for a new listing (new-listing alert emails): photos + the one recorded
// demo voice. Private (noindex); the slug is a hash of the listing URL.
Route::get('/demo-video/{slug}', function (string $slug) {
    $demo = \App\Services\Alertas\DemoDoImovel::info($slug) ?? abort(404);
    $final = route('video').'?'.http_build_query(['anuncio' => $demo['url'], 'utm_source' => 'imocena', 'utm_medium' => 'email', 'utm_campaign' => 'demo-imovel']).'#pedido';

    return view('demo-imovel', ['demo' => $demo, 'final' => $final]);
})->name('demo.imovel');

// Stripe: the Payment Links' webhook, and where a buyer lands after paying.
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');
Route::view('/privacidade', 'privacidade')->name('privacidade');

// Guides for agents (resources/guias/*.md), found through search and AI answers.
Route::get('/guias', function (\App\Services\Guias\Guias $guias) {
    $todos = $guias->todos();

    return view('guias.index', ['temas' => $guias->porTema(), 'total' => count($todos), 'esquema' => [[
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Guias Imocena',
        'url' => route('guias'),
        'inLanguage' => 'pt-PT',
        'hasPart' => array_values(array_map(fn ($g) => ['@type' => 'Article', 'headline' => $g['titulo'], 'url' => route('guias.ver', $g['slug'])], $todos)),
    ]]]);
})->name('guias');
Route::get('/guias/{slug}', function (string $slug, \App\Services\Guias\Guias $guias) {
    $guia = $guias->encontrar($slug);
    abort_if($guia === null, 404);
    // An agency's page shows that agency's example reel, and its button opens the form for that agency.
    $reel = $guia['agencia'] ? Landing::demoReel($guia['agencia'])['url'] : null;

    // Its own short, once made (public/media/guias/<slug>.mp4 on the server).
    $video = is_file(public_path("media/guias/{$slug}.mp4")) ? asset("media/guias/{$slug}.mp4") : null;

    // Its photos (imocena:fotos-blog): the first opens the article, the second goes in the text.
    $fotos = \App\Services\Guias\Guias::fotos($slug);
    $guia['html'] = \App\Services\Guias\Guias::comFotos($guia['html'], $fotos, $guia['titulo']);

    return view('guias.artigo', ['guia' => $guia, 'outros' => $guias->relacionados($guia), 'reel' => $reel, 'video' => $video, 'fotos' => $fotos]);
})->where('slug', '[a-z0-9-]+')->name('guias.ver');

// For search engines: every public page, and the rules for crawlers.
Route::get('/sitemap.xml', function (\App\Services\Guias\Guias $guias) {
    $urls = [['loc' => route('home'), 'lastmod' => null], ['loc' => route('video'), 'lastmod' => null]];
    $urls[] = ['loc' => route('guias'), 'lastmod' => collect($guias->todos())->max('atualizado')];
    foreach ($guias->todos() as $g) {
        $urls[] = ['loc' => route('guias.ver', $g['slug']), 'lastmod' => $g['atualizado']];
    }
    foreach (array_keys(DemoListingScraper::listings()) as $demo) {
        $urls[] = ['loc' => route('demo', $demo), 'lastmod' => null];
    }
    $urls[] = ['loc' => route('termos'), 'lastmod' => null];
    $urls[] = ['loc' => route('privacidade'), 'lastmod' => null];

    return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::get('/robots.txt', fn () => response(implode("\n", [
    'User-agent: *',
    'Disallow: /conta',
    'Disallow: /v/',
    'Disallow: /obrigado',
    'Disallow: /entrar/',
    'Disallow: /emails/',
    'Disallow: /login',
    'Disallow: /register',
    'Disallow: /stripe/',
    '',
    'Sitemap: '.route('sitemap'),
    '',
]))->header('Content-Type', 'text/plain; charset=UTF-8'))->name('robots');
Route::view('/termos', 'termos')->name('termos');

Route::get('/obrigado', function (LinkDeAcesso $contas) {
    $sessao = (string) request()->query('session_id', '');
    $compra = $sessao !== '' ? DB::table('compras')->where('sessao', $sessao)->first() : null;
    // Signed straight in: the checkout session id is only ever shown to the buyer, and it
    // signs in once, within a day of paying. Later visits use the emailed link (/login).
    if ($compra && $compra->user_id && ! $compra->login_em && now()->diffInHours($compra->created_at, true) < 24
        && ($user = \App\Models\User::find($compra->user_id)) && ! $user->isAdmin()) {
        $contas->entrar($user);
        DB::table('compras')->where('id', $compra->id)->update(['login_em' => now()]);
    }
    $lead = $compra?->lead_id ? DB::table('leads')->find($compra->lead_id) : null;
    // Attribution: the buyer's first touch (this browser's, else the free video's).
    if ($compra && ! $compra->origem && ($origem = \App\Services\Marketing\Origem::json() ?? $lead?->origem)) {
        DB::table('compras')->where('id', $compra->id)->update(['origem' => $origem]);
    }
    // The purchase is reported to Meta and Google once: the page carries it for the
    // browser tags (after cookie consent), and the server sends it to Meta too.
    $medir = null;
    if ($compra && ! $compra->medido_em) {
        $medir = ['id' => 'compra-'.$compra->id, 'valor' => round($compra->total / 100, 2), 'moeda' => strtoupper((string) ($compra->moeda ?: 'eur')), 'videos' => (int) $compra->videos];
        DB::table('compras')->where('id', $compra->id)->update(['medido_em' => now()]);
        app(\App\Services\Marketing\MetaConversoes::class)->enviar('Purchase', $medir['id'],
            ['value' => $medir['valor'], 'currency' => $medir['moeda'], 'num_items' => $medir['videos'], 'content_type' => 'product'],
            $compra->email, json_decode((string) ($compra->origem ?? $origem ?? 'null'), true));
    }

    // Ferramentas IA: back to the video it was bought from (or the tools page for a pack bought there).
    // (The credits are one balance: a pack bought from an AI video carries it as ferramenta-<id>.)
    $ferramenta = $compra ? \App\Services\Pagamentos\Compras::ferramentaId((string) (json_decode((string) $compra->dados, true)['client_reference_id'] ?? '')) : null;
    if ($compra && ($ferramenta || $compra->videos_ia > 0) && Auth::check()) {
        $f = $ferramenta ? \App\Models\FerramentaIa::where('user_id', Auth::id())->find($ferramenta) : null;

        return redirect($f ? route('ferramentas.video', $f->id) : route('ferramentas'))->with('pago', true);
    }

    // Imoplanta: back to what was bought (its webhook starts it): the property, or the list for a pack.
    if ($compra && ($compra->imoplanta_id || $compra->imoveis > 0) && Auth::check()) {
        $p = $compra->imoplanta_id ? Imoplanta::where('user_id', Auth::id())->find($compra->imoplanta_id) : null;

        return redirect($p ? ($p->estado === Imoplanta::A_PAGAR ? route('plantas') : route('imoplanta.imovel', $p->id)) : route('imoplanta'))->with('pago', true);
    }

    return view('obrigado', ['compra' => $compra, 'lead' => $lead, 'video' => $lead ? app(LeadVideo::class)->ficheiro($lead) : null, 'medir' => $medir]);
})->name('obrigado');

// Passwordless sign-in: the signed link from the email (or the thank-you page's own sign-in).
Route::get('/entrar/{user}', function (int $user, LinkDeAcesso $contas) {
    $conta = \App\Models\User::findOrFail($user);
    abort_if($conta->isAdmin(), 403);   // the operator signs in with a password; links are for customers
    $contas->entrar($conta);
    $para = (string) request()->query('para', '');   // signed with the link: a page of this site only

    return str_starts_with($para, '/') && ! str_starts_with($para, '//') ? redirect($para) : redirect()->route($conta->isAdmin() ? 'imoveis' : 'conta');
})->middleware('signed')->name('entrar.link');

Route::get('/v/{lead}', function (int $lead, LeadVideo $videos) {
    $row = $videos->lead($lead);
    $ficheiro = $row ? $videos->ficheiro($row) : null;
    abort_unless($ficheiro, 404);

    return request()->boolean('download')
        ? response()->download($ficheiro, 'imocena-video.mp4')
        : response()->file($ficheiro);
})->middleware('signed')->name('lead.video');

// Promotional emails go only to leads with marketing_em set and marketing_cancelado_em
// null, and each one links here (and in its List-Unsubscribe header). GET asks, POST
// cancels — link scanners only GET — and POST is CSRF-exempt for one-click (RFC 8058).
Route::match(['get', 'post'], '/emails/cancelar/{lead}', function (int $lead) {
    if (request()->isMethod('post')) {
        DB::table('leads')->where('id', $lead)->whereNull('marketing_cancelado_em')->update(['marketing_cancelado_em' => now()]);
        // The same person may have asked on several listings: one click stops them all.
        if ($email = DB::table('leads')->where('id', $lead)->value('email')) {
            DB::table('leads')->where('email', $email)->whereNull('marketing_cancelado_em')->update(['marketing_cancelado_em' => now()]);
        }
    }

    return view('emails-cancelar', ['cancelado' => (bool) DB::table('leads')->where('id', $lead)->value('marketing_cancelado_em')]);
})->middleware('signed')->name('lead.emails.cancelar');


// Imoplanta's landing page: the steps shown with GIFs of the demo flat (public/imoplanta-demo/landing).
Route::view('/da-planta-ao-video', 'imoplanta.landing')->name('plantas.sobre');
// Ferramentas IA's landing page: each tool with its example clip (public/exemplos-ia).
Route::view('/ferramentas-ia', 'ferramentas.landing')->name('ferramentas.sobre');


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

    return redirect()->route('home');
})->name('logout');

// ── The application ──────────────────────────────────────────────────────────
// Every route below needs a signed-in user — pages AND the file-serving routes,
// which hand out rendered media. Applied here rather than to the `web` group:
// Livewire registers its own /livewire/update endpoint with `web`, so gating that
// group logs guests out of the sign-in and sign-up forms themselves (their form
// submissions are Livewire requests). Livewire re-applies Authenticate to update
// requests for these components on its own — it is persistent middleware.
// A customer's own area: their videos and credits. Everything after it is the studio.
Route::livewire('/conta', Conta::class)->middleware('auth')->name('conta');
// A customer's video editor, and the pictures it shows (only their own videos').
Route::livewire('/conta/videos/{lead}/editar', EditarVideo::class)->middleware('auth')->name('conta.editar');
Route::livewire('/conta/novo', \App\Livewire\NovoVideoPagina::class)->middleware('auth')->name('conta.novo');
Route::livewire('/conta/definicoes', \App\Livewire\ContaDefinicoes::class)->middleware('auth')->name('conta.definicoes');

// A track from the music library, for a customer to hear before choosing it.
Route::get('/conta/musicas/{nome}', function (string $nome, \App\Services\Shorts\MusicLibrary $biblioteca) {
    $ficheiro = $biblioteca->pathFor($nome);
    abort_unless($ficheiro !== null, 404);

    return response()->file($ficheiro, ['Cache-Control' => 'private, max-age=86400']);
})->middleware('auth')->where('nome', '[A-Za-z0-9._-]+')->name('conta.musica');
Route::get('/conta/videos/{lead}/imagens/{imagem}', function (int $lead, string $imagem, LeadVideo $videos) {
    $registo = DB::table('leads')->where('id', $lead)->where('user_id', Auth::id())->first();
    $clip = $registo ? $videos->clip($registo) : null;
    $img = $clip ? collect($clip->images ?? [])->firstWhere('id', $imagem) : null;
    $disco = Storage::disk(config('contentmachine.clips.disk'));
    abort_unless($img && isset($img['path']) && $disco->exists($img['path']), 404);

    return response()->file($disco->path($img['path']), ['Cache-Control' => 'private, max-age=86400']);
})->middleware('auth')->name('conta.imagem');

// The agent's photo kept with the account (PerfilDoConsultor), shown on the "Novo vídeo" page.
Route::get('/conta/foto-consultor', function () {
    $caminho = (string) ((Auth::user()->agente ?? [])['foto'] ?? '');
    $disco = Storage::disk(config('contentmachine.clips.disk'));
    abort_unless($caminho !== '' && $disco->exists($caminho), 404);

    return response()->file($disco->path($caminho), ['Cache-Control' => 'private, max-age=86400']);
})->middleware('auth')->name('conta.foto-consultor');

// The sample of a picture effect in a theme (the editor's "how it looks"); 404 until rendered.
Route::get('/conta/amostras/{tema}/{efeito}', function (string $tema, string $efeito) {
    abort_unless(preg_match('/^[a-z0-9-]+$/', $tema.$efeito), 404);
    app(\App\Services\Themes\ThemeContext::class)->use($tema);
    $ficheiro = \App\Services\Contas\AmostrasDeEfeitos::caminho($efeito);
    abort_unless(is_file($ficheiro), 404);

    return response()->file($ficheiro, ['Cache-Control' => 'private, max-age=604800', 'Content-Type' => 'video/mp4']);
})->middleware('auth')->name('conta.amostra');


// ── Imoplanta ────────────────────────────────────────────────────────────────
// Signed-in accounts, but for now only those in imoplanta.acesso (and admins): the `imoplanta` gate.
Route::middleware('auth')->group(function () {
    // Imoplanta, for now: only the accounts in imoplanta.acesso (and admins). Livewire re-applies `can` on its updates.
    Route::middleware('can:imoplanta')->group(function () {
        // Email → plans → payment, then the editor. Public later; for now behind the same gate.
        Route::livewire('/plantas', ImoplantaComecar::class)->name('plantas');
        // Imoplanta: house plans → 3D model → shots → photoreal images (paid per property).
        Route::livewire('/imoplanta', ImoplantaLista::class)->name('imoplanta');
        Route::livewire('/imoplanta/{imovel}', ImoplantaImovel::class)->whereNumber('imovel')->name('imoplanta.imovel');

        // A property's files: a plan or reference image (n = index), an image's render or photo (n = image id).
        // Only its owner (or an admin). ?download=1 saves a photo, the walkthrough or the realistic 3D floor plan.
        Route::get('/imoplanta/{imovel}/ficheiro/{qual}/{n?}', function (Imoplanta $imovel, string $qual, ?int $n = null) {
            abort_unless($imovel->visivelPara(Auth::user()), 404);
            $img = in_array($qual, ['render', 'foto'], true) ? $imovel->imagens()->find($n) : null;
            $path = match ($qual) {
                'planta' => $imovel->plantas[$n ?? 0] ?? null,
                'referencia' => ($imovel->referencias ?? [])[$n ?? 0] ?? null,
                'render' => $img?->render,
                'foto' => $img?->foto,
                'acabamentos' => $imovel->acabamentos_img,
                'video' => $imovel->video,
                'clip' => ((array) $imovel->video_clips)[$n] ?? null,   // n = the image the clip was made from
                'aereo' => $imovel->aereo($n ?? ImoplantaCasa::nivelPrincipal($imovel->casa ?? [])),   // n = the storey
                'planta_video' => $imovel->extra('planta', 'video'),                                    // the plan video add-on
                'reel', 'reel_previa' => null,                                                           // the Imocena reel add-on (and its preview): below
            };
            if (in_array($qual, ['reel', 'reel_previa'], true)) {   // it lives with the listing reels, not on the Imoplanta disk
                $id = $imovel->extra('video', $qual === 'reel' ? 'clip' : 'previa_clip');
                $clip = $id ? app(ClipStore::class)->find($id) : null;
                abort_if($qual === 'reel_previa' && $clip?->status !== ClipRecord::STATUS_DONE, 404);   // re-rendering unmarked: not a preview any more
                abort_unless($clip && $clip->output_path && is_file($clip->output_path), 404);

                return request()->boolean('download')
                    ? response()->download($clip->output_path, 'imoplanta-'.$imovel->id.'-video-imocena.mp4')
                    : response()->file($clip->output_path);
            }
            $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
            abort_unless(is_string($path) && $disk->exists($path), 404);

            if ($qual === 'video' && request()->boolean('download')) {
                return response()->download($disk->path($path), 'imoplanta-'.$imovel->id.'-walkthrough.mp4');
            }
            if ($qual === 'planta_video' && request()->boolean('download')) {
                return response()->download($disk->path($path), 'imoplanta-'.$imovel->id.'-video-planta.mp4');
            }
            if ($qual === 'aereo' && request()->boolean('download')) {
                return response()->download($disk->path($path), 'imoplanta-'.$imovel->id.'-planta-3d-'.Str::slug($imovel->casa['niveis'][$n ?? 0]['nome'] ?? 'piso-'.($n ?? 0)).'.png');
            }
            if ($qual === 'clip') {
                return response()->download($disk->path($path), 'imoplanta-'.$imovel->id.'-cena-'.($imovel->imagens()->find($n)?->numero ?? $n).'.mp4');
            }

            return $qual === 'foto' && request()->boolean('download')
                ? response()->download($disk->path($path), 'imoplanta-'.$imovel->id.'-'.$img->numero.'.png')
                : response()->file($disk->path($path), ['Cache-Control' => 'private, max-age=3600']);
        })->whereNumber('imovel')->whereIn('qual', ['planta', 'referencia', 'render', 'foto', 'acabamentos', 'video', 'clip', 'aereo', 'planta_video', 'reel', 'reel_previa'])->whereNumber('n')->name('imoplanta.ficheiro');

        // Everything of one kind in one zip: the walkthrough's scenes, the photos, or the 3D floor plans. Owner or admin.
        Route::get('/imoplanta/{imovel}/zip/{qual}', function (Imoplanta $imovel, string $qual) {
            abort_unless($imovel->visivelPara(Auth::user()), 404);
            $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
            $imagens = $imovel->imagens()->get();
            $nivel = fn (int $n) => Str::slug($imovel->casa['niveis'][$n]['nome'] ?? 'piso-'.$n);
            $ficheiros = match ($qual) {   // name in the zip => path
                'cenas' => $imagens->filter(fn ($i) => isset(((array) $imovel->video_clips)[$i->id]))
                    ->mapWithKeys(fn ($i) => ['cena-'.$i->numero.'.mp4' => ((array) $imovel->video_clips)[$i->id]])->all(),
                'fotos' => $imagens->whereNotNull('foto')->mapWithKeys(fn ($i) => ['foto-'.$i->numero.'.png' => $i->foto])->all(),
                'plantas' => collect($imovel->niveisComPlanta())->filter(fn ($n) => $imovel->aereo($n))
                    ->mapWithKeys(fn ($n) => ['planta-3d-'.$nivel($n).'.png' => $imovel->aereo($n)])->all(),
            };
            $ficheiros = array_filter($ficheiros, fn ($f) => is_string($f) && $disk->exists($f));
            abort_if($ficheiros === [], 404);
            $zip = new ZipArchive;
            $tmp = tempnam(sys_get_temp_dir(), 'imoplanta-zip');
            $zip->open($tmp, ZipArchive::OVERWRITE);
            foreach ($ficheiros as $nome => $f) {
                $zip->addFile($disk->path($f), $nome);
            }
            $zip->close();

            return response()->download($tmp, 'imoplanta-'.$imovel->id.'-'.$qual.'.zip')->deleteFileAfterSend();
        })->whereNumber('imovel')->whereIn('qual', ['cenas', 'fotos', 'plantas'])->name('imoplanta.zip');

        // The browser's render of one planned shot (three.js, 1600×900 PNG), which the photographer starts from.
        // Only the property's owner; accepted once per image (409 after that), so a resumed or second tab is harmless.
        Route::post('/imoplanta/{imovel}/render/{imagem}', function (Request $request, Imoplanta $imovel, int $imagem, Imoplantas $imoplantas) {
            abort_unless($imovel->user_id === Auth::id(), 404);
            $img = $imovel->imagens()->findOrFail($imagem);
            [$w, $h] = ImoplantaImagem::RENDER;
            $v = Validator::make($request->all(), ['render' => ['required', 'file', 'mimes:png', 'max:12288', "dimensions:width=$w,height=$h"]]);
            if ($v->fails()) {   // JSON for the page's fetch (this app renders JSON errors only under api/*)
                return response()->json(['ok' => false, 'erro' => $v->errors()->first()], 422);
            }

            return $imoplantas->receberRender($img, $request->file('render'))
                ? response()->json(['ok' => true])
                : response()->json(['ok' => false], 409);
        })->whereNumber('imovel')->whereNumber('imagem')->middleware('throttle:60,1')->name('imoplanta.render');

        // the top-view render (the plan in 3D) for the reference photo (made once, before the shots)
        Route::post('/imoplanta/{imovel}/aereo/{nivel?}', function (Request $request, Imoplanta $imovel, Imoplantas $imoplantas, ?int $nivel = null) {
            abort_unless($imovel->user_id === Auth::id(), 404);
            // framed on the house at its own proportions: the long side 1600 px, the other at least 400
            $v = Validator::make($request->all(), ['render' => ['required', 'file', 'mimes:png', 'max:12288', 'dimensions:max_width=1600,max_height=1600,min_width=400,min_height=400',
                function ($a, $f, $fail) { $d = @getimagesize($f->getRealPath()); if (! $d || max($d[0], $d[1]) !== 1600) { $fail('A vista de cima tem de ter 1600 px no lado maior.'); } }]]);
            if ($v->fails()) {
                return response()->json(['ok' => false, 'erro' => $v->errors()->first()], 422);
            }

            return $imoplantas->receberAereo($imovel, $request->file('render'), $nivel ?? ImoplantaCasa::nivelPrincipal($imovel->casa ?? []), $request->input('salas'))
                ? response()->json(['ok' => true])
                : response()->json(['ok' => false], 409);
        })->whereNumber('imovel')->whereNumber('nivel')->middleware('throttle:60,1')->name('imoplanta.aereo');
    });

    // Ferramentas IA (photo → approved frames → Kling clip), for now the same accounts as Imoplanta: the `ferramentas` gate.
    Route::middleware('can:ferramentas')->group(function () {
        Route::livewire('/ferramentas', FerramentasLista::class)->name('ferramentas');
        Route::livewire('/ferramentas/nova/{ferramenta}', FerramentasVideo::class)->name('ferramentas.nova');
        Route::livewire('/ferramentas/{video}', FerramentasVideo::class)->whereNumber('video')->name('ferramentas.video');
        // A video's files, to its owner (or an admin): the photo, the frames, the clip. ?baixar=1 saves it.
        Route::get('/ferramentas/{video}/ficheiro/{qual}', function (\App\Models\FerramentaIa $video, string $qual) {
            abort_unless($video->user_id === Auth::id() || Auth::user()->is_admin, 404);
            $path = $video->{$qual};
            $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
            abort_unless($path && $disk->exists($path), 404);
            if (request()->boolean('baixar')) {
                return response()->download($disk->path($path), 'imocena-'.$video->ferramenta.'-'.$video->id.'-'.$qual.'.'.pathinfo($path, PATHINFO_EXTENSION));
            }

            return response()->file($disk->path($path), ['Cache-Control' => 'private, max-age=604800']);
        })->whereNumber('video')->whereIn('qual', ['original', 'inicio', 'fim', 'video'])->name('ferramentas.ficheiro');
        // The animated plan's top-view render, from the customer's browser (its 3D model drawn from above): the frames start.
        Route::post('/ferramentas/{video}/render', function (\App\Models\FerramentaIa $video, \Illuminate\Http\Request $request, \App\Services\Ferramentas\Ferramentas $ferramentas) {
            abort_unless($video->user_id === Auth::id() || Auth::user()->is_admin, 404);
            $request->validate(['render' => 'required|file|mimes:png|max:20480']);

            return response()->json(null, $ferramentas->receberRender($video, $request->file('render'), $request->input('salas')) ? 200 : 409);
        })->whereNumber('video')->name('ferramentas.render');
    });
});

// ── The studio ───────────────────────────────────────────────────────────────
// Admins only — pages AND the file-serving routes, which hand out rendered media.
Route::middleware(['auth', 'admin'])->group(function () {
    Route::livewire('/imoveis', Imoveis::class)->name('imoveis');
    Route::livewire('/ativos', Ativos::class)->name('ativos');
    Route::livewire('/clientes', \App\Livewire\Clientes::class)->name('clientes');
    Route::livewire('/videos', \App\Livewire\TodosVideos::class)->name('videos.todos');
    Route::livewire('/campanhas', \App\Livewire\Campanhas::class)->name('campanhas');
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

    Route::livewire('/design-system', DesignSystem::class)->name('design-system');
    Route::livewire('/definicoes', Definicoes::class)->name('definicoes');
    // Imoplanta's real cost per property vs. its credit's revenue (customers never see costs).
    Route::livewire('/admin/imoplanta-custos', ImoplantaCustos::class)->name('custos.imoplanta');
});
