<?php

declare(strict_types=1);

namespace VoiceKit\Concerns;

use VoiceKit\File;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * Audio analysis (`/v1/analyze`, `/v1/eval`).
 */
trait AnalyzesAudio
{
    /**
     * Starts an asynchronous analysis job (emotions, keywords, entities,
     * optionally per speaker); poll it with {@see AnalyzesAudio::getAnalysisJob()}.
     *
     * @param list<string> $keyterms
     *
     * @throws VoiceKitError
     */
    public function analyze(
        File|string $audio,
        ?string $language = null,
        bool $diarization = false,
        array $keyterms = [],
        bool $clean = false,
        ?string $webhookUrl = null,
    ): Result {
        return $this->upload(
            '/v1/analyze',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'language' => $language,
                'diarization' => $diarization ?: null,
                'clean' => $clean ?: null,
                'webhookUrl' => $webhookUrl,
                'keyterms' => self::csv($keyterms) !== '' ? self::csv($keyterms) : null,
            ]),
        );
    }

    /**
     * Analyses a short file (up to 3 minutes) synchronously.
     *
     * `$emotions`, `$keywords` and `$entities` default to `true` server-side;
     * pass an explicit `false` to disable one extractor.
     *
     * @param list<string> $keyterms
     *
     * @throws VoiceKitError
     */
    public function analyzeSync(
        File|string $audio,
        ?string $language = null,
        bool $diarization = false,
        ?bool $emotions = null,
        ?bool $keywords = null,
        ?bool $entities = null,
        array $keyterms = [],
        bool $clean = false,
    ): Result {
        return $this->upload(
            '/v1/analyze/sync',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'language' => $language,
                'diarization' => $diarization ?: null,
                'clean' => $clean ?: null,
                'emotions' => $emotions,
                'keywords' => $keywords,
                'entities' => $entities,
                'keyterms' => self::csv($keyterms) !== '' ? self::csv($keyterms) : null,
            ]),
        );
    }

    /**
     * Polls an analysis job.
     *
     * @throws VoiceKitError
     */
    public function getAnalysisJob(string $jobId): Result
    {
        return $this->get('/v1/analyze/' . rawurlencode($jobId));
    }

    /**
     * Measures transcription quality (word error rate) against a reference text.
     *
     * @param bool|null $normalize ignore case and punctuation; `null` = server default.
     *
     * @throws VoiceKitError
     */
    public function evaluate(
        File|string $audio,
        string $reference,
        ?string $language = null,
        ?bool $normalize = null,
    ): Result {
        return $this->upload(
            '/v1/eval',
            self::singleFilePart('audio', $audio),
            self::formFields([
                'reference' => $reference,
                'language' => $language,
                'normalize' => $normalize,
            ]),
        );
    }
}
