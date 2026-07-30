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

    public function test_delete_removes_the_record(): void
    {
        $p = $this->store()->create(['type' => 'animation', 'input_kind' => 'text']);
        $this->assertNotNull($this->store()->find($p->id));

        $p->delete();
        $this->assertNull($this->store()->find($p->id));
    }
}
