<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VoiceKit\Streaming\WebSocket;
use VoiceKit\Tests\Support\WebSocketFixture;
use VoiceKit\VoiceKitError;

final class WebSocketTest extends TestCase
{
    /** @var list<resource> */
    private array $sockets = [];

    protected function tearDown(): void
    {
        foreach ($this->sockets as $socket) {
            WebSocketFixture::close($socket);
        }

        $this->sockets = [];
    }

    /**
     * @return array{0: WebSocket, 1: resource} the socket plus the server end.
     */
    private function socketPair(): array
    {
        [$clientEnd, $serverEnd] = WebSocketFixture::pair();
        $this->sockets[] = $clientEnd;
        $this->sockets[] = $serverEnd;

        return [WebSocket::fromStream($clientEnd), $serverEnd];
    }

    public function test_handshake_writes_the_upgrade_request_and_validates_the_response(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $key = $socket->sendHandshake('ws://api.test/v1/transcribe/stream?language=ru', ['X-Api-Key' => 'rtt_test']);
        $request = WebSocketFixture::read($serverEnd);

        $this->assertStringContainsString('GET /v1/transcribe/stream?language=ru HTTP/1.1', $request);
        $this->assertStringContainsString('Host: api.test', $request);
        $this->assertStringContainsString('Upgrade: websocket', $request);
        $this->assertStringContainsString('Connection: Upgrade', $request);
        $this->assertStringContainsString('Sec-WebSocket-Version: 13', $request);
        $this->assertStringContainsString('Sec-WebSocket-Key: ' . $key, $request);
        $this->assertStringContainsString('X-Api-Key: rtt_test', $request);

        WebSocketFixture::write($serverEnd, WebSocketFixture::handshakeResponse($key));
        $socket->verifyHandshake($key);

        $this->assertTrue($socket->isOpen());
    }

    public function test_handshake_includes_a_non_default_port(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $key = $socket->sendHandshake('wss://api.test:8443/v1/vad/stream');

        $this->assertStringContainsString('Host: api.test:8443', WebSocketFixture::read($serverEnd));
        $this->assertNotSame('', $key);
    }

    public function test_handshake_rejects_a_non_101_response(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $key = $socket->sendHandshake('ws://api.test/v1/transcribe/stream');
        WebSocketFixture::write($serverEnd, "HTTP/1.1 403 Forbidden\r\n\r\n{\"code\":\"streaming_forbidden\"}");

        try {
            $socket->verifyHandshake($key);
            $this->fail('Expected a VoiceKitError.');
        } catch (VoiceKitError $error) {
            $this->assertSame(403, $error->statusCode());
            $this->assertSame('streaming_forbidden', $error->errorCode());
        }
    }

    public function test_handshake_rejects_a_bad_accept_header(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $key = $socket->sendHandshake('ws://api.test/v1/transcribe/stream');
        WebSocketFixture::write($serverEnd, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nSec-WebSocket-Accept: nonsense\r\n\r\n");

        $this->expectException(VoiceKitError::class);
        $this->expectExceptionMessage('Sec-WebSocket-Accept');

        $socket->verifyHandshake($key);
    }

    public function test_receive_returns_a_server_text_frame(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('{"type":"session"}'));

        $this->assertSame('{"type":"session"}', $socket->receive());
    }

    public function test_receive_reassembles_fragmented_frames(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('{"type":', WebSocket::OPCODE_TEXT, false));
        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('"final"}', WebSocket::OPCODE_CONTINUATION, true));

        $this->assertSame('{"type":"final"}', $socket->receive());
    }

    public function test_receive_answers_pings_with_pongs(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('ping', WebSocket::OPCODE_PING));
        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('payload'));

        $this->assertSame('payload', $socket->receive());

        $frame = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));
        $this->assertNotNull($frame);
        $this->assertSame(WebSocket::OPCODE_PONG, $frame['opcode']);
        $this->assertSame('ping', $frame['payload']);
    }

    public function test_receive_returns_null_after_a_close_frame(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame(pack('n', 1000), WebSocket::OPCODE_CLOSE));

        $this->assertNull($socket->receive());
    }

    public function test_receive_returns_null_when_the_peer_disconnects(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        WebSocketFixture::close($serverEnd);

        $this->assertNull($socket->receive());
    }

    public function test_server_frames_with_extended_lengths_are_read(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $payload = str_repeat('ё', 400);
        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame($payload));

        $this->assertSame($payload, $socket->receive());
    }

    public function test_send_audio_writes_a_masked_binary_frame(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $socket->sendBinary("\x01\x02\x03\x04");

        $frame = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($frame);
        $this->assertSame(WebSocket::OPCODE_BINARY, $frame['opcode']);
        $this->assertSame("\x01\x02\x03\x04", $frame['payload']);
    }

    public function test_send_text_and_close_write_the_expected_opcodes(): void
    {
        [$socket, $serverEnd] = $this->socketPair();

        $socket->sendText('{"type":"stop"}');
        $text = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($text);
        $this->assertSame(WebSocket::OPCODE_TEXT, $text['opcode']);
        $this->assertSame('{"type":"stop"}', $text['payload']);

        $socket->close();
        $close = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($close);
        $this->assertSame(WebSocket::OPCODE_CLOSE, $close['opcode']);
        $this->assertFalse($socket->isOpen());
    }

    public function test_encode_and_decode_handle_extended_lengths(): void
    {
        $medium = str_repeat('a', 200);
        $large = str_repeat('b', 70000);

        foreach ([$medium, $large] as $payload) {
            $encoded = WebSocket::encode($payload, WebSocket::OPCODE_TEXT);
            $decoded = WebSocket::decode($encoded);

            $this->assertNotNull($decoded);
            $this->assertSame($payload, $decoded['payload']);
            $this->assertSame(WebSocket::OPCODE_TEXT, $decoded['opcode']);
        }
    }

    public function test_decode_returns_null_for_incomplete_frames(): void
    {
        $this->assertNull(WebSocket::decode("\x81"));
        $this->assertNull(WebSocket::decode(substr(WebSocket::encode('payload', WebSocket::OPCODE_TEXT), 0, 4)));
    }

    public function test_masked_server_frames_are_unmasked(): void
    {
        $decoded = WebSocket::decode(WebSocket::encode('hello', WebSocket::OPCODE_TEXT));

        $this->assertNotNull($decoded);
        $this->assertSame('hello', $decoded['payload']);
    }

    public function test_from_http_url_switches_the_scheme(): void
    {
        $this->assertSame('wss://ttsapi.ru/v1/vad/stream', WebSocket::fromHttpUrl('https://ttsapi.ru/v1/vad/stream'));
        $this->assertSame('ws://localhost:8080/v1/vad/stream', WebSocket::fromHttpUrl('http://localhost:8080/v1/vad/stream'));
        $this->assertSame('ws://localhost/v1/vad/stream', WebSocket::fromHttpUrl('ws://localhost/v1/vad/stream'));
    }

    public function test_connect_reports_unreachable_hosts(): void
    {
        $this->expectException(VoiceKitError::class);

        WebSocket::connect('ws://127.0.0.1:1/v1/vad/stream', [], 1.0);
    }
}
