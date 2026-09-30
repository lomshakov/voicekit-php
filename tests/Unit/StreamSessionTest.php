<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VoiceKit\Streaming\StreamSession;
use VoiceKit\Streaming\WebSocket;
use VoiceKit\Tests\Support\WebSocketFixture;

final class StreamSessionTest extends TestCase
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
     * @return array{0: StreamSession, 1: resource} the session plus the server end.
     */
    private function session(): array
    {
        [$clientEnd, $serverEnd] = WebSocketFixture::pair();
        $this->sockets[] = $clientEnd;
        $this->sockets[] = $serverEnd;

        return [new StreamSession(WebSocket::fromStream($clientEnd)), $serverEnd];
    }

    public function test_send_audio_writes_raw_pcm16(): void
    {
        [$session, $serverEnd] = $this->session();

        $session->sendAudio("\x00\x01");

        $frame = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($frame);
        $this->assertSame(WebSocket::OPCODE_BINARY, $frame['opcode']);
        $this->assertSame("\x00\x01", $frame['payload']);
    }

    public function test_stop_sends_a_control_message(): void
    {
        [$session, $serverEnd] = $this->session();

        $session->stop();

        $frame = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($frame);
        $this->assertSame('{"type":"stop"}', $frame['payload']);
    }

    public function test_send_json_accepts_raw_strings(): void
    {
        [$session, $serverEnd] = $this->session();

        $session->sendJson('{"type":"ping"}');

        $frame = WebSocket::decode(WebSocketFixture::readAvailable($serverEnd));

        $this->assertNotNull($frame);
        $this->assertSame('{"type":"ping"}', $frame['payload']);
    }

    public function test_receive_decodes_events_until_the_session_closes(): void
    {
        [$session, $serverEnd] = $this->session();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('{"type":"session","session_id":"s_1"}'));
        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('{"type":"final","text":"Привет","segments":[{"start":0.5}]}'));

        $sessionEvent = $session->receive();
        $this->assertNotNull($sessionEvent);
        $this->assertSame('s_1', $sessionEvent->str('session_id'));
        $this->assertTrue($session->isOpen());

        $final = $session->receive();
        $this->assertNotNull($final);
        $this->assertSame('Привет', $final->str('text'));
        $this->assertSame(0.5, $final->list('segments')[0]->float('start'));

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame(pack('n', 1000), WebSocket::OPCODE_CLOSE));
        $this->assertNull($session->receive());
        $this->assertFalse($session->isOpen());
    }

    public function test_receive_wraps_non_json_payloads(): void
    {
        [$session, $serverEnd] = $this->session();

        WebSocketFixture::write($serverEnd, WebSocketFixture::serverFrame('not json'));

        $event = $session->receive();

        $this->assertNotNull($event);
        $this->assertSame('not json', $event->str('raw'));
    }

    public function test_close_is_idempotent(): void
    {
        [$session] = $this->session();

        $session->close();
        $session->close();

        $this->assertFalse($session->isOpen());
    }
}
