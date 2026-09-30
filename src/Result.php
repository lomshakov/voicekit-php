<?php

declare(strict_types=1);

namespace VoiceKit;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;

/**
 * A decoded JSON object returned by the API.
 *
 * The API evolves, so the SDK hands back dynamic objects with typed accessors
 * instead of freezing every field into a class. Nested objects become
 * {@see Result} instances too, so chains stay type-safe:
 *
 * ```php
 * $result = $client->getTranscriptionJob($jobId);
 * $text = $result->str('text');
 * $seconds = $result->float('duration_seconds');
 * foreach ($result->list('segments') as $segment) {
 *     echo $segment->float('start'), ' ', $segment->str('text'), PHP_EOL;
 * }
 *
 * // Array access works as well:
 * $status = $result['status'];
 * ```
 *
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
final class Result implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Decodes a JSON object body. An empty body yields an empty result.
     *
     * @throws VoiceKitError when the body is not a JSON object.
     */
    public static function fromJson(string $json): self
    {
        if (trim($json) === '') {
            return new self();
        }

        $decoded = json_decode($json, true);

        if ($decoded === []) {
            // `{}` and `[]` decode to the same empty array; both are valid here.
            return new self();
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new VoiceKitError('VoiceKit: expected a JSON object, got: ' . substr($json, 0, 200));
        }

        /** @var array<string, mixed> $decoded */
        return new self($decoded);
    }

    /**
     * Wraps an associative array in a {@see Result}.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Converts raw JSON values: objects become {@see Result}, lists stay lists,
     * scalars pass through unchanged.
     */
    public static function wrap(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::wrap($item), $value);
        }

        /** @var array<string, mixed> $value */
        return new self($value);
    }

    /**
     * The underlying array, untouched (nested objects stay arrays).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Whether the key is present (a `null` value still counts as present).
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * The raw value at `$key`, with nested objects wrapped in {@see Result}.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        return self::wrap($this->data[$key]);
    }

    /**
     * A string value (`''` when absent).
     */
    public function str(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * An integer value (`0` when absent).
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * A floating-point value (`0.0` when absent).
     */
    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->data[$key] ?? null;

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * A boolean value (`false` when absent).
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        return $default;
    }

    /**
     * A nested object value, or `null` when absent or not an object.
     */
    public function obj(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return new self($value);
    }

    /**
     * A nested list of objects.
     *
     * @return list<Result>
     */
    public function list(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item) && !array_is_list($item)) {
                /** @var array<string, mixed> $item */
                $items[] = new self($item);
            }
        }

        return $items;
    }

    /**
     * A nested list of strings.
     *
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The number of top-level keys.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return Traversable<string, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->get($offset) : null;
    }

    /**
     * @throws LogicException results are read-only.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('VoiceKit results are read-only.');
    }

    /**
     * @throws LogicException results are read-only.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('VoiceKit results are read-only.');
    }
}
