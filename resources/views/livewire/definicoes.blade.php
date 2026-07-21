<div>
    <x-page-header
        eyebrow="Tomus · VIII"
        title="Definições"
        cota="005.1 · IAT · '26"
        lead="As variáveis da casa — marca, perfis sociais e as fontes que o agregador vai vasculhar. Guardadas no vault." />

    <form wire:submit="guardar" class="space-y-6">
        {{-- Geral --}}
        <x-panel eyebrow="Casa" title="Geral" glyph="◆">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="eyebrow block mb-1.5">Nome da marca</label>
                    <input type="text" wire:model="geral.nome_marca"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5">Sítio / website</label>
                    <input type="url" wire:model="geral.sitio" placeholder="https://…"
                           class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-body focus:border-teal focus:outline-none">
                </div>
            </div>
        </x-panel>

        {{-- Perfis sociais --}}
        <x-panel eyebrow="Redes" title="Perfis sociais" glyph="❧">
            <p class="text-ink-soft -mt-2 mb-4">Identificador (@handle) e ligação de cada perfil que monitorizamos.</p>
            <div class="grid sm:grid-cols-2 gap-5">
                @foreach ($perfis as $rede => $dados)
                    @php $m = $plataformasMeta[$rede] ?? ['label' => ucfirst($rede), 'cor' => '#2dbab4', 'glifo' => '•']; @endphp
                    <div class="border border-ink-soft/15 rounded-sm p-4 bg-surface/30">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-xl" style="color: {{ $m['cor'] }}">{{ $m['glifo'] }}</span>
                            <span class="font-display text-xl text-ink">{{ $m['label'] }}</span>
                        </div>
                        <label class="eyebrow block mb-1">Identificador</label>
                        <input type="text" wire:model="perfis.{{ $rede }}.handle" placeholder="@ateca"
                               class="w-full mb-3 bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                        <label class="eyebrow block mb-1">Ligação</label>
                        <input type="url" wire:model="perfis.{{ $rede }}.url" placeholder="https://…"
                               class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-1.5 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Fontes do agregador --}}
        <x-panel eyebrow="Agregador" title="Fontes a vasculhar" glyph="☙">
            <p class="text-ink-soft -mt-2 mb-4">Uma entrada por linha — canais, subreddits, contas ou ligações que alimentam o relatório de notícias.</p>
            <div class="grid sm:grid-cols-2 gap-5">
                @php
                    $rotulos = [
                        'youtube' => ['Canais de YouTube', 'canal ou URL por linha'],
                        'reddit' => ['Subreddits', 'r/nome por linha'],
                        'twitter' => ['Contas de X / Twitter', '@conta por linha'],
                        'tiktok' => ['Contas de TikTok', '@conta por linha'],
                    ];
                @endphp
                @foreach ($fontes as $fonte => $texto)
                    @php $r = $rotulos[$fonte] ?? [ucfirst($fonte), 'uma por linha']; @endphp
                    <div>
                        <label class="eyebrow block mb-1.5">{{ $r[0] }}</label>
                        <textarea wire:model="fontes.{{ $fonte }}" rows="4" placeholder="{{ $r[1] }}"
                                  class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none"></textarea>
                    </div>
                @endforeach
            </div>
        </x-panel>

        {{-- Gerador de Clips --}}
        <x-panel eyebrow="Oficina" title="Gerador de Clips" glyph="✂">
            <p class="text-ink-soft -mt-2 mb-4">Endereço da API ShortsCreator que corta os vídeos e grava as legendas.</p>
            <div>
                <label class="eyebrow block mb-1.5">URL da API</label>
                <input type="url" wire:model="shorts.api_url" placeholder="http://localhost:5000"
                       class="w-full bg-papyrus/60 border border-ink-soft/25 rounded-sm px-3 py-2 text-ink font-mono text-sm focus:border-teal focus:outline-none">
                <p class="mt-1.5 font-mono text-xs text-ink-faint">Vazio → usa <span class="text-ink-soft">SHORTS_API_URL</span> do .env.</p>
            </div>
        </x-panel>

        {{-- Barra de acção --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-teal text-papyrus font-display text-xl px-7 py-2.5 rounded-sm hover:bg-teal-deep transition shadow-engraved">
                Guardar definições
            </button>
            @if ($guardado)
                <span class="text-good font-mono text-sm">✓ Guardado às {{ $guardado }} no vault (definicoes/definicoes.md).</span>
            @endif
        </div>

        <p class="font-mono text-xs text-ink-faint pt-2 border-t border-ink-soft/10">
            🔒 Nota: as chaves de API (Apify, TubeLab, Gemini…) vivem no <span class="text-ink-soft">.env</span>, por segurança — não são geridas aqui.
        </p>
    </form>
</div>
