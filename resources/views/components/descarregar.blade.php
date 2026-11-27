@props(['href'])
{{-- The "Descarregar MP4" button. A plain download link changes nothing on screen (customers tapped it again and again, and
     Clarity logged dead clicks although the file arrived: 2026-11-27), and on an iPhone the file ends up in Files, not Photos.
     So: on a phone that can share files, the first tap fetches the video and the second opens the share sheet ("Guardar
     vídeo" → Photos/Gallery; two taps because the sheet needs a fresh tap after a long download). Everywhere else the link
     downloads as before, with a visible "A descarregar…". If anything fails, the plain download. --}}
<span class="contents" x-data="{
    estado: '', ficheiro: null,
    async clicar(e) {
        const partilha = navigator.canShare && window.File && matchMedia('(pointer: coarse)').matches;
        if (! partilha) {   // a computer, or a browser that cannot share files: the download itself, now with a sign it started
            this.estado = 'a-descarregar';
            setTimeout(() => this.estado = '', 8000);
            return;
        }
        e.preventDefault();
        if (this.estado === 'a-preparar') return;
        if (this.ficheiro) {
            try { await navigator.share({ files: [this.ficheiro] }); }
            catch (err) { if (err?.name !== 'AbortError') location.href = this.$refs.link.href; }
            return;
        }
        this.estado = 'a-preparar';
        try {
            const r = await fetch(this.$refs.link.href);
            if (! r.ok) throw new Error(r.status);
            const f = new File([await r.blob()], 'video-imocena.mp4', { type: 'video/mp4' });
            if (! navigator.canShare({ files: [f] })) throw new Error('canShare');
            this.ficheiro = f;
            this.estado = 'pronto';
        } catch (err) {
            this.estado = '';
            location.href = this.$refs.link.href;
        }
    },
}">
    <a href="{{ $href }}" x-ref="link" x-on:click="clicar($event)" {{ $attributes }}>
        <span x-show="estado === ''" class="contents">{{ $slot }}</span>
        <span x-show="estado === 'a-descarregar'" x-cloak>✓ A descarregar…</span>
        <span x-show="estado === 'a-preparar'" x-cloak>A preparar o vídeo…</span>
        <span x-show="estado === 'pronto'" x-cloak>Guardar vídeo →</span>
    </a>
    <span x-show="estado === 'a-descarregar'" x-cloak role="status" class="basis-full w-full text-[13px] text-[#C9CED6]">✓ A descarregar. O vídeo fica nas transferências do navegador.</span>
    <span x-show="estado === 'pronto'" x-cloak role="status" class="basis-full w-full text-[13px] text-[#C9CED6]">Toque em «Guardar vídeo» para o pôr nas fotografias.</span>
</span>
