{{-- Where the word-synced captions sit (RenderJob → ClipProps.captions). --}}
<div>
    <label class="eyebrow block mb-2">Subtitles</label>
    <div class="flex flex-wrap gap-1.5">
        @foreach (['bottom' => 'Bottom', 'middle' => 'Middle', 'top' => 'Top', 'none' => 'No subtitles'] as $valor => $rotulo)
            <label class="cursor-pointer font-mono text-[0.65rem] px-3 py-1.5 rounded-sm border transition {{ $legendas === $valor ? 'border-teal/50 text-teal bg-teal/10' : 'border-ink-soft/20 text-ink-soft hover:text-ink' }}">
                <input type="radio" wire:model.live="legendas" value="{{ $valor }}" class="hidden" />{{ $rotulo }}
            </label>
        @endforeach
    </div>
</div>
