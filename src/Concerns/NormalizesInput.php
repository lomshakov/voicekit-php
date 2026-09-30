<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\File;

/**
 * Shared input helpers used by the endpoint traits.
 *
 * @internal
 */
trait NormalizesInput
{
    /**
     * Adds a value unless it is `null`, an empty string or an empty list.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private static function withOptional(array $body, string $key, mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return $body;
        }

        $body[$key] = $value;

        return $body;
    }

    /**
     * Renders a string list as the comma-separated form the API expects
     * (`keyterms`, `profile_ids`).
     *
     * @param list<string> $values
     */
    private static function csv(array $values): string
    {
        $filtered = [];
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $filtered[] = $trimmed;
            }
        }

        return implode(',', $filtered);
    }

    /**
     * Renders scalars as the strings a multipart form needs.
     *
     * @param array<string, bool|float|int|string|null> $values
     *
     * @return array<string, string>
     */
    private static function formFields(array $values): array
    {
        $fields = [];
        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $fields[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $fields;
    }

    /**
     * Wraps one or more files into the `[field, File]` pairs a multipart body needs.
     *
     * @param File|string|array<int, File|string> $files
     *
     * @return list<array{0: string, 1: File}>
     */
    private static function fileParts(string $field, File|string|array $files): array
    {
        $parts = [];
        foreach (File::collect($files) as $file) {
            $parts[] = [$field, $file];
        }

        return $parts;
    }

    /**
     * Wraps a single file into the `[field, File]` pair a multipart body needs.
     *
     * @return list<array{0: string, 1: File}>
     */
    private static function singleFilePart(string $field, File|string $file): array
    {
        return [[$field, File::of($file)]];
    }
}
