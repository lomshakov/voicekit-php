<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VoiceKit\File;
use VoiceKit\Http\Response;
use VoiceKit\Tests\Support\FakeTransport;
use VoiceKit\VoiceKitClient;

final class MediaEffectsTest extends TestCase
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

    public function test_apply_audio_effects_encodes_the_chain(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_1']));

        $job = $this->client()->applyAudioEffects(
            File::fromString('RIFF', 'voice.wav'),
            [['type' => 'reverb', 'room_size' => 0.5]],
            outputFormat: 'mp3',
            webhookUrl: 'https://example.test/hook',
        );

        $this->assertSame('job_1', $job->str('job_id'));
        $this->assertSame('/v1/audio/effects', $this->transport->lastPath());

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('[{"type":"reverb","room_size":0.5}]', $fields['effects']);
        $this->assertSame('mp3', $fields['output_format']);
        $this->assertSame('https://example.test/hook', $fields['webhookUrl']);
    }

    public function test_apply_audio_effects_sends_an_empty_chain_by_default(): void
    {
        $this->client()->applyAudioEffects(File::fromString('RIFF', 'voice.wav'));

        $this->assertSame('[]', $this->transport->lastMultipartFields()['effects']);
    }

    public function test_audio_effects_job_helpers(): void
    {
        $this->transport->push(FakeTransport::ok(['status' => 'completed']));
        $this->client()->getAudioEffectsJob('job_1');
        $this->assertSame('/v1/audio/effects/job_1', $this->transport->lastPath());

        $this->transport->push(new Response(200, 'RIFF'));
        $this->assertSame('RIFF', $this->client()->downloadAudioEffects('job_1'));
        $this->assertSame('/v1/audio/effects/job_1/audio', $this->transport->lastPath());
    }

    public function test_apply_video_effects_can_replace_the_audio_track(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_2']));

        $this->client()->applyVideoEffects(
            File::fromString('video', 'clip.mp4'),
            [['type' => 'pitch', 'semitones' => 2]],
            File::fromString('audio', 'track.wav'),
            mode: 'mux',
            outputFormat: 'mp4',
        );

        $this->assertSame('/v1/video/effects', $this->transport->lastPath());
        $this->assertSame(1, $this->transport->lastMultipartPartCount('video'));
        $this->assertSame(1, $this->transport->lastMultipartPartCount('audio'));

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('mux', $fields['mode']);
        $this->assertSame('mp4', $fields['output_format']);

        $body = (string) $this->transport->lastRequest()->body;
        $this->assertStringStartsWith('multipart/form-data', (string) $this->transport->lastRequest()->headers['Content-Type']);
        $this->assertStringContainsString('Content-Type: video/mp4', $body);
    }

    public function test_apply_video_effects_without_a_replacement_track(): void
    {
        $this->client()->applyVideoEffects(File::fromString('video', 'clip.mp4'));

        $this->assertSame(0, $this->transport->lastMultipartPartCount('audio'));
        $this->assertSame('/v1/video/effects', $this->transport->lastPath());
    }

    public function test_video_effects_job_helpers(): void
    {
        $this->transport->push(FakeTransport::ok(['status' => 'processing']));
        $this->client()->getVideoEffectsJob('job_2');
        $this->assertSame('/v1/video/effects/job_2', $this->transport->lastPath());

        $this->transport->push(new Response(200, 'MP4'));
        $this->assertSame('MP4', $this->client()->downloadVideoEffects('job_2'));
        $this->assertSame('/v1/video/effects/job_2/file', $this->transport->lastPath());
    }

    public function test_clean_audio_sends_the_preset_as_json(): void
    {
        $this->transport->push(FakeTransport::ok(['job_id' => 'job_3']));

        $this->client()->cleanAudio(
            File::fromString('RIFF', 'noisy.wav'),
            ['denoise' => ['strength' => 0.8], 'high_pass' => 80],
            outputFormat: 'ogg',
        );

        $fields = $this->transport->lastMultipartFields();
        $this->assertSame('{"denoise":{"strength":0.8},"high_pass":80}', $fields['options']);
        $this->assertSame('ogg', $fields['output_format']);
        $this->assertSame('/v1/audio/clean', $this->transport->lastPath());
    }

    public function test_clean_audio_without_a_preset(): void
    {
        $this->client()->cleanAudio(File::fromString('RIFF', 'noisy.wav'));

        $this->assertArrayNotHasKey('options', $this->transport->lastMultipartFields());
    }

    public function test_audio_cleaning_job_helpers(): void
    {
        $this->transport->push(FakeTransport::ok(['status' => 'completed']));
        $this->client()->getAudioCleaningJob('job_3');
        $this->assertSame('/v1/audio/clean/job_3', $this->transport->lastPath());

        $this->transport->push(new Response(200, 'RIFF'));
        $this->assertSame('RIFF', $this->client()->downloadAudioCleaning('job_3'));
        $this->assertSame('/v1/audio/clean/job_3/audio', $this->transport->lastPath());
    }

    public function test_voice_id_passport(): void
    {
        $this->transport->push(FakeTransport::ok(['language' => 'ru', 'ai_probability' => 0.02]));

        $passport = $this->client()->analyzeVoice(File::fromString('RIFF', 'sample.wav'));

        $this->assertSame('ru', $passport->str('language'));
        $this->assertSame(0.02, $passport->float('ai_probability'));
        $this->assertSame('/v1/voice-id', $this->transport->lastPath());
    }

    public function test_voice_id_enrolment_verification_and_identification(): void
    {
        $this->transport->push(FakeTransport::ok(['profile_id' => 'vp_1']));
        $this->assertSame('vp_1', $this->client()->enrollVoice(File::fromString('RIFF', 'a.wav'), 'Алиса')->str('profile_id'));
        $this->assertSame('/v1/voice-id/enroll', $this->transport->lastPath());
        $this->assertSame('Алиса', $this->transport->lastMultipartFields()['name']);

        $this->transport->push(FakeTransport::ok(['verified' => true]));
        $this->assertTrue($this->client()->verifyVoice(File::fromString('RIFF', 'b.wav'), 'vp_1')->bool('verified'));
        $this->assertSame('/v1/voice-id/verify', $this->transport->lastPath());
        $this->assertSame('vp_1', $this->transport->lastMultipartFields()['profile_id']);

        $this->transport->push(FakeTransport::ok(['profile_id' => 'vp_2']));
        $this->client()->identifyVoice(File::fromString('RIFF', 'c.wav'), ['vp_1', 'vp_2']);
        $this->assertSame('/v1/voice-id/identify', $this->transport->lastPath());
        $this->assertSame('vp_1,vp_2', $this->transport->lastMultipartFields()['profile_ids']);

        $this->client()->identifyVoice(File::fromString('RIFF', 'c.wav'));
        $this->assertArrayNotHasKey('profile_ids', $this->transport->lastMultipartFields());
    }

    public function test_voice_profile_management(): void
    {
        $this->transport->push(FakeTransport::ok(['items' => [['profile_id' => 'vp_1']]]));
        $this->assertCount(1, $this->client()->listVoiceProfiles());
        $this->assertSame('/v1/voice-id/profiles', $this->transport->lastPath());

        $this->transport->push(new Response(204, ''));
        $this->client()->deleteVoiceProfile('vp_1');
        $this->assertSame('DELETE', $this->transport->lastRequest()->method);
        $this->assertSame('/v1/voice-id/profiles/vp_1', $this->transport->lastPath());
    }
}
