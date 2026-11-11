<!DOCTYPE html>
<html lang="pt-PT" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Imocena' }} · Imocena</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Poppins:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-nocturna min-h-screen text-ink antialiased">
    <div x-data="{ nav: false }" class="flex min-h-screen">
        {{-- Mobile top bar (hamburger) — hidden on lg+ --}}
        <header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 flex items-center justify-between px-4 bg-vellum/95 backdrop-blur border-b border-ink-soft/15">
            <a href="{{ route('imoveis') }}" class="font-display text-2xl text-ink">Imocena</a>
            <button type="button" @click="nav = !nav" aria-label="Abrir ou fechar o menu" class="p-2 -mr-2 text-2xl leading-none text-ink">☰</button>
        </header>

        {{-- Drawer backdrop (mobile) --}}
        <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="lg:hidden fixed inset-0 z-40 bg-black/50"></div>

        {{-- ============ Side shelf (navigation) — off-canvas drawer on mobile, static on lg+ ============ --}}
        <aside x-bind:class="{ '!translate-x-0': nav }"
               class="w-64 shrink-0 border-r border-ink-soft/15 bg-vellum/95 lg:bg-vellum/40 flex flex-col fixed lg:sticky top-0 h-screen z-50 -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-out">
            <div class="px-6 py-3 border-b border-ink-soft/15">
                <a href="{{ route('imoveis') }}" class="block">
                    <span class="font-display text-2xl text-ink">Imocena</span>
                    <span class="block eyebrow mt-0.5 text-[0.55rem]">Vídeos de imóveis</span>
                </a>
            </div>

            <div class="px-4 py-3 border-b border-ink-soft/15">
                @livewire('theme-switcher')
            </div>


            {{-- Closing the drawer belongs to the DESTINATION links only: on the
                 <aside> it also swallowed taps on the project switcher, closing
                 the drawer the moment its dropdown opened. --}}
            {{-- Collapsed sections live in localStorage ($persist, from Livewire's
                 Alpine), so the shelf stays how you left it across page loads.
                 Storing what's CLOSED means a new section arrives open. --}}
            <nav @click="nav = false"
                 x-data="{
                    fechados: $persist([]).as('cm-shelf-fechados'),
                    aberto(chave, forcado) { return forcado || ! this.fechados.includes(chave) },
                    alternar(chave) {
                        this.fechados = this.fechados.includes(chave)
                            ? this.fechados.filter(c => c !== chave)
                            : [...this.fechados, chave]
                    },
                 }"
                 class="flex-1 min-h-0 overflow-y-auto py-2">
                @php
                    // Grouped by pipeline stage: what you gather → what you make →
                    // what you make it from → where it goes out. Groups without a
                    // title (Dashboard, Settings) are the un-staged bookends.
                    //
                    // 'match' overrides the default "<route>*" highlight test. Content
                    // Transformer needs it because 'clips*' would also match every
                    // 'clips-animados…' route; Animated Clips and Effects Studio need it
                    // because they split those same routes between two entries.
                    //
                    // 'subs' are the pages that share one entry — they're listed here
                    // AND as an in-page tab bar (partials/*-tabs.blade.php), and only
                    // unfold in the shelf while their parent is the active section.
                    $grupos = [
                        ['titulo' => null, 'itens' => [
                            ['route' => 'imoveis',         'label' => 'Os meus vídeos',    'sub' => 'Portal do consultor',            'color' => '#F06052', 'glyph' => '⌂'],
                            ['route' => 'clientes',        'label' => 'Clientes',          'sub' => 'Contas · vídeos',                'color' => '#F06052', 'glyph' => '☺'],
                            ['route' => 'campanhas',       'label' => 'Campanhas',         'sub' => 'Funil · anúncios',               'color' => '#F06052', 'glyph' => '◔'],
                        ]],
                        ['titulo' => 'Criar', 'itens' => [
                            ['route' => 'clips-animados',  'label' => 'Imóveis',           'sub' => 'Vídeos animados',          'color' => '#05B8E6', 'glyph' => '✦', 'match' => ['clips-animados']],
                            ['route' => 'clips-animados.sfx', 'label' => 'Estúdio de efeitos', 'sub' => 'SFX · VFX · Fundos', 'color' => '#05B8E6', 'glyph' => '✶', 'match' => ['clips-animados.sfx*', 'clips-animados.vfx*', 'clips-animados.backgrounds'], 'subs' => [
                                ['route' => 'clips-animados.sfx',         'label' => 'Estúdio SFX',  'match' => 'clips-animados.sfx*'],
                                ['route' => 'clips-animados.vfx',         'label' => 'Laboratório VFX', 'match' => 'clips-animados.vfx*'],
                                ['route' => 'clips-animados.backgrounds', 'label' => 'Fundos',      'match' => 'clips-animados.backgrounds'],
                            ]],
                        ]],
                        ['titulo' => 'Biblioteca', 'itens' => [
                            ['route' => 'ativos',          'label' => 'Recursos',          'sub' => 'Media · Música',      'color' => '#05B8E6', 'glyph' => '♫'],
                            ['route' => 'design-system',   'label' => 'Sistema de design', 'sub' => 'Marca do tema',      'color' => '#05B8E6', 'glyph' => '❖'],
                        ]],
                        ['titulo' => null, 'itens' => [
                            ['route' => 'definicoes',      'label' => 'Definições',        'sub' => 'Variáveis',          'color' => '#05B8E6', 'glyph' => '⚙'],
                        ]],
                    ];
                    $n = 0; // running number across the whole shelf
                @endphp

                @foreach ($grupos as $grupo)
                    @php
                        // A section holding the page you're on stays open no matter what
                        // was collapsed before — otherwise the shelf hides where you are.
                        $chave = strtolower($grupo['titulo'] ?? '');
                        $grupoAtivo = collect($grupo['itens'])
                            ->contains(fn ($i) => request()->routeIs(...($i['match'] ?? [$i['route'].'*'])));
                    @endphp

                    @if ($grupo['titulo'])
                        <button type="button" @click.stop="alternar('{{ $chave }}')"
                                :aria-expanded="String(aberto('{{ $chave }}', {{ $grupoAtivo ? 'true' : 'false' }}))"
                                class="w-full flex items-center gap-1.5 px-6 pt-2 pb-0 eyebrow text-[0.55rem] text-ink-faint/70 hover:text-ink-soft transition">
                            <span class="w-2 text-[0.5rem] leading-none"
                                  x-text="aberto('{{ $chave }}', {{ $grupoAtivo ? 'true' : 'false' }}) ? '▾' : '▸'">▾</span>
                            <span>{{ $grupo['titulo'] }}</span>
                            <span class="ml-auto normal-case tracking-normal font-mono text-[0.55rem]"
                                  x-show="! aberto('{{ $chave }}', {{ $grupoAtivo ? 'true' : 'false' }})" x-cloak>{{ count($grupo['itens']) }}</span>
                        </button>
                    @elseif (! $loop->first)
                        {{-- Settings has no stage; a rule keeps it from reading as part of PUBLISH. --}}
                        <div class="mx-6 mt-2.5 mb-1.5 border-t border-ink-soft/15"></div>
                    @endif

                    <div class="space-y-0.5"
                         @if ($grupo['titulo']) x-show="aberto('{{ $chave }}', {{ $grupoAtivo ? 'true' : 'false' }})" x-collapse @endif>
                        @foreach ($grupo['itens'] as $item)
                            @php $active = request()->routeIs(...($item['match'] ?? [$item['route'].'*'])); @endphp
                            {{-- data-nav marks the shelf's own links, so a test can tell an
                                 active ENTRY from an active sub or in-page tab. --}}
                            <a href="{{ route($item['route']) }}"
                               data-nav="entry" @if ($active)aria-current="page"@endif
                               class="group flex items-center gap-3 pl-4 pr-4 py-0.5 mx-2 rounded-sm transition
                                      {{ $active ? 'bg-surface/70 text-ink' : 'text-ink-soft hover:bg-surface/40 hover:text-ink' }}">
                                <span class="w-1.5 self-stretch rounded-full transition-all"
                                      style="background: {{ $active ? $item['color'] : 'transparent' }}; box-shadow: {{ $active ? '0 0 8px '.$item['color'].'88' : 'none' }}"></span>
                                <span class="w-5 text-center text-sm" style="color: {{ $item['color'] }}">{{ $item['glyph'] }}</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-display text-base leading-tight">{{ $item['label'] }}</span>
                                    <span class="block text-[0.62rem] font-mono text-ink-faint truncate">{{ $item['sub'] }}</span>
                                </span>
                                <span class="font-mono text-[0.6rem] text-ink-faint">{{ str_pad($n++, 2, '0', STR_PAD_LEFT) }}</span>
                            </a>

                            @if ($active && ! empty($item['subs']))
                                {{-- ml-16 puts the rule between the glyph and the label column,
                                     so a sub's text lines up with its parent's title. --}}
                                <div class="ml-16 mr-4 pl-3 border-l border-ink-soft/40 space-y-px py-0.5">
                                    @foreach ($item['subs'] as $sub)
                                        @php
                                            $subAtivo = request()->routeIs($sub['match'] ?? $sub['route'])
                                                && collect($sub['params'] ?? [])->every(fn ($v, $k) => request()->route($k) === $v);
                                        @endphp
                                        <a href="{{ route($sub['route'], $sub['params'] ?? []) }}"
                                           data-nav="sub" @if ($subAtivo)aria-current="page"@endif
                                           class="flex items-center gap-2 rounded-sm px-2 py-1 font-mono text-[0.62rem] transition
                                                  {{ $subAtivo ? 'bg-surface/50 text-ink' : 'text-ink-faint hover:text-ink hover:bg-surface/30' }}">
                                            <span style="color: {{ $subAtivo ? $item['color'] : 'transparent' }}">·</span>
                                            <span class="truncate">{{ $sub['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="px-6 py-2.5 border-t border-ink-soft/15 flex items-center justify-between gap-2">
                <span class="font-mono text-[0.6rem] text-ink-faint truncate" title="{{ auth()->user()?->email }}">
                    {{ auth()->user()?->email }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="font-mono text-[0.6rem] text-ink-faint hover:text-rust underline underline-offset-2 transition">
                        Sair
                    </button>
                </form>
            </div>

        </aside>

        {{-- ============ Content ============ --}}
        <main class="flex-1 min-w-0 overflow-x-hidden pt-14 lg:pt-0">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Loading overlay (particles) — global, triggerable by any component
         via dispatch('loader-show'/'loader-hide') or window.CMLoader. --}}
    <x-particle-loader />

    {{-- Global toasts (bottom-right corner). At <body> level, outside the
         scroller/transform, so position:fixed pins to the screen. Listens for the
         'toast' event dispatched by any Livewire component. --}}
    <div x-data="{ toasts: [] }"
         @toast.window="const id = Date.now() + Math.random();
                        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'ok' });
                        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4500)"
         style="position:fixed; bottom:1rem; right:1rem; z-index:9999; width:20rem; max-width:90vw; display:flex; flex-direction:column; gap:.5rem; pointer-events:none;">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition.opacity
                 style="pointer-events:auto;"
                 :style="t.type === 'erro'
                    ? 'border:1px solid rgba(169,58,72,.5); color:#A93A48;'
                    : 'border:1px solid rgba(61,122,76,.5); color:#3D7A4C;'"
                 class="rounded-sm px-4 py-2.5 font-mono text-sm shadow-engraved bg-surface flex items-start gap-2">
                <span x-text="t.type === 'erro' ? '✕' : '✓'"></span>
                <span x-text="t.message" class="flex-1 break-words"></span>
                <button type="button" @click="toasts = toasts.filter(x => x.id !== t.id)" aria-label="Fechar" class="opacity-50 hover:opacity-100">×</button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
