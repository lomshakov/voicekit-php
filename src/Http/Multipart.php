<?php

declare(strict_types=1);

namespace VoiceKit\Http;

use VoiceKit\File;
use VoiceKit\VoiceKitError;

/**
 * Builds `multipart/form-data` bodies without external dependencies.
 *
 * @internal
 */
final class Multipart
{
    /**
     * @param list<array{0: string, 1: File}> $files  field name plus payload.
     * @param array<string, string>           $fields plain form fields.
     *
     * @return array{0: string, 1: string} content type plus encoded body.
     */
    public static function build(array $files, array $fields, ?string $boundary = null): array
    {
        $boundary ??= 'voicekit-' . bin2hex(random_bytes(16));
        $body = '';

        foreach ($files as [$field, $file]) {
            $body .= '--' . $boundary . "\r\n";
            $body .= sprintf(
                "Content-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\n",
                self::escape($field),
                self::escape($file->name()),
            );
            $body .= 'Content-Type: ' . $file->contentType() . "\r\n\r\n";
            $body .= $file->contents() . "\r\n";
        }

        $keys = array_keys($fields);
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            $body .= '--' . $boundary . "\r\n";
            $body .= sprintf("Content-Disposition: form-data; name=\"%s\"\r\n\r\n", self::escape($key));
            $body .= $fields[$key] . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        return ['multipart/form-data; boundary=' . $boundary, $body];
    }

    /**
     * Strips characters that would break out of the header field.
     */
    private static function escape(string $value): string
    {
        $escaped = preg_replace('/[\r\n"]/', '', $value) ?? $value;

        if ($escaped === '') {
            throw new VoiceKitError('VoiceKit: multipart field names must not be empty.');
        }

        return $escaped;
    }
}
