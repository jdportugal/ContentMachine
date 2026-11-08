<?php

namespace App\Services\Pagamentos;

use RuntimeException;

/**
 * Verifies a Stripe webhook's Stripe-Signature header (scheme v1: HMAC-SHA256 of
 * "<timestamp>.<raw body>" with the endpoint's signing secret). No SDK needed.
 */
class StripeWebhook
{
    public const TOLERANCE = 300; // seconds, like Stripe's own libraries

    public function __construct(private string $secret) {}

    /** @return array<string,mixed> the event, when the signature is genuine */
    public function event(string $payload, string $header, ?int $now = null): array
    {
        if ($this->secret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$k][] = $v;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];
        if ($timestamp <= 0 || $signatures === []) {
            throw new RuntimeException('Malformed Stripe-Signature header.');
        }
        if (abs(($now ?? time()) - $timestamp) > self::TOLERANCE) {
            throw new RuntimeException('Stripe-Signature timestamp outside tolerance.');
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);
                if (! is_array($event) || empty($event['type'])) {
                    throw new RuntimeException('Stripe event is not JSON.');
                }

                return $event;
            }
        }
        throw new RuntimeException('Stripe-Signature does not match.');
    }

    /** The header a client would send — for tests. */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
