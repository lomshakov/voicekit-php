<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Effects;
use VoiceKit\File;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Audio/video effects and audio cleaning (`/v1/audio/effects`, `/v1/video/effects`,
 * `/v1/audio/clean`). All of them are asynchronous jobs.
 */
trait ProcessesMediaEffects
{
    /**
     * Starts an audio-effects job; poll it with
     * {@see ProcessesMediaEffects::getAudioEffectsJob()} and fetch the result with
     * {@see ProcessesMediaEffects::downloadAudioEffects()}.
     *
     * ```php
     * $job = $client->applyAudioEffects('voice.wav', [
     *     ['type' => 'reverb', 'room_size' => 0.5],
     *     ['type' => 'compressor', 'ratio' => 3],
     * ]);
     * $result = $client->downloadAudioEffects($job->str('job_id'));
     * ```
     *
     * @param array<int, array<string, mixed>> $effects      effect chain, applied in order.
     * @param string|null                      $outputFormat `wav` (default), `mp3` or `ogg`.
     *
     * @throws VoiceKitError
     */
    public function applyAudioEffects(
        File|string $audio,
        array $effects = [],
        ?string $outputFormat = null,
        ?string $webhookUrl = null,
    ): Result {
        return $this->upload(
            '/v1/audio/effects',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'effects' => Effects::encode($effects),
                'output_format' => $outputFormat,
                'webhookUrl' => $webhookUrl,
            ]),
        );
    }

    /**
     * Polls an audio-effects job.
     *
     * @throws VoiceKitError
     */
    public function getAudioEffectsJob(string $jobId): Result
    {
        return $this->get('/v1/audio/effects/' . rawurlencode($jobId));
    }

    /**
     * Downloads the processed audio of a completed job.
     *
     * @throws VoiceKitError
     */
    public function downloadAudioEffects(string $jobId): string
    {
        return $this->download('/v1/audio/effects/' . rawurlencode($jobId) . '/audio');
    }

    /**
     * Starts a video-effects job (Pro/Business).
     *
     * @param array<int, array<string, mixed>> $effects      effect chain applied to the audio track.
     * @param File|string|null                 $audio        replaces the video's audio track (mux mode only).
     * @param string|null                      $mode         `mux` (default, video output) or `audio`.
     * @param string|null                      $outputFormat overrides the container of the artifact.
     *
     * @throws VoiceKitError
     */
    public function applyVideoEffects(
        File|string $video,
        array $effects = [],
        File|string|null $audio = null,
        ?string $mode = null,
        ?string $outputFormat = null,
        ?string $webhookUrl = null,
    ): Result {
        $files = self::singleFilePart('video', $video);
        if ($audio !== null) {
            $files[] = ['audio', File::of($audio)];
        }

        return $this->upload(
            '/v1/video/effects',
            $files,
            self::formFields([
                'effects' => Effects::encode($effects),
                'mode' => $mode,
                'output_format' => $outputFormat,
                'webhookUrl' => $webhookUrl,
            ]),
        );
    }

    /**
     * Polls a video-effects job.
     *
     * @throws VoiceKitError
     */
    public function getVideoEffectsJob(string $jobId): Result
    {
        return $this->get('/v1/video/effects/' . rawurlencode($jobId));
    }

    /**
     * Downloads the artifact (video or audio) of a completed video-effects job.
     *
     * @throws VoiceKitError
     */
    public function downloadVideoEffects(string $jobId): string
    {
        return $this->download('/v1/video/effects/' . rawurlencode($jobId) . '/file');
    }

    /**
     * Starts an audio-cleaning job (denoise, normalize, filters); poll it with
     * {@see ProcessesMediaEffects::getAudioCleaningJob()}.
     *
     * ```php
     * $job = $client->cleanAudio('noisy.wav');                       // denoise + normalize
     * $job = $client->cleanAudio('noisy.wav', [                      // or a custom preset
     *     'denoise' => ['strength' => 0.8, 'stationary' => true],
     *     'normalize' => ['target_db' => -1.0],
     *     'high_pass' => 80,
     *     'low_pass' => 12000,
     * ]);
     * ```
     *
     * @param array<string, mixed>|null $options cleaning preset; `null` = denoise + normalize.
     *
     * @throws VoiceKitError
     */
    public function cleanAudio(
        File|string $audio,
        ?array $options = null,
        ?string $outputFormat = null,
        ?string $webhookUrl = null,
    ): Result {
        return $this->upload(
            '/v1/audio/clean',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'options' => $options === null ? null : self::encodeJson($options),
                'output_format' => $outputFormat,
                'webhookUrl' => $webhookUrl,
            ]),
        );
    }

    /**
     * Polls an audio-cleaning job.
     *
     * @throws VoiceKitError
     */
    public function getAudioCleaningJob(string $jobId): Result
    {
        return $this->get('/v1/audio/clean/' . rawurlencode($jobId));
    }

    /**
     * Downloads the cleaned audio of a completed job.
     *
     * @throws VoiceKitError
     */
    public function downloadAudioCleaning(string $jobId): string
    {
        return $this->download('/v1/audio/clean/' . rawurlencode($jobId) . '/audio');
    }
}
