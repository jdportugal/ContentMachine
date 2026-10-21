{{-- Copies its text to the clipboard, with a brief "copied" flash. --}}
@props(['text', 'label' => '⧉ copiar prompt'])
<button type="button" data-copy="{{ $text }}"
        onclick="navigator.clipboard.writeText(this.dataset.copy); const o = this.textContent; this.textContent = '✓ copiado'; setTimeout(() => this.textContent = o, 1200)"
        class="{{ $attributes->get('class', 'font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-teal hover:border-teal/40 transition') }}">{{ $label }}</button>
