<?php

namespace Tests\Feature\Publishing;

use App\Services\Publishing\BlotatoClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlotatoClientTest extends TestCase
{
    private function client(): BlotatoClient
    {
        return new BlotatoClient('test-key');
    }

    public function test_http_media_url_passes_through_without_upload(): void
    {
        Http::fake();

        $url = $this->client()->uploadMedia('https://cdn.example.com/a.jpg');

        $this->assertSame('https://cdn.example.com/a.jpg', $url);
        Http::assertNothingSent();
    }

    public function test_local_file_uses_presigned_upload_flow(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        file_put_contents($tmp, 'BYTES');

        Http::fake([
            '*/v2/media/uploads' => Http::response([
                'presignedUrl' => 'https://upload.example.com/put-here',
                'publicUrl' => 'https://media.blotato.com/final.mp4',
            ]),
            'upload.example.com/*' => Http::response('', 200),
        ]);

        $url = $this->client()->uploadMedia($tmp);

        $this->assertSame('https://media.blotato.com/final.mp4', $url);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/media/uploads')
            && $r['filename'] === basename($tmp)
            && $r->hasHeader('blotato-api-key', 'test-key'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->url() === 'https://upload.example.com/put-here'
            && $r->body() === 'BYTES');

        @unlink($tmp);
    }

    public function test_publish_immediate_omits_scheduling_fields(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'post_1'])]);

        $res = $this->client()->publish('acc_1', 'linkedin', 'hello', ['https://m/x.jpg']);

        $this->assertSame('post_1', $res['id']);
        Http::assertSent(function (Request $r) {
            $b = $r->data();

            return str_contains($r->url(), '/v2/posts')
                && $b['post']['accountId'] === 'acc_1'
                && $b['post']['target']['targetType'] === 'linkedin'
                && $b['post']['content']['mediaUrls'] === ['https://m/x.jpg']
                && ! array_key_exists('scheduledTime', $b)
                && ! array_key_exists('useNextFreeSlot', $b);
        });
    }

    public function test_publish_with_scheduled_time(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'post_2'])]);

        $this->client()->publish('acc', 'threads', 'hi', [], '2026-08-01T10:00:00+00:00');

        Http::assertSent(fn (Request $r) => $r->data()['scheduledTime'] === '2026-08-01T10:00:00+00:00');
    }

    public function test_publish_with_next_free_slot(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'post_3'])]);

        $this->client()->publish('acc', 'instagram', 'hi', [], null, true);

        Http::assertSent(fn (Request $r) => ($r->data()['useNextFreeSlot'] ?? null) === true);
    }

    /** Blotato 400s a TikTok post without `isBrandedContent` (their exact name). */
    public function test_tiktok_target_carries_the_branded_content_disclosure(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'p1'])]);

        $this->client()->publish('acc', 'tiktok', 'hello');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/posts')
            && $r['post']['target']['isBrandedContent'] === false
            && $r['post']['target']['isAiGenerated'] === true);
    }

    /** Blotato 422s an Instagram post with more than 5 hashtags — cap, don't fail. */
    public function test_instagram_captions_are_capped_at_five_hashtags(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'p1'])]);

        $this->client()->publish('acc', 'instagram', "Great news!

#ai #video #claude #news #tech #automation #extra");

        Http::assertSent(fn (Request $r) => $r['post']['content']['text'] === "Great news!

#ai #video #claude #news #tech");
    }

    /** Other platforms keep every hashtag — the cap is Instagram's rule alone. */
    public function test_other_platforms_keep_all_hashtags(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'p1'])]);

        $this->client()->publish('acc', 'youtube', "T

#a #b #c #d #e #f #g");

        Http::assertSent(fn (Request $r) => $r['post']['content']['text'] === "T

#a #b #c #d #e #f #g");
    }

    public function test_youtube_target_gets_required_extras(): void
    {
        Http::fake(['*/v2/posts' => Http::response(['id' => 'yt'])]);

        $this->client()->publish('acc', 'youtube', 'My great video title', ['https://m/v.mp4']);

        Http::assertSent(function (Request $r) {
            $t = $r->data()['post']['target'];

            return $t['targetType'] === 'youtube'
                && $t['privacyStatus'] === 'public'
                && $t['title'] === 'My great video title'
                && $t['shouldNotifySubscribers'] === false;
        });
    }
}
