<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Semantic search, question answering and meeting protocols.
 */
trait SearchesRecordings
{
    /**
     * Runs a hybrid semantic + full-text search over the recording library
     * (Pro/Business).
     *
     * @param int|null    $limit                maximum number of hits.
     * @param string|null $keywords             enables full-text ranking on top of semantic similarity.
     * @param string|null $source               one of `upload`, `link`, `bot`, `stream`.
     * @param string|null $speaker              diarization label such as `SPEAKER_00`.
     * @param string      $from                 ISO-8601 lower bound (UTC).
     * @param string      $to                   ISO-8601 upper bound (UTC).
     * @param float|null  $minDurationSeconds   minimum recording length.
     * @param float|null  $maxDurationSeconds   maximum recording length.
     *
     * @throws VoiceKitError
     */
    public function search(
        string $query,
        ?int $limit = null,
        ?string $keywords = null,
        ?string $source = null,
        ?string $speaker = null,
        ?string $from = null,
        ?string $to = null,
        ?float $minDurationSeconds = null,
        ?float $maxDurationSeconds = null,
    ): Result {
        $body = ['query' => $query];
        $body = self::withOptional($body, 'limit', $limit);
        $body = self::withOptional($body, 'keywords', $keywords);
        $body = self::withOptional($body, 'source', $source);
        $body = self::withOptional($body, 'speaker', $speaker);
        $body = self::withOptional($body, 'from', $from);
        $body = self::withOptional($body, 'to', $to);
        $body = self::withOptional($body, 'min_duration_seconds', $minDurationSeconds);
        $body = self::withOptional($body, 'max_duration_seconds', $maxDurationSeconds);

        return $this->post('/v1/search', $body);
    }

    /**
     * Answers a question over the recording library (RAG) with verbatim citations.
     *
     * @throws VoiceKitError
     */
    public function ask(string $query): Result
    {
        return $this->post('/v1/ask', ['query' => $query]);
    }

    /**
     * Generates a meeting protocol from a recording transcript.
     *
     * @param string $template `custom` (default), `standup`, `demo`, `interview`,
     *                         `retro` or `one_on_one`.
     *
     * @throws VoiceKitError
     */
    public function meetingProtocol(string $recordingId, string $template = 'custom'): Result
    {
        return $this->post('/v1/meetings/protocol', [
            'recording_id' => $recordingId,
            'template' => $template === '' ? 'custom' : $template,
        ]);
    }

    /**
     * Formats a date for the `from`/`to` search filters (UTC, ISO-8601).
     */
    public static function timestamp(DateTimeInterface|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DateTimeInterface::ATOM);
    }
}
