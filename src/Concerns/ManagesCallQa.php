<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Call QA: checklist scoring, analytics and exports (`/v1/qa`) — Pro/Business.
 */
trait ManagesCallQa
{
    /**
     * Scores a recording against a QA checklist.
     *
     * ```php
     * $evaluation = $client->qaEvaluate($recordingId, [
     *     ['id' => 'greeting', 'kind' => 'required', 'description' => 'Поздоровался', 'weight' => 1.0],
     * ]);
     * ```
     *
     * @param array<int, array<string, mixed>> $checklist checklist items.
     * @param string|null                      $webhookUrl receives a `qa.violation` event when the call is flagged.
     *
     * @throws VoiceKitError
     */
    public function qaEvaluate(string $recordingId, array $checklist = [], ?string $webhookUrl = null): Result
    {
        $body = ['recording_id' => $recordingId];

        if ($checklist !== []) {
            $body['checklist'] = array_values($checklist);
        }

        $body = self::withOptional($body, 'webhook_url', $webhookUrl);

        return $this->post('/v1/qa/evaluate', $body);
    }

    /**
     * The score trend, top violations and score by operator.
     *
     * @param int $days look-back window (default 30).
     *
     * @throws VoiceKitError
     */
    public function qaAnalytics(int $days = 30): Result
    {
        return $this->get('/v1/qa/analytics', ['days' => $days > 0 ? $days : 30]);
    }

    /**
     * Persisted QA evaluations (newest first).
     *
     * @throws VoiceKitError
     */
    public function qaEvaluations(int $limit = 20, int $offset = 0): Result
    {
        return $this->get('/v1/qa/evaluations', [
            'limit' => $limit > 0 ? $limit : 20,
            'offset' => $offset > 0 ? $offset : null,
        ]);
    }

    /**
     * Exports QA evaluations as CSV or JSON for CRM import.
     *
     * @throws VoiceKitError
     */
    public function qaExport(string $format = 'csv', int $days = 30): string
    {
        return $this->download('/v1/qa/evaluations/export', [
            'format' => $format === '' ? 'csv' : $format,
            'days' => $days > 0 ? $days : 30,
        ]);
    }
}
