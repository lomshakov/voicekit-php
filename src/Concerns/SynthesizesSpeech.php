<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use Generator;
use VoiceKit\Effects;
use VoiceKit\Http\Request;
use VoiceKit\Http\StreamDownloader;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Speech synthesis: one-shot, chunked streaming and long-form async jobs.
 */
trait SynthesizesSpeech
{
    /**
     * Converts text to speech and returns the raw audio bytes.
     *
     * ```php
     * $audio = $client->synthesize(
     *     'Привет! Это синтез русской речи.',
     *     voice: 'preset_anna',
     *     format: 'mp3',
     * );
     * file_put_contents('speech.mp3', $audio);
     * ```
     *
     * @param string                    $text       UTF-8 text (max 5 000 characters per call).
     * @param string|null               $voice      preset id (`preset_anna`) or a cloned voice id.
     * @param string|null               $format     `mp3` (default), `wav` or `ogg`.
     * @param int|null                  $sampleRate output sample rate in Hz.
     * @param float|null                $speed      rate multiplier, `1.0` = normal.
     * @param float|null                $pitch      shift in semitones.
     * @param string|null               $emotion    preset emotion on supported voices.
     * @param bool|null                 $ssml       treat the input as SSML.
     * @param bool|null                 $putAccent  accepted for compatibility, no effect.
     * @param bool|null                 $putYo      accepted for compatibility, no effect.
     * @param bool|null                 $normalize  apply loudness normalisation.
     * @param string|null               $model      `standard` (default) or `premium` (Pro/Business).
     * @param string|null               $language   synthesis language for cloned voices.
     * @param string|array<int, array<string, mixed>>|null $effects effect chain, see {@see Effects}.
     *
     * @throws VoiceKitError
     */
    public function synthesize(
        string $text,
        ?string $voice = null,
        ?string $format = null,
        ?int $sampleRate = null,
        ?float $speed = null,
        ?float $pitch = null,
        ?string $emotion = null,
        ?bool $ssml = null,
        ?bool $putAccent = null,
        ?bool $putYo = null,
        ?bool $normalize = null,
        ?string $model = null,
        ?string $language = null,
        string|array|null $effects = null,
    ): string {
        $body = $this->synthesisBody(
            $text,
            $voice,
            $format,
            $sampleRate,
            $speed,
            $pitch,
            $emotion,
            $ssml,
            $putAccent,
            $putYo,
            $normalize,
            $model,
            $language,
            $effects,
        );

        return $this->request('POST', '/v1/synthesize', [], self::encodeJson($body))->body;
    }

    /**
     * Streams the synthesis of a long text (Pro/Business) instead of waiting for
     * the whole file: chunks arrive as the engine produces them.
     *
     * ```php
     * $out = fopen('long.mp3', 'wb');
     * foreach ($client->synthesizeStream($longText, voice: 'preset_anna') as $chunk) {
     *     fwrite($out, $chunk);
     * }
     * fclose($out);
     * ```
     *
     * @param string|array<int, array<string, mixed>>|null $effects
     *
     * @return Generator<int, string>
     *
     * @throws VoiceKitError `streaming_forbidden` on Free/Basic.
     */
    public function synthesizeStream(
        string $text,
        ?string $voice = null,
        ?string $format = null,
        ?int $sampleRate = null,
        ?float $speed = null,
        ?float $pitch = null,
        ?string $emotion = null,
        ?bool $ssml = null,
        ?string $model = null,
        ?string $language = null,
        string|array|null $effects = null,
    ): Generator {
        $body = $this->synthesisBody(
            $text,
            $voice,
            $format,
            $sampleRate,
            $speed,
            $pitch,
            $emotion,
            $ssml,
            null,
            null,
            null,
            $model,
            $language,
            $effects,
        );

        return StreamDownloader::download(
            new Request(
                'POST',
                $this->url('/v1/synthesize/stream'),
                ['Content-Type' => 'application/json', 'Accept' => '*/*'] + $this->defaultHeaders(),
                self::encodeJson($body),
            ),
            $this->timeout,
        );
    }

    /**
     * Queues a long-form synthesis job (audiobooks and similar).
     * Long-form synthesis always produces WAV.
     *
     * @throws VoiceKitError
     */
    public function synthesizeAsync(
        string $text,
        ?string $voice = null,
        ?string $format = null,
        ?int $sampleRate = null,
        ?float $speed = null,
        ?string $model = null,
        ?string $language = null,
        ?string $webhookUrl = null,
    ): Result {
        $body = ['text' => $text];
        $body = self::withOptional($body, 'voice', $voice);
        $body = self::withOptional($body, 'format', $format);
        $body = self::withOptional($body, 'sample_rate', $sampleRate);
        $body = self::withOptional($body, 'speed', $speed);
        $body = self::withOptional($body, 'model', $model);
        $body = self::withOptional($body, 'language', $language);

        return $this->post('/v1/synthesize/async', $body, ['webhookUrl' => $webhookUrl]);
    }

    /**
     * Polls a long-form synthesis job and returns its manifest.
     *
     * @throws VoiceKitError
     */
    public function getSynthesisJob(string $jobId): Result
    {
        return $this->get('/v1/synthesize/async/' . rawurlencode($jobId));
    }

    /**
     * Downloads the produced WAV of a completed job.
     *
     * @throws VoiceKitError
     */
    public function downloadSynthesisAudio(string $jobId): string
    {
        return $this->download('/v1/synthesize/async/' . rawurlencode($jobId) . '/audio');
    }

    /**
     * @param string|array<int, array<string, mixed>>|null $effects
     *
     * @return array<string, mixed>
     */
    private function synthesisBody(
        string $text,
        ?string $voice,
        ?string $format,
        ?int $sampleRate,
        ?float $speed,
        ?float $pitch,
        ?string $emotion,
        ?bool $ssml,
        ?bool $putAccent,
        ?bool $putYo,
        ?bool $normalize,
        ?string $model,
        ?string $language,
        string|array|null $effects,
    ): array {
        $body = ['text' => $text];
        $body = self::withOptional($body, 'voice', $voice);
        $body = self::withOptional($body, 'format', $format);
        $body = self::withOptional($body, 'sample_rate', $sampleRate);
        $body = self::withOptional($body, 'speed', $speed);
        $body = self::withOptional($body, 'pitch', $pitch);
        $body = self::withOptional($body, 'emotion', $emotion);
        $body = self::withOptional($body, 'model', $model);
        $body = self::withOptional($body, 'language', $language);
        $body = self::withOptional($body, 'effects', $effects === null ? null : Effects::normalize($effects));

        foreach (['ssml' => $ssml, 'put_accent' => $putAccent, 'put_yo' => $putYo, 'normalize' => $normalize] as $key => $value) {
            if ($value !== null) {
                $body[$key] = $value;
            }
        }

        return $body;
    }
}
