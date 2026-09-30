<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\File;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Voice ID: voice passports and speaker biometrics (`/v1/voice-id`).
 */
trait ManagesVoiceId
{
    /**
     * Builds a voice passport from a clip: language, gender, age group,
     * emotional background, a speaker embedding and an AI-vs-human probability
     * (`ai_probability` is `null` when anti-spoofing is not configured).
     *
     * @throws VoiceKitError
     */
    public function analyzeVoice(File|string $audio): Result
    {
        return $this->upload('/v1/voice-id', self::singleFilePart('audio', $audio));
    }

    /**
     * Stores a clip as a reusable voice profile; the response carries `profile_id`.
     *
     * @throws VoiceKitError
     */
    public function enrollVoice(File|string $audio, ?string $name = null): Result
    {
        return $this->upload(
            '/v1/voice-id/enroll',
            self::singleFilePart('audio', $audio),
            self::formFields(['name' => $name]),
        );
    }

    /**
     * Compares a clip against an enrolled profile (1:1). The response carries
     * `profile_id`, `similarity`, `verified` and `threshold`.
     *
     * @throws VoiceKitError
     */
    public function verifyVoice(File|string $audio, string $profileId): Result
    {
        return $this->upload(
            '/v1/voice-id/verify',
            self::singleFilePart('audio', $audio),
            ['profile_id' => $profileId],
        );
    }

    /**
     * Finds the closest matching profile (1:N). Pass an empty list to search
     * every profile of the caller.
     *
     * @param list<string> $profileIds
     *
     * @throws VoiceKitError
     */
    public function identifyVoice(File|string $audio, array $profileIds = []): Result
    {
        return $this->upload(
            '/v1/voice-id/identify',
            self::singleFilePart('audio', $audio),
            self::formFields(['profile_ids' => self::csv($profileIds) !== '' ? self::csv($profileIds) : null]),
        );
    }

    /**
     * The caller's enrolled voice profiles.
     *
     * @return list<Result>
     *
     * @throws VoiceKitError
     */
    public function listVoiceProfiles(): array
    {
        return $this->getList('/v1/voice-id/profiles');
    }

    /**
     * Deletes an enrolled voice profile.
     *
     * @throws VoiceKitError
     */
    public function deleteVoiceProfile(string $profileId): void
    {
        $this->delete('/v1/voice-id/profiles/' . rawurlencode($profileId));
    }
}
