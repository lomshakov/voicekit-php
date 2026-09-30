<?php

declare(strict_types=1);

namespace VoiceKit;

use InvalidArgumentException;
use VoiceKit\Concerns\AnalyzesAudio;
use VoiceKit\Concerns\HandlesTextIntelligence;
use VoiceKit\Concerns\ManagesAccount;
use VoiceKit\Concerns\ManagesBatches;
use VoiceKit\Concerns\ManagesCallQa;
use VoiceKit\Concerns\ManagesRecordings;
use VoiceKit\Concerns\ManagesVoiceId;
use VoiceKit\Concerns\ManagesVoices;
use VoiceKit\Concerns\NormalizesInput;
use VoiceKit\Concerns\ProcessesMediaEffects;
use VoiceKit\Concerns\SearchesRecordings;
use VoiceKit\Concerns\StreamsAudio;
use VoiceKit\Concerns\SynthesizesSpeech;
use VoiceKit\Concerns\TranscribesAudio;
use VoiceKit\Http\CurlTransport;
use VoiceKit\Http\Multipart;
use VoiceKit\Http\Request;
use VoiceKit\Http\Response;
use VoiceKit\Http\StreamTransport;
use VoiceKit\Http\Transport;

/**
 * Official PHP client for the VoiceKit API — Russian speech synthesis (TTS),
 * transcription (STT) with diarization, voice cloning, voice biometrics,
 * audio/video effects, call QA, semantic search and batch jobs.
 *
 * ```php
 * use VoiceKit\VoiceKitClient;
 *
 * $client = new VoiceKitClient('rtt_…');
 *
 * $audio = $client->synthesize('Привет! Это синтез русской речи.', voice: 'preset_anna', format: 'mp3');
 * file_put_contents('speech.mp3', $audio);
 * ```
 *
 * Every method either returns a {@see Result} wrapper, raw binary (`string`) or
 * plain text. Failures throw {@see VoiceKitError}.
 *
 * @see https://ttsapi.ru/docs
 */
final class VoiceKitClient
{
    use NormalizesInput;
    use SynthesizesSpeech;
    use ManagesVoices;
    use TranscribesAudio;
    use AnalyzesAudio;
    use HandlesTextIntelligence;
    use ProcessesMediaEffects;
    use ManagesVoiceId;
    use ManagesRecordings;
    use ManagesCallQa;
    use SearchesRecordings;
    use ManagesBatches;
    use ManagesAccount;
    use StreamsAudio;

    public const DEFAULT_BASE_URL = 'https://ttsapi.ru';

    public const VERSION = '0.4.0';

    public const DEFAULT_TIMEOUT_SECONDS = 120.0;

    private string $apiKey;

    private string $baseUrl;

    private float $timeout;

    private Transport $transport;

    /** @var array<string, string> */
    private array $headers;

    /**
     * @param string                $apiKey    key issued in the cabinet (`rtt_…`).
     * @param string                $baseUrl   API endpoint (default {@see VoiceKitClient::DEFAULT_BASE_URL}).
     * @param float                 $timeout   per-request timeout in seconds (default 120).
     * @param Transport|null        $transport custom transport (cURL is used by default).
     * @param array<string, string> $headers   extra headers sent with every request.
     *
     * @throws InvalidArgumentException when the key, base URL or timeout is invalid.
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT_SECONDS,
        ?Transport $transport = null,
        array $headers = [],
    ) {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new InvalidArgumentException('VoiceKit: a non-empty API key is required.');
        }

        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            throw new InvalidArgumentException('VoiceKit: baseUrl must be a non-empty URL.');
        }

        if ($timeout <= 0.0) {
            throw new InvalidArgumentException('VoiceKit: timeout must be greater than zero.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->transport = $transport ?? self::createDefaultTransport($timeout);
        $this->headers = $headers;
    }

    /**
     * Builds a client from an environment variable (default `VOICEKIT_API_KEY`).
     *
     * @throws InvalidArgumentException when the variable is missing or empty.
     */
    public static function fromEnvironment(
        string $variable = 'VOICEKIT_API_KEY',
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT_SECONDS,
        ?Transport $transport = null,
    ): self {
        $value = getenv($variable);
        if ($value === false || trim($value) === '') {
            throw new InvalidArgumentException(sprintf('VoiceKit: environment variable %s is not set.', $variable));
        }

        return new self($value, $baseUrl, $timeout, $transport);
    }

    /**
     * The configured API endpoint.
     */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * The SDK release version.
     */
    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * The active transport.
     */
    public function transport(): Transport
    {
        return $this->transport;
    }

    /**
     * The per-request timeout in seconds.
     */
    public function timeout(): float
    {
        return $this->timeout;
    }

    /**
     * Headers attached to every request (includes the API key).
     *
     * @return array<string, string>
     */
    public function defaultHeaders(): array
    {
        return $this->headers + [
            'X-Api-Key' => $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'voicekit-php/' . self::VERSION,
        ];
    }

    // ───────────────────────────── low-level API ─────────────────────────────

    /**
     * Sends an authenticated request and returns the raw response.
     *
     * Use it for endpoints the SDK does not wrap yet:
     *
     * ```php
     * $response = $client->request('POST', '/v1/synthesize', body: json_encode(['text' => 'Привет']));
     * ```
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     *
     * @throws VoiceKitError
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?string $body = null,
        array $headers = [],
    ): Response {
        $headers += $this->defaultHeaders();

        if ($body !== null && !isset($headers['Content-Type']) && !isset($headers['content-type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send(
            new Request(strtoupper($method), $this->url($path, $query), $headers, $body),
        );

        if (!$response->isSuccessful()) {
            throw VoiceKitError::fromResponse($response->status, $response->body);
        }

        return $response;
    }

    /**
     * A `GET` returning a JSON object.
     *
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function get(string $path, array $query = []): Result
    {
        return Result::fromJson($this->request('GET', $path, $query)->body);
    }

    /**
     * A `POST` with a JSON body.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function post(string $path, array $body = [], array $query = []): Result
    {
        return Result::fromJson($this->request('POST', $path, $query, self::encodeJson($body))->body);
    }

    /**
     * A `PATCH` with a JSON body.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function patch(string $path, array $body = [], array $query = []): Result
    {
        return Result::fromJson($this->request('PATCH', $path, $query, self::encodeJson($body))->body);
    }

    /**
     * A `DELETE`.
     *
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function delete(string $path, array $query = []): Result
    {
        return Result::fromJson($this->request('DELETE', $path, $query)->body);
    }

    /**
     * Downloads a binary payload (audio, PDF, CSV export, …).
     *
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function download(string $path, array $query = []): string
    {
        return $this->request('GET', $path, $query, null, ['Accept' => '*/*'])->body;
    }

    /**
     * Downloads a text payload (WebVTT/SRT subtitles, …).
     *
     * @param array<string, mixed> $query
     *
     * @throws VoiceKitError
     */
    public function text(string $path, array $query = []): string
    {
        return $this->request('GET', $path, $query, null, ['Accept' => 'text/plain, */*'])->body;
    }

    /**
     * A `GET` returning a JSON list; both a bare array and an object wrapping
     * the list (`items`, `values`, `data`, `voices`, `profiles`, `recordings`)
     * are accepted.
     *
     * @param array<string, mixed> $query
     *
     * @return list<Result>
     *
     * @throws VoiceKitError
     */
    public function getList(string $path, array $query = []): array
    {
        return self::decodeList($this->request('GET', $path, $query)->body);
    }

    /**
     * A `GET` returning a list of strings.
     *
     * @param array<string, mixed> $query
     *
     * @return list<string>
     *
     * @throws VoiceKitError
     */
    public function getStrings(string $path, array $query = [], string $key = 'values'): array
    {
        $json = $this->request('GET', $path, $query)->body;
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded) && array_is_list($decoded)) {
            $values = [];
            foreach ($decoded as $item) {
                if (is_string($item)) {
                    $values[] = $item;
                }
            }

            return $values;
        }

        return Result::fromJson($json)->strings($key);
    }

    /**
     * Uploads files with a `multipart/form-data` request and returns the JSON
     * response.
     *
     * @param list<array{0: string, 1: File}> $files
     * @param array<string, string>           $fields
     * @param array<string, mixed>            $query
     *
     * @throws VoiceKitError
     */
    public function upload(string $path, array $files, array $fields = [], array $query = []): Result
    {
        [$contentType, $body] = Multipart::build($files, $fields);

        return Result::fromJson(
            $this->request('POST', $path, $query, $body, ['Content-Type' => $contentType])->body,
        );
    }

    // ─────────────────────────────── internals ───────────────────────────────

    /**
     * @param array<string, mixed> $query
     */
    private function url(string $path, array $query = []): string
    {
        $filtered = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        $url = $this->baseUrl . $path;

        return $filtered === []
            ? $url
            : $url . '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws VoiceKitError
     */
    private static function encodeJson(array $payload): string
    {
        if ($payload === []) {
            // Every JSON body is an object: PHP would render an empty array as `[]`.
            return '{}';
        }

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new VoiceKitError('VoiceKit: the request body could not be encoded: ' . $exception->getMessage(), null, null, null, $exception);
        }
    }

    /**
     * @return list<Result>
     */
    private static function decodeList(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $wrapped = Result::wrap(json_decode($json, true));

        if (is_array($wrapped)) {
            return array_values(array_filter($wrapped, static fn (mixed $item): bool => $item instanceof Result));
        }

        if (!$wrapped instanceof Result) {
            return [];
        }

        foreach (['items', 'values', 'data', 'voices', 'profiles', 'recordings'] as $key) {
            $list = $wrapped->list($key);
            if ($list !== []) {
                return $list;
            }
        }

        return [];
    }

    private static function createDefaultTransport(float $timeout): Transport
    {
        return extension_loaded('curl')
            ? new CurlTransport($timeout)
            : new StreamTransport($timeout);
    }
}
