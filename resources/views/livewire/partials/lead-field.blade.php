{{-- Optional news LEAD: one line pinned on screen for the whole clip.
     Pre-filled automatically when the script came from a news bit (`> …`). --}}
<div>
    <label class="eyebrow block mb-2">Lead — on-screen line (optional)</label>
    <input type="text" wire:model="lead" maxlength="120"
           placeholder="e.g. GPT-5 just got 60% cheaper — for everyone"
           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-2.5 text-ink placeholder:text-ink-faint focus:border-teal/50 focus:outline-none" />
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">Shown as a banner for the entire video. Leave empty for none.</p>
</div>
