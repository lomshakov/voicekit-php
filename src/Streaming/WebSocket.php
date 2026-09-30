<?php

declare(strict_types=1);

namespace VoiceKit\Streaming;

use VoiceKit\VoiceKitError;

/**
 * Minimal RFC 6455 WebSocket client built on PHP streams — no extensions and no
 * Composer packages required.
 *
 * It is used by {@see \VoiceKit\VoiceKitClient::transcribeStream()} and
 * {@see \VoiceKit\VoiceKitClient::vadStream()}; most callers should use
 * {@see StreamSession} instead.
 */
final class WebSocket
{
    public const OPCODE_CONTINUATION = 0x0;

    public const OPCODE_TEXT = 0x1;

    public const OPCODE_BINARY = 0x2;

    public const OPCODE_CLOSE = 0x8;

    public const OPCODE_PING = 0x9;

    public const OPCODE_PONG = 0xA;

    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private const HANDSHAKE_TIMEOUT_SECONDS = 30.0;

    /** @var resource|null */
    private $stream;

    private string $buffer = '';

    private bool $open = false;

    /**
     * @param resource $stream
     */
    private function __construct($stream)
    {
        $this->stream = $stream;
        $this->open = true;
    }

    /**
     * Opens a `ws://` or `wss://` connection and performs the upgrade handshake.
     *
     * @param array<string, string> $headers extra headers (the API key goes here).
     *
     * @throws VoiceKitError
     */
    public static function connect(string $url, array $headers = [], float $timeout = 30.0): self
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw VoiceKitError::fromTransport(sprintf('invalid WebSocket URL "%s".', $url));
        }

        $secure = ($parts['scheme'] ?? 'ws') === 'wss';
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : ($secure ? 443 : 80);
        $transport = $secure ? 'ssl' : 'tcp';

        $contextOptions = [];
        if ($secure) {
            $contextOptions['ssl'] = [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ];
        }

        $errorCode = 0;
        $errorMessage = '';

        $stream = @stream_socket_client(
            sprintf('%s://%s:%d', $transport, $host, $port),
            $errorCode,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions),
        );

        if ($stream === false) {
            throw VoiceKitError::fromTransport(sprintf(
                'unable to connect to %s://%s:%d (%d: %s).',
                $transport,
                $host,
                $port,
                $errorCode,
                $errorMessage !== '' ? $errorMessage : 'unknown error',
            ));
        }

        $seconds = (int) $timeout;
        stream_set_timeout($stream, $seconds, (int) (($timeout - $seconds) * 1_000_000));

        $socket = new self($stream);

        try {
            $key = $socket->sendHandshake($url, $headers);
            $socket->verifyHandshake($key);
        } catch (VoiceKitError $error) {
            $socket->close();

            throw $error;
        }

        return $socket;
    }

    /**
     * Wraps an already-connected stream (used by the test-suite).
     *
     * @param resource $stream
     */
    public static function fromStream($stream): self
    {
        return new self($stream);
    }

    /**
     * Writes the upgrade request and returns the generated `Sec-WebSocket-Key`.
     *
     * @param array<string, string> $headers
     *
     * @throws VoiceKitError
     */
    public function sendHandshake(string $url, array $headers = []): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw VoiceKitError::fromTransport(sprintf('invalid WebSocket URL "%s".', $url));
        }

        $secure = ($parts['scheme'] ?? 'ws') === 'wss';
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : ($secure ? 443 : 80);
        $target = (string) ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . (string) $parts['query'] : '');
        $defaultPort = $secure ? 443 : 80;

        $key = base64_encode(random_bytes(16));

        $lines = [
            sprintf('GET %s HTTP/1.1', $target),
            sprintf('Host: %s%s', $host, $port === $defaultPort ? '' : ':' . $port),
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: ' . $key,
            'Sec-WebSocket-Version: 13',
        ];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $this->writeRaw(implode("\r\n", $lines) . "\r\n\r\n");

        return $key;
    }

    /**
     * Reads and validates the upgrade response.
     *
     * @throws VoiceKitError
     */
    public function verifyHandshake(string $key): void
    {
        $block = $this->readHeaderBlock();
        $separator = strpos($block, "\r\n\r\n");
        $head = $separator === false ? $block : substr($block, 0, $separator);
        $this->buffer = $separator === false ? '' : substr($block, $separator + 4);

        $lines = explode("\r\n", $head);
        $statusLine = (string) array_shift($lines);
        $status = preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $matches) === 1 ? (int) $matches[1] : 0;

        $parsed = [];
        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsed[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        if ($status !== 101) {
            throw VoiceKitError::fromResponse($status > 0 ? $status : 502, trim($this->buffer));
        }

        $expected = base64_encode(sha1($key . self::GUID, true));
        if (($parsed['sec-websocket-accept'] ?? '') !== $expected) {
            throw VoiceKitError::fromTransport('the WebSocket handshake returned an invalid Sec-WebSocket-Accept header.');
        }
    }

    /**
     * Converts an `http(s)://` API URL into its `ws(s)://` equivalent.
     */
    public static function fromHttpUrl(string $url): string
    {
        if (str_starts_with($url, 'https://')) {
            return 'wss://' . substr($url, 8);
        }

        if (str_starts_with($url, 'http://')) {
            return 'ws://' . substr($url, 7);
        }

        return $url;
    }

    /**
     * The `Sec-WebSocket-Accept` value a compliant server must echo for a key.
     */
    public static function acceptFor(string $key): string
    {
        return base64_encode(sha1($key . self::GUID, true));
    }

    /**
     * Sends a UTF-8 text frame.
     *
     * @throws VoiceKitError
     */
    public function sendText(string $payload): void
    {
        $this->writeRaw(self::encode($payload, self::OPCODE_TEXT));
    }

    /**
     * Sends a binary frame (raw PCM16, 16 kHz, mono, little-endian).
     *
     * @throws VoiceKitError
     */
    public function sendBinary(string $payload): void
    {
        $this->writeRaw(self::encode($payload, self::OPCODE_BINARY));
    }

    /**
     * Reads the next message, transparently answering pings and reassembling
     * fragmented frames.
     *
     * @return string|null the message payload, or `null` once the server closed
     *                     the session.
     *
     * @throws VoiceKitError
     */
    public function receive(): ?string
    {
        $payload = '';

        while (true) {
            $frame = $this->readFrame();
            if ($frame === null) {
                $this->open = false;

                return null;
            }

            switch ($frame['opcode']) {
                case self::OPCODE_CLOSE:
                    $this->open = false;

                    return null;

                case self::OPCODE_PING:
                    $this->writeRaw(self::encode($frame['payload'], self::OPCODE_PONG));

                    continue 2;

                case self::OPCODE_PONG:
                    continue 2;

                case self::OPCODE_CONTINUATION:
                    $payload .= $frame['payload'];

                    break;

                default:
                    $payload = $frame['payload'];
            }

            if ($frame['fin']) {
                return $payload;
            }
        }
    }

    /**
     * Sends a close frame with a normal-closure status and closes the socket.
     */
    public function close(int $code = 1000, string $reason = ''): void
    {
        if (!$this->open) {
            return;
        }

        $this->open = false;
        $stream = $this->stream;

        if (is_resource($stream)) {
            try {
                $this->writeRaw(self::encode(pack('n', $code) . $reason, self::OPCODE_CLOSE));
            } catch (VoiceKitError) {
                // The peer is already gone; closing the socket below is enough.
            }

            fclose($stream);
        }

        $this->stream = null;
    }

    public function isOpen(): bool
    {
        return $this->open && is_resource($this->stream);
    }

    /**
     * Encodes a client frame (always masked, as RFC 6455 requires).
     */
    public static function encode(string $payload, int $opcode, bool $fin = true): string
    {
        $length = strlen($payload);
        $header = chr(($fin ? 0x80 : 0x00) | ($opcode & 0x0F));

        if ($length < 126) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $header .= chr(0x80 | 127) . pack('J', $length);
        }

        $mask = random_bytes(4);
        $masked = $payload;

        for ($index = 0; $index < $length; $index++) {
            $masked[$index] = $payload[$index] ^ $mask[$index % 4];
        }

        return $header . $mask . $masked;
    }

    /**
     * Decodes one masked-or-unmasked frame. Used by the test-suite.
     *
     * @return array{fin: bool, opcode: int, payload: string}|null `null` when the
     *                                                             buffer holds an incomplete frame.
     */
    public static function decode(string $bytes, int $offset = 0): ?array
    {
        if (strlen($bytes) - $offset < 2) {
            return null;
        }

        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);
        $fin = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;
        $cursor = $offset + 2;

        if ($length === 126) {
            if (strlen($bytes) - $cursor < 2) {
                return null;
            }

            $length = self::unpackLength('n', substr($bytes, $cursor, 2));
            $cursor += 2;
        } elseif ($length === 127) {
            if (strlen($bytes) - $cursor < 8) {
                return null;
            }

            $length = self::unpackLength('J', substr($bytes, $cursor, 8));
            $cursor += 8;
        }

        $mask = '';
        if ($masked) {
            if (strlen($bytes) - $cursor < 4) {
                return null;
            }

            $mask = substr($bytes, $cursor, 4);
            $cursor += 4;
        }

        if (strlen($bytes) - $cursor < $length) {
            return null;
        }

        $payload = substr($bytes, $cursor, $length);

        for ($index = 0; $index < $length && $mask !== ''; $index++) {
            $payload[$index] = $payload[$index] ^ $mask[$index % 4];
        }

        return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * @return array{fin: bool, opcode: int, payload: string}|null
     *
     * @throws VoiceKitError
     */
    private function readFrame(): ?array
    {
        $header = $this->read(2);
        if ($header === null) {
            return null;
        }

        $first = ord($header[0]);
        $second = ord($header[1]);
        $fin = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;

        if ($length === 126) {
            $extended = $this->read(2);
            if ($extended === null) {
                return null;
            }

            $length = self::unpackLength('n', $extended);
        } elseif ($length === 127) {
            $extended = $this->read(8);
            if ($extended === null) {
                return null;
            }

            $length = self::unpackLength('J', $extended);
        }

        $mask = null;
        if ($masked) {
            $mask = $this->read(4);
            if ($mask === null) {
                return null;
            }
        }

        $payload = '';
        if ($length > 0) {
            $read = $this->read($length);
            if ($read === null || strlen($read) !== $length) {
                return null;
            }

            $payload = $read;
        }

        if ($mask !== null && $mask !== '') {
            for ($index = 0; $index < $length; $index++) {
                $payload[$index] = $payload[$index] ^ $mask[$index % 4];
            }
        }

        return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * @throws VoiceKitError
     */
    private function writeRaw(string $bytes): void
    {
        if (!is_resource($this->stream)) {
            throw VoiceKitError::fromTransport('the WebSocket connection is closed.');
        }

        $written = @fwrite($this->stream, $bytes);
        if ($written === false || $written !== strlen($bytes)) {
            throw VoiceKitError::fromTransport('the WebSocket frame could not be written.');
        }
    }

    /**
     * Reads exactly `$length` bytes, or `null` when the peer closed the socket.
     *
     * @throws VoiceKitError when the read times out.
     */
    private function read(int $length): ?string
    {
        while (strlen($this->buffer) < $length) {
            if (!is_resource($this->stream)) {
                return null;
            }

            $chunk = @fread($this->stream, max($length - strlen($this->buffer), 8192));
            if ($chunk === false || $chunk === '') {
                if ($this->timedOut()) {
                    throw VoiceKitError::fromTransport('the WebSocket read timed out.');
                }

                return null;
            }

            $this->buffer .= $chunk;
        }

        $slice = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);

        return $slice;
    }

    /**
     * @throws VoiceKitError
     */
    private function readHeaderBlock(): string
    {
        $block = '';
        $deadline = microtime(true) + self::HANDSHAKE_TIMEOUT_SECONDS;

        while (!str_contains($block, "\r\n\r\n")) {
            if (!is_resource($this->stream)) {
                throw VoiceKitError::fromTransport('the WebSocket handshake was interrupted.');
            }

            $chunk = @fread($this->stream, 1024);
            if ($chunk === false || $chunk === '') {
                if ($this->timedOut() && microtime(true) < $deadline) {
                    continue;
                }

                throw VoiceKitError::fromTransport('the WebSocket handshake failed.');
            }

            $block .= $chunk;
        }

        return $block;
    }

    private function timedOut(): bool
    {
        $stream = $this->stream;

        if (!is_resource($stream)) {
            return false;
        }

        $meta = stream_get_meta_data($stream);

        return $meta['timed_out'] === true;
    }

    /**
     * Decodes a 16- or 64-bit big-endian frame length.
     */
    private static function unpackLength(string $format, string $bytes): int
    {
        $unpacked = unpack($format, $bytes);

        return $unpacked === false ? 0 : (int) $unpacked[1];
    }
}
