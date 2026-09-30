<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VoiceKit\File;
use VoiceKit\Http\Response;
use VoiceKit\Tests\Support\FakeTransport;
use VoiceKit\VoiceKitClient;

final class TranscriptionTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
    }

    private function client(): VoiceKitClient
    {
        return new VoiceKitClient('rtt_test', VoiceKitClient::DEFAULT_BASE_URL, 5.0, $this->transport);
    }

    private function audio(string $contents = 'RIFFfake'): File
    {
        return File::fromString($contents, 'call.wav');
    }

    public function test_transcribe_uploads_audio_with_options(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_1', 'status' => 'queued']));

        $job = $this->client()->transcribe(
            $this->audio(),
            language: 'ru',
            diarization: true,
            keyterms: ['диагноз', ' препарат '],
            clean: true,
            longForm: true,
            webhookUrl: 'https://example.test/hook',
        );

        $this->assertSame('job_1', $job->str('job_id'));
        $this->assertSame('/v1/transcribe', $this->transport->lastPath());
        $this->assertSame('RIFFfake', $this->transport->lastMultipartFields()['audio']);

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('ru', $fields['language']);
        $this->assertSame('true', $fields['diarization']);
        $this->assertSame('true', $fields['clean']);
        $this->assertSame('true', $fields['longForm']);
        $this->assertSame('https://example.test/hook', $fields['webhookUrl']);
        $this->assertSame('диагноз,препарат', $fields['keyterms']);
    }

    public function test_transcribe_omits_disabled_flags(): void
    {
        $this->client()->transcribe($this->audio());

        $fields = $this->transport->lastMultipartFields();

        $this->assertArrayNotHasKey('diarization', $fields);
        $this->assertArrayNotHasKey('clean', $fields);
        $this->assertArrayNotHasKey('longForm', $fields);
        $this->assertArrayNotHasKey('keyterms', $fields);
    }

    public function test_transcribe_sync_and_job_polling(): void
    {
        $this->transport->push(FakeTransport::ok(['text' => 'Привет']));
        $result = $this->client()->transcribeSync($this->audio(), language: 'ru', diarization: true);
        $this->assertSame('Привет', $result->str('text'));
        $this->assertSame('/v1/transcribe/sync', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['status' => 'completed']));
        $this->assertSame('completed', $this->client()->getTranscriptionJob('job_1')->str('status'));
        $this->assertSame('/v1/transcribe/job_1', $this->transport->lastPath());
    }

    public function test_subtitles_downloads_text(): void
    {
        $this->transport->push(new Response(200, 'WEBVTT', ['content-type' => 'text/vtt']));

        $vtt = $this->client()->subtitles('job_1', format: 'srt', targetLanguage: 'en', hotMarks: true);

        $this->assertSame('WEBVTT', $vtt);
        $this->assertSame('/v1/transcribe/job_1/subtitles', $this->transport->lastPath());
        $this->assertSame(['format' => 'srt', 'target_language' => 'en', 'hot_marks' => 'true'], $this->transport->lastQuery());
    }

    public function test_translate_transcript_posts_json(): void
    {
        $this->transport->push(FakeTransport::ok(['target_language' => 'en']));

        $this->client()->translateTranscript('job_1', 'en');

        $this->assertSame('/v1/transcribe/job_1/translate', $this->transport->lastPath());
        $this->assertSame(['target_language' => 'en'], $this->transport->lastJsonBody());
    }

    public function test_vad_uploads_audio(): void
    {
        $this->transport->push(FakeTransport::ok(['segments' => []]));

        $this->client()->vad($this->audio());

        $this->assertSame('/v1/vad', $this->transport->lastPath());
        $this->assertSame(['audio' => 'RIFFfake'], $this->transport->lastMultipartFields());
    }

    public function test_analyze_and_analyze_sync(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_2']));
        $job = $this->client()->analyze($this->audio(), language: 'ru', diarization: true, keyterms: ['бюджет']);
        $this->assertSame('job_2', $job->str('job_id'));
        $this->assertSame('/v1/analyze', $this->transport->lastPath());
        $this->assertSame('бюджет', $this->transport->lastMultipartFields()['keyterms']);

        $this->transport->push(FakeTransport::ok(['emotions' => []]));
        $sync = $this->client()->analyzeSync($this->audio(), emotions: false, keywords: true);
        $this->assertSame('/v1/analyze/sync', $this->transport->lastPath());

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('false', $fields['emotions']);
        $this->assertSame('true', $fields['keywords']);
        $this->assertArrayNotHasKey('entities', $fields);
    }

    public function test_get_analysis_job(): void
    {
        $this->transport->push(FakeTransport::ok(['status' => 'completed']));

        $this->client()->getAnalysisJob('job_2');

        $this->assertSame('/v1/analyze/job_2', $this->transport->lastPath());
    }

    public function test_evaluate_sends_the_reference_text(): void
    {
        $this->transport->push(FakeTransport::ok(['wer' => 0.12]));

        $result = $this->client()->evaluate($this->audio(), 'Здравствуйте', language: 'ru', normalize: true);

        $this->assertSame('/v1/eval', $this->transport->lastPath());
        $this->assertSame('Здравствуйте', $this->transport->lastMultipartFields()['reference']);
        $this->assertSame('true', $this->transport->lastMultipartFields()['normalize']);
        $this->assertSame(0.12, $result->float('wer'));
    }

    public function test_text_intelligence_endpoints(): void
    {
        $this->transport->push(FakeTransport::ok(['language' => 'ru']));
        $this->assertSame('ru', $this->client()->detectLanguage('Как дела?')->str('language'));
        $this->assertSame('/v1/detect-language', $this->transport->lastPath());
        $this->assertSame(['text' => 'Как дела?'], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['text' => 'redacted']));
        $this->client()->redact('Иван', 'ru');
        $this->assertSame('/v1/redact', $this->transport->lastPath());
        $this->assertSame(['text' => 'Иван', 'language' => 'ru'], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['topics' => []]));
        $this->client()->topics('Нейросети');
        $this->assertSame('/v1/analyze/topics', $this->transport->lastPath());
        $this->assertSame(['text' => 'Нейросети'], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['summary' => 'ok']));
        $this->client()->summarize('Длинный текст', maxSentences: 3);
        $this->assertSame('/v1/analyze/summarize', $this->transport->lastPath());
        $this->assertSame(['text' => 'Длинный текст', 'max_sentences' => 3], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['flagged' => false]));
        $this->client()->moderate('текст');
        $this->assertSame('/v1/moderate', $this->transport->lastPath());
    }
}
