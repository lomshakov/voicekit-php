<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VoiceKit\Tests\Support\FakeTransport;
use VoiceKit\VoiceKitClient;
use VoiceKit\VoiceKitError;

final class VoiceKitClientTest extends TestCase
{
    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
    }

    private function client(string $baseUrl = VoiceKitClient::DEFAULT_BASE_URL): VoiceKitClient
    {
        return new VoiceKitClient('rtt_test', $baseUrl, 30.0, $this->transport);
    }

    public function test_rejects_a_blank_api_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VoiceKitClient('   ');
    }

    public function test_rejects_a_blank_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VoiceKitClient('rtt_test', '   ');
    }

    public function test_rejects_a_non_positive_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new VoiceKitClient('rtt_test', VoiceKitClient::DEFAULT_BASE_URL, 0.0);
    }

    public function test_builds_from_an_environment_variable(): void
    {
        putenv('VOICEKIT_TEST_KEY=rtt_env');

        try {
            $client = VoiceKitClient::fromEnvironment('VOICEKIT_TEST_KEY');
            $this->assertSame('rtt_env', $client->defaultHeaders()['X-Api-Key']);
        } finally {
            putenv('VOICEKIT_TEST_KEY');
        }
    }

    public function test_missing_environment_variable_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VoiceKitClient::fromEnvironment('VOICEKIT_MISSING_KEY');
    }

    public function test_sends_authentication_and_version_headers(): void
    {
        $this->client()->get('/v1/usage');

        $request = $this->transport->lastRequest();
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://ttsapi.ru/v1/usage', $request->url);
        $this->assertSame('rtt_test', $request->headers['X-Api-Key']);
        $this->assertSame('application/json', $request->headers['Accept']);
        $this->assertSame('voicekit-php/' . VoiceKitClient::VERSION, $request->headers['User-Agent']);
    }

    public function test_trims_the_trailing_slash_of_the_base_url(): void
    {
        $this->client('https://ttsapi.ru/')->get('/v1/usage');

        $this->assertSame('https://ttsapi.ru/v1/usage', $this->transport->lastRequest()->url);
    }

    public function test_merges_extra_headers_with_the_call_headers(): void
    {
        $client = new VoiceKitClient('rtt_test', VoiceKitClient::DEFAULT_BASE_URL, 30.0, $this->transport, [
            'X-Trace' => 'abc',
        ]);

        $client->post('/v1/x', ['a' => 1]);

        $this->assertSame('abc', $this->transport->lastHeader('X-Trace'));
        $this->assertSame('application/json', $this->transport->lastHeader('Content-Type'));
    }

    public function test_query_strings_skip_empty_values(): void
    {
        $this->client()->get('/v1/recordings', [
            'source' => 'upload',
            'tag' => null,
            'folder' => '',
            'limit' => 0,
            'flag' => true,
        ]);

        $this->assertSame(
            ['source' => 'upload', 'limit' => '0', 'flag' => 'true'],
            $this->transport->lastQuery(),
        );
    }

    public function test_maps_problem_details_to_an_error(): void
    {
        $this->transport->push(FakeTransport::problem(403, [
            'code' => 'streaming_forbidden',
            'detail' => "Streaming is not included in the 'Free' plan.",
        ]));

        try {
            $this->client()->get('/v1/synthesize/stream');
            $this->fail('Expected a VoiceKitError.');
        } catch (VoiceKitError $error) {
            $this->assertSame(403, $error->statusCode());
            $this->assertSame('streaming_forbidden', $error->errorCode());
            $this->assertTrue($error->isForbidden());
            $this->assertTrue($error->isCode('streaming_forbidden'));
            $this->assertTrue(VoiceKitError::matches($error, 'streaming_forbidden'));
            $this->assertStringContainsString('HTTP 403', $error->getMessage());
        }
    }

    public function test_decodes_a_bare_array_response(): void
    {
        $this->transport->push(FakeTransport::ok([['id' => 'preset_anna'], ['id' => 'preset_dmitri']]));

        $voices = $this->client()->getList('/v1/voices');

        $this->assertCount(2, $voices);
        $this->assertSame('preset_anna', $voices[0]->str('id'));
    }

    public function test_decodes_a_wrapped_list_response(): void
    {
        $this->transport->push(FakeTransport::ok(['items' => [['id' => 'rec_1']]]));

        $items = $this->client()->getList('/v1/recordings');

        $this->assertCount(1, $items);
        $this->assertSame('rec_1', $items[0]->str('id'));
    }

    public function test_decodes_string_lists(): void
    {
        $this->transport->push(FakeTransport::ok(['values' => ['sales', 'warm']]));
        $this->assertSame(['sales', 'warm'], $this->client()->getStrings('/v1/recordings/tags'));

        $this->transport->push(FakeTransport::ok(['a', 'b']));
        $this->assertSame(['a', 'b'], $this->client()->getStrings('/v1/recordings/folders'));
    }

    public function test_download_asks_for_binary_payloads(): void
    {
        $this->transport->push(new \VoiceKit\Http\Response(200, 'ID3fake'));

        $audio = $this->client()->download('/v1/synthesize/async/job_1/audio');

        $this->assertSame('ID3fake', $audio);
        $this->assertSame('*/*', $this->transport->lastHeader('Accept'));
    }

    public function test_upload_builds_a_multipart_body(): void
    {
        $this->client()->upload(
            '/v1/transcribe',
            [['audio', \VoiceKit\File::fromString('RIFFdata', 'call.wav')]],
            ['language' => 'ru'],
        );

        $request = $this->transport->lastRequest();
        $this->assertStringStartsWith('multipart/form-data; boundary=', (string) $request->headers['Content-Type']);
        $this->assertStringContainsString('Content-Disposition: form-data; name="audio"; filename="call.wav"', (string) $request->body);
        $this->assertStringContainsString('Content-Type: audio/wav', (string) $request->body);
        $this->assertStringContainsString('name="language"', (string) $request->body);
        $this->assertStringContainsString('RIFFdata', (string) $request->body);
    }

    public function test_exposes_configuration(): void
    {
        $client = $this->client('https://example.test/');

        $this->assertSame('https://example.test', $client->baseUrl());
        $this->assertSame(30.0, $client->timeout());
        $this->assertSame(VoiceKitClient::VERSION, $client->version());
        $this->assertSame($this->transport, $client->transport());
    }

    public function test_returns_raw_responses_for_unwrapped_endpoints(): void
    {
        $this->transport->push(new \VoiceKit\Http\Response(200, '{"ok":true}', []));

        $response = $this->client()->request('POST', '/v1/custom', [], '{"x":1}');

        $this->assertSame(200, $response->status);
        $this->assertSame('{"ok":true}', $response->body);
        $this->assertSame('{"x":1}', $this->transport->lastRequest()->body);
    }
}
