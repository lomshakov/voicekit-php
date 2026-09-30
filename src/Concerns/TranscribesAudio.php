<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\File;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Transcription (`/v1/transcribe`, `/v1/vad`).
 */
trait TranscribesAudio
{
    /**
     * Starts an asynchronous transcription job; poll it with
     * {@see TranscribesAudio::getTranscriptionJob()}.
     *
     * ```php
     * $job = $client->transcribe('call.mp3', language: 'ru', diarization: true);
     * ```
     *
     * @param File|string               $audio      audio file (up to 512 MB; `longForm` raises the limit to 4 h).
     * @param string|null               $language   ISO-639-1 hint, `null` = auto-detect.
     * @param bool                      $diarization label speakers (Basic and above).
     * @param list<string>              $keyterms   vocabulary hints for recognition.
     * @param bool                      $clean      denoise + normalize before recognition.
     * @param bool                      $longForm   long-form mode (up to 4 h / 512 MB).
     * @param string|null               $webhookUrl receives the result when the job completes.
     *
     * @throws VoiceKitError
     */
    public function transcribe(
        File|string $audio,
        ?string $language = null,
        bool $diarization = false,
        array $keyterms = [],
        bool $clean = false,
        bool $longForm = false,
        ?string $webhookUrl = null,
    ): Result {
        return $this->upload(
            '/v1/transcribe',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'language' => $language,
                'diarization' => $diarization ?: null,
                'clean' => $clean ?: null,
                'longForm' => $longForm ?: null,
                'webhookUrl' => $webhookUrl,
                'keyterms' => self::csv($keyterms) !== '' ? self::csv($keyterms) : null,
            ]),
        );
    }

    /**
     * Transcribes a short file (up to 3 minutes) synchronously.
     *
     * @param list<string> $keyterms
     *
     * @throws VoiceKitError
     */
    public function transcribeSync(
        File|string $audio,
        ?string $language = null,
        bool $diarization = false,
        array $keyterms = [],
        bool $clean = false,
    ): Result {
        return $this->upload(
            '/v1/transcribe/sync',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'language' => $language,
                'diarization' => $diarization ?: null,
                'clean' => $clean ?: null,
                'keyterms' => self::csv($keyterms) !== '' ? self::csv($keyterms) : null,
            ]),
        );
    }

    /**
     * Polls a transcription job.
     *
     * @throws VoiceKitError
     */
    public function getTranscriptionJob(string $jobId): Result
    {
        return $this->get('/v1/transcribe/' . rawurlencode($jobId));
    }

    /**
     * Downloads WebVTT/SRT captions of a completed transcription job.
     *
     * @param string      $format         `vtt` (default) or `srt`.
     * @param string|null $targetLanguage translate the captions, e.g. `en`.
     * @param bool        $hotMarks       mark fast or unclear speech.
     *
     * @throws VoiceKitError `subtitles_unavailable` (409) while the job is running.
     */
    public function subtitles(
        string $jobId,
        string $format = 'vtt',
        ?string $targetLanguage = null,
        bool $hotMarks = false,
    ): string {
        return $this->text('/v1/transcribe/' . rawurlencode($jobId) . '/subtitles', [
            'format' => $format,
            'target_language' => $targetLanguage,
            'hot_marks' => $hotMarks ? 'true' : null,
        ]);
    }

    /**
     * Translates a completed transcript, keeping timestamps and speakers.
     * Billed against the plan's LLM token budget (Pro/Business).
     *
     * @throws VoiceKitError
     */
    public function translateTranscript(string $jobId, string $targetLanguage): Result
    {
        return $this->post('/v1/transcribe/' . rawurlencode($jobId) . '/translate', [
            'target_language' => $targetLanguage,
        ]);
    }

    /**
     * Detects speech segments in a file with Silero VAD.
     *
     * @throws VoiceKitError
     */
    public function vad(File|string $audio): Result
    {
        return $this->upload('/v1/vad', self::singleFilePart('audio', $audio));
    }
}
