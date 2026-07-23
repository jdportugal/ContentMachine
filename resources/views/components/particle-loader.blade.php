{{-- Nebula particle loader — reusable full-screen loading overlay.
     Rendered once in the layout; controlled by window events / window.CMLoader.
     See resources/js/app.js for the trigger API. --}}
<div x-data="particleLoader" x-cloak x-show="open"
     x-transition:enter="cm-loader-enter" x-transition:leave="cm-loader-leave"
     class="cm-loader" role="status" aria-live="polite" aria-busy="true">
    <canvas x-ref="canvas" class="cm-loader__canvas"></canvas>
    <div class="cm-loader__center">
        <p class="cm-loader__msg" x-text="message">A processar</p>
        <div class="cm-loader__dots" aria-hidden="true"><span></span><span></span><span></span></div>
    </div>
</div>
