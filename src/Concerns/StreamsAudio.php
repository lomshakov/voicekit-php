<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Streaming\StreamSession;
use VoiceKit\Streaming\WebSocket;

/**
 * WebSocket streaming sessions (`/v1/transcribe/stream`, `/v1/vad/stream`) —
 * Pro/Business.
 */
trait StreamsAudio
{
    /**
     * Opens a streaming transcription session. Send raw PCM16 audio, then
     * `stop()` and read events until `receive()` returns `null`.
     *
     * ```php
     * $stream = $client->transcribeStream(language: 'ru', keyterms: ['диагноз']);
     * $stream->sendAudio($pcm16);
     * $stream->stop();
     * while (($event = $stream->receive()) !== null) {
     *     echo $event->str('type'), PHP_EOL;
     * }
     * $stream->close();
     * ```
     *
     * @param list<string> $keyterms vocabulary hints (joined into `keyterms`).
     * @param bool|null    $interim  request interim results; `null` = server default.
     */
    public function transcribeStream(?string $language = null, array $keyterms = [], ?bool $interim = null): StreamSession
    {
        $query = [];
        if ($language !== null && $language !== '') {
            $query['language'] = $language;
        }

        $terms = self::csv($keyterms);
        if ($terms !== '') {
            $query['keyterms'] = $terms;
        }

        if ($interim !== null) {
            $query['interim'] = $interim ? 'true' : 'false';
        }

        return $this->openStream('/v1/transcribe/stream', $query);
    }

    /**
     * Opens a streaming voice-activity session: turn detection only, no
     * recognition.
     */
    public function vadStream(): StreamSession
    {
        return $this->openStream('/v1/vad/stream', []);
    }

    /**
     * @param array<string, string> $query
     */
    private function openStream(string $path, array $query): StreamSession
    {
        $url = WebSocket::fromHttpUrl($this->url($path, $query));

        return new StreamSession(WebSocket::connect($url, $this->defaultHeaders(), $this->timeout));
    }
}
