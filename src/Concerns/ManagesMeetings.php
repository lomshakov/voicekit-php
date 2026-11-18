<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Meeting intelligence: captured meetings, calendar, protocols and templates,
 * plus the webhook signing secret.
 */
trait ManagesMeetings
{
    /**
     * Automatically captured meetings (Zoom, Meet, calendar), newest first.
     *
     * @throws VoiceKitError
     */
    public function meetingEvents(int $limit = 50): Result
    {
        return $this->get('/v1/meetings/events', ['limit' => (string) $limit]);
    }

    /**
     * One captured meeting: status, job, recording and protocol ids.
     *
     * @throws VoiceKitError
     */
    public function meetingEvent(string $eventId): Result
    {
        return $this->get('/v1/meetings/events/' . rawurlencode($eventId));
    }

    /**
     * Point a captured meeting at a CRM deal so its protocol lands there.
     *
     * @throws VoiceKitError
     */
    public function setMeetingTarget(
        string $eventId,
        ?string $dealId = null,
        ?string $phone = null,
        ?string $email = null,
    ): Result {
        return $this->put(
            '/v1/meetings/events/' . rawurlencode($eventId) . '/target',
            array_filter(
                ['deal_id' => $dealId, 'phone' => $phone, 'email' => $email],
                static fn (?string $value): bool => $value !== null,
            ),
        );
    }

    /**
     * Retry a failed capture now.
     *
     * @throws VoiceKitError
     */
    public function retryMeeting(string $eventId): Result
    {
        return $this->post('/v1/meetings/events/' . rawurlencode($eventId) . '/retry');
    }

    /**
     * Provider calendar events pulled from the connected account.
     *
     * @throws VoiceKitError
     */
    public function meetingCalendar(int $limit = 50): Result
    {
        return $this->get('/v1/meetings/calendar', ['limit' => (string) $limit]);
    }

    /**
     * Pull the provider calendar now instead of waiting for the scheduler.
     *
     * @throws VoiceKitError
     */
    public function syncMeetingCalendar(): Result
    {
        return $this->post('/v1/meetings/calendar/sync');
    }

    /**
     * Write the protocol link back to the provider event.
     *
     * @throws VoiceKitError
     */
    public function writebackMeetingCalendar(string $eventId): Result
    {
        return $this->post('/v1/meetings/events/' . rawurlencode($eventId) . '/calendar');
    }

    /**
     * Persisted meeting protocols, newest first.
     *
     * @throws VoiceKitError
     */
    public function protocols(int $limit = 50): Result
    {
        return $this->get('/v1/meetings/protocols', ['limit' => (string) $limit]);
    }

    /**
     * @throws VoiceKitError
     */
    public function protocol(string $protocolId): Result
    {
        return $this->get('/v1/meetings/protocols/' . rawurlencode($protocolId));
    }

    /**
     * Approve a draft protocol; only approved ones may be pushed to CRM.
     *
     * @throws VoiceKitError
     */
    public function approveProtocol(string $protocolId): Result
    {
        return $this->post('/v1/meetings/protocols/' . rawurlencode($protocolId) . '/approve');
    }

    /**
     * Replace the protocol Markdown.
     *
     * @throws VoiceKitError
     */
    public function updateProtocol(string $protocolId, string $markdown): Result
    {
        return $this->put(
            '/v1/meetings/protocols/' . rawurlencode($protocolId),
            ['markdown' => $markdown],
        );
    }

    /**
     * Download a protocol as `md`, `pdf` or `docx`.
     *
     * @throws VoiceKitError
     */
    public function exportProtocol(string $protocolId, string $format = 'md'): string
    {
        return $this->download(
            '/v1/meetings/protocols/' . rawurlencode($protocolId) . '/export?format=' . rawurlencode($format),
        );
    }

    /**
     * Build a protocol against an inline schema or a saved template.
     *
     * @param array<string, mixed>|null $schema
     *
     * @throws VoiceKitError
     */
    public function customProtocol(
        string $recordingId,
        ?string $templateId = null,
        ?array $schema = null,
        ?string $webhookUrl = null,
    ): Result {
        $body = array_filter(
            ['template_id' => $templateId, 'webhook_url' => $webhookUrl],
            static fn (?string $value): bool => $value !== null,
        );
        $body['recording_id'] = $recordingId;
        if ($schema !== null) {
            $body['schema'] = $schema;
        }

        return $this->post('/v1/meetings/protocol/custom', $body);
    }

    /**
     * @throws VoiceKitError
     */
    public function protocolTemplates(): Result
    {
        return $this->get('/v1/meetings/templates');
    }

    /**
     * @param array<string, mixed>|null $schema
     *
     * @throws VoiceKitError
     */
    public function createProtocolTemplate(string $name, ?array $schema = null): Result
    {
        $body = ['name' => $name];
        if ($schema !== null) {
            $body['schema'] = $schema;
        }

        return $this->post('/v1/meetings/templates', $body);
    }

    /**
     * @param array<string, mixed>|null $schema
     *
     * @throws VoiceKitError
     */
    public function updateProtocolTemplate(string $templateId, string $name, ?array $schema = null): Result
    {
        $body = ['name' => $name];
        if ($schema !== null) {
            $body['schema'] = $schema;
        }

        return $this->put('/v1/meetings/templates/' . rawurlencode($templateId), $body);
    }

    /**
     * @throws VoiceKitError
     */
    public function deleteProtocolTemplate(string $templateId): void
    {
        $this->delete('/v1/meetings/templates/' . rawurlencode($templateId));
    }

    /**
     * Signing secret behind `X-VoiceKit-Signature`; created on first call.
     *
     * @throws VoiceKitError
     */
    public function webhookSecret(): Result
    {
        return $this->get('/v1/webhooks/secret');
    }

    /**
     * Issue a new signing secret; the previous one stops verifying at once.
     *
     * @throws VoiceKitError
     */
    public function rotateWebhookSecret(): Result
    {
        return $this->post('/v1/webhooks/secret/rotate');
    }
}
