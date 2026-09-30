<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VoiceKit\Effects;

final class EffectsTest extends TestCase
{
    public function test_encodes_a_chain(): void
    {
        $encoded = Effects::encode([
            ['type' => 'reverb', 'room_size' => 0.5],
            ['type' => 'pitch', 'semitones' => 2],
        ]);

        $this->assertSame(
            '[{"type":"reverb","room_size":0.5},{"type":"pitch","semitones":2}]',
            $encoded,
        );
    }

    public function test_encodes_variadic_descriptors(): void
    {
        $this->assertSame('[{"type":"compressor","ratio":3}]', Effects::chain(['type' => 'compressor', 'ratio' => 3]));
        $this->assertSame('[]', Effects::chain());
    }

    public function test_passes_through_an_encoded_chain(): void
    {
        $this->assertSame('[{"type":"reverb"}]', Effects::normalize('[{"type":"reverb"}]'));
        $this->assertSame('[{"type":"reverb"}]', Effects::normalize([['type' => 'reverb']]));
    }

    public function test_rejects_values_json_cannot_encode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Effects::encode([['type' => 'reverb', 'room_size' => NAN]]);
    }

    public function test_unicode_is_not_escaped(): void
    {
        $this->assertSame('[{"type":"ё"}]', Effects::encode([['type' => 'ё']]));
    }
}
