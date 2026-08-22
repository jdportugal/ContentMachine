<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over Zernio's comment/DM automation API (https://zernio.com/api/v1).
 *
 * One call registers the "DM me GUIDE and I'll send the link" loop: Zernio watches
 * the connected account, and when a comment — or a DM, with alsoMatchInDms — carries
 * one of the keywords, it sends the DM with the link button (and, optionally, a
 * public reply to the comment). It runs on Meta's official Graph API, so nothing
 * here polls or scrapes; we hand over the rules and Zernio does the rest.
 */
class ZernioClient
{
    public function __construct(
        private readonly ?string $key = null,
        private readonly ?string $baseUrl = null,
    ) {}

    private function http(): PendingRequest
    {
        $key = $this->key ?: (string) config('services.zernio.key');
        if ($key === '') {
            throw new RuntimeException('Zernio API key is not configured (Settings → API keys).');
        }

        return Http::baseUrl($this->baseUrl ?: (string) config('services.zernio.base_url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(60);
    }

    /**
     * The accounts connected in Zernio, optionally filtered by platform — each
     * row carries `_id` (the account id) and `profileId` (its profile), which
     * are exactly the two ids the DM automations need.
     *
     * @return array<int,array<string,mixed>>
     */
    public function accounts(?string $platform = null): array
    {
        return $this->http()
            ->get('/accounts', array_filter(['platform' => $platform, 'status' => 'connected']))
            ->throw()
            ->json('accounts') ?? [];
    }

    /**
     * Creates an account-wide comment-to-DM automation.
     *
     * Account-wide (no platformPostId) because we publish through Blotato and
     * never learn the platform's own media id — the keyword is what ties a DM
     * back to the post, which is exactly how the CTA reads to a viewer.
     *
     * @param  array<int,string>  $keywords  at least one — alsoMatchInDms needs it
     * @return array<string,mixed> Zernio's response (contains automation.id)
     */
    public function commentToDm(
        string $profileId,
        string $accountId,
        string $name,
        string $dmMessage,
        array $keywords,
        ?string $linkUrl = null,
        ?string $linkLabel = null,
        ?string $commentReply = null,
    ): array {
        $keywords = array_values(array_filter(array_map('trim', $keywords)));
        if ($keywords === []) {
            throw new RuntimeException('A DM automation needs at least one keyword.');
        }

        $body = [
            'profileId' => $profileId,
            'accountId' => $accountId,
            'name' => $name,
            'dmMessage' => $dmMessage,
            'keywords' => $keywords,
            // 'word' matches the keyword as a whole word (and tolerates typos),
            // so "GUIDE" doesn't fire on "guidelines".
            'matchMode' => 'word',
            'typoTolerance' => true,
            // The DM door: someone who DMs the keyword gets the link too, not
            // only someone who comments it.
            'alsoMatchInDms' => true,
        ];

        if ($linkUrl) {
            $body['buttons'] = [[
                'type' => 'url',
                'title' => $linkLabel ?: 'Open the link',
                'url' => $linkUrl,
            ]];
        }

        if ($commentReply) {
            $body['commentReply'] = $commentReply;
        }

        return $this->http()->post('/comment-automations', $body)->throw()->json() ?? [];
    }
}
