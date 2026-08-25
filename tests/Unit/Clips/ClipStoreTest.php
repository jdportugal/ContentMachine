<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Tests\TestCase;

class ClipStoreTest extends TestCase
{
    private function store(): ClipStore
    {
        return app(ClipStore::class);
    }

    public function test_creates_reads_updates_and_reports_active_state(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá',
            'plan' => ['duration' => 1.5],
        ]);

        // Round-trips through the vault JSON file (nested arrays intact).
        $fresh = $this->store()->findOrFail($p->id);
        $this->assertSame(['duration' => 1.5], $fresh->plan);
        $this->assertSame(ClipRecord::STATUS_DRAFT, $fresh->status);
        $this->assertTrue($fresh->isActive());

        $p->update(['status' => ClipRecord::STATUS_DONE]);
        $this->assertFalse($this->store()->findOrFail($p->id)->isActive());
    }

    /**
     * A slow job must not put back the world as it found it.
     *
     * GenerateRequestImageJob reads the clip, spends up to a minute in the image
     * generator, then writes. `save()` persists the WHOLE record, so merging onto
     * the snapshot it started with silently reverted every field changed
     * meanwhile: the user pressed "continue" (status → planning) mid-generation
     * and the finishing job put `collecting` back, bouncing them onto the collect
     * screen again with their images already there. The same lost update reverts a
     * prompt the user edited and other suggestions' image_uploads.
     */
    public function test_a_stale_writer_does_not_revert_fields_it_did_not_touch(): void
    {
        $p = $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'text',
            'status' => ClipRecord::STATUS_COLLECTING,
            'meta' => ['image_uploads' => ['a' => 'img-a']],
        ]);

        // The queued job's snapshot, taken BEFORE the slow generation.
        $job = $this->store()->findOrFail($p->id);

        // Meanwhile the user hits "continue", and another suggestion lands.
        $web = $this->store()->findOrFail($p->id);
        $web->update([
            'status' => ClipRecord::STATUS_PLANNING,
            'meta' => ['image_uploads' => ['a' => 'img-a', 'b' => 'img-b']],
        ]);

        // …and only now does the job write its own result.
        $job->update(['images' => [['id' => 'img-c']]]);

        $final = $this->store()->findOrFail($p->id);
        $this->assertSame(ClipRecord::STATUS_PLANNING, $final->status, 'the job reverted the status');
        $this->assertSame(['a' => 'img-a', 'b' => 'img-b'], $final->meta['image_uploads'], 'the job reverted meta');
        $this->assertSame([['id' => 'img-c']], $final->images, 'the job lost its own write');
    }

    public function test_delete_removes_the_record(): void
    {
        $p = $this->store()->create(['type' => 'animation', 'input_kind' => 'text']);
        $this->assertNotNull($this->store()->find($p->id));

        $p->delete();
        $this->assertNull($this->store()->find($p->id));
    }
}
