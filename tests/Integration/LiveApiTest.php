<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VoiceKit\VoiceKitClient;

/**
 * Smoke-tests against the live API. They are skipped unless `VOICEKIT_API_KEY`
 * is set, so `composer test` stays offline by default:
 *
 * ```bash
 * VOICEKIT_API_KEY=rtt_… vendor/bin/phpunit --testsuite integration
 * ```
 */
final class LiveApiTest extends TestCase
{
    private VoiceKitClient $client;

    protected function setUp(): void
    {
        $key = getenv('VOICEKIT_API_KEY');
        if ($key === false || trim($key) === '') {
            $this->markTestSkipped('Set VOICEKIT_API_KEY to run the live API tests.');
        }

        $this->client = new VoiceKitClient($key, timeout: 60.0);
    }

    public function test_usage_reports_the_plan(): void
    {
        $usage = $this->client->usage();

        $this->assertFalse($usage->toArray() === []);
    }

    public function test_synthesize_returns_playable_audio(): void
    {
        $audio = $this->client->synthesize('Привет! Это проверка PHP SDK.', voice: 'preset_anna', format: 'mp3');

        $this->assertGreaterThan(1000, strlen($audio));
        $this->assertSame(0, strpos($audio, 'ID3'));
    }
}
