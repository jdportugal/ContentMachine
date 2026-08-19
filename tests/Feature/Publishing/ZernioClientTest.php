<?php

namespace Tests\Feature\Publishing;

use App\Services\Publishing\ZernioClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ZernioClientTest extends TestCase
{
    private function client(): ZernioClient
    {
        return new ZernioClient('sk_test', 'https://zernio.test/api/v1');
    }

    public function test_comment_to_dm_sends_keywords_link_button_and_public_reply(): void
    {
        Http::fake(['*/comment-automations' => Http::response(['success' => true, 'automation' => ['id' => 'auto_1']])]);

        $res = $this->client()->commentToDm(
            profileId: 'profile_1',
            accountId: 'acc_ig',
            name: 'My post',
            dmMessage: "Here's the link 👇",
            keywords: ['GUIDE'],
            linkUrl: 'https://example.com/guide',
            linkLabel: 'Open the link',
            commentReply: 'Sent you a DM 📩',
        );

        $this->assertSame('auto_1', $res['automation']['id']);
        Http::assertSent(function (Request $r) {
            $b = $r->data();

            return $r->hasHeader('Authorization', 'Bearer sk_test')
                && $b['profileId'] === 'profile_1'
                && $b['accountId'] === 'acc_ig'
                && $b['keywords'] === ['GUIDE']
                && $b['dmMessage'] === "Here's the link 👇"
                && $b['commentReply'] === 'Sent you a DM 📩'
                && $b['buttons'] === [['type' => 'url', 'title' => 'Open the link', 'url' => 'https://example.com/guide']]
                // The DM door — without it only commenters would be answered.
                && $b['alsoMatchInDms'] === true
                && $b['matchMode'] === 'word';
        });
    }

    public function test_a_blank_keyword_is_rejected_before_any_request(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);

        try {
            $this->client()->commentToDm('p', 'a', 'name', 'msg', ['  ']);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_a_missing_key_is_reported_not_sent_anonymously(): void
    {
        Http::fake();
        config(['services.zernio.key' => '']);

        $this->expectExceptionMessage('Zernio API key is not configured');

        (new ZernioClient)->commentToDm('p', 'a', 'name', 'msg', ['GUIDE']);
    }
}
