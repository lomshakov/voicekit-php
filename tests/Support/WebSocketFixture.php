<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Support;

use RuntimeException;
use VoiceKit\Streaming\WebSocket;

/**
 * Helpers for driving {@see WebSocket} over an in-process socket pair, so the
 * frame codec and the handshake are covered without a network server.
 */
final class WebSocketFixture
{
    /**
     * @return array{0: resource, 1: resource} the client and server ends.
     */
    public static function pair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('stream_socket_pair() is not available.');
        }

        return [$pair[0], $pair[1]];
    }

    /**
     * A server frame (never masked).
     */
    public static function serverFrame(string $payload, int $opcode = WebSocket::OPCODE_TEXT, bool $fin = true): string
    {
        $length = strlen($payload);
        $header = chr(($fin ? 0x80 : 0x00) | $opcode);

        if ($length < 126) {
            $header .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }

        return $header . $payload;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function handshakeResponse(string $key, int $status = 101, array $headers = []): string
    {
        $lines = [$status === 101 ? 'HTTP/1.1 101 Switching Protocols' : sprintf('HTTP/1.1 %d Error', $status)];

        if ($status === 101) {
            $lines[] = 'Upgrade: websocket';
            $lines[] = 'Connection: Upgrade';
            $lines[] = 'Sec-WebSocket-Accept: ' . WebSocket::acceptFor($key);
        }

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines) . "\r\n\r\n";
    }

    /**
     * Reads whatever the peer has written so far.
     *
     * @param resource $stream
     */
    public static function read($stream, int $length = 8192): string
    {
        stream_set_blocking($stream, true);
        $chunk = fread($stream, max(1, $length));

        return $chunk === false ? '' : $chunk;
    }

    /**
     * Reads only the bytes already buffered (never blocks).
     *
     * @param resource $stream
     */
    public static function readAvailable($stream, int $length = 8192): string
    {
        stream_set_blocking($stream, false);
        $chunk = fread($stream, max(1, $length));

        return $chunk === false ? '' : $chunk;
    }

    /**
     * @param resource $stream
     */
    public static function write($stream, string $bytes): void
    {
        fwrite($stream, $bytes);
    }

    /**
     * @param resource $stream
     */
    public static function close($stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }
}
