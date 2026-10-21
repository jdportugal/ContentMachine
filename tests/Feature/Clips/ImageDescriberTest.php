<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\ImageDescriber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageDescriberTest extends TestCase
{
    public function test_it_describes_only_images_without_a_description_and_sends_the_pixels(): void
    {
        $disk = Storage::fake(config('contentmachine.clips.disk'));
        $disk->putFileAs('clips/uploads', UploadedFile::fake()->image('a.jpg'), 'a.jpg');
        $disk->putFileAs('clips/uploads', UploadedFile::fake()->image('b.jpg'), 'b.jpg');

        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text',
            'text' => '{"img_a": "Kitchen with island, bright"}']]])]);

        $out = app(ImageDescriber::class)->describe([
            ['id' => 'img_a', 'path' => 'clips/uploads/a.jpg', 'description' => ''],
            ['id' => 'img_b', 'path' => 'clips/uploads/b.jpg', 'description' => 'the agent'],
        ], 'T3 in Alvalade');

        $this->assertSame(['Kitchen with island, bright', 'the agent'], array_column($out, 'description'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => collect($r->data()['messages'][0]['content'])->where('type', 'image')->count() === 1);
    }
}
