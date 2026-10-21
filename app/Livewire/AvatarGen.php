<?php

namespace App\Livewire;

use App\Jobs\Avatars\GenerateLipsyncJob;
use App\Services\Avatars\AvatarStore;
use App\Services\Avatars\FalLipsync;
use App\Services\Clips\FfmpegVideoCompositor;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Costs\CostLedger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Avatar Gen — upload a talking-head video and an audio track, get the video
 * back with the mouth re-animated to the audio (fal.ai, Sync Lipsync 2 Pro).
 *
 * Deliberately NOT in the side menu yet: reachable at /avatar-gen only.
 */
#[Layout('components.layouts.app')]
#[Title('Avatar Gen')]
class AvatarGen extends Component
{
    use WithFileUploads;

    /** Checked here so a bad file fails free, rather than after an upload to fal. */
    private const VIDEO_MAX_MB = 200;

    public $video;

    public $audio;

    private function store(): AvatarStore
    {
        return app(AvatarStore::class);
    }

    public function gerar(): void
    {
        $this->validate([
            'video' => 'required|file|mimes:mp4,mov,webm,m4v|max:'.(self::VIDEO_MAX_MB * 1024),
            'audio' => 'required|file|mimes:mp3,wav,m4a,aac,ogg|max:51200',
        ], [
            'video.required' => 'Pick the video whose lips should move.',
            'video.mimes' => 'The video has to be .mp4, .mov, .webm or .m4v.',
            'video.max' => 'The video has to be under '.self::VIDEO_MAX_MB.'MB.',
            'audio.required' => 'Pick the audio to lip-sync to.',
            'audio.mimes' => 'Unsupported audio format — use mp3, wav, m4a, aac or ogg.',
        ]);

        // Sync Lipsync 2 Pro documents no duration limit — the bill does. At
        // $5 a minute a long upload is real money, so anything over the ceiling
        // is refused here with the price it would have cost.
        $segundos = app(FfmpegVideoCompositor::class)->probeDuration($this->video->getRealPath());
        $maximo = self::maxSegundos();
        if ($segundos > $maximo) {
            $this->addError('video', 'That video is '.round($segundos).'s — the ceiling is '.$maximo.'s (it would cost ≈$'
                .number_format(GenerateLipsyncJob::preco($segundos), 2).'). Trim it, or raise FAL_LIPSYNC_MAX_SECONDS.');

            return;
        }

        $avatar = $this->store()->create([
            'video_path' => $this->video->store('avatars/uploads'),
            'audio_path' => $this->audio->store('avatars/uploads'),
            'display_name' => $this->video->getClientOriginalName(),
            'audio_name' => $this->audio->getClientOriginalName(),
        ]);

        GenerateLipsyncJob::dispatch($avatar->id());

        $this->reset('video', 'audio');
    }

    /** The money guard's ceiling, in seconds. */
    public static function maxSegundos(): int
    {
        return (int) config('contentmachine.custos.fal_lipsync_max_seconds', 120);
    }

    /**
     * Pick a failed lip-sync back up. fal has already run (and billed) the
     * request, so this collects that same result — it never submits a new one.
     */
    public function retomar(string $id): void
    {
        $avatar = $this->store()->find($id);
        if (! $avatar || ! is_array($avatar->get('fal_request'))) {
            return;
        }

        $avatar->update(['status' => EffectRecord::STATUS_PENDING, 'error' => null]);
        GenerateLipsyncJob::dispatch($id);
    }

    public function apagar(string $id): void
    {
        $this->store()->deleteById($id);
    }

    /** @return Collection<int,EffectRecord> */
    public function getAvatarsProperty(): Collection
    {
        return $this->store()->all();
    }

    /** Poll only while something is still generating. */
    public function getBusyProperty(): bool
    {
        return $this->avatars->contains(fn (EffectRecord $a) => $a->status === EffectRecord::STATUS_PENDING);
    }

    public function render()
    {
        return view('livewire.avatar-gen', [
            'configurado' => app(FalLipsync::class)->configured(),
            'custos' => app(CostLedger::class)->totaisPorPeca('avatar'),
            'precoPorMinuto' => (float) config('contentmachine.custos.fal_lipsync_min', 5.00),
            'maxSegundos' => self::maxSegundos(),
        ]);
    }
}
