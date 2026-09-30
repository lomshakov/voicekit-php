<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use VoiceKit\Result;

final class ResultTest extends TestCase
{
    public function test_decodes_json_and_wraps_nested_objects(): void
    {
        $result = Result::fromJson('{"job_id":"job_1","duration_seconds":1.5,"status":"completed","segments":[{"start":0.0,"text":"Привет"}],"speakers":["SPEAKER_00"]}');

        $this->assertSame('job_1', $result->str('job_id'));
        $this->assertSame(1.5, $result->float('duration_seconds'));
        $this->assertSame(1, $result->int('duration_seconds'));

        $segments = $result->list('segments');
        $this->assertCount(1, $segments);
        $this->assertSame('Привет', $segments[0]->str('text'));

        $this->assertSame(['SPEAKER_00'], $result->strings('speakers'));
        $this->assertSame('completed', $result['status']);
    }

    public function test_empty_body_yields_an_empty_result(): void
    {
        $result = Result::fromJson('   ');

        $this->assertCount(0, $result);
        $this->assertSame([], $result->toArray());
        $this->assertSame('fallback', $result->str('missing', 'fallback'));
    }

    public function test_rejects_non_object_json(): void
    {
        $this->expectException(\VoiceKit\VoiceKitError::class);

        Result::fromJson('[1,2,3]');
    }

    public function test_accessors_return_defaults_for_missing_or_mistyped_values(): void
    {
        $result = new Result(['number' => '42', 'true' => 'true', 'false' => 'no', 'object' => ['a' => 1], 'items' => [['id' => 1], 'skip']]);

        $this->assertSame(42, $result->int('number'));
        $this->assertSame(42.0, $result->float('number'));
        $this->assertTrue($result->bool('true'));
        $this->assertFalse($result->bool('false'));
        $this->assertFalse($result->bool('missing'));
        $this->assertSame('', $result->str('missing'));
        $this->assertNull($result->get('missing'));
        $this->assertSame('x', $result->get('missing', 'x'));
        $this->assertNull($result->obj('items'));
        $this->assertSame(1, $result->obj('object')?->int('a'));
        $this->assertCount(1, $result->list('items'));
    }

    public function test_is_iterable_and_serialisable(): void
    {
        $result = new Result(['a' => 1, 'b' => 2]);

        $keys = [];
        foreach ($result as $key => $value) {
            $keys[] = $key;
        }

        $this->assertSame(['a', 'b'], $keys);
        $this->assertSame('{"a":1,"b":2}', json_encode($result->jsonSerialize()));
    }

    public function test_results_are_read_only(): void
    {
        $result = new Result(['a' => 1]);

        $this->expectException(LogicException::class);

        $result['b'] = 2;
    }

    public function test_has_reports_present_but_null_keys(): void
    {
        $result = new Result(['a' => null]);

        $this->assertTrue($result->has('a'));
        $this->assertFalse($result->has('b'));
        $this->assertNull($result['a']);
        $this->assertNull($result['b']);
    }
}
