{{-- Visualizador em ecrã inteiro. Abre com o evento window «abrir-lightbox»
     ({ imgs: [urls], i: índiceInicial }); navega com as setas / teclado. --}}
<style>[x-cloak]{display:none!important}</style>
<div x-data="{
        open: false, imgs: [], i: 0,
        abrir(imgs, i){ this.imgs = imgs || []; this.i = i || 0; this.open = this.imgs.length > 0; if(this.open) document.body.style.overflow='hidden'; },
        fechar(){ this.open = false; document.body.style.overflow=''; },
        prox(){ if(this.i < this.imgs.length - 1) this.i++; },
        ant(){ if(this.i > 0) this.i--; }
     }"
     x-on:abrir-lightbox.window="abrir($event.detail.imgs, $event.detail.i)"
     x-on:keydown.escape.window="if(open) fechar()"
     x-on:keydown.arrow-right.window="if(open) prox()"
     x-on:keydown.arrow-left.window="if(open) ant()"
     x-show="open" x-cloak x-transition.opacity
     class="fixed inset-0 z-[60] bg-ink/95 backdrop-blur-sm flex items-center justify-center"
     @click.self="fechar()">
    <button @click="fechar()" title="Fechar (Esc)"
            class="absolute top-3 right-5 text-papyrus/70 hover:text-papyrus text-4xl leading-none">×</button>
    <button x-show="i > 0" @click.stop="ant()" title="Anterior (←)"
            class="absolute left-2 md:left-8 text-papyrus/60 hover:text-papyrus text-6xl leading-none select-none px-3">‹</button>
    <img :src="imgs[i]" class="max-h-[92vh] max-w-[88vw] object-contain rounded-sm shadow-2xl border border-papyrus/10">
    <button x-show="i < imgs.length - 1" @click.stop="prox()" title="Seguinte (→)"
            class="absolute right-2 md:right-8 text-papyrus/60 hover:text-papyrus text-6xl leading-none select-none px-3">›</button>
    <div x-show="imgs.length > 1" x-text="(i + 1) + ' / ' + imgs.length"
         class="absolute bottom-5 left-1/2 -translate-x-1/2 font-mono text-xs text-papyrus/60"></div>
</div>
