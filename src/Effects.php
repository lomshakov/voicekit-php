<?php

declare(strict_types=1);

namespace VoiceKit;

use InvalidArgumentException;
use JsonException;

/**
 * Helpers for the effect chains accepted by `/v1/synthesize`,
 * `/v1/audio/effects` and `/v1/video/effects`.
 *
 * An effect is a descriptor such as `['type' => 'reverb', 'room_size' => 0.5]`;
 * the API applies the chain in order.
 *
 * ```php
 * $chain = Effects::encode([
 *     ['type' => 'reverb', 'room_size' => 0.5],
 *     ['type' => 'pitch', 'semitones' => 2],
 * ]);
 *
 * $audio = $client->synthesize('Привет!', voice: 'preset_anna', effects: $chain);
 * ```
 */
final class Effects
{
    /**
     * Encodes an effect chain into the JSON string the API expects.
     *
     * @param array<int, array<string, mixed>> $chain
     *
     * @throws InvalidArgumentException when the chain cannot be encoded.
     */
    public static function encode(array $chain): string
    {
        try {
            return json_encode(
                array_values($chain),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('VoiceKit: unable to encode the effect chain: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Encodes an effect chain given as variadic descriptors.
     *
     * @param array<string, mixed> ...$effects
     *
     * @throws InvalidArgumentException
     */
    public static function chain(array ...$effects): string
    {
        return self::encode(array_values($effects));
    }

    /**
     * Accepts either an already-encoded chain or an array of descriptors.
     *
     * @param string|array<int, array<string, mixed>> $effects
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string|array $effects): string
    {
        return is_string($effects) ? $effects : self::encode($effects);
    }
}
