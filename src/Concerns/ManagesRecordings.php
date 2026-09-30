<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Recordings library, transcripts, exports and share links (`/v1/recordings`).
 */
trait ManagesRecordings
{
    /**
     * Lists the caller's recordings (newest first).
     *
     * @param string|null $source one of `upload`, `link`, `bot`, `stream`.
     * @param int|null    $limit  page size.
     * @param int|null    $offset pagination offset.
     *
     * @throws VoiceKitError
     */
    public function listRecordings(
        ?string $source = null,
        ?string $tag = null,
        ?string $folder = null,
        ?int $limit = null,
        ?int $offset = null,
    ): Result {
        return $this->get('/v1/recordings', [
            'source' => $source,
            'tag' => $tag,
            'folder' => $folder,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * The distinct tags across the caller's recordings.
     *
     * @return list<string>
     *
     * @throws VoiceKitError
     */
    public function recordingTags(): array
    {
        return $this->getStrings('/v1/recordings/tags');
    }

    /**
     * The distinct folders across the caller's recordings.
     *
     * @return list<string>
     *
     * @throws VoiceKitError
     */
    public function recordingFolders(): array
    {
        return $this->getStrings('/v1/recordings/folders');
    }

    /**
     * Starts a diarized transcription from a public audio URL; the recording is
     * tagged with source `link`.
     *
     * @throws VoiceKitError
     */
    public function recordingFromLink(string $url, ?string $language = null): Result
    {
        return $this->post('/v1/recordings/from-link', self::withOptional(['url' => $url], 'language', $language));
    }

    /**
     * A recording's metadata.
     *
     * @throws VoiceKitError
     */
    public function getRecording(string $recordingId): Result
    {
        return $this->get('/v1/recordings/' . rawurlencode($recordingId));
    }

    /**
     * A recording's transcript (segments, words, speakers).
     *
     * @throws VoiceKitError
     */
    public function recordingTranscript(string $recordingId): Result
    {
        return $this->get('/v1/recordings/' . rawurlencode($recordingId) . '/transcript');
    }

    /**
     * A recording's speakers, timeline and talk-time metrics.
     *
     * @throws VoiceKitError
     */
    public function recordingSpeakers(string $recordingId): Result
    {
        return $this->get('/v1/recordings/' . rawurlencode($recordingId) . '/speakers');
    }

    /**
     * Updates a recording's tags and/or folder.
     *
     * ```php
     * $client->updateRecording($id, tags: ['sales', 'warm'], folder: 'Q3');
     * $client->updateRecording($id, clearTags: true, folder: ''); // clear both
     * ```
     *
     * @param list<string>|null $tags      replaces the tag set; `null` leaves it untouched.
     * @param bool              $clearTags removes every tag (wins over `$tags`).
     * @param string|null       $folder    moves the recording; `null` leaves it untouched,
     *                                     an empty string clears it.
     *
     * @throws VoiceKitError
     */
    public function updateRecording(
        string $recordingId,
        ?array $tags = null,
        bool $clearTags = false,
        ?string $folder = null,
    ): Result {
        $body = [];

        if ($clearTags) {
            $body['tags'] = null;
        } elseif ($tags !== null) {
            $body['tags'] = array_values($tags);
        }

        if ($folder !== null) {
            $body['folder'] = $folder;
        }

        return $this->patch('/v1/recordings/' . rawurlencode($recordingId), $body);
    }

    /**
     * Renames a diarized speaker or assigns a role
     * (`operator`, `client`, `participant`).
     *
     * @throws VoiceKitError
     */
    public function updateSpeaker(
        string $recordingId,
        string $speakerId,
        ?string $displayName = null,
        ?string $role = null,
    ): Result {
        $body = self::withOptional([], 'display_name', $displayName);
        $body = self::withOptional($body, 'role', $role);

        return $this->patch(
            '/v1/recordings/' . rawurlencode($recordingId) . '/speakers/' . rawurlencode($speakerId),
            $body,
        );
    }

    /**
     * Deletes a recording and its stored audio.
     *
     * @throws VoiceKitError
     */
    public function deleteRecording(string $recordingId): void
    {
        $this->delete('/v1/recordings/' . rawurlencode($recordingId));
    }

    /**
     * Downloads a recording's stored audio.
     *
     * @throws VoiceKitError
     */
    public function downloadRecordingAudio(string $recordingId): string
    {
        return $this->download('/v1/recordings/' . rawurlencode($recordingId) . '/audio');
    }

    /**
     * Exports a transcript as `txt`, `md`, `srt`, `vtt`, `docx` or `pdf`.
     *
     * @throws VoiceKitError
     */
    public function exportRecording(string $recordingId, string $format = 'txt'): string
    {
        return $this->download(
            '/v1/recordings/' . rawurlencode($recordingId) . '/export',
            ['format' => $format === '' ? 'txt' : $format],
        );
    }

    /**
     * Creates a public share link for a recording.
     *
     * @param int|null    $expiresInSeconds limits the link lifetime; `null` = no expiry.
     * @param string|null $password         protects the link.
     *
     * @throws VoiceKitError
     */
    public function createShare(
        string $recordingId,
        ?int $expiresInSeconds = null,
        ?string $password = null,
    ): Result {
        $body = self::withOptional([], 'expires_in_seconds', $expiresInSeconds);
        $body = self::withOptional($body, 'password', $password);

        return $this->post('/v1/recordings/' . rawurlencode($recordingId) . '/share', $body);
    }

    /**
     * The active share links of a recording.
     *
     * @return list<Result>
     *
     * @throws VoiceKitError
     */
    public function listShares(string $recordingId): array
    {
        return $this->getList('/v1/recordings/' . rawurlencode($recordingId) . '/share');
    }

    /**
     * Revokes a share link.
     *
     * @throws VoiceKitError
     */
    public function revokeShare(string $recordingId, string $token): void
    {
        $this->delete('/v1/recordings/' . rawurlencode($recordingId) . '/share/' . rawurlencode($token));
    }
}
