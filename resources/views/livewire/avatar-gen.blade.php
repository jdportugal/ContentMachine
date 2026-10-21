<div @if ($this->busy) wire:poll.5s @endif>
    <x-page-header
        eyebrow="Tomus · A"
        title="Avatar Gen"
        cota="741.6 · ACM · '26"
        lead="Upload a talking-head video and the audio it should be saying — fal.ai's Sync Lipsync 2 Pro re-animates the mouth to match." />

    @unless ($configurado)
        <div class="mb-6 px-4 py-3 rounded-sm border border-bad/40 bg-bad/5">
            <p class="font-mono text-[0.7rem] text-bad">No fal.ai key set. Add one under
                <a href="{{ route('definicoes') }}" wire:navigate class="underline">Settings → API keys</a> before generating.</p>
        </div>
    @endunless

    <x-panel eyebrow="New" title="Lip-sync a video" glyph="☉">
        <form wire:submit="gerar" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="eyebrow block">Video · the face</label>
                    <input type="file" wire:model="video" accept="video/mp4,video/quicktime,video/webm"
                           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink-soft text-sm file:mr-3 file:px-3 file:py-1 file:rounded-sm file:border-0 file:bg-teal/15 file:text-teal file:font-mono file:text-[0.65rem]" />
                    <p class="font-mono text-[0.55rem] text-ink-faint">.mp4 / .mov / .webm / .m4v · up to {{ $maxSegundos }}s · under 200MB</p>
                    @error('video') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="video" class="font-mono text-[0.6rem] text-teal">uploading…</div>
                </div>

                <div class="space-y-2">
                    <label class="eyebrow block">Audio · the voice</label>
                    <input type="file" wire:model="audio" accept="audio/*"
                           class="w-full bg-surface/40 border border-ink-soft/20 rounded-sm px-3 py-2 text-ink-soft text-sm file:mr-3 file:px-3 file:py-1 file:rounded-sm file:border-0 file:bg-teal/15 file:text-teal file:font-mono file:text-[0.65rem]" />
                    <p class="font-mono text-[0.55rem] text-ink-faint">mp3 / wav / m4a / aac / ogg · longer than the video is cut off</p>
                    @error('audio') <p class="text-bad text-sm">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="audio" class="font-mono text-[0.6rem] text-teal">uploading…</div>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <button type="submit" @disabled(! $configurado)
                        class="font-display text-lg px-6 py-2 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        wire:loading.attr="disabled" wire:target="gerar,video,audio">
                    ☉ Lip-sync
                </button>
                <span class="font-mono text-[0.6rem] text-ink-faint">
                    ${{ number_format($precoPorMinuto, 2) }} per minute of video, seconds rounded up — a 10s clip is ${{ number_format($precoPorMinuto / 6, 2) }}. Takes a few minutes.
                </span>
            </div>
        </form>
    </x-panel>

    @if ($this->avatars->isNotEmpty())
        @php $total = array_sum($custos); @endphp
        <div class="mt-8">
            <div class="flex items-baseline justify-between mb-4 gap-3">
                <div class="eyebrow">Your lip-syncs · {{ $this->avatars->count() }}</div>
                @if ($total > 0)
                    <span class="font-mono text-[0.55rem] text-ink-faint" title="Total fal.ai spend recorded on this page">
                        total ≈ ${{ number_format($total, 2) }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($this->avatars as $a)
                    <div class="foxing bg-vellum/50 border border-ink-soft/15 rounded-sm p-3 flex flex-col" wire:key="avatar-{{ $a->id() }}">
                        <div class="relative rounded-sm overflow-hidden bg-black/60 flex items-center justify-center aspect-video">
                            @if ($a->status === 'active')
                                {{-- wire:ignore so the poll never restarts playback mid-watch. --}}
                                <video wire:ignore class="w-full h-full object-contain" controls playsinline preload="metadata"
                                       src="{{ route('avatar-gen.media', $a->id()) }}"></video>
                            @elseif ($a->status === 'failed')
                                <div class="p-3 text-center">
                                    <div class="text-bad text-lg">✕</div>
                                    <p class="font-mono text-[0.55rem] text-bad/90 mt-1">{{ \Illuminate\Support\Str::limit($a->error, 200) }}</p>
                                    @if (is_array($a->get('fal_request')))
                                        <p class="font-mono text-[0.5rem] text-ink-faint mt-2">fal already ran this one — Resume collects it without paying again.</p>
                                    @endif
                                </div>
                            @else
                                <div class="text-center">
                                    <div class="h-1 w-20 mx-auto bg-surface/40 rounded-full overflow-hidden"><div class="h-full bg-teal/60 animate-pulse w-2/3"></div></div>
                                    <p class="font-mono text-[0.55rem] text-ink-faint mt-2">Lip-syncing at fal.ai…</p>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 min-w-0 flex-1">
                            <p class="text-ink text-sm truncate" title="{{ $a->display_name }}">{{ $a->display_name ?: $a->id() }}</p>
                            <p class="font-mono text-[0.55rem] text-ink-faint mt-1 truncate">♪ {{ $a->get('audio_name', '—') }}</p>
                            <p class="font-mono text-[0.55rem] text-ink-faint mt-1">
                                @if ($a->get('duration')) {{ $a->get('duration') }}s @endif
                                @if (($custos[$a->id()] ?? 0) > 0)
                                    · <span class="text-teal"
                                            title="{{ $a->get('cost_estimated') ? 'Estimated — fal did not report its billable units for this request' : 'What fal reports it billed for this request' }}">
                                        {{ $a->get('cost_estimated') ? '≈' : '' }}${{ number_format($custos[$a->id()], 2) }}</span>
                                @elseif ($a->status === 'active')
                                    · <span class="text-ink-faint" title="ffprobe could not measure the result, so the per-minute cost could not be worked out — fal still billed for it">cost not measured</span>
                                @endif
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($a->status === 'failed' && is_array($a->get('fal_request')))
                                <button type="button" wire:click="retomar('{{ $a->id() }}')"
                                        class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">↻ Resume</button>
                            @endif
                            @if ($a->status === 'active')
                                <a href="{{ route('avatar-gen.media', $a->id()) }}?download=1"
                                   class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-teal/50 text-teal hover:bg-teal/10 transition">⤓ Download .mp4</a>
                            @endif
                            <button type="button" wire:click="apagar('{{ $a->id() }}')"
                                    wire:confirm="Delete this lip-sync and its video?"
                                    class="font-mono text-[0.7rem] px-3 py-1.5 rounded-sm border border-ink-soft/20 text-ink-soft hover:text-bad hover:border-bad/40 transition">✕ Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <x-empty-state class="mt-8" glyph="☉" title="No lip-syncs yet"
                       note="Upload a video and an audio track above and the result will show up here, ready to download." />
    @endif
</div>
