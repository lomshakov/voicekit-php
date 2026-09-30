<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Batch operations (`/v1/batch`).
 */
trait ManagesBatches
{
    /**
     * Queues a batch of synthesis requests (up to 20 items); poll it with
     * {@see ManagesBatches::getBatch()}. Each item is a `/v1/synthesize` body.
     *
     * ```php
     * $batch = $client->batchSynthesize([
     *     ['text' => 'Первый текст', 'voice' => 'preset_anna'],
     *     ['text' => 'Второй текст', 'voice' => 'preset_dmitri'],
     * ]);
     * ```
     *
     * @param list<array<string, mixed>> $items
     *
     * @throws VoiceKitError
     */
    public function batchSynthesize(array $items): Result
    {
        return $this->post('/v1/batch/synthesize', ['items' => array_values($items)]);
    }

    /**
     * Queues a batch of analysis requests (up to 10 items); each item needs an
     * inline base64 `audio` field — see {@see \VoiceKit\File::base64Of()}.
     *
     * ```php
     * $batch = $client->batchAnalyze([
     *     ['audio' => File::base64Of('call.wav'), 'language' => 'ru'],
     * ]);
     * ```
     *
     * @param list<array<string, mixed>> $items
     *
     * @throws VoiceKitError
     */
    public function batchAnalyze(array $items): Result
    {
        return $this->post('/v1/batch/analyze', ['items' => array_values($items)]);
    }

    /**
     * Polls a batch job.
     *
     * @throws VoiceKitError
     */
    public function getBatch(string $batchId): Result
    {
        return $this->get('/v1/batch/' . rawurlencode($batchId));
    }
}
