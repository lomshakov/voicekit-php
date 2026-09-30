<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use VoiceKit\VoiceKitError;

final class VoiceKitErrorTest extends TestCase
{
    public function test_reads_problem_details(): void
    {
        $error = VoiceKitError::fromResponse(429, '{"code":"rate_limited","title":"Too many requests","detail":"Slow down."}');

        $this->assertSame(429, $error->statusCode());
        $this->assertSame('rate_limited', $error->errorCode());
        $this->assertTrue($error->isRateLimited());
        $this->assertStringContainsString('Slow down.', $error->getMessage());
        $this->assertSame('{"code":"rate_limited","title":"Too many requests","detail":"Slow down."}', $error->responseBody());
    }

    public function test_falls_back_to_the_title_then_the_body(): void
    {
        $titled = VoiceKitError::fromResponse(404, '{"title":"Not found"}');
        $this->assertStringContainsString('Not found', $titled->getMessage());
        $this->assertNull($titled->errorCode());

        $plain = VoiceKitError::fromResponse(500, 'upstream timeout');
        $this->assertStringContainsString('upstream timeout', $plain->getMessage());
    }

    public function test_uses_the_reason_phrase_for_empty_bodies(): void
    {
        $error = VoiceKitError::fromResponse(401, '');

        $this->assertStringContainsString('Unauthorized', $error->getMessage());
        $this->assertTrue($error->isUnauthorized());
        $this->assertNull($error->responseBody());
    }

    public function test_describes_transport_failures(): void
    {
        $error = VoiceKitError::fromTransport('connection refused', new RuntimeException('boom'));

        $this->assertNull($error->statusCode());
        $this->assertNull($error->errorCode());
        $this->assertFalse($error->isNotFound());
        $this->assertStringContainsString('transport error', $error->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $error->getPrevious());
    }

    public function test_matches_only_voicekit_errors(): void
    {
        $this->assertFalse(VoiceKitError::matches(new RuntimeException('x'), 'quota_exceeded'));
        $this->assertFalse(VoiceKitError::matches(VoiceKitError::fromResponse(403, '{"code":"effects_forbidden"}'), 'quota_exceeded'));
        $this->assertTrue(VoiceKitError::matches(VoiceKitError::fromResponse(403, '{"code":"effects_forbidden"}'), 'effects_forbidden'));
    }
}
