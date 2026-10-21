<div @if ($this->sfxBusy || $this->showreelBusy) wire:poll.3s @endif>
    <x-page-header
        eyebrow="Tomus · IV"
        title="Estúdio SFX"
        cota="741.5 · ACM · '26"
        lead="O vocabulário de movimento que o renderizador consegue produzir — crie efeitos, junte sons e marque os que abrem um vídeo." />

    @include('livewire.partials.studio-tabs')

    @if ($this->detail)
        {{-- ══════════════════════════ DETAIL VIEW (one effect) ══════════════════════════ --}}
        @php($d = $this->detail)
        <a href="{{ route('clips-animados.sfx') }}" wire:navigate class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6 inline-block">← voltar ao SFX</a>

        <x-panel eyebrow="{{ $d['kind'] === 'builtin' ? 'Efeito incorporado' : 'Efeito' }}" title="{{ $d['label'] }}" glyph="✷">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- big preview, in the box you pick. One component serves all three
                     formats — this renders the SAME effect in each frame so you can
                     see how it lays itself out (half = top half of an overlay clip). --}}
                <div>
                <div class="flex flex-wrap gap-1.5 mb-2">
                    @foreach (\App\Services\Clips\EffectLibrary::FORMATS as $fmt => $rotulo)
                        <button type="button" wire:click="verFormato('{{ $fmt }}')"
                                class="font-mono text-[0.6rem] px-2.5 py-1 rounded-sm border transition
                                       {{ $formato === $fmt ? 'border-teal/50 text-teal bg-teal/10' : 'border-ink-soft/20 text-ink-faint hover:text-ink' }}">
                            {{ $rotulo }}@unless ($d['formatos'][$fmt] ?? false)<span class="opacity-60"> ○</span>@endunless
                        </button>
                    @endforeach
                </div>
                <div class="relative {{ ['portrait' => 'aspect-[9/16]', 'half' => 'aspect-[9/8]', 'landscape' => 'aspect-video'][$formato] ?? 'aspect-[9/16]' }} max-h-[60vh] mx-auto rounded-sm overflow-hidden bg-black/60 flex items-center justify-center w-full">
                    @if (! ($d['formatos'][$formato] ?? false) && ($d['status'] ?? 'active') !== 'failed')
                        <div class="text-center">
                            <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                            </div>
                            <p class="font-mono text-[0.6rem] text-ink-faint mt-3">A renderizar a pré-visualização ({{ strtolower(\App\Services\Clips\EffectLibrary::FORMATS[$formato]) }})…</p>
                        </div>
                    @elseif (($d['status'] ?? null) === 'failed')
                        <div class="p-3 text-center">
                            <div class="text-bad text-2xl">✕</div>
                            <p class="font-mono text-[0.6rem] text-bad/90 mt-2">{{ \Illuminate\Support\Str::limit($d['error'] ?? 'A geração falhou', 200) }}</p>
                        </div>
                    @elseif (isset($sfxReady[$d['slug']]) && ($d['status'] ?? 'active') !== 'pending')
                        {{-- ?v= and the key both carry the render's version: same slug + same
                             design = same URL, so without it the browser keeps the video it
                             already has and a rewritten effect looks unchanged. --}}
                        <video class="w-full h-full object-cover" wire:key="prev-{{ $d['slug'] }}-{{ $formato }}-{{ $d['formatos'][$formato] ?? 0 }}"
                               src="{{ route('clips-animados.sfx-preview', ['slug' => $d['slug'], 'format' => $formato, 'v' => $d['formatos'][$formato] ?? 0]) }}" autoplay loop muted playsinline></video>
                        @if (in_array($d['status'] ?? null, ['updating'], true) || in_array($d['override'] ?? null, ['pending', 'updating'], true))
                            <div class="absolute inset-0 bg-black/55 flex items-center justify-center">
                                <p class="font-mono text-xs text-teal animate-pulse">A atualizar…</p>
                            </div>
                        @endif
                    @else
                        <div class="text-center">
                            <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden">
                                <div class="h-full bg-teal/60 animate-pulse w-2/3"></div>
                            </div>
                            <p class="font-mono text-[0.6rem] text-ink-faint mt-3">A renderizar a pré-visualização…</p>
                        </div>
                    @endif
                </div>
                </div>

                {{-- meta + actions --}}
                <div class="space-y-5">
                    <div>
                        <p class="font-mono text-[0.6rem] text-ink-faint">{{ $d['slug'] }}{{ $d['kind'] === 'builtin' ? ' · incorporado' : '' }}</p>
                        @if (!empty($d['description']))
                            <p class="text-ink-soft text-sm mt-2">{{ $d['description'] }}</p>
                        @endif
                        @if (($d['kind'] === 'builtin') && $d['override'] === 'active')
                            <p class="font-mono text-[0.55rem] text-teal mt-2">● tem uma versão personalizada deste efeito incorporado</p>
                        @endif
                        @if (!empty($d['prompt']))
                            <div class="mt-3 flex items-start gap-2">
                                <p class="text-ink-faint text-sm italic flex-1 min-w-0">“{{ $d['prompt'] }}”</p>
                                <x-copy-btn :text="$d['prompt']" label="⧉ copiar"
                                            class="font-mono text-[0.6rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition shrink-0" />
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($d['kind'] === 'custom')
                            @if ($d['status'] === 'active')
                                <button type="button" wire:click="alternarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['enabled'] ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['enabled'] ? '● ativo' : '○ desativado' }}</button>
                                <button type="button" wire:click="alternarIntro('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['intro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['intro'] ? '★ intro' : '☆ intro' }}</button>
                                <button type="button" wire:click="alternarOutro('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['outro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['outro'] ? '★ outro' : '☆ outro' }}</button>
                                <button type="button" wire:click="editarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ Melhorar com IA</button>
                                <button type="button" wire:click="abrirUso"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/40 text-teal hover:bg-teal/10 transition">▶ Usar com os seus dados</button>
                                <button type="button" wire:click="abrirAudio('{{ $d['slug'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ in_array($d['slug'], $sfxAudio, true) ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ in_array($d['slug'], $sfxAudio, true) ? '🔊 Som' : '♪ Adicionar som' }}</button>
                                @if ($d['versions'])
                                    <button type="button" wire:click="abrirHistorico('{{ $d['id'] }}')"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⟲ Histórico ({{ $d['versions'] }})</button>
                                @endif
                            @elseif ($d['status'] === 'failed')
                                <button type="button" wire:click="editarSfx('{{ $d['id'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ Tentar de novo</button>
                                {{-- A failed effect is exactly the one you need to roll back — the
                                     previous version is still on the record, so offer it here too. --}}
                                @if ($d['versions'])
                                    <button type="button" wire:click="abrirHistorico('{{ $d['id'] }}')"
                                            class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/40 text-teal hover:bg-teal/10 transition">⟲ Repor uma versão funcional ({{ $d['versions'] }})</button>
                                @endif
                            @else
                                <span class="font-mono text-[0.62rem] text-ink-faint self-center">{{ $d['status'] === 'updating' ? 'A atualizar…' : 'A gerar…' }}</span>
                            @endif
                            <a href="{{ route('clips-animados.sfx-export', $d['id']) }}"
                               class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Exportar</a>
                            <button type="button" wire:click="apagarSfx('{{ $d['id'] }}')"
                                    wire:confirm="Apagar este efeito? Os clips já renderizados mantêm o vídeo."
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕ Apagar</button>
                        @else
                            <button type="button" wire:click="alternarBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['allowed'] ? 'border-teal/40 text-teal' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['allowed'] ? '● ativo' : '○ desativado' }}</button>
                            <button type="button" wire:click="alternarIntroBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['intro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['intro'] ? '★ intro' : '☆ intro' }}</button>
                            <button type="button" wire:click="alternarOutroBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ $d['outro'] ? 'border-gold/50 text-gold' : 'border-ink-soft/25 text-ink-faint' }}">{{ $d['outro'] ? '★ outro' : '☆ outro' }}</button>
                            <button type="button" wire:click="editarBuiltin('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">✎ {{ $d['override'] ? 'Editar versão personalizada' : 'Personalizar com IA' }}</button>
                            <button type="button" wire:click="abrirUso"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/40 text-teal hover:bg-teal/10 transition">▶ Usar com os seus dados</button>
                            <button type="button" wire:click="abrirAudio('{{ $d['slug'] }}')"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border {{ in_array($d['slug'], $sfxAudio, true) ? 'border-teal/40 text-teal' : 'border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40' }} transition">{{ in_array($d['slug'], $sfxAudio, true) ? '🔊 Som' : '♪ Adicionar som' }}</button>
                            @if ($d['override'] && $d['versions'])
                                <button type="button" wire:click="abrirHistorico('{{ $d['overrideId'] }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⟲ Histórico ({{ $d['versions'] }})</button>
                            @endif
                            <a href="{{ route('clips-animados.sfx-export', $d['slug']) }}"
                               class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Exportar</a>
                            @if ($d['override'])
                                <button type="button" wire:click="resetBuiltin('{{ $d['slug'] }}')"
                                        wire:confirm="Repor «{{ $d['slug'] }}» no efeito incorporado original? A sua versão personalizada é apagada."
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">↺ Repor original</button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </x-panel>

        {{-- Use: render this effect with your own photos and texts --}}
        @if ($usoAberto)
            @php($inputs = $this->usoInputs)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="eyebrow">Usar este efeito · {{ strtolower(\App\Services\Clips\EffectLibrary::FORMATS[$formato]) }}</label>
                    <button type="button" wire:click="fecharUso" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">fechar</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form wire:submit="renderizarUso" class="space-y-3">
                        @foreach ($inputs['imagens'] as $k)
                            <div wire:key="uso-img-{{ $k }}">
                                <label class="font-mono text-[0.62rem] text-ink-soft block mb-1">{{ $k === 'src' ? 'imagem principal' : $k }}</label>
                                <div class="flex items-center gap-3">
                                    @if (($usoImagens[$k] ?? null) && ! $errors->has('usoImagens.'.$k))
                                        <img src="{{ $usoImagens[$k]->temporaryUrl() }}" class="h-12 rounded-sm border border-ink-soft/20" alt="{{ $k }}">
                                    @endif
                                    <input type="file" wire:model="usoImagens.{{ $k }}" accept="image/*" class="text-xs text-ink-soft">
                                </div>
                                @error('usoImagens.'.$k) <p class="text-bad text-sm">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        @if ($inputs['texto'] !== '')
                            <div>
                                <label class="font-mono text-[0.62rem] text-ink-soft block mb-1">texto</label>
                                <input type="text" wire:model="usoTexto" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none">
                            </div>
                        @endif
                        @foreach ($inputs['campos'] as $k => $default)
                            <div wire:key="uso-campo-{{ $k }}">
                                <label class="font-mono text-[0.62rem] text-ink-soft block mb-1">{{ $k }}</label>
                                @if (str_contains($default, "\n"))
                                    <textarea wire:model="usoCampos.{{ $k }}" rows="4" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 font-mono text-xs text-ink focus:border-teal/50 focus:outline-none"></textarea>
                                @else
                                    <input type="text" wire:model="usoCampos.{{ $k }}" class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none">
                                @endif
                            </div>
                        @endforeach
                        <div class="flex items-center gap-2">
                            <label class="font-mono text-[0.62rem] text-ink-soft">duração (s)</label>
                            <input type="number" step="0.5" min="1" max="30" wire:model="usoDuracao" class="w-20 bg-surface/40 border border-ink-soft/20 rounded-sm px-2 py-1 text-ink focus:border-teal/50 focus:outline-none">
                            @error('usoDuracao') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="renderizarUso,usoImagens"
                                class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">✦ Renderizar vídeo</button>
                    </form>
                    <div class="{{ ['portrait' => 'aspect-[9/16]', 'half' => 'aspect-[9/8]', 'landscape' => 'aspect-video'][$formato] ?? 'aspect-[9/16]' }} max-h-[60vh] w-full mx-auto rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                        @if ($this->usoEstado === 'ready')
                            <div class="w-full h-full relative">
                                <video class="w-full h-full object-contain" wire:key="uso-{{ $usoSaida }}" src="{{ route('clips-animados.sfx-use', $usoSaida) }}" autoplay loop muted playsinline controls></video>
                                <a href="{{ route('clips-animados.sfx-use', ['file' => $usoSaida, 'dl' => 1]) }}"
                                   class="absolute top-2 right-2 font-mono text-[0.62rem] px-2 py-1 rounded-sm bg-black/60 text-teal">⤓ Descarregar</a>
                            </div>
                        @elseif ($this->usoEstado === 'rendering')
                            <p class="font-mono text-xs text-teal animate-pulse">A renderizar…</p>
                        @elseif ($this->usoEstado === 'failed')
                            <p class="font-mono text-[0.6rem] text-bad/90 p-3 text-center">{{ \Illuminate\Support\Str::limit($this->usoErro, 300) }}</p>
                        @else
                            <p class="font-mono text-[0.6rem] text-ink-faint p-3 text-center">Preencha os dados e renderize.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Refine / customize panel --}}
        @if ($editingSfxId || $sfxOverrideSlug)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <form wire:submit="guardarSfxEdicao" class="space-y-3">
                    <label class="eyebrow block">{{ $sfxOverrideSlug ? 'Personalizar efeito incorporado · '.$sfxOverrideSlug : 'Melhorar este efeito' }}</label>
                    <textarea wire:model="sfxEditPrompt" rows="3"
                              placeholder="{{ $sfxOverrideSlug ? 'Descreva o aspeto e o comportamento deste efeito incorporado…' : 'Descreva a alteração que quer…' }}"
                              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                    @error('sfxEditPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            ✦ {{ $sfxOverrideSlug ? 'Criar versão personalizada' : 'Gerar de novo' }}
                        </button>
                        <button type="button" wire:click="cancelarSfxEdicao" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">cancelar</button>
                        <span class="font-mono text-[0.6rem] text-ink-faint">A versão atual mantém-se até a nova ficar renderizada.</span>
                    </div>
                </form>
            </div>
        @endif

        {{-- Sound panel --}}
        @if ($audioEditingSlug)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <label class="eyebrow block">Som do efeito · {{ $audioEditingSlug }}</label>
                    <button type="button" wire:click="fecharAudio" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">fechar</button>
                </div>
                @if (in_array($audioEditingSlug, $sfxAudio, true))
                    <div class="flex items-center gap-3 mb-2">
                        <audio controls src="{{ route('clips-animados.sfx-audio', $audioEditingSlug) }}" class="h-9 max-w-xs"></audio>
                        <button type="button" wire:click="apagarAudio('{{ $audioEditingSlug }}')"
                                wire:confirm="Remover este som? Os clips já renderizados mantêm-no."
                                class="font-mono text-[0.62rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">remover</button>
                    </div>
                @endif
                <p class="font-mono text-[0.55rem] text-ink-faint mb-4">Toca uma vez sempre que este efeito aparece num clip.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <form wire:submit="uploadAudio" class="space-y-2">
                        <label class="eyebrow block">Carregar um som</label>
                        <input type="file" wire:model="audioUpload" accept="audio/*"
                               class="block w-full text-sm text-ink-soft file:mr-3 file:py-1.5 file:px-3 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                        <div wire:loading wire:target="audioUpload" class="font-mono text-[0.6rem] text-ink-faint">a carregar…</div>
                        @error('audioUpload') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="uploadAudio,audioUpload"
                                class="font-display text-base px-4 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">↥ Anexar</button>
                    </form>
                    <form wire:submit="gerarAudio" class="space-y-2">
                        <label class="eyebrow block">Ou gerar com IA</label>
                        <textarea wire:model="audioGenPrompt" rows="2" placeholder="ex.: um toque cristalino curto, com um fim suave"
                                  class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                        @error('audioGenPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="gerarAudio"
                                class="font-display text-base px-4 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">
                            <span wire:loading.remove wire:target="gerarAudio">✦ Gerar</span>
                            <span wire:loading wire:target="gerarAudio">a gerar…</span>
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Version history panel --}}
        @if ($historyId && $this->historyEffect)
            @php($histEffect = $this->historyEffect)
            <div class="foxing bg-vellum/60 border border-teal/30 rounded-sm p-4 mt-6">
                <div class="flex items-baseline justify-between gap-3 mb-3">
                    <label class="eyebrow block">Histórico de versões · {{ $histEffect->display_name }}</label>
                    <button type="button" wire:click="fecharHistorico" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink">fechar</button>
                </div>
                <p class="font-mono text-[0.55rem] text-ink-faint mb-3">Reponha uma versão anterior. A atual fica guardada, por isso pode ir e voltar. A pré-visualização é renderizada de novo depois de repor.</p>
                <div class="space-y-2">
                    @foreach (array_reverse($histEffect->versions ?? [], true) as $i => $v)
                        <div class="flex items-center gap-3 bg-surface/30 border border-ink-soft/15 rounded-sm p-2" wire:key="ver-{{ $histEffect->id }}-{{ $i }}">
                            <span class="font-mono text-[0.55rem] text-ink-faint shrink-0">v{{ $i + 1 }}</span>
                            <span class="flex-1 min-w-0 text-sm text-ink truncate" title="{{ $v['prompt'] ?? '' }}">{{ \Illuminate\Support\Str::limit($v['prompt'] ?? '—', 90) }}</span>
                            @if (!empty($v['created_at']))
                                <span class="font-mono text-[0.5rem] text-ink-faint shrink-0">{{ \Illuminate\Support\Carbon::parse($v['created_at'])->diffForHumans() }}</span>
                            @endif
                            @if (!empty($v['prompt']))
                                <x-copy-btn :text="$v['prompt']" label="⧉ copiar"
                                            class="font-mono text-[0.6rem] px-2 py-1 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition shrink-0" />
                            @endif
                            <button type="button" wire:click="reverterSfx('{{ $histEffect->id }}', {{ $i }})"
                                    wire:confirm="Repor esta versão? A versão atual fica guardada no histórico."
                                    class="font-mono text-[0.6rem] px-2 py-1 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition shrink-0">↩ repor</button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- ══════════════════════════ GALLERY VIEW ══════════════════════════ --}}
        <a href="{{ route('clips-animados') }}" class="font-mono text-[0.62rem] text-ink-soft hover:text-ink mb-6 inline-block">← voltar a Imóveis</a>

        <x-panel eyebrow="Biblioteca de efeitos" title="SFX" glyph="✷">
            <p class="text-ink-soft text-sm mb-5">
                O vocabulário de movimento que o renderizador já consegue produzir — mais os seus. Descreva um novo efeito e
                a IA escreve um componente Remotion que segue o sistema de design. Clique num efeito para o gerir —
                melhorar, adicionar um som, marcá-lo como <span class="text-gold">★ intro</span> ou <span class="text-gold">★ outro</span>, ou apagá-lo.
            </p>
            <livewire:after-intro-setting />
            <form wire:submit="gerarSfx" class="space-y-3 mb-2">
                <label class="eyebrow block">Descreva um novo efeito</label>
                <textarea wire:model="sfxPrompt" rows="3" placeholder="ex.: um glitch intermitente que encaixa o título no lugar, com uma rápida separação de aberração cromática"
                          class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none"></textarea>
                @error('sfxPrompt') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                {{-- A mockup of the final frame turns the effect into a template: it rebuilds
                     that layout from inputs (photos, texts) you give each time you use it. --}}
                <div class="flex items-center gap-3">
                    @if ($sfxReference && ! $errors->has('sfxReference'))
                        <img src="{{ $sfxReference->temporaryUrl() }}" class="h-16 rounded-sm border border-ink-soft/20" alt="Referência">
                        <button type="button" wire:click="$set('sfxReference', null)" class="font-mono text-[0.6rem] text-ink-soft hover:text-bad">✕ remover</button>
                    @else
                        <label class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition cursor-pointer">
                            ⊕ Imagem de referência (opcional)
                            <input type="file" wire:model="sfxReference" accept="image/*" class="hidden">
                        </label>
                        <span class="font-mono text-[0.6rem] text-ink-faint">Uma maquete da imagem final — o efeito passa a ser um modelo que a anima até ficar no lugar.</span>
                    @endif
                    <span wire:loading wire:target="sfxReference" class="font-mono text-[0.6rem] text-ink-faint">a carregar…</span>
                </div>
                @error('sfxReference') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition"
                            wire:loading.attr="disabled" wire:target="gerarSfx,sfxReference">
                        ✦ Criar efeito
                    </button>
                    <span class="font-mono text-[0.6rem] text-ink-faint">As cores e os tipos de letra seguem o sistema de design.</span>
                </div>
            </form>

            {{-- Export / import — move a whole effect set (component + sound) between installs. --}}
            <div class="mt-4 pt-4 border-t border-ink-soft/10 flex flex-wrap items-center gap-x-4 gap-y-2">
                <a href="{{ route('clips-animados.sfx-export', 'all') }}"
                   class="font-mono text-xs px-3 py-1.5 rounded-sm border border-ink-soft/25 text-ink-soft hover:text-teal hover:border-teal/40 transition">⤓ Exportar todos os efeitos</a>
                <form wire:submit="importarSfx" class="flex items-center gap-2">
                    <input type="file" wire:model="importFile" accept="application/json,.json"
                           class="text-xs text-ink-soft file:mr-2 file:py-1 file:px-2 file:rounded-sm file:border file:border-ink-soft/20 file:bg-surface/40 file:text-ink-soft" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="importFile,importarSfx"
                            class="font-mono text-xs px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition whitespace-nowrap">
                        <span wire:loading.remove wire:target="importFile,importarSfx">⤒ Importar</span>
                        <span wire:loading wire:target="importFile,importarSfx">a importar…</span>
                    </button>
                </form>
                @error('importFile') <span class="text-bad text-xs">{{ $message }}</span> @enderror
            </div>
        </x-panel>

        {{-- Showreel --}}
        <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div>
                    <div class="eyebrow">Showreel</div>
                    <p class="text-ink-soft text-sm mt-1">Um vídeo que mostra cada efeito à vez, com o nome ao centro.</p>
                </div>
                <button type="button" wire:click="gerarShowreel" wire:loading.attr="disabled" wire:target="gerarShowreel"
                        @if ($this->showreelBusy) disabled @endif
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50 shrink-0">
                    @if ($this->showreelBusy) a renderizar… @elseif ($this->showreelReady) ↻ Refazer @else ✦ Criar showreel @endif
                </button>
            </div>
            @if ($this->showreelBusy)
                <p class="font-mono text-[0.6rem] text-ink-faint">A renderizar o showreel — pode demorar um minuto. Atualiza sozinho.</p>
            @elseif ($this->showreelReady)
                <video wire:ignore class="rounded-sm border border-ink-soft/15 bg-black w-full max-h-[70vh]" controls preload="metadata"
                       src="{{ route('clips-animados.showreel') }}?v={{ $this->showreelVersion }}"></video>
            @endif
        </div>

        {{-- Rewrite every effect for the three frames --}}
        @if ($this->effects->isNotEmpty())
            <div class="foxing bg-vellum/40 border border-ink-soft/15 rounded-sm p-4 mt-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="eyebrow">Formatos</div>
                        <p class="text-ink-soft text-sm mt-1">
                            Um efeito tem de funcionar em três formatos: vertical, meio ecrã (cenas sobre vídeo,
                            em que o vídeo ocupa a metade de baixo) e horizontal. Os efeitos escritos antes disto
                            mediam-se pelo ecrã inteiro, por isso numa cena de meio ecrã transbordam e ficam cortados.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        <button type="button" wire:click="tornarResponsivos" wire:loading.attr="disabled" wire:target="tornarResponsivos"
                                wire:confirm="Reescrever todos os efeitos ativos para os três formatos? Cada um mantém a descrição; a versão anterior fica no histórico."
                                class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-50">
                            ⌗ Reescrever todos
                        </button>
                        @if ($this->recuperaveis)
                            <button type="button" wire:click="recuperarFalhados" wire:loading.attr="disabled" wire:target="recuperarFalhados"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-gold/50 text-gold hover:bg-gold/10 transition disabled:opacity-50">
                                ⟲ Recuperar {{ $this->recuperaveis }} com falha
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Custom (generated) effects --}}
        @if ($this->effects->isNotEmpty())
            <div class="mt-8">
                <div class="flex items-baseline justify-between mb-4 gap-3">
                    <div class="eyebrow">Os seus efeitos · {{ $this->effects->count() }}</div>
                    <span class="font-mono text-[0.55rem] text-ink-faint">clique para gerir · ● ativo = permitido · ★ intro / outro = pode abrir / fechar um vídeo</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach ($this->effects as $effect)
                        <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col relative transition hover:border-teal/40 {{ $effect->status === 'active' && ! $effect->enabled ? 'opacity-60' : '' }}" wire:key="sfx-{{ $effect->id }}">
                            <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                                @if (in_array($effect->status, ['active', 'updating'], true) && isset($sfxReady[$effect->slug]))
                                    <video class="w-full h-full object-cover" wire:key="thumb-{{ $effect->slug }}-{{ $sfxReady[$effect->slug] }}"
                                           src="{{ route('clips-animados.sfx-preview', ['slug' => $effect->slug, 'v' => $sfxReady[$effect->slug]]) }}" autoplay loop muted playsinline></video>
                                    @if ($effect->status === 'updating')
                                        <div class="absolute inset-0 bg-black/55 flex items-center justify-center"><p class="font-mono text-[0.55rem] text-teal animate-pulse">A atualizar…</p></div>
                                    @endif
                                @elseif ($effect->status === 'failed')
                                    <div class="p-2 text-center"><div class="text-bad text-lg">✕</div><p class="font-mono text-[0.52rem] text-bad/90 mt-1 line-clamp-3">{{ \Illuminate\Support\Str::limit($effect->error, 90) }}</p></div>
                                @else
                                    <div class="text-center">
                                        <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                                        <p class="font-mono text-[0.52rem] text-ink-faint mt-2">{{ $effect->status === 'updating' ? 'A atualizar…' : 'A gerar…' }}</p>
                                    </div>
                                @endif
                                @if ($effect->status === 'active' && ($effect->intro || $effect->outro))
                                    <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-gold/20 text-gold border border-gold/30">★ {{ implode(' · ', array_filter([$effect->intro ? 'intro' : null, $effect->outro ? 'outro' : null])) }}</div>
                                @endif
                                @if (in_array($effect->slug, $sfxAudio, true))
                                    <div class="absolute top-1 right-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-teal/20 text-teal border border-teal/30">🔊</div>
                                @endif
                                {{-- On/off right here (z-20, above the overlay link) — no need to open the effect. --}}
                                @if ($effect->status === 'active')
                                    <button type="button" wire:click="alternarSfx('{{ $effect->id }}')"
                                            title="{{ $effect->enabled ? 'Permitido nos clips — clique para desativar' : 'Desativado — clique para permitir nos clips' }}"
                                            class="absolute bottom-1 left-1 z-20 font-mono text-[0.5rem] px-1.5 py-0.5 rounded-sm border backdrop-blur-sm transition {{ $effect->enabled ? 'bg-teal/25 text-teal border-teal/40 hover:bg-teal/40' : 'bg-black/60 text-ink-faint border-ink-soft/30 hover:text-ink' }}">
                                        {{ $effect->enabled ? '● ativo' : '○ desativado' }}
                                    </button>
                                @endif
                            </div>
                            <div class="mt-2 min-w-0">
                                <p class="font-display text-sm text-ink truncate">{{ $effect->display_name }}</p>
                                <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ in_array($effect->status, ['active', 'updating'], true) ? $effect->slug : (['failed' => 'falhou', 'pending' => 'pendente'][$effect->status] ?? $effect->status) }}</p>
                            </div>

                            {{-- Full-card link to open the effect (z-10, below the toggle). --}}
                            <a href="{{ route('clips-animados.sfx.detail', $effect->id) }}" wire:navigate
                               class="absolute inset-0 z-10 rounded-sm" aria-label="Abrir {{ $effect->display_name }}"></a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Built-in effects --}}
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">Incorporados · {{ count($builtins) }}</div>
                <span class="font-mono text-[0.55rem] text-ink-faint">clique para gerir ou personalizar</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                @foreach ($builtins as $b)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-2 flex flex-col relative transition hover:border-teal/40 {{ $b['allowed'] ? '' : 'opacity-60' }}" wire:key="builtin-{{ $b['slug'] }}">
                        <div class="relative aspect-[9/16] rounded-sm overflow-hidden bg-black/60 flex items-center justify-center">
                            @if (isset($sfxReady[$b['slug']]))
                                <video class="w-full h-full object-cover" wire:key="thumb-{{ $b['slug'] }}-{{ $sfxReady[$b['slug']] }}"
                                       src="{{ route('clips-animados.sfx-preview', ['slug' => $b['slug'], 'v' => $sfxReady[$b['slug']]]) }}" autoplay loop muted playsinline></video>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-16 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-ink-soft/40 animate-pulse w-1/2"></div></div>
                                    <p class="font-mono text-[0.52rem] text-ink-faint mt-2">A renderizar…</p>
                                </div>
                            @endif
                            @if ($b['intro'] || $b['outro'])
                                <div class="absolute top-1 right-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-gold/20 text-gold border border-gold/30" title="{{ implode(' · ', array_filter([$b['intro'] ? 'intro' : null, $b['outro'] ? 'outro' : null])) }}">★</div>
                            @endif
                            @if ($b['override'] === 'active')
                                <div class="absolute top-1 left-1 font-mono text-[0.5rem] px-1 py-0.5 rounded-sm bg-teal/20 text-teal border border-teal/30">personalizado</div>
                            @endif
                            {{-- On/off right here (z-20, above the overlay link) — no need to open the effect. --}}
                            <button type="button" wire:click="alternarBuiltin('{{ $b['slug'] }}')"
                                    title="{{ $b['allowed'] ? 'Permitido nos clips — clique para desativar' : 'Desativado — clique para permitir nos clips' }}"
                                    class="absolute bottom-1 left-1 z-20 font-mono text-[0.5rem] px-1.5 py-0.5 rounded-sm border backdrop-blur-sm transition {{ $b['allowed'] ? 'bg-teal/25 text-teal border-teal/40 hover:bg-teal/40' : 'bg-black/60 text-ink-faint border-ink-soft/30 hover:text-ink' }}">
                                {{ $b['allowed'] ? '● ativo' : '○ desativado' }}
                            </button>
                        </div>
                        <div class="mt-2 min-w-0">
                            <p class="font-display text-sm text-ink truncate">{{ $b['label'] }}</p>
                            <p class="font-mono text-[0.5rem] text-ink-faint truncate">{{ $b['slug'] }}</p>
                        </div>

                        {{-- Full-card link to open the effect (z-10, below the toggle). --}}
                        <a href="{{ route('clips-animados.sfx.detail', $b['slug']) }}" wire:navigate
                           class="absolute inset-0 z-10 rounded-sm" aria-label="Abrir {{ $b['label'] }}"></a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
