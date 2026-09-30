<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Text intelligence (`/v1/detect-language`, `/v1/redact`, `/v1/analyze/topics`,
 * `/v1/analyze/summarize`, `/v1/moderate`).
 */
trait HandlesTextIntelligence
{
    /**
     * Identifies the language of a raw text.
     *
     * @throws VoiceKitError
     */
    public function detectLanguage(string $text): Result
    {
        return $this->post('/v1/detect-language', ['text' => $text]);
    }

    /**
     * Masks PII (names, phones, addresses, card numbers) in a raw text.
     *
     * @throws VoiceKitError
     */
    public function redact(string $text, ?string $language = null): Result
    {
        return $this->post('/v1/redact', self::withOptional(['text' => $text], 'language', $language));
    }

    /**
     * Extracts key topics from a raw text.
     *
     * @throws VoiceKitError
     */
    public function topics(string $text, ?string $language = null): Result
    {
        return $this->post('/v1/analyze/topics', self::withOptional(['text' => $text], 'language', $language));
    }

    /**
     * Condenses a raw text into a short summary.
     *
     * @param int|null $maxSentences cap the summary length; `null` = server default.
     *
     * @throws VoiceKitError
     */
    public function summarize(string $text, ?string $language = null, ?int $maxSentences = null): Result
    {
        $body = self::withOptional(['text' => $text], 'language', $language);
        $body = self::withOptional($body, 'max_sentences', $maxSentences);

        return $this->post('/v1/analyze/summarize', $body);
    }

    /**
     * Flags profanity, insults and hate speech in a raw text.
     *
     * @throws VoiceKitError
     */
    public function moderate(string $text, ?string $language = null): Result
    {
        return $this->post('/v1/moderate', self::withOptional(['text' => $text], 'language', $language));
    }
}
