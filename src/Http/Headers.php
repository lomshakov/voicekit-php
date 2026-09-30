<?php

declare(strict_types=1);

namespace VoiceKit\Http;

/**
 * Parses the header block produced by PHP's HTTP stream wrappers
 * (`$http_response_header`) into a status code and a header map.
 *
 * @internal
 */
final class Headers
{
    /**
     * @param array<int, string> $rawHeaders
     *
     * @return array{status:int, headers:array<string, string>}
     */
    public static function parse(array $rawHeaders): array
    {
        $status = 0;
        $headers = [];

        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                // A redirect chain emits several status lines: the last one wins.
                $status = (int) $matches[1];
                $headers = [];

                continue;
            }

            $separator = strpos($line, ':');
            if ($separator === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $separator)));
            $headers[$name] = trim(substr($line, $separator + 1));
        }

        return ['status' => $status, 'headers' => $headers];
    }
}
