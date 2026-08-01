<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\ImageRequests;
use PHPUnit\Framework\TestCase;

class ImageRequestsTest extends TestCase
{
    private function plan(): array
    {
        return ['scenes' => [
            ['layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'gronk logo']]]],
            ['layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'gronk logo']]]],       // dup prompt
            ['layers' => [['type' => 'image-reveal', 'params' => ['src' => 'img_x', 'generate' => 'already set']]]], // fulfilled → skip
            ['layers' => [['type' => 'kinetic-text', 'text' => 'hi']]],                                  // not an image
        ]];
    }

    public function test_collects_distinct_pending_prompts_only(): void
    {
        $requests = ImageRequests::collect($this->plan());

        $this->assertCount(1, $requests);
        $this->assertSame('gronk logo', $requests[0]['prompt']);
        $this->assertSame(ImageRequests::key('gronk logo'), $requests[0]['key']);
    }

    public function test_apply_uploads_sets_src_on_matching_layers_and_leaves_the_rest(): void
    {
        $uploads = [ImageRequests::key('gronk logo') => 'img_upload'];
        $out = ImageRequests::applyUploads($this->plan(), $uploads);

        $this->assertSame('img_upload', $out['scenes'][0]['layers'][0]['params']['src']);
        $this->assertSame('img_upload', $out['scenes'][1]['layers'][0]['params']['src']); // dup prompt too
        $this->assertSame('img_x', $out['scenes'][2]['layers'][0]['params']['src']);       // untouched
    }
}
