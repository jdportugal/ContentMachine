{{-- Convide um colega (Convites): the watermark comes off when a colleague makes their free video by this link. --}}
<div class="mt-6 pt-6 border-t border-ivory/10 flex flex-col gap-3">
    <span class="text-xs tracking-[.28em] text-coral">OU GRÁTIS: CONVIDE UM COLEGA</span>
    <p class="text-sm leading-relaxed text-[#C9CED6]">Envie o seu link a um colega consultor. Quando ele fizer o vídeo grátis dele, a sua versão <strong class="text-ivory font-medium">sem marca d’água</strong> fica pronta e enviamo-la para o seu email. Ele também ganha um vídeo grátis.</p>
    @if (! $c['ativo'])
        <form wire:submit="ativarConvite" class="flex flex-col gap-2.5" novalidate>
            <div class="flex flex-col sm:flex-row gap-2.5">
                <input type="text" wire:model="meuNome" placeholder="O seu nome" autocomplete="name" maxlength="60"
                       class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
                <input type="email" wire:model="meuEmail" placeholder="O seu email" autocomplete="email" inputmode="email"
                       class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
            </div>
            @error('meuNome') <p class="text-sm text-coral">{{ $message }}</p> @enderror
            @error('meuEmail') <p class="text-sm text-coral">{{ $message }}</p> @enderror
            <button type="submit" class="self-start min-h-[48px] px-6 rounded-full bg-ivory text-navy font-semibold hover:brightness-95">Criar o meu link de convite</button>
            <p class="text-xs text-[#8B95A7]">O seu nome aparece no convite. O email serve só para lhe enviarmos o vídeo sem marca d’água.</p>
        </form>
    @else
        <div class="flex flex-wrap gap-2.5" x-data="{ copiado: false }">
            <a href="{{ $c['whatsapp'] }}" target="_blank" rel="noopener" wire:click="partilhou('whatsapp')"
               class="inline-flex items-center justify-center gap-2 min-h-[48px] px-5 rounded-full bg-[#25D366] text-navy font-semibold hover:brightness-105">Enviar por WhatsApp</a>
            <button type="button" class="inline-flex items-center justify-center min-h-[48px] px-5 rounded-full border border-ivory/40 text-ivory font-semibold hover:bg-ivory/5"
                    x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js($c['link'])).then(() => { copiado = true; $wire.partilhou('copiado'); setTimeout(() => copiado = false, 2500) })"
                    x-text="copiado ? 'Link copiado ✓' : 'Copiar o link'">Copiar o link</button>
        </div>
        <p class="truncate text-xs text-[#8B95A7] select-all">{{ $c['link'] }}</p>
        <form wire:submit="convidar" class="flex flex-col sm:flex-row gap-2.5" novalidate>
            <input type="email" wire:model="emailColega" placeholder="Ou o email do colega: enviamos nós" inputmode="email"
                   class="flex-1 min-w-0 min-h-[48px] px-4 rounded-full bg-navy border border-ivory/15 text-ivory placeholder:text-[#8B95A7] focus:border-cyan focus:outline-none">
            <button type="submit" class="min-h-[48px] px-5 rounded-full border border-cyan/60 text-cyan font-semibold hover:bg-cyan/10">Enviar convite</button>
        </form>
        @error('emailColega') <p class="text-sm text-coral">{{ $message }}</p> @enderror
        @if ($conviteEnviado !== '')
            <p class="text-sm text-cyan" role="status">Convite enviado para {{ $conviteEnviado }}. Pode convidar mais colegas.</p>
        @endif
        <p class="text-xs text-[#8B95A7]">Enviamos a versão limpa para {{ $c['email'] }} assim que um colega fizer o vídeo dele.</p>
    @endif
</div>
