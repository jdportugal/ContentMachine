<?php

namespace Tests\Unit\Clips;

use App\Models\ClipProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClipProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_json_columns_and_reports_active_state(): void
    {
        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá',
            'plan' => ['duration' => 1.5],
        ]);

        $this->assertSame(['duration' => 1.5], $p->fresh()->plan);
        $this->assertSame(ClipProject::STATUS_DRAFT, $p->status);
        $this->assertTrue($p->isActive());

        $p->update(['status' => ClipProject::STATUS_DONE]);
        $this->assertFalse($p->isActive());
    }
}
