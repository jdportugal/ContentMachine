<div>
    <label class="eyebrow block mb-2">Backdrop</label>
    <select wire:model="background"
            class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-4 py-3 text-ink focus:border-teal/50 focus:outline-none">
        <option value="auto">Auto — the AI picks one that fits</option>
        <option value="none">None — themed backdrop</option>
        @foreach ($this->enabledBackgrounds as $bg)
            <option value="{{ $bg->slug }}">{{ $bg->display_name }}</option>
        @endforeach
    </select>
    <p class="mt-1 font-mono text-[0.6rem] text-ink-faint">
        The full-screen background behind every scene. Manage them in ◆ Backgrounds.
    </p>
</div>
