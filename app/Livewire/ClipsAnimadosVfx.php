<?php

namespace App\Livewire;

use App\Jobs\Clips\GenerateVfxJob;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\VfxStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * VFX Lab — describe an animation, pick its proportions and length, get a
 * finished video to drop into a long-form edit. Same generator and same
 * design-system guard as the SFX studio, but the output is an asset you
 * download rather than a layer the planner can use.
 */
#[Layout('components.layouts.app')]
#[Title('VFX Lab')]
class ClipsAnimadosVfx extends Component
{
    /** Longest render offered — past this a single clip ties up the queue for too long. */
    private const MAX_DURATION = 15;

    public string $prompt = '';

    public string $aspect = '16:9';

    public float $duration = 5;

    public bool $transparent = false;

    private function store(): VfxStore
    {
        return app(VfxStore::class);
    }

    public function gerarVfx(): void
    {
        $this->validate([
            'prompt' => 'required|string|min:8|max:600',
            'aspect' => 'required|in:'.implode(',', array_keys(VfxStore::ASPECTS)),
            'duration' => 'required|numeric|min:1|max:'.self::MAX_DURATION,
        ], [
            'prompt.required' => 'Describe the animation you want.',
            'prompt.min' => 'Give a bit more detail (at least 8 characters).',
            'duration.max' => 'Keep it to '.self::MAX_DURATION.' seconds or less.',
        ]);

        $size = VfxStore::ASPECTS[$this->aspect];

        $vfx = $this->store()->create([
            'prompt' => trim($this->prompt),
            'aspect' => $this->aspect,
            'width' => $size['width'],
            'height' => $size['height'],
            'duration' => round($this->duration, 2),
            'transparent' => $this->transparent,
            'display_name' => Str::limit(trim($this->prompt), 40),
        ]);

        GenerateVfxJob::dispatch($vfx->id());
        $this->prompt = '';
    }

    public function apagarVfx(string $id): void
    {
        $this->store()->deleteById($id);
    }

    /** @return Collection<int,EffectRecord> */
    public function getVfxProperty(): Collection
    {
        return $this->store()->all();
    }

    /** Poll only while something is still rendering. */
    public function getBusyProperty(): bool
    {
        return $this->vfx->contains(fn (EffectRecord $v) => $v->status === EffectRecord::STATUS_PENDING);
    }

    public function render()
    {
        return view('livewire.clips-animados-vfx', [
            'aspects' => VfxStore::ASPECTS,
            'maxDuration' => self::MAX_DURATION,
        ]);
    }
}
