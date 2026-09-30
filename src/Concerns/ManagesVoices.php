<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\File;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Voice catalog and voice cloning (`/v1/voices`).
 */
trait ManagesVoices
{
    /**
     * The voice catalog (preset and cloned voices of the caller).
     *
     * @return list<Result>
     *
     * @throws VoiceKitError
     */
    public function voices(): array
    {
        return $this->getList('/v1/voices');
    }

    /**
     * One voice by id, e.g. `preset_anna`.
     *
     * @throws VoiceKitError
     */
    public function voice(string $voiceId): Result
    {
        return $this->get('/v1/voices/' . rawurlencode($voiceId));
    }

    /**
     * Creates a cloned voice from 1–3 reference recordings (Pro/Business).
     *
     * `$promptText` must be the exact transcript of the reference audio.
     *
     * ```php
     * $voice = $client->createCloneVoice(
     *     name: 'Иван',
     *     promptText: 'Здравствуйте, это образец моего голоса.',
     *     samples: ['ivan-1.wav', 'ivan-2.wav'],
     * );
     * echo $voice->str('id'); // use it with model: 'premium'
     * ```
     *
     * @param File|string|array<int, File|string> $samples reference audio files.
     *
     * @throws VoiceKitError
     */
    public function createCloneVoice(
        string $name,
        string $promptText,
        File|string|array $samples,
        ?string $language = null,
    ): Result {
        return $this->upload(
            '/v1/voices/clone',
            self::fileParts('samples', $samples),
            self::formFields([
                'name' => $name,
                'prompt_text' => $promptText,
                'language' => $language,
            ]),
        );
    }

    /**
     * The caller's cloned voices.
     *
     * @return list<Result>
     *
     * @throws VoiceKitError
     */
    public function listCloneVoices(): array
    {
        return $this->getList('/v1/voices/clone');
    }

    /**
     * One cloned voice by id.
     *
     * @throws VoiceKitError
     */
    public function getCloneVoice(string $cloneId): Result
    {
        return $this->get('/v1/voices/clone/' . rawurlencode($cloneId));
    }

    /**
     * Deletes a cloned voice and its reference latents.
     *
     * @throws VoiceKitError
     */
    public function deleteCloneVoice(string $cloneId): void
    {
        $this->delete('/v1/voices/clone/' . rawurlencode($cloneId));
    }
}
