<?php

declare(strict_types=1);

// Walks through the VoiceKit PHP SDK.
//
//   VOICEKIT_API_KEY=rtt_… php examples/quickstart.php

require __DIR__ . '/../vendor/autoload.php';

use VoiceKit\File;
use VoiceKit\VoiceKitClient;
use VoiceKit\VoiceKitError;

$client = VoiceKitClient::fromEnvironment();

// 1. Synthesize speech.
$audio = $client->synthesize('Привет! Это синтез русской речи.', voice: 'preset_anna', format: 'mp3');
file_put_contents(__DIR__ . '/speech.mp3', $audio);
printf("synthesized %d bytes → examples/speech.mp3\n", strlen($audio));

// 2. Transcribe the produced audio (async job → poll).
$job = $client->transcribe(File::fromString($audio, 'speech.mp3'), language: 'ru');
printf("transcription queued: %s\n", $job->str('job_id'));

for ($attempt = 0; $attempt < 30; $attempt++) {
    $result = $client->getTranscriptionJob($job->str('job_id'));
    $status = $result->str('status');

    if ($status === 'completed' || $status === 'failed') {
        printf("transcription: %s %s\n", $status, $result->str('text'));
        break;
    }

    sleep(1);
}

// 3. Text intelligence.
printf("language: %s\n", $client->detectLanguage('Как дела?')->str('language'));

// 4. Voice catalog and monthly usage.
printf("voices available: %d\n", count($client->voices()));
printf("characters used: %d\n", $client->usage()->int('characters_used'));

// 5. Plan-gated calls report a clear error code.
try {
    foreach ($client->synthesizeStream('Первое предложение. Второе предложение.') as $chunk) {
        break; // the first chunk is enough for the example
    }
} catch (VoiceKitError $error) {
    printf("streaming needs a Pro or Business plan: %s\n", (string) $error->errorCode());
}
