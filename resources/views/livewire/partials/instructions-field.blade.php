{{-- Extra direction for THIS clip only, added to the end of the planner's
     prompt where it overrides the project's style manual. Project-wide style
     lives in Design System → Animation instructions. --}}
<div>
    <label class="eyebrow block mb-2">Instructions for this clip (optional)</label>
    <textarea wire:model="instructions" rows="6" maxlength="10000"
              placeholder="e.g. keep it dark and cinematic, no charts — this one is a story, not data"
              class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none"></textarea>
    @error('instructions') <p class="mt-1 text-bad text-sm">{{ $message }}</p> @enderror
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">
        Up to 10,000 characters — paste the full listing/brief here. Applies to this video only and wins over the project's style manual.
        For every clip in the project, edit
        <a href="{{ route('design-system') }}" class="text-teal hover:opacity-70 underline">Design System → Animation instructions</a>.
    </p>
</div>
