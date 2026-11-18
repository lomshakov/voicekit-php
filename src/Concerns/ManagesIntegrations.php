<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Integrations, CRM push and card top-up.
 */
trait ManagesIntegrations
{
    /**
     * External connections (Bitrix24, amoCRM, 1С), active and revoked.
     *
     * @throws VoiceKitError
     */
    public function integrations(): Result
    {
        return $this->get('/v1/integrations/connections');
    }

    /**
     * Store a provider credential (incoming-webhook URL or OAuth token).
     *
     * @throws VoiceKitError
     */
    public function connectIntegration(
        string $provider,
        string $secret,
        ?string $displayName = null,
        ?string $baseUrl = null,
        ?string $refreshToken = null,
        ?string $scopes = null,
    ): Result {
        $body = array_filter(
            [
                'display_name' => $displayName,
                'base_url' => $baseUrl,
                'refresh_token' => $refreshToken,
                'scopes' => $scopes,
            ],
            static fn (?string $value): bool => $value !== null,
        );
        $body['provider'] = $provider;
        $body['secret'] = $secret;

        return $this->post('/v1/integrations/connections', $body);
    }

    /**
     * Revoke a connection.
     *
     * @throws VoiceKitError
     */
    public function deleteIntegration(string $connectionId): void
    {
        $this->delete('/v1/integrations/connections/' . rawurlencode($connectionId));
    }

    /**
     * Start the OAuth handshake; meeting providers require consent.
     *
     * @throws VoiceKitError
     */
    public function integrationAuthorizeUrl(string $provider, bool $consent = false): Result
    {
        return $this->post(
            '/v1/integrations/' . rawurlencode($provider) . '/authorize',
            ['consent' => $consent],
        );
    }

    /**
     * Push a recording artifact (protocol, transcript, qa) to a CRM deal.
     *
     * @throws VoiceKitError
     */
    public function pushRecording(
        string $recordingId,
        string $provider,
        string $kind = 'protocol',
        ?string $dealId = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $sourceUrl = null,
    ): Result {
        $body = array_filter(
            [
                'deal_id' => $dealId,
                'phone' => $phone,
                'email' => $email,
                'source_url' => $sourceUrl,
            ],
            static fn (?string $value): bool => $value !== null,
        );
        $body['provider'] = $provider;
        $body['kind'] = $kind;

        return $this->post('/v1/recordings/' . rawurlencode($recordingId) . '/push', $body);
    }

    /**
     * Push history for a recording, newest first.
     *
     * @throws VoiceKitError
     */
    public function recordingPushes(string $recordingId): Result
    {
        return $this->get('/v1/recordings/' . rawurlencode($recordingId) . '/pushes');
    }

    /**
     * Retry a failed push, reusing the target stored with it.
     *
     * @throws VoiceKitError
     */
    public function retryRecordingPush(string $recordingId, string $provider, string $kind): Result
    {
        return $this->post(
            '/v1/recordings/' . rawurlencode($recordingId)
                . '/pushes/' . rawurlencode($provider) . '/' . rawurlencode($kind) . '/retry',
        );
    }

    /**
     * Create a card top-up payment and return the payment link.
     *
     * @throws VoiceKitError
     */
    public function topUp(float $amountRub): Result
    {
        return $this->post('/v1/billing/topup', ['amount_rub' => $amountRub]);
    }
}
