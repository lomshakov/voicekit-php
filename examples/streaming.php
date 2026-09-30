<?php

declare(strict_types=1);

// Streaming synthesis and streaming transcription (Pro/Business).
//
//   VOICEKIT_API_KEY=rtt_… php examples/streaming.php

require __DIR__ . '/../vendor/autoload.php';

use VoiceKit\VoiceKitClient;
use VoiceKit\VoiceKitError;

$client = VoiceKitClient::fromEnvironment();

$text = 'Первое предложение озвучивается сразу. '
    . 'Второе предложение приходит, пока играет первое. '
    . 'Так длинный текст начинает звучать без ожидания.';

// 1. Streaming synthesis: write chunks as the engine produces them.
try {
    $handle = fopen(__DIR__ . '/long.mp3', 'wb');
    $bytes = 0;

    foreach ($client->synthesizeStream($text, voice: 'preset_anna', format: 'mp3') as $chunk) {
        $bytes += fwrite($handle, $chunk);
    }

    fclose($handle);
    printf("streamed %d bytes → examples/long.mp3\n", $bytes);
} catch (VoiceKitError $error) {
    printf("streaming synthesis failed: %s\n", $error->getMessage());
}

// 2. Streaming transcription over WebSocket (raw PCM16, 16 kHz, mono).
try {
    $stream = $client->transcribeStream(language: 'ru', keyterms: ['диагноз'], interim: true);

    // Send 16-bit mono PCM: ffmpeg -i in.mp3 -ar 16000 -ac 1 -f s16le out.raw
    $stream->sendAudio((string) file_get_contents(__DIR__ . '/pcm16.raw'));
    $stream->stop();

    while (($event = $stream->receive()) !== null) {
        printf("[%s] %s\n", $event->str('type'), $event->str('text'));
    }

    $stream->close();
} catch (VoiceKitError $error) {
    printf("streaming transcription failed: %s\n", $error->getMessage());
}
