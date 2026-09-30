<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use Generator;
use PHPUnit\Framework\TestCase;
use VoiceKit\File;
use VoiceKit\Http\Response;
use VoiceKit\Tests\Support\FakeTransport;
use VoiceKit\VoiceKitClient;
use VoiceKit\VoiceKitError;

final class SynthesisTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
    }

    private function client(string $baseUrl = VoiceKitClient::DEFAULT_BASE_URL): VoiceKitClient
    {
        return new VoiceKitClient('rtt_test', $baseUrl, 5.0, $this->transport);
    }

    public function test_synthesize_returns_raw_audio(): void
    {
        $this->transport->push(new Response(200, 'ID3audio', ['content-type' => 'audio/mpeg']));

        $audio = $this->client()->synthesize('Привет', voice: 'preset_anna', format: 'mp3');

        $this->assertSame('ID3audio', $audio);
        $this->assertSame('/v1/synthesize', $this->transport->lastPath());
        $this->assertSame('POST', $this->transport->lastRequest()->method);
        $this->assertSame(
            ['text' => 'Привет', 'voice' => 'preset_anna', 'format' => 'mp3'],
            $this->transport->lastJsonBody(),
        );
    }

    public function test_synthesize_omits_unset_options_and_encodes_an_effect_chain(): void
    {
        $this->client()->synthesize('Привет', effects: [['type' => 'reverb', 'room_size' => 0.5]]);

        $body = $this->transport->lastJsonBody();

        $this->assertSame('[{"type":"reverb","room_size":0.5}]', $body['effects']);
        $this->assertArrayNotHasKey('speed', $body);
        $this->assertArrayNotHasKey('ssml', $body);
        $this->assertArrayNotHasKey('sample_rate', $body);
    }

    public function test_synthesize_keeps_explicit_false_flags(): void
    {
        $this->client()->synthesize('Привет', ssml: false, normalize: false, speed: 1.25, sampleRate: 48000);

        $body = $this->transport->lastJsonBody();

        $this->assertFalse($body['ssml']);
        $this->assertFalse($body['normalize']);
        $this->assertSame(1.25, $body['speed']);
        $this->assertSame(48000, $body['sample_rate']);
    }

    public function test_synthesize_stream_returns_a_generator_and_reports_transport_failures(): void
    {
        $stream = $this->client('http://127.0.0.1:1')->synthesizeStream('Привет', voice: 'preset_anna');

        $this->assertInstanceOf(Generator::class, $stream);

        $this->expectException(VoiceKitError::class);
        $stream->current();
    }

    public function test_synthesize_async_queues_a_job_and_passes_the_webhook(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_1', 'status' => 'queued']));

        $job = $this->client()->synthesizeAsync('Длинный текст', voice: 'preset_anna', webhookUrl: 'https://example.test/hook');

        $this->assertSame('job_1', $job->str('job_id'));
        $this->assertSame('/v1/synthesize/async', $this->transport->lastPath());
        $this->assertSame(['webhookUrl' => 'https://example.test/hook'], $this->transport->lastQuery());
        $this->assertSame(['text' => 'Длинный текст', 'voice' => 'preset_anna'], $this->transport->lastJsonBody());
    }

    public function test_job_polling_and_audio_download(): void
    {
        $this->transport->push(FakeTransport::ok(['status' => 'completed']));
        $this->assertSame('completed', $this->client()->getSynthesisJob('job_1')->str('status'));
        $this->assertSame('/v1/synthesize/async/job_1', $this->transport->lastPath());

        $this->transport->push(new Response(200, 'RIFFwav'));
        $this->assertSame('RIFFwav', $this->client()->downloadSynthesisAudio('job_1'));
        $this->assertSame('/v1/synthesize/async/job_1/audio', $this->transport->lastPath());
    }

    public function test_voice_catalog(): void
    {
        $this->transport->push(FakeTransport::ok([['id' => 'preset_anna']]));
        $voices = $this->client()->voices();

        $this->assertSame('/v1/voices', $this->transport->lastPath());
        $this->assertSame('preset_anna', $voices[0]->str('id'));

        $this->transport->push(FakeTransport::ok(['id' => 'preset_anna', 'name' => 'Анна']));
        $this->assertSame('Анна', $this->client()->voice('preset_anna')->str('name'));
        $this->assertSame('/v1/voices/preset_anna', $this->transport->lastPath());
    }

    public function test_create_clone_voice_uploads_every_sample(): void
    {
        $this->transport->push(FakeTransport::ok(['id' => 'clone_1']));

        $voice = $this->client()->createCloneVoice(
            name: 'Иван',
            promptText: 'Здравствуйте, это образец моего голоса.',
            samples: [File::fromString('a', 'ivan-1.wav'), File::fromString('b', 'ivan-2.wav')],
            language: 'ru',
        );

        $this->assertSame('clone_1', $voice->str('id'));
        $this->assertSame('/v1/voices/clone', $this->transport->lastPath());
        $this->assertSame(2, $this->transport->lastMultipartPartCount('samples'));

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('Иван', $fields['name']);
        $this->assertSame('Здравствуйте, это образец моего голоса.', $fields['prompt_text']);
        $this->assertSame('ru', $fields['language']);
    }

    public function test_clone_voice_management(): void
    {
        $this->transport->push(FakeTransport::ok(['items' => [['id' => 'clone_1']]]));
        $this->assertCount(1, $this->client()->listCloneVoices());
        $this->assertSame('/v1/voices/clone', $this->transport->lastPath());

        $this->transport->push(FakeTransport::ok(['id' => 'clone_1']));
        $this->assertSame('clone_1', $this->client()->getCloneVoice('clone_1')->str('id'));
        $this->assertSame('/v1/voices/clone/clone_1', $this->transport->lastPath());

        $this->transport->push(new Response(204, ''));
        $this->client()->deleteCloneVoice('clone_1');
        $this->assertSame('DELETE', $this->transport->lastRequest()->method);
        $this->assertSame('/v1/voices/clone/clone_1', $this->transport->lastPath());
    }
}
