<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Trip\TripIndexRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class TripIndexRequestTest extends TestCase
{
    public function test_tripdate_accepts_date_only_format(): void
    {
        $validator = Validator::make(
            ['tripdate' => '2026-03-26'],
            (new TripIndexRequest)->rules()
        );

        self::assertTrue($validator->passes());
    }

    public function test_tripdate_accepts_date_and_time_format(): void
    {
        $validator = Validator::make(
            ['tripdate' => '2026-03-26 18:00'],
            (new TripIndexRequest)->rules()
        );

        self::assertTrue($validator->passes());
    }

    public function test_triptime_requires_tripdate(): void
    {
        $validator = Validator::make(
            ['triptime' => '18:00'],
            (new TripIndexRequest)->rules()
        );

        self::assertFalse($validator->passes());
        self::assertArrayHasKey('tripdate', $validator->errors()->toArray());
    }
}
