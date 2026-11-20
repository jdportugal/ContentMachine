{{-- "Gravo a minha voz": the text to read (optional, shown large while recording), a recorder in the browser, or an audio file. --}}
<div class="flex flex-col gap-4"
     x-data="{
        estado: '{{ $vozGravada ? 'enviado' : 'pronto' }}', rec: null, partes: [], url: null, seg: 0, relogio: null,
        async gravar() {
            let s;
            try { s = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }); }
            catch (e) { this.estado = 'sem-microfone'; return; }
            const tipo = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find(t => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
            this.rec = new MediaRecorder(s, tipo ? { mimeType: tipo } : {});
            this.partes = [];
            this.rec.ondataavailable = e => e.data.size && this.partes.push(e.data);
            this.rec.onstop = () => {
                s.getTracks().forEach(t => t.stop());
                clearInterval(this.relogio);
                const b = new Blob(this.partes, { type: this.rec.mimeType || 'audio/webm' });
                this.url && URL.revokeObjectURL(this.url);
                this.url = URL.createObjectURL(b);
                this.estado = 'a-enviar';
                $wire.upload('vozGravada', new File([b], 'voz.' + (b.type.includes('mp4') ? 'm4a' : 'webm'), { type: b.type }),
                    () => this.estado = 'enviado', () => this.estado = 'erro');
            };
            this.rec.start();
            this.seg = 0;
            this.relogio = setInterval(() => this.seg++, 1000);
            this.estado = 'a-gravar';
        },
        parar() { this.rec && this.rec.state === 'recording' && this.rec.stop(); },
        tempo() { return Math.floor(this.seg / 60) + ':' + String(this.seg % 60).padStart(2, '0'); },
     }">
    <button type="button" wire:click="sugerirTexto" wire:loading.attr="disabled" wire:target="sugerirTexto"
            class="self-start inline-flex items-center gap-2 min-h-[44px] rounded-full border border-cyan/60 px-4 text-sm text-cyan disabled:opacity-60">
        <span wire:loading.remove wire:target="sugerirTexto">✎ Escrever o texto por mim</span>
        <span wire:loading wire:target="sugerirTexto">A escrever o texto… (uns segundos)</span>
    </button>
    <label class="flex flex-col gap-1.5">
        <span class="text-[13px] text-[#C9CED6]">O texto que vai ler (opcional)</span>
        <textarea wire:model="narracao" rows="7" maxlength="1500" placeholder="Escreva ou cole aqui o texto. Fica à vista enquanto grava."
                  x-init="$nextTick(() => { $el.style.height = 'auto'; $el.style.height = Math.max($el.scrollHeight + 2, 180) + 'px' })"
                  x-on:input="$el.style.height = 'auto'; $el.style.height = Math.max($el.scrollHeight + 2, 180) + 'px'"
                  x-effect="estado; $nextTick(() => { $el.style.height = 'auto'; $el.style.height = Math.max($el.scrollHeight + 2, 180) + 'px' })"
                  :class="estado === 'a-gravar' ? 'text-[20px] leading-[1.6] border-cyan' : 'text-[16px] leading-relaxed border-ivory/20'"
                  class="w-full rounded-[14px] bg-navy border p-4 text-ivory placeholder:text-[#7C8696] focus:border-cyan focus:outline-none"></textarea>
    </label>

    {{-- While recording it stays in view above the create bar, so Parar is always at hand. --}}
    <div :class="estado === 'a-gravar' ? 'sticky bottom-[124px] z-10 shadow-[0_-8px_24px_rgba(0,0,0,.45)]' : ''"
         class="rounded-[16px] border border-ivory/15 bg-navy-2 p-4 flex flex-col gap-3">
        <div class="flex items-center gap-3">
            <button type="button" x-show="estado !== 'a-gravar'" x-on:click="gravar()"
                    class="shrink-0 inline-flex items-center gap-2 min-h-[52px] rounded-full bg-coral px-5 text-navy font-semibold">
                <span class="w-3 h-3 rounded-full bg-navy" aria-hidden="true"></span>
                <span x-text="estado === 'enviado' || estado === 'erro' ? 'Gravar de novo' : 'Gravar'">Gravar</span>
            </button>
            <button type="button" x-show="estado === 'a-gravar'" x-on:click="parar()" x-cloak
                    class="shrink-0 inline-flex items-center gap-2 min-h-[52px] rounded-full border-2 border-coral px-5 text-ivory font-semibold">
                <span class="w-3 h-3 bg-coral" aria-hidden="true"></span> Parar
            </button>
            <span class="text-sm" aria-live="polite">
                <span x-show="estado === 'pronto'" class="text-[#AEB5C1]">Toque em Gravar e leia com calma, perto do telemóvel.</span>
                <span x-show="estado === 'a-gravar'" x-cloak class="text-coral"><span class="inline-block w-2 h-2 rounded-full bg-coral animate-pulse"></span> A gravar · <span x-text="tempo()"></span></span>
                <span x-show="estado === 'a-enviar'" x-cloak class="text-cyan">A guardar a gravação…</span>
                <span x-show="estado === 'enviado'" x-cloak class="text-cyan">Gravação pronta. Ouça antes de criar o vídeo.</span>
                <span x-show="estado === 'erro'" x-cloak class="text-coral">Não conseguimos guardar a gravação. Tente outra vez.</span>
                <span x-show="estado === 'sem-microfone'" x-cloak class="text-coral">Sem acesso ao microfone. Autorize-o no navegador, ou carregue um ficheiro.</span>
            </span>
        </div>
        <audio x-show="url && estado !== 'a-gravar'" x-cloak :src="url" controls class="w-full"></audio>
        <p class="text-xs text-[#AEB5C1]">Até cerca de 1 minuto. Num sítio sossegado, e termine com o seu contacto.</p>
    </div>

    <label class="flex items-center justify-center gap-2 min-h-[52px] rounded-[14px] border border-dashed border-ivory/30 px-4 text-sm text-ivory cursor-pointer">
        <input type="file" accept="audio/*" wire:model="vozGravada" class="sr-only" x-on:change="url = null">
        @if ($vozGravada && ! str_starts_with($vozGravada->getClientOriginalName(), 'voz.'))
            ♪ {{ $vozGravada->getClientOriginalName() }} · trocar
        @else
            Ou carregue um ficheiro de áudio (MP3, M4A, WAV)
        @endif
    </label>
    <span wire:loading wire:target="vozGravada" class="text-xs text-cyan">A enviar…</span>
    @error('vozGravada') <p class="text-sm text-coral">{{ $message }}</p> @enderror
</div>
