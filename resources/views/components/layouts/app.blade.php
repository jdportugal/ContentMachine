<!DOCTYPE html>
<html lang="pt" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Máquina de Conteúdo' }} · IATECA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-nocturna min-h-screen text-ink antialiased">
    <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="lg:flex min-h-screen">
        {{-- ============ Barra superior (só telemóvel) ============ --}}
        <header class="lg:hidden sticky top-0 z-30 flex items-center justify-between gap-3 h-14 px-4 border-b border-ink-soft/15 bg-papyrus/95 backdrop-blur">
            <a href="{{ route('painel') }}" class="flex items-baseline gap-2 min-w-0">
                <span class="font-display text-lg text-teal" style="letter-spacing:.06em">IATECA</span>
                <span class="eyebrow text-[0.5rem] truncate">Máquina · de · Conteúdo</span>
            </a>
            <button type="button" @click="sidebarOpen = true" aria-label="Abrir menu"
                    class="p-2 -mr-2 text-ink-soft hover:text-ink transition">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
        </header>

        {{-- Fundo escurecido (só telemóvel, quando o menu está aberto) --}}
        <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
             class="lg:hidden fixed inset-0 z-40 bg-black/60"></div>

        {{-- ============ Estante lateral (navegação) ============ --}}
        <aside class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 max-w-[85vw] shrink-0 flex-col border-r border-ink-soft/15 bg-vellum backdrop-blur transform transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:z-auto lg:max-w-none lg:bg-vellum/40 lg:backdrop-blur-none"
               :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="px-6 py-3 border-b border-ink-soft/15 flex items-center justify-between gap-2">
                <a href="{{ route('painel') }}" class="block min-w-0" @click="sidebarOpen = false">
                    <span class="font-display text-lg text-teal tracking-wide" style="letter-spacing:.06em">IATECA</span>
                    <span class="block eyebrow mt-0.5 text-[0.55rem]">Máquina · de · Conteúdo</span>
                </a>
                <button type="button" @click="sidebarOpen = false" aria-label="Fechar menu"
                        class="lg:hidden p-1 -mr-1 text-ink-soft hover:text-ink transition">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-2 space-y-0.5">
                @php
                    $nav = [
                        ['route' => 'painel',          'label' => 'Painel',            'sub' => 'Vista geral',        'color' => '#FFB347', 'glyph' => '◆'],
                        ['route' => 'monitorizacao',   'label' => 'Monitorização',     'sub' => 'Redes sociais',      'color' => '#C77DFF', 'glyph' => '◈'],
                        ['route' => 'clips',           'label' => 'Gerador de Clips',  'sub' => 'Vídeo',              'color' => '#5A7BFF', 'glyph' => '▲'],
                        ['route' => 'clips-animados',  'label' => 'Clips Animados',    'sub' => 'Animação',           'color' => '#4DE0E0', 'glyph' => '✦'],
                        ['route' => 'ativos',          'label' => 'Ativos',            'sub' => 'Media · Música',     'color' => '#4DE08A', 'glyph' => '♫'],
                        ['route' => 'publicacoes',     'label' => 'Publicações',       'sub' => 'Posts · Carrosséis', 'color' => '#9C7DFF', 'glyph' => '◇'],
                        ['route' => 'rascunhos',       'label' => 'Rascunhos',         'sub' => 'Agendamento',        'color' => '#FF8A4D', 'glyph' => '⬡'],
                        ['route' => 'noticias',        'label' => 'Notícias',          'sub' => 'Agregador',          'color' => '#FFD98A', 'glyph' => '✷'],
                        ['route' => 'design-system',   'label' => 'Sistema de Design', 'sub' => 'Marca do conteúdo',  'color' => '#FF5C7A', 'glyph' => '❖'],
                        ['route' => 'definicoes',      'label' => 'Definições',        'sub' => 'Variáveis',          'color' => '#8AE0FF', 'glyph' => '⚙'],
                    ];
                @endphp

                @foreach ($nav as $i => $item)
                    @php $active = request()->routeIs($item['route'].'*'); @endphp
                    <a href="{{ route($item['route']) }}" @click="sidebarOpen = false"
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
                    <div>CÉREBRO ·
                        <a href="obsidian://open?path={{ rawurlencode(config('contentmachine.vault.path')) }}"
                           class="text-teal underline decoration-teal/40 underline-offset-2 hover:decoration-teal transition"
                           title="Abrir a vault no Obsidian">Obsidian Vault</a>
                    </div>
                    <div>006.3 · IAT · '26</div>
                </div>
            </div>
        </aside>

        {{-- ============ Conteúdo ============ --}}
        <main class="flex-1 min-w-0 overflow-x-hidden">
            <div class="max-w-6xl mx-auto px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Overlay de carregamento (partículas) — global, accionável por qualquer
         componente via dispatch('loader-show'/'loader-hide') ou window.CMLoader. --}}
    <x-particle-loader />

    {{-- Toasts globais (canto inferior direito). Ao nível do <body>, fora do
         scroller/transform, para o position:fixed fixar ao ecrã. Ouve o evento
         'toast' despachado por qualquer componente Livewire. --}}
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
