# VoiceKit — PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/lomshakov/voicekit-client.svg)](https://packagist.org/packages/lomshakov/voicekit-client)
[![CI](https://github.com/lomshakov/voicekit-php/actions/workflows/ci.yml/badge.svg)](https://github.com/lomshakov/voicekit-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](./LICENSE)

Official PHP client for **[VoiceKit](https://ttsapi.ru)** — the REST API for Russian speech:
neural speech synthesis (TTS), transcription (STT) with diarization and timestamps,
sentiment analysis, voice cloning, voice biometrics, audio/video effects, call QA,
semantic search over recordings and batch operations.

> **Links:** [Website](https://ttsapi.ru) · [Documentation](https://ttsapi.ru/docs) · [API reference](https://ttsapi.ru/swagger) · [Pricing](https://ttsapi.ru/pricing) · [Blog](https://ttsapi.ru/blog)

No runtime dependencies: **PHP 8.1+** with `ext-json`. cURL is used when available
and the SDK falls back to PHP streams when it is not; streaming sessions use raw
sockets, so no WebSocket extension is needed either.

## Install

```bash
composer require lomshakov/voicekit-client
```

## Quick start

```php
<?php

require 'vendor/autoload.php';

use VoiceKit\VoiceKitClient;

$client = new VoiceKitClient('rtt_…');       // or VoiceKitClient::fromEnvironment()

// Speech synthesis → raw audio bytes
$audio = $client->synthesize('Привет! Это синтез русской речи.', voice: 'preset_anna', format: 'mp3');
file_put_contents('speech.mp3', $audio);

// Transcription (async → poll)
$job = $client->transcribe('speech.mp3', language: 'ru', diarization: true, keyterms: ['диагноз', 'препарат']);

do {
    $result = $client->getTranscriptionJob($job->str('job_id'));
    usleep(1_000_000);
} while (!in_array($result->str('status'), ['completed', 'failed'], true));

echo $result->str('text'), PHP_EOL;
```

### Configuration

```php
$client = new VoiceKitClient(
    apiKey: 'rtt_…',                              // required
    baseUrl: 'https://ttsapi.ru',                 // default
    timeout: 120.0,                               // default, seconds
    transport: new CurlTransport(timeout: 60.0),  // optional
    headers: ['X-Trace' => 'abc'],                // optional, sent with every request
);
```

`VoiceKitClient::fromEnvironment('VOICEKIT_API_KEY')` reads the key from the
environment, and any `VoiceKit\Http\Transport` implementation can replace the
built-in one (proxy, PSR-18 bridge, test double).

### Error handling

Every non-2xx response becomes a `VoiceKit\VoiceKitError` carrying the RFC 7807
`code`; network failures carry a message and no status:

```php
use VoiceKit\VoiceKitError;

try {
    foreach ($client->synthesizeStream($longText) as $chunk) {
        // …
    }
} catch (VoiceKitError $error) {
    if ($error->isForbidden()) {
        echo 'upgrade your plan: ', $error->errorCode(); // streaming_forbidden
    } elseif ($error->isCode('quota_exceeded')) {
        echo 'monthly quota is over';
    } elseif ($error->isRateLimited()) {
        echo 'slow down';
    } else {
        echo $error->getMessage();
    }
}
```

## Features

| Area | Methods |
| --- | --- |
| Synthesis | `synthesize`, `synthesizeStream`, `synthesizeAsync`, `getSynthesisJob`, `downloadSynthesisAudio` |
| Voices | `voices`, `voice`, `createCloneVoice`, `listCloneVoices`, `getCloneVoice`, `deleteCloneVoice` |
| Transcription | `transcribe`, `transcribeSync`, `getTranscriptionJob`, `subtitles`, `translateTranscript`, `vad` |
| Analysis | `analyze`, `analyzeSync`, `getAnalysisJob`, `evaluate` |
| Text intelligence | `detectLanguage`, `redact`, `topics`, `summarize`, `moderate` |
| Effects | `applyAudioEffects`, `applyVideoEffects`, `cleanAudio` + job/status/download helpers |
| Voice ID | `analyzeVoice`, `enrollVoice`, `verifyVoice`, `identifyVoice`, `listVoiceProfiles`, `deleteVoiceProfile` |
| Recordings | `listRecordings`, `getRecording`, `recordingTranscript`, `recordingSpeakers`, `updateRecording`, `updateSpeaker`, `recordingFromLink`, `exportRecording`, `downloadRecordingAudio`, share links |
| Call QA | `qaEvaluate`, `qaAnalytics`, `qaEvaluations`, `qaExport` |
| Search | `search`, `ask`, `meetingProtocol` |
| Batch / account | `batchSynthesize`, `batchAnalyze`, `getBatch`, `usage`, `billingBalance` |
| Streaming | `transcribeStream`, `vadStream` (WebSocket, Pro/Business) |

### Streaming synthesis (Pro/Business)

```php
$out = fopen('long.mp3', 'wb');

foreach ($client->synthesizeStream($longText, voice: 'preset_anna') as $chunk) {
    fwrite($out, $chunk); // chunks arrive as the engine produces them
}

fclose($out);
```

### Audio effects (Free/Basic/Pro/Business)

```php
use VoiceKit\Effects;

$chain = Effects::encode([
    ['type' => 'reverb', 'room_size' => 0.5],
    ['type' => 'pitch', 'semitones' => 2],
]);

// Inline during synthesis
$audio = $client->synthesize('Привет!', voice: 'preset_anna', effects: $chain);

// Or as a background job over an existing file
$job = $client->applyAudioEffects('voice.wav', [
    ['type' => 'compressor', 'ratio' => 3],
], outputFormat: 'mp3');

$processed = $client->downloadAudioEffects($job->str('job_id'));
```

### Audio cleaning (Free/Basic/Pro/Business)

```php
$job = $client->cleanAudio('noisy.wav'); // one click: denoise + normalize

$job = $client->cleanAudio('noisy.wav', [ // or a custom preset
    'denoise' => ['strength' => 0.8, 'stationary' => true],
    'normalize' => ['target_db' => -1.0],
    'high_pass' => 80,
    'low_pass' => 12000,
]);

$clean = $client->downloadAudioCleaning($job->str('job_id'));
```

### Voice ID (Pro/Business)

```php
// Voice passport: language, gender, age, emotion, speaker embedding, AI-vs-human
$passport = $client->analyzeVoice('sample.wav');

// Voice biometrics over your own profiles
$profile = $client->enrollVoice('speaker.wav', 'Алиса');
$check = $client->verifyVoice('other.wav', $profile->str('profile_id'));
$match = $client->identifyVoice('other.wav'); // 1:N over every profile
```

### Recordings, QA & meeting intelligence (Pro/Business)

```php
$page = $client->listRecordings(source: 'link', limit: 10);
$job = $client->recordingFromLink('https://example.com/call.mp3', 'ru');
$speakers = $client->recordingSpeakers($recordingId);
$client->updateSpeaker($recordingId, 'SPEAKER_00', displayName: 'Иван', role: 'operator');

$client->updateRecording($recordingId, tags: ['sales', 'warm'], folder: 'Q3');
$pdf = $client->exportRecording($recordingId, 'pdf');

$evaluation = $client->qaEvaluate($recordingId, [
    ['id' => 'greeting', 'kind' => 'required', 'description' => 'Поздоровался', 'weight' => 1.0],
]);
$trend = $client->qaAnalytics(30);

$hits = $client->search('почему клиент отказался?', limit: 5, keywords: 'дорого');
$answer = $client->ask('почему клиент отказался от Pro?');
$protocol = $client->meetingProtocol($recordingId, 'standup');
```

### WebSocket streaming (Pro/Business)

```php
$stream = $client->transcribeStream(language: 'ru', keyterms: ['диагноз']);

$stream->sendAudio($pcm16Chunk); // raw PCM16, 16 kHz mono
$stream->stop();                 // finalize the utterance

while (($event = $stream->receive()) !== null) {
    // session / vad / partial / final / error
    echo $event->str('type'), ': ', $event->str('text'), PHP_EOL;
}

$stream->close();
```

### Batches

```php
use VoiceKit\File;

$batch = $client->batchSynthesize([
    ['text' => 'Первый текст', 'voice' => 'preset_anna'],
    ['text' => 'Второй текст', 'voice' => 'preset_dmitri'],
]);

$status = $client->getBatch($batch->str('batch_id'));

// Analysis batches take inline base64 audio
$batch = $client->batchAnalyze([
    ['audio' => File::base64Of('call.wav'), 'language' => 'ru'],
]);
```

## Dynamic responses

The API evolves, so JSON answers come back as `VoiceKit\Result` — an array-backed
object with typed accessors. Nested objects are wrapped too, and array access
works as usual:

```php
$result = $client->getTranscriptionJob($jobId);

$text = $result->str('text');
$seconds = $result->float('duration_seconds');
$status = $result['status'];

foreach ($result->list('segments') as $segment) {
    echo $segment->float('start'), ' ', $segment->str('text'), PHP_EOL;
}
```

`Result` implements `ArrayAccess`, `Countable`, `IteratorAggregate` and
`JsonSerializable`; `toArray()` returns the untouched payload.

## Calling endpoints the SDK does not wrap yet

```php
$response = $client->request('POST', '/v1/synthesize', body: json_encode(['text' => 'Привет']));

$usage = $client->get('/v1/usage');
$voices = $client->getList('/v1/voices');
$tags = $client->getStrings('/v1/recordings/tags');
$audio = $client->download('/v1/recordings/rec_1/audio');
$job = $client->upload('/v1/vad', [['audio', File::fromPath('call.wav')]]);
```

## Requirements

| PHP | `ext-json` | `ext-curl` | `ext-openssl` |
| --- | --- | --- | --- |
| 8.1+ | required | recommended (falls back to streams) | required for `wss://` streaming |

## Development

```bash
composer install
composer test        # PHPUnit, offline (live tests are skipped)
composer analyse     # PHPStan level 8
VOICEKIT_API_KEY=rtt_… vendor/bin/phpunit --testsuite integration
```

## License

[MIT](./LICENSE) © VoiceKit
