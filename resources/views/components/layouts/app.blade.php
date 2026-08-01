<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Brand Machine' }} · AI Content Machines</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-nocturna min-h-screen text-ink antialiased">
    <div x-data="{ nav: false }" class="flex min-h-screen">
        {{-- Mobile top bar (hamburger) — hidden on lg+ --}}
        <header class="lg:hidden fixed top-0 inset-x-0 z-30 h-14 flex items-center justify-between px-4 bg-vellum/95 backdrop-blur border-b border-ink-soft/15">
            <a href="{{ route('painel') }}" class="font-display text-lg text-teal tracking-wide" style="letter-spacing:.06em">Brand Machine</a>
            <button type="button" @click="nav = !nav" aria-label="Toggle menu" class="p-2 -mr-2 text-2xl leading-none text-ink">☰</button>
        </header>

        {{-- Drawer backdrop (mobile) --}}
        <div x-show="nav" x-cloak x-transition.opacity @click="nav = false" class="lg:hidden fixed inset-0 z-40 bg-black/50"></div>

        {{-- ============ Side shelf (navigation) — off-canvas drawer on mobile, static on lg+ ============ --}}
        <aside @click="nav = false" x-bind:class="{ '!translate-x-0': nav }"
               class="w-64 shrink-0 border-r border-ink-soft/15 bg-vellum/95 lg:bg-vellum/40 flex flex-col fixed lg:sticky top-0 h-screen z-50 -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-out">
            <div class="px-6 py-3 border-b border-ink-soft/15">
                <a href="{{ route('painel') }}" class="block">
                    <span class="font-display text-lg text-teal tracking-wide" style="letter-spacing:.06em">Brand Machine</span>
                    <span class="block eyebrow mt-0.5 text-[0.55rem]">AI Content Machines</span>
                </a>
            </div>

            <div class="px-4 py-3 border-b border-ink-soft/15">
                @livewire('project-switcher')
            </div>

            <nav class="flex-1 min-h-0 overflow-y-auto py-2 space-y-0.5">
                @php
                    $nav = [
                        ['route' => 'painel',          'label' => 'Dashboard',         'sub' => 'Overview',           'color' => '#FFB347', 'glyph' => '◆'],
                        ['route' => 'monitorizacao',   'label' => 'Monitoring',        'sub' => 'Social networks',    'color' => '#C77DFF', 'glyph' => '◈'],
                        ['route' => 'clips',           'label' => 'Clip Generator',    'sub' => 'Video',              'color' => '#5A7BFF', 'glyph' => '▲'],
                        ['route' => 'clips-animados',  'label' => 'Animated Clips',    'sub' => 'Animation',          'color' => '#4DE0E0', 'glyph' => '✦'],
                        ['route' => 'clips-animados.sfx', 'label' => 'SFX Studio',      'sub' => 'Effects · Intros',   'color' => '#6FE0D0', 'glyph' => '✶'],
                        ['route' => 'ativos',          'label' => 'Assets',            'sub' => 'Media · Music',      'color' => '#4DE08A', 'glyph' => '♫'],
                        ['route' => 'publicacoes',     'label' => 'Posts',             'sub' => 'Posts · Carousels',  'color' => '#9C7DFF', 'glyph' => '◇'],
                        ['route' => 'finished',        'label' => 'Finished',          'sub' => 'Publishing',         'color' => '#FF8A4D', 'glyph' => '⬡'],
                        ['route' => 'noticias',        'label' => 'News',              'sub' => 'Aggregator',         'color' => '#FFD98A', 'glyph' => '✷'],
                        ['route' => 'design-system',   'label' => 'Design System',     'sub' => 'Content brand',      'color' => '#FF5C7A', 'glyph' => '❖'],
                        ['route' => 'definicoes',      'label' => 'Settings',          'sub' => 'Variables',          'color' => '#8AE0FF', 'glyph' => '⚙'],
                    ];
                @endphp

                @foreach ($nav as $i => $item)
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="group flex items-center gap-3 pl-4 pr-4 py-1.5 mx-2 rounded-sm transition
                              {{ $active ? 'bg-surface/70 text-ink' : 'text-ink-soft hover:bg-surface/40 hover:text-ink' }}">
                        <span class="w-1.5 self-stretch rounded-full transition-all"
                              style="background: {{ $active ? $item['color'] : 'transparent' }}; box-shadow: {{ $active ? '0 0 8px '.$item['color'].'88' : 'none' }}"></span>
                        <span class="w-5 text-center text-sm" style="color: {{ $item['color'] }}">{{ $item['glyph'] }}</span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-display text-base leading-tight">{{ $item['label'] }}</span>
                            <span class="block text-[0.62rem] font-mono text-ink-faint truncate">{{ $item['sub'] }}</span>
                        </span>
                        <span class="font-mono text-[0.6rem] text-ink-faint">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="px-6 py-2.5 border-t border-ink-soft/15">
                <div class="font-mono text-[0.6rem] text-ink-faint leading-relaxed">
                    <div>BRAIN ·
                        <a href="obsidian://open?path={{ rawurlencode(config('contentmachine.vault.path')) }}"
                           class="text-teal underline decoration-teal/40 underline-offset-2 hover:decoration-teal transition"
                           title="Open the vault in Obsidian">Obsidian Vault</a>
                    </div>
                    <div>006.3 · ACM · '26</div>
                </div>
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
                    ? 'border:1px solid rgba(255,92,122,.6); color:#FF8FA6;'
                    : 'border:1px solid rgba(18,183,106,.6); color:#4DE08A;'"
                 class="rounded-sm px-4 py-2.5 font-mono text-sm shadow-engraved bg-surface flex items-start gap-2">
                <span x-text="t.type === 'erro' ? '✕' : '✓'"></span>
                <span x-text="t.message" class="flex-1 break-words"></span>
                <button type="button" @click="toasts = toasts.filter(x => x.id !== t.id)" class="opacity-50 hover:opacity-100">×</button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
