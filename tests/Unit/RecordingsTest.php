<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use VoiceKit\File;
use VoiceKit\Http\Response;
use VoiceKit\Tests\Support\FakeTransport;
use VoiceKit\VoiceKitClient;

final class RecordingsTest extends TestCase
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

    public function test_list_recordings_forwards_filters(): void
    {
        $this->transport->push(FakeTransport::ok(['total' => 1, 'items' => [['recording_id' => 'rec_1']]]));

        $page = $this->client()->listRecordings(source: 'link', tag: 'sales', folder: 'Q3', limit: 10, offset: 20);

        $this->assertSame('/v1/recordings', $this->transport->lastPath());
        $this->assertSame(
            ['source' => 'link', 'tag' => 'sales', 'folder' => 'Q3', 'limit' => '10', 'offset' => '20'],
            $this->transport->lastQuery(),
        );
        $this->assertSame('rec_1', $page->list('items')[0]->str('recording_id'));
    }

    public function test_tags_and_folders(): void
    {
        $this->transport->push(FakeTransport::ok(['values' => ['sales']]));
        $this->assertSame(['sales'], $this->client()->recordingTags());
        $this->assertSame('/v1/recordings/tags', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['values' => ['Q3']]));
        $this->assertSame(['Q3'], $this->client()->recordingFolders());
        $this->assertSame('/v1/recordings/folders', $this->transport->lastPath());
    }

    public function test_recording_from_link(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_1']));

        $this->client()->recordingFromLink('https://example.test/call.mp3', 'ru');

        $this->assertSame('/v1/recordings/from-link', $this->transport->lastPath());
        $this->assertSame(['url' => 'https://example.test/call.mp3', 'language' => 'ru'], $this->transport->lastJsonBody());
    }

    public function test_recording_readers(): void
    {
        $this->transport->push(FakeTransport::ok(['recording_id' => 'rec_1']));
        $this->client()->getRecording('rec_1');
        $this->assertSame('/v1/recordings/rec_1', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['segments' => []]));
        $this->client()->recordingTranscript('rec_1');
        $this->assertSame('/v1/recordings/rec_1/transcript', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['speakers' => []]));
        $this->client()->recordingSpeakers('rec_1');
        $this->assertSame('/v1/recordings/rec_1/speakers', $this->transport->lastPath());
    }

    public function test_update_recording_patches_tags_and_folder(): void
    {
        $this->transport->push(FakeTransport::ok(['recording_id' => 'rec_1']));

        $this->client()->updateRecording('rec_1', tags: ['sales', 'warm'], folder: 'Q3');

        $this->assertSame('PATCH', $this->transport->lastRequest()->method);
        $this->assertSame('/v1/recordings/rec_1', $this->transport->lastPath());
        $this->assertSame(['tags' => ['sales', 'warm'], 'folder' => 'Q3'], $this->transport->lastJsonBody());
    }

    public function test_update_recording_can_clear_tags_and_folder(): void
    {
        $this->client()->updateRecording('rec_1', clearTags: true, folder: '');

        $body = $this->transport->lastRequest()->body;

        $this->assertSame('{"tags":null,"folder":""}', $body);
    }

    public function test_update_recording_sends_an_empty_patch_when_nothing_changes(): void
    {
        $this->client()->updateRecording('rec_1');

        $this->assertSame('{}', $this->transport->lastRequest()->body);
    }

    public function test_update_speaker(): void
    {
        $this->client()->updateSpeaker('rec_1', 'SPEAKER_00', displayName: 'Иван', role: 'operator');

        $this->assertSame('/v1/recordings/rec_1/speakers/SPEAKER_00', $this->transport->lastPath());
        $this->assertSame(['display_name' => 'Иван', 'role' => 'operator'], $this->transport->lastJsonBody());
    }

    public function test_delete_and_download(): void
    {
        $this->transport->push(new Response(204, ''));
        $this->client()->deleteRecording('rec_1');
        $this->assertSame('DELETE', $this->transport->lastRequest()->method);

        $this->transport->push(new Response(200, 'RIFF'));
        $this->assertSame('RIFF', $this->client()->downloadRecordingAudio('rec_1'));
        $this->assertSame('/v1/recordings/rec_1/audio', $this->transport->lastPath());
    }

    public function test_export_recording_defaults_to_txt(): void
    {
        $this->transport->push(new Response(200, 'transcript'));

        $this->assertSame('transcript', $this->client()->exportRecording('rec_1', ''));
        $this->assertSame(['format' => 'txt'], $this->transport->lastQuery());

        $this->client()->exportRecording('rec_1', 'pdf');
        $this->assertSame(['format' => 'pdf'], $this->transport->lastQuery());
    }

    public function test_share_links(): void
    {
        $this->transport->push(FakeTransport::ok(['token' => 'tok_1']));
        $this->assertSame('tok_1', $this->client()->createShare('rec_1', expiresInSeconds: 3600, password: 'secret')->str('token'));
        $this->assertSame('/v1/recordings/rec_1/share', $this->transport->lastPath());
        $this->assertSame(['expires_in_seconds' => 3600, 'password' => 'secret'], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['items' => [['token' => 'tok_1']]]));
        $this->assertCount(1, $this->client()->listShares('rec_1'));

        $this->transport->push(new Response(204, ''));
        $this->client()->revokeShare('rec_1', 'tok_1');
        $this->assertSame('/v1/recordings/rec_1/share/tok_1', $this->transport->lastPath());
    }

    public function test_qa_evaluation(): void
    {
        $this->transport->push(FakeTransport::ok(['score' => 0.9]));

        $result = $this->client()->qaEvaluate('rec_1', [
            ['id' => 'greeting', 'kind' => 'required', 'description' => 'Поздоровался', 'weight' => 1.0],
        ], webhookUrl: 'https://example.test/hook');

        $this->assertSame(0.9, $result->float('score'));
        $this->assertSame('/v1/qa/evaluate', $this->transport->lastPath());
        $this->assertSame('https://example.test/hook', $this->transport->lastJsonBody()['webhook_url']);

        $this->client()->qaEvaluate('rec_1');
        $this->assertArrayNotHasKey('checklist', $this->transport->lastJsonBody());
    }

    public function test_qa_analytics_evaluations_and_export(): void
    {
        $this->transport->push(FakeTransport::ok(['trend' => []]));
        $this->client()->qaAnalytics(7);
        $this->assertSame('/v1/qa/analytics', $this->transport->lastPath());
        $this->assertSame(['days' => '7'], $this->transport->lastQuery());

        $this->client()->qaAnalytics();
        $this->assertSame(['days' => '30'], $this->transport->lastQuery());

        $this->transport->push(FakeTransport::ok(['items' => []]));
        $this->client()->qaEvaluations(limit: 5, offset: 10);
        $this->assertSame(['limit' => '5', 'offset' => '10'], $this->transport->lastQuery());

        $this->transport->push(new Response(200, 'id,score'));
        $this->assertSame('id,score', $this->client()->qaExport('csv', 7));
        $this->assertSame(['format' => 'csv', 'days' => '7'], $this->transport->lastQuery());
    }

    public function test_search_and_ask(): void
    {
        $this->transport->push(FakeTransport::ok(['hits' => []]));

        $this->client()->search(
            'почему клиент отказался?',
            limit: 5,
            keywords: 'дорого',
            source: 'link',
            speaker: 'SPEAKER_00',
            from: '2026-01-01T00:00:00Z',
            to: '2026-02-01T00:00:00Z',
            minDurationSeconds: 30.5,
        );

        $this->assertSame('/v1/search', $this->transport->lastPath());
        $this->assertSame([
            'query' => 'почему клиент отказался?',
            'limit' => 5,
            'keywords' => 'дорого',
            'source' => 'link',
            'speaker' => 'SPEAKER_00',
            'from' => '2026-01-01T00:00:00Z',
            'to' => '2026-02-01T00:00:00Z',
            'min_duration_seconds' => 30.5,
        ], $this->transport->lastJsonBody());

        $this->transport->push(FakeTransport::ok(['answer' => 'дорого']));
        $this->assertSame('дорого', $this->client()->ask('почему?')->str('answer'));
        $this->assertSame('/v1/ask', $this->transport->lastPath());
    }

    public function test_meeting_protocol_defaults_to_custom(): void
    {
        $this->transport->push(FakeTransport::ok(['protocol' => []]));

        $this->client()->meetingProtocol('rec_1');

        $this->assertSame(['recording_id' => 'rec_1', 'template' => 'custom'], $this->transport->lastJsonBody());

        $this->client()->meetingProtocol('rec_1', 'standup');
        $this->assertSame('standup', $this->transport->lastJsonBody()['template']);
    }

    public function test_timestamp_helper_formats_dates_in_utc(): void
    {
        $date = new DateTimeImmutable('2026-05-01 12:30:00', new DateTimeZone('Europe/Moscow'));

        $this->assertSame('2026-05-01T09:30:00+00:00', VoiceKitClient::timestamp($date));
        $this->assertSame('2026-05-01T09:30:00Z', VoiceKitClient::timestamp('2026-05-01T09:30:00Z'));
    }

    public function test_batches(): void
    {
        $this->transport->push(FakeTransport::ok(['batch_id' => 'batch_1']));

        $this->client()->batchSynthesize([
            ['text' => 'Первый текст', 'voice' => 'preset_anna'],
            ['text' => 'Второй текст', 'voice' => 'preset_dmitri'],
        ]);

        $this->assertSame('/v1/batch/synthesize', $this->transport->lastPath());
        $this->assertSame(
            ['items' => [
                ['text' => 'Первый текст', 'voice' => 'preset_anna'],
                ['text' => 'Второй текст', 'voice' => 'preset_dmitri'],
            ]],
            $this->transport->lastJsonBody(),
        );

        $this->transport->push(FakeTransport::ok(['batch_id' => 'batch_2']));
        $this->client()->batchAnalyze([
            ['audio' => File::base64Of(File::fromString('RIFF', 'call.wav')), 'language' => 'ru'],
        ]);
        $this->assertSame('/v1/batch/analyze', $this->transport->lastPath());
        $this->assertSame(base64_encode('RIFF'), $this->transport->lastJsonBody()['items'][0]['audio']);

        $this->transport->push(FakeTransport::ok(['status' => 'completed']));
        $this->assertSame('completed', $this->client()->getBatch('batch_1')->str('status'));
        $this->assertSame('/v1/batch/batch_1', $this->transport->lastPath());
    }

    public function test_account_endpoints(): void
    {
        $this->transport->push(FakeTransport::ok(['characters_used' => 120]));
        $this->assertSame(120, $this->client()->usage()->int('characters_used'));
        $this->assertSame('/v1/usage', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['balance' => 100.0, 'plan' => 'Free']));
        $this->assertSame('Free', $this->client()->billingBalance()->str('plan'));
        $this->assertSame('/v1/billing/balance', $this->transport->lastPath());
    }
}
